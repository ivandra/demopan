<?php

class DomainsController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    /**
     * GET /domains?id=6
     * Показывает форму + результат последней проверки (из session flash)
     */
    public function form(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $accounts = DB::pdo()->query("SELECT * FROM registrar_accounts WHERE provider='namecheap' ORDER BY id DESC")->fetchAll();
        $contacts = DB::pdo()->query("SELECT * FROM registrar_contacts ORDER BY id DESC")->fetchAll();
        $servers  = DB::pdo()->query("SELECT * FROM fastpanel_servers ORDER BY id DESC")->fetchAll();

        // flash-результат проверки (чтобы после POST /domains/check не делать "назад" на отчеты)
        $pricing = null;
        $pricingError = null;
        $lastDeployReportId = null;

        if (!empty($_SESSION['flash_domain_check'][$siteId])) {
            $flash = $_SESSION['flash_domain_check'][$siteId];
            unset($_SESSION['flash_domain_check'][$siteId]);

            $pricing        = $flash['pricing'] ?? null;
            $pricingError   = $flash['error'] ?? null;
            $lastDeployReportId = $flash['deploy_id'] ?? null;
        }
		
		$availableIps = $this->getAvailableIpsForSite($site, $servers);
error_log('availableIps count=' . count($availableIps) . ' siteId=' . $siteId);


        $this->view('domains/form', compact(
			'site', 'accounts', 'contacts', 'servers',
			'pricing', 'pricingError', 'lastDeployReportId',
			'availableIps'
		));
    }

    /**
     * POST /domains/check?id=6
     * body: registrar_account_id (+ hidden vps_ip)
     */
    public function check(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        // сохраняем vps_ip, чтобы не терялся
        $vpsIp = trim((string)($_POST['vps_ip'] ?? ''));
        if ($vpsIp !== '') {
            DB::pdo()->prepare("UPDATE sites SET vps_ip=? WHERE id=?")->execute([$vpsIp, $siteId]);
        }

        $accountId = (int)($_POST['registrar_account_id'] ?? (int)($site['registrar_account_id'] ?? 0));
        if ($accountId <= 0) die('registrar_account_id required');

        $account = $this->loadRegistrarAccount($accountId);

        $domain = $this->normalizeDomain((string)$site['domain']);
        if ($domain === '') die('bad domain');

        $max = (float)config('namecheap.max_price_usd', 7.0);

        $serverId = isset($site['fastpanel_server_id']) ? (int)$site['fastpanel_server_id'] : null;
        if ($serverId !== null && $serverId <= 0) $serverId = null;

        $deployId = $this->createDeployment($siteId, $serverId, 'domain_check');

        $payload = [
            'stage' => 'domain_check',
            'site_id' => $siteId,
            'domain' => $domain,
            'registrar_account_id' => $accountId,
            'sandbox' => (bool)$account['is_sandbox'],
            'max_price_usd' => $max,
            'vps_ip' => $vpsIp,
        ];

        try {
            $client = $this->makeClientFromAccount($account);

            $check = $client->checkDomain($domain);
            [$sld, $tld] = $client->splitSldTld($domain);

            // Варианты цен (ты это уже добавлял)
            // ОЖИДАЕМЫЙ ФОРМАТ:
            // [
            //   'regular_price' => float|null,
            //   'your_price'    => float|null,
            //   'coupon_price'  => float|null,
            //   'promo_code'    => string|null,
            //   'min_price'     => float,           // минимальная из доступных
            //   'candidates'    => array            // необязательно
            // ]
            $variants = $client->getPricingRegister1YVariants($tld);

            $regular = isset($variants['regular_price']) && is_numeric($variants['regular_price']) ? (float)$variants['regular_price'] : null;
            $your    = isset($variants['your_price']) && is_numeric($variants['your_price']) ? (float)$variants['your_price'] : null;
            $coupon  = isset($variants['coupon_price']) && is_numeric($variants['coupon_price']) ? (float)$variants['coupon_price'] : null;
            $promo   = (string)($variants['promo_code'] ?? '');

            // минимальная цена (то, что ты просил)
            $minPrice = null;
            $cands = [];
            foreach ([$coupon, $your, $regular] as $p) {
                if (is_numeric($p)) $cands[] = (float)$p;
            }
            if (!empty($cands)) {
                $minPrice = min($cands);
            }
            if ($minPrice === null && isset($variants['min_price']) && is_numeric($variants['min_price'])) {
                $minPrice = (float)$variants['min_price'];
            }
            if ($minPrice === null) {
                // fallback, чтобы не падать
                $minPrice = $regular ?? $your ?? 0.0;
            }

            $status = 'checked';
            $err = '';

            if (($check['available'] ?? false) !== true) {
                $status = 'unavailable';
            } elseif ($minPrice > $max) {
                $status = 'too_expensive';
                $err = 'min_price ' . $minPrice . ' > max ' . $max;
            }

            DB::pdo()->prepare("
                UPDATE sites SET
                    registrar_account_id=?,
                    domain_registrar='namecheap',
                    domain_purchase_status=?,
                    domain_price_usd=?,
                    domain_checked_at=NOW(),
                    domain_purchase_error=?,
                    dns_error=IFNULL(dns_error,'')
                WHERE id=?
            ")->execute([$accountId, $status, $minPrice, $err, $siteId]);

            // сохраняем отчет (как и раньше)
            $this->safeUpdateDeploymentDone($deployId, $payload, [
                'check' => $check,
                'tld' => $tld,
                'price_usd_min' => $minPrice,
                'pricing' => [
                    'regular_price' => $regular,
                    'your_price' => $your,
                    'coupon_price' => $coupon,
                    'promo_code' => $promo !== '' ? $promo : null,
                ],
                'decision' => $status,
                'candidates_sample' => array_slice((array)($variants['candidates'] ?? []), 0, 5),
            ]);

            // flash в сессию — и возвращаем на /domains?id=...
            $_SESSION['flash_domain_check'][$siteId] = [
                'deploy_id' => $deployId,
                'pricing' => [
                    'domain' => $domain,
                    'tld' => $tld,
                    'available' => (bool)($check['available'] ?? false),
                    'premium' => (bool)($check['premium'] ?? false),
                    'decision' => $status,
                    'max_price_usd' => $max,
                    'min_price' => $minPrice,
                    'regular_price' => $regular,
                    'your_price' => $your,
                    'coupon_price' => $coupon,
                    'promo_code' => $promo,
                ],
            ];

            $this->redirect('/domains?id=' . $siteId);

        } catch (Throwable $e) {
            DB::pdo()->prepare("
                UPDATE sites SET
                    registrar_account_id=?,
                    domain_registrar='namecheap',
                    domain_purchase_status='error',
                    domain_purchase_error=?,
                    domain_checked_at=NOW()
                WHERE id=?
            ")->execute([$accountId, $e->getMessage(), $siteId]);

            $this->safeUpdateDeploymentError($deployId, $e->getMessage(), $payload);

            $_SESSION['flash_domain_check'][$siteId] = [
                'deploy_id' => $deployId,
                'error' => $e->getMessage(),
            ];

            $this->redirect('/domains?id=' . $siteId);
        }
    }

    /**
     * POST /domains/purchase-dns?id=6
     * body: registrar_account_id, registrar_contact_id, vps_ip
     */
    public function purchaseAndDns(): void
{
    $this->requireAuth();

    require_once __DIR__ . '/../Services/Crypto.php';
    require_once __DIR__ . '/../Services/NamecheapClient.php';

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    $site = $this->loadSite($siteId);

    $accountId = (int)($_POST['registrar_account_id'] ?? (int)($site['registrar_account_id'] ?? 0));
    $contactId = (int)($_POST['registrar_contact_id'] ?? (int)($site['registrar_contact_id'] ?? 0));
    $vpsIp     = trim((string)($_POST['vps_ip'] ?? ''));

    if ($accountId <= 0) die('registrar_account_id required');
    if ($contactId <= 0) die('registrar_contact_id required');
    if ($vpsIp === '') die('vps_ip required');
    if (!filter_var($vpsIp, FILTER_VALIDATE_IP)) {
        die('vps_ip must be a plain IP address without port');
    }

    // сохраняем выбранные параметры
    DB::pdo()->prepare("
        UPDATE sites SET
            registrar_account_id=?,
            registrar_contact_id=?,
            vps_ip=?,
            domain_registrar='namecheap',
            domain_purchase_status='processing',
            domain_purchase_error=NULL,
            dns_status='processing',
            dns_error=NULL
        WHERE id=?
    ")->execute([$accountId, $contactId, $vpsIp, $siteId]);

    $account = $this->loadRegistrarAccount($accountId);
    $contact = $this->loadRegistrarContact($contactId);

    $domain = $this->normalizeDomain((string)$site['domain']);
    if ($domain === '') die('bad domain');

    $serverId = isset($site['fastpanel_server_id']) ? (int)$site['fastpanel_server_id'] : null;
    if ($serverId !== null && $serverId <= 0) $serverId = null;

    $deployId = $this->createDeployment($siteId, $serverId, 'domain_purchase_dns');

    $payload = [
        'stage' => 'domain_purchase_dns',
        'site_id' => $siteId,
        'domain' => $domain,
        'registrar_account_id' => $accountId,
        'registrar_contact_id' => $contactId,
        'vps_ip' => $vpsIp,
        'sandbox' => (bool)$account['is_sandbox'],
    ];

    // 1) МГНОВЕННО отвечаем браузеру редиректом
    $redirectUrl = '/domains?id=' . $siteId . '&msg=started';
    if (!headers_sent()) {
        header('Location: ' . $redirectUrl, true, 302);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo 'Started...';

    // 2) Закрываем HTTP-ответ, дальше выполняем "в фоне"
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        @ob_end_flush();
        @flush();
    }

    // чтобы не упасть по времени
    @set_time_limit(180);

    // 3) ДОЛГАЯ ЧАСТЬ
    try {
        $client = $this->makeClientFromAccount($account);
        [$sld, $tld] = $client->splitSldTld($domain);

        // Перечитать текущий статус из БД (важно: $site устарел)
        $st = DB::pdo()->prepare("SELECT domain_purchase_status FROM sites WHERE id=?");
        $st->execute([$siteId]);
        $row = $st->fetch();
        $purchaseStatus = (string)($row['domain_purchase_status'] ?? 'none');

        $createResp = null;
        if ($purchaseStatus !== 'purchased') {
            DB::pdo()->prepare("
                UPDATE sites SET
                    domain_purchase_status='purchasing',
                    domain_purchase_error=NULL
                WHERE id=?
            ")->execute([$siteId]);

            $createResp = $client->createDomain($sld, $tld, 1, $this->contactToNamecheap($contact));

            DB::pdo()->prepare("
                UPDATE sites SET
                    domain_purchase_status='purchased',
                    domain_registered_at=NOW(),
                    domain_purchase_error=NULL
                WHERE id=?
            ")->execute([$siteId]);
        }

        DB::pdo()->prepare("
            UPDATE sites SET
                dns_status='applying',
                dns_error=NULL
            WHERE id=?
        ")->execute([$siteId]);

        $hosts = [
            ['host' => '@',   'type' => 'A',     'address' => $vpsIp,  'ttl' => 300],
            ['host' => 'www', 'type' => 'CNAME', 'address' => $domain, 'ttl' => 300],
        ];

        $dnsResp = $client->setHosts($sld, $tld, $hosts);

        DB::pdo()->prepare("
            UPDATE sites SET
                dns_status='configured',
                dns_applied_at=NOW(),
                dns_error=NULL
            WHERE id=?
        ")->execute([$siteId]);

        $this->safeUpdateDeploymentDone($deployId, $payload, [
            'create' => $createResp,
            'dns' => $dnsResp,
            'hosts' => $hosts,
        ]);

    } catch (Throwable $e) {
        DB::pdo()->prepare("
            UPDATE sites SET
                domain_purchase_status=IF(domain_purchase_status IN ('processing','purchasing'),'error',domain_purchase_status),
                domain_purchase_error=?,
                dns_status=IF(dns_status IN ('processing','applying'),'error',dns_status),
                dns_error=?
            WHERE id=?
        ")->execute([$e->getMessage(), $e->getMessage(), $siteId]);

        $this->safeUpdateDeploymentError($deployId, $e->getMessage(), $payload);
    }

    // ВАЖНО: тут ничего не редиректим и не пишем, ответ уже отдан
    return;
}


    // ---------------- helpers ----------------

    private function normalizeDomain(string $domain): string
    {
        $domain = preg_replace('~^https?://~i', '', trim($domain));
        return rtrim($domain, '/');
    }

    private function makeClientFromAccount(array $acc): NamecheapClient
    {
        $isSandbox = (int)($acc['is_sandbox'] ?? 1) === 1;

        $endpoint = $isSandbox
            ? (string)config('namecheap.endpoint_sandbox')
            : (string)config('namecheap.endpoint');

        require_once __DIR__ . '/../Services/Crypto.php';
        $apiKey = Crypto::decrypt((string)$acc['api_key_enc']);

        return new NamecheapClient(
            $endpoint,
            (string)$acc['api_user'],
            $apiKey,
            (string)$acc['username'],
            (string)$acc['client_ip'],
            (int)config('namecheap.timeout', 30)
        );
    }

    private function normalizeNamecheapPhone(string $phone): string
    {
        $p = trim($phone);
        $p = preg_replace('~(?!^\+)[^\d]~', '', $p);

        if ($p !== '' && $p[0] !== '+') {
            $p = '+' . $p;
        }

        if (preg_match('~^\+(\d{1,3})(\d{6,})$~', $p, $m)) {
            return '+' . $m[1] . '.' . $m[2];
        }

        return $phone;
    }

    private function contactToNamecheap(array $c): array
    {
        return [
            'first_name' => (string)$c['first_name'],
            'last_name'  => (string)$c['last_name'],
            'organization' => (string)($c['organization'] ?? ''),
            'address1'   => (string)$c['address1'],
            'address2'   => (string)($c['address2'] ?? ''),
            'city'       => (string)$c['city'],
            'state_province' => (string)($c['state_province'] ?? ''),
            'postal_code'=> (string)$c['postal_code'],
            'country'    => (string)$c['country'],
            'phone' => $this->normalizeNamecheapPhone((string)$c['phone']),
            'email'      => (string)$c['email'],
        ];
    }

    private function createDeployment(int $siteId, ?int $serverId, string $status): int
    {
        if ($serverId !== null && $serverId <= 0) {
            $serverId = null;
        }

        $stmt = DB::pdo()->prepare("
            INSERT INTO deployments (site_id, server_id, status)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$siteId, $serverId, $status]);

        return (int)DB::pdo()->lastInsertId();
    }

    private function safeUpdateDeploymentDone(int $deployId, array $payloadData, array $responseData): void
    {
        $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE);
        $respJson    = json_encode($responseData, JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE deployments SET status='done', payload=?, response=? WHERE id=?";

        DB::withReconnect(function(PDO $pdo) use ($sql, $payloadJson, $respJson, $deployId) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$payloadJson, $respJson, $deployId]);
            return true;
        });
    }

    private function safeUpdateDeploymentError(int $deployId, string $error, array $payloadData): void
    {
        $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE deployments SET status='error', last_error=?, payload=? WHERE id=?";

        DB::withReconnect(function(PDO $pdo) use ($sql, $error, $payloadJson, $deployId) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$error, $payloadJson, $deployId]);
            return true;
        });
    }

    private function loadSite(int $siteId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) die('site not found');
        return $site;
    }

    private function loadRegistrarAccount(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM registrar_accounts WHERE id=? AND provider='namecheap'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('registrar account not found');
        return $row;
    }

    private function loadRegistrarContact(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM registrar_contacts WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('registrar contact not found');
        return $row;
    }
	
private function getAvailableIpsForSite(array $site, array $servers): array
{
    // 1) берем сервер из site.fastpanel_server_id
    $sid = (int)($site['fastpanel_server_id'] ?? 0);
    if ($sid <= 0) return [];

    $srv = null;
    foreach ($servers as $s) {
        if ((int)($s['id'] ?? 0) === $sid) { $srv = $s; break; }
    }
    if (!$srv) return [];

    $ips = [];

    // 2) main source: fastpanel_servers.extra_ips (как в DeployController::form)
    $extra = trim((string)($srv['extra_ips'] ?? ''));
    if ($extra !== '') {
        $parts = preg_split('~[,\s]+~', $extra);
        foreach ($parts as $v) {
            $v = trim($v);
            if ($v === '') continue;

            // строгая проверка ipv4 как в deploy
            if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $v)) {
                $ips[] = $v;
            }
        }
    }

    $ips = array_values(array_unique($ips));

    // 3) fallback: если extra_ips пуст — пытаемся вытащить IP из host (как в deploy)
    if (empty($ips)) {
        $host = (string)($srv['host'] ?? '');
        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);
        $host = trim($host);

        if (preg_match('~^\d+\.\d+\.\d+\.\d+$~', $host)) {
            $ips = [$host];
        } else {
            // список IP не задан
            $ips = [];
        }
    }

    // порядок — как удобно в UI
    sort($ips);
    return $ips;
}


}
