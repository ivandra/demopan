<?php

class SiteSubdomainsController extends Controller
{
    /* -------------------- helpers -------------------- */

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) $this->redirect('/login');
    }

    private function loadSite(int $siteId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
        $stmt->execute([$siteId]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$site) die('site not found');
        return $site;
    }

    private function loadServer(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) die('server not found');
        return $row;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = preg_replace('~^https?://~i', '', trim($domain));
        return rtrim($domain, '/');
    }

    private function guardHostsBeforeSet(string $domain, array $normalizedHosts, array $rawHosts = []): void
    {
        @error_log('[Namecheap getHosts] domain=' . $domain
            . ' raw_count=' . count($rawHosts)
            . ' norm_count=' . count($normalizedHosts)
            . ' sample=' . json_encode(array_slice($normalizedHosts, 0, 3), JSON_UNESCAPED_UNICODE)
        );

        // Предохранитель: если вернулось слишком мало — НЕ делаем setHosts
        if (count($normalizedHosts) < 2) {
            throw new RuntimeException(
                'Namecheap: suspicious normalized hosts count=' . count($normalizedHosts) . ' (skip setHosts to avoid wiping)'
            );
        }
    }

    /* -------------------- registrar helpers -------------------- */

    private function listNamecheapAccounts(): array
    {
        $st = DB::pdo()->prepare("
            SELECT id, provider, is_sandbox, username, api_user, api_key_enc, client_ip, is_default
            FROM registrar_accounts
            WHERE provider='namecheap'
            ORDER BY is_default DESC, is_sandbox ASC, id ASC
        ");
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        @error_log('[NC listAccounts] rows=' . count($rows) . ' sample=' . json_encode(
            array_map(function ($r) {
                return [
                    'id' => $r['id'] ?? null,
                    'username' => $r['username'] ?? null,
                    'api_user' => $r['api_user'] ?? null,
                    'api_key_enc_len' => strlen((string)($r['api_key_enc'] ?? '')),
                    'client_ip' => $r['client_ip'] ?? null,
                    'is_sandbox' => $r['is_sandbox'] ?? null,
                    'is_default' => $r['is_default'] ?? null,
                ];
            }, array_slice($rows, 0, 3)),
            JSON_UNESCAPED_UNICODE
        ));

        return $rows;
    }

    private function loadRegistrarAccountById(int $id): ?array
    {
        if ($id <= 0) return null;
        $st = DB::pdo()->prepare("SELECT * FROM registrar_accounts WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadRegistrarAccountForSite(array $site): ?array
    {
        $pdo = DB::pdo();

        $rid = (int)($site['registrar_account_id'] ?? 0);
        if ($rid > 0) {
            $st = $pdo->prepare("SELECT * FROM registrar_accounts WHERE id=? LIMIT 1");
            $st->execute([$rid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        // fallback на default
        $st = $pdo->prepare("SELECT * FROM registrar_accounts WHERE provider='namecheap' AND is_default=1 LIMIT 1");
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function makeNamecheapClientFromRegistrarAccount(array $acc): NamecheapClient
    {
        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';

        $isSandbox = (int)($acc['is_sandbox'] ?? 1) === 1;

        $endpoint = $isSandbox
            ? 'https://api.sandbox.namecheap.com/xml.response'
            : 'https://api.namecheap.com/xml.response';

        $enc = (string)($acc['api_key_enc'] ?? '');
        if ($enc === '') {
            throw new RuntimeException('Namecheap: api_key_enc is empty for acc_id=' . (int)($acc['id'] ?? 0));
        }

        $apiKey = Crypto::decrypt($enc);

        // timeout НЕ берем из БД (чтобы не зависеть от колонки)
        $timeout = 30;

        @error_log('[NC makeClient] acc_id=' . (int)($acc['id'] ?? 0)
            . ' app_key_len=' . strlen((string)config('app_key', ''))
            . ' api_key_enc_len=' . strlen($enc)
        );

        return new NamecheapClient(
            $endpoint,
            (string)($acc['api_user'] ?? ''),
            $apiKey,
            (string)($acc['username'] ?? ''),
            (string)($acc['client_ip'] ?? ''),
            $timeout
        );
    }

    private function isNamecheapAccountMismatchError(string $msg): bool
    {
        $m = strtolower($msg);

        if (strpos($m, '2019166') !== false) return true;
        if (strpos($m, "doesn't seem to be associated") !== false) return true;
        if (strpos($m, 'domain name not found') !== false) return true;
        if (strpos($m, 'domain not found') !== false) return true;

        return false;
    }

    private function detectRegistrarAccountForDomain(string $domain): ?array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') return null;

        $accounts = $this->listNamecheapAccounts();

        foreach ($accounts as $acc) {
            try {
                $nc = $this->makeNamecheapClientFromRegistrarAccount($acc);
                $info = $nc->getDomainInfo($domain);
                if (is_array($info)) return $acc;
            } catch (Throwable $e) {
                @error_log('[DetectRegistrar] domain=' . $domain . ' acc_id=' . (int)($acc['id'] ?? 0) . ' err=' . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /* -------------------- DNS helpers -------------------- */

    private function normalizeNamecheapHosts(array $hosts): array
    {
        $out = [];

        foreach ($hosts as $h) {
            if (!is_array($h)) continue;

            $host = (string)($h['host'] ?? $h['HostName'] ?? $h['Name'] ?? '');
            $type = (string)($h['type'] ?? $h['RecordType'] ?? $h['Type'] ?? '');
            $addr = (string)($h['address'] ?? $h['Address'] ?? '');
            $ttl  = (string)($h['ttl'] ?? $h['TTL'] ?? 300);

            if ($addr === '' && isset($h['Value'])) {
                // на всякий случай, иногда встречается Value
                $addr = (string)$h['Value'];
            }

            $row = [
                'host'    => trim($host),
                'type'    => strtoupper(trim($type)),
                'address' => trim($addr),
                'ttl'     => (int)$ttl,
            ];

            if (isset($h['mxpref']) || isset($h['MXPref'])) {
                $row['mxpref'] = (int)($h['mxpref'] ?? $h['MXPref']);
            }

            if ($row['host'] === '' || $row['type'] === '' || $row['address'] === '') continue;

            $out[] = $row;
        }

        return $out;
    }

    /**
     * ВАЖНО: Upsert логика.
     * 1) Удаляем любые записи для host из labels (любого типа).
     * 2) Добавляем ровно одну A-запись на нужный IP (чтобы не плодить дубли и чтобы IP реально менялся).
     */
    private function mergeDnsHostsUpsertSubA(array $existing, array $labels, string $ip, int $ttl = 300): array
    {
        $labelsSet = [];
        foreach ($labels as $l) {
            $l = strtolower(trim((string)$l));
            if ($l !== '') $labelsSet[$l] = true;
        }

        $out = [];

        foreach ($existing as $h) {
            if (!is_array($h)) continue;

            $host = strtolower(trim((string)($h['host'] ?? '')));
            $type = strtoupper(trim((string)($h['type'] ?? '')));
            $addr = trim((string)($h['address'] ?? ''));

            if ($host === '' || $type === '' || $addr === '') continue;

            if (isset($labelsSet[$host])) {
                // выкидываем любые записи на этот host (A/CNAME/AAAA/TXT...)
                continue;
            }

            $out[] = $h;
        }

        foreach (array_keys($labelsSet) as $l) {
            $out[] = [
                'host'    => $l,
                'type'    => 'A',
                'address' => $ip,
                'ttl'     => $ttl,
            ];
        }

        return $out;
    }

    private function mergeDnsHostsRemoveLabels(array $existing, array $labels): array
    {
        $labelsSet = [];
        foreach ($labels as $l) $labelsSet[strtolower(trim($l))] = true;

        $out = [];
        foreach ($existing as $h) {
            if (!is_array($h)) continue;

            $host = strtolower(trim((string)($h['host'] ?? '')));
            $type = strtoupper(trim((string)($h['type'] ?? '')));
            $addr = trim((string)($h['address'] ?? ''));

            if ($host === '' || $type === '' || $addr === '') continue;

            // удаляем любые записи для host из labels (A/CNAME/MX/AAAA и т.д.)
            if (isset($labelsSet[$host])) continue;

            $out[] = $h;
        }

        return $out;
    }

    /* -------------------- IP helpers -------------------- */

    /**
     * ТВОЕ требование: IP для поддоменов берем по A(@) текущего домена.
     * Это основной метод.
     */
    private function detectRootAFromHosts(array $hosts): string
    {
        foreach ($hosts as $h) {
            if (!is_array($h)) continue;

            $host = strtolower(trim((string)($h['host'] ?? '')));
            $type = strtoupper(trim((string)($h['type'] ?? '')));
            if ($host !== '@' || $type !== 'A') continue;

            $ip = trim((string)($h['address'] ?? ''));
            if ($ip !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip)) return $ip;
        }
        return '';
    }

    /**
     * optional: если ты все же разрешаешь вручную передать ip через форму (updateIp и т.п.)
     */
    private function normalizePostedIp(): string
    {
        $ip = trim((string)($_POST['ip'] ?? ''));
        if ($ip !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip)) return $ip;
        return '';
    }

    /**
     * fallback: если A(@) нет — берем из VPS (как раньше).
     */
    private function pickServerIpForSite(array $site): string
    {
        // если руками задан vps_ip — используем
        $manual = trim((string)($site['vps_ip'] ?? ''));
        if ($manual !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $manual)) return $manual;

        $sid = (int)($site['fastpanel_server_id'] ?? 0);
        if ($sid <= 0) return '';

        $stmt = DB::pdo()->prepare("SELECT host, extra_ips FROM fastpanel_servers WHERE id=? LIMIT 1");
        $stmt->execute([$sid]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$server) return '';

        $ips = [];

        $extra = trim((string)($server['extra_ips'] ?? ''));
        if ($extra !== '') {
            foreach (preg_split('~[,\s]+~', $extra) as $v) {
                $v = trim($v);
                if ($v !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $v)) $ips[] = $v;
            }
        }

        $ips = array_values(array_unique($ips));
        if (!empty($ips)) return $ips[0];

        $host = (string)($server['host'] ?? '');
        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);
        $host = trim($host);

        if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $host)) return $host;

        return '';
    }

    private function getAvailableIpsForSite(array $site): array
    {
        $sid = (int)($site['fastpanel_server_id'] ?? 0);
        if ($sid <= 0) return [];

        $st = DB::pdo()->prepare("SELECT host, extra_ips FROM fastpanel_servers WHERE id=? LIMIT 1");
        $st->execute([$sid]);
        $server = $st->fetch(PDO::FETCH_ASSOC);
        if (!$server) return [];

        $ips = [];

        $extra = trim((string)($server['extra_ips'] ?? ''));
        if ($extra !== '') {
            foreach (preg_split('~[,\s]+~', $extra) as $v) {
                $v = trim($v);
                if ($v !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $v)) $ips[] = $v;
            }
        }

        $host = (string)($server['host'] ?? '');
        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);
        $host = trim($host);
        if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $host)) $ips[] = $host;

        $manual = trim((string)($site['vps_ip'] ?? ''));
        if ($manual !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $manual)) $ips[] = $manual;

        $ips = array_values(array_unique($ips));
        @error_log('[availableIps] count=' . count($ips) . ' siteId=' . (int)($site['id'] ?? 0));
        return $ips;
    }

    /* -------------------- actions -------------------- */

    public function form(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $catalog = DB::pdo()->query("SELECT * FROM subdomain_catalog WHERE is_active=1 ORDER BY label ASC")
            ->fetchAll(PDO::FETCH_ASSOC);

        $stmt = DB::pdo()->prepare("SELECT * FROM site_subdomains WHERE site_id=? ORDER BY label ASC");
        $stmt->execute([$siteId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $registrarAccounts = $this->listNamecheapAccounts();
        $currentAccount = $this->loadRegistrarAccountById((int)($site['registrar_account_id'] ?? 0));

        $availableIps = $this->getAvailableIpsForSite($site);

        $this->view('sites/subdomains', compact(
            'site', 'catalog', 'rows', 'registrarAccounts', 'currentAccount', 'availableIps'
        ));
    }

    public function setRegistrar(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $rid = (int)($_POST['registrar_account_id'] ?? 0);

        if ($rid === 0) {
            DB::pdo()->prepare("UPDATE sites SET registrar_account_id=NULL WHERE id=?")->execute([$siteId]);
            $this->redirect('/sites/subdomains?id=' . $siteId);
        }

        $st = DB::pdo()->prepare("SELECT id FROM registrar_accounts WHERE id=? AND provider='namecheap' LIMIT 1");
        $st->execute([$rid]);
        if (!$st->fetchColumn()) die('bad registrar account');

        DB::pdo()->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?")->execute([$rid, $siteId]);

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function detectRegistrar(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        $found = $this->detectRegistrarAccountForDomain($domain);

        if ($found) {
            $rid = (int)($found['id'] ?? 0);

            DB::pdo()->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?")
                ->execute([$rid, $siteId]);

            @error_log('[DetectRegistrar] OK site_id=' . $siteId . ' domain=' . $domain . ' registrar_account_id=' . $rid);
        } else {
            @error_log('[DetectRegistrar] NOT FOUND site_id=' . $siteId . ' domain=' . $domain);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function applyBatch(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/FastpanelClient.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $pdo = DB::pdo();

        $site = $this->loadSite($siteId);
        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        // 1) labels каталога
        $catalog = $pdo->query("SELECT label FROM subdomain_catalog WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $labelsMap = [];
        foreach ($catalog as $c) {
            $l = strtolower(trim((string)($c['label'] ?? '')));
            if ($l !== '') $labelsMap[$l] = true;
        }
        $labels = array_keys($labelsMap);

        if (empty($labels)) {
            $this->redirect('/sites/subdomains?id=' . $siteId);
        }

        // 2) upsert в site_subdomains
        $ins = $pdo->prepare("
            INSERT INTO site_subdomains(site_id,label,fqdn,from_catalog,enabled,dns_status,ssl_status,last_error)
            VALUES(?,?,?,?,?,NULL,NULL,NULL)
            ON DUPLICATE KEY UPDATE
                fqdn=VALUES(fqdn),
                from_catalog=1,
                updated_at=NOW()
        ");

        $fqdns = [];
        foreach ($labels as $l) {
            $fqdn = $l . '.' . $domain;
            $fqdns[] = $fqdn;
            $ins->execute([$siteId, $l, $fqdn, 1, 1]);
        }

        // 3) FastPanel aliases
        $vpsOk = ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0);
        if ($vpsOk) {
            $serverId = (int)($site['fastpanel_server_id'] ?? 0);
            if ($serverId > 0) {
                $server = $this->loadServer($serverId);
                $password = Crypto::decrypt((string)$server['password_enc']);

                $fp = new FastpanelClient(
                    (string)$server['host'],
                    (bool)$server['verify_tls'],
                    (int)config('fastpanel.timeout', 30)
                );
                $fp->login((string)$server['username'], $password);
                $fp->addSiteAliases((int)$site['fp_site_id'], $fqdns);
            }
        }

        // 4) DNS Namecheap
        $dnsOk = false;
        $dnsErr = '';

        try {
            $acc = $this->loadRegistrarAccountForSite($site);
            if (!$acc) {
                $dnsErr = 'registrar account not found (dns skipped)';
            } else {

                $doDns = function (array $accRow) use ($domain, $site, $labels, &$dnsErr): bool {
                    $nc = $this->makeNamecheapClientFromRegistrarAccount($accRow);
                    [$sld, $tld] = $nc->splitSldTld($domain);

                    $existingRaw = $nc->getHosts($sld, $tld);
                    $existing = $this->normalizeNamecheapHosts($existingRaw);

                    $this->guardHostsBeforeSet($domain, $existing, $existingRaw);

                    // ---- ВАЖНО: IP берем от A(@) домена ----
                    $targetIp = $this->detectRootAFromHosts($existing);

                    // optional: если хочешь разрешить ручной ip (например, форма могла прислать ip)
                    // $posted = $this->normalizePostedIp();
                    // if ($posted !== '') $targetIp = $posted;

                    // fallback: если A(@) нет (на домене еще не настроен DNS)
                    if ($targetIp === '') {
                        $targetIp = $this->pickServerIpForSite($site);
                    }

                    if ($targetIp === '') {
                        $dnsErr = 'cannot determine target ip (no @ A and no vps ip fallback)';
                        return false;
                    }

                    @error_log('[DNS] targetIp=' . $targetIp . ' domain=' . $domain);

                    // Upsert sub A (без дублей + всегда нужный IP)
                    $merged = $this->mergeDnsHostsUpsertSubA($existing, $labels, $targetIp, 300);

                    @error_log('[DNS] merged_sample=' . json_encode(array_slice(
                        array_values(array_filter($merged, function ($h) {
                            return isset($h['type']) && strtoupper((string)$h['type']) === 'A';
                        })),
                        0,
                        3
                    ), JSON_UNESCAPED_UNICODE));

                    $nc->setHosts($sld, $tld, $merged);

                    return true;
                };

                try {
                    $dnsOk = $doDns($acc);
                } catch (Throwable $e1) {
                    $dnsErr = $e1->getMessage();

                    // если аккаунт не тот — пробуем автоопределить и ретраим
                    if ($this->isNamecheapAccountMismatchError($dnsErr)) {
                        $found = $this->detectRegistrarAccountForDomain($domain);
                        if ($found) {
                            $rid = (int)($found['id'] ?? 0);

                            DB::pdo()->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?")
                                ->execute([$rid, (int)($site['id'] ?? 0)]);

                            $site['registrar_account_id'] = $rid;

                            try {
                                $dnsOk = $doDns($found);
                                $dnsErr = '';
                                @error_log('[DNS] retry OK site_id=' . (int)($site['id'] ?? 0) . ' registrar_account_id=' . $rid);
                            } catch (Throwable $e2) {
                                $dnsOk = false;
                                $dnsErr = $e2->getMessage();
                                @error_log('[DNS] retry FAIL site_id=' . (int)($site['id'] ?? 0) . ' err=' . $dnsErr);
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $dnsErr = $e->getMessage();
            $dnsOk = false;
        }

        if ($dnsOk) {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='ok', last_error=NULL WHERE site_id=? AND from_catalog=1")
                ->execute([$siteId]);
        } else {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='skip', last_error=? WHERE site_id=? AND from_catalog=1")
                ->execute([$dnsErr, $siteId]);
            @error_log('[DNS] site_id=' . $siteId . ' dnsOk=0 err=' . $dnsErr);
        }

        // 5) SSL (если используешь)
        try {
            if ($vpsOk) {
                $sslDone = $this->ensureWildcardSelfSignedIfNeeded($site);
                if ($sslDone) {
                    $pdo->prepare("UPDATE site_subdomains SET ssl_status='ok', last_error=NULL WHERE site_id=? AND from_catalog=1")
                        ->execute([$siteId]);
                }
            }
        } catch (Throwable $e) {
            @error_log('[SSL] site_id=' . $siteId . ' ' . $e->getMessage());
            $pdo->prepare("UPDATE site_subdomains SET ssl_status='error', last_error=? WHERE site_id=? AND from_catalog=1")
                ->execute([$e->getMessage(), $siteId]);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function updateIp(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        $newIp = trim((string)($_POST['ip'] ?? ''));
        if ($newIp === '' || !preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $newIp)) die('bad ip');

        $updateRoot = (int)($_POST['update_root'] ?? 0) === 1;

        DB::pdo()->prepare("UPDATE sites SET vps_ip=? WHERE id=?")->execute([$newIp, $siteId]);

        // labels каталога
        $pdo = DB::pdo();
        $catalog = $pdo->query("SELECT label FROM subdomain_catalog WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $labelsSet = [];
        foreach ($catalog as $c) {
            $l = strtolower(trim((string)($c['label'] ?? '')));
            if ($l !== '') $labelsSet[$l] = true;
        }
        $labels = array_keys($labelsSet);

        $dnsOk = false;
        $dnsErr = '';

        try {
            $acc = $this->loadRegistrarAccountForSite($site);
            if (!$acc) throw new RuntimeException('registrar account not found');

            $nc = $this->makeNamecheapClientFromRegistrarAccount($acc);
            [$sld, $tld] = $nc->splitSldTld($domain);

            $existingRaw = $nc->getHosts($sld, $tld);
            $hosts = $this->normalizeNamecheapHosts($existingRaw);

            $this->guardHostsBeforeSet($domain, $hosts, $existingRaw);

            $labelsMap = array_fill_keys($labels, true);

            // обновляем существующие A
            foreach ($hosts as &$h) {
                if (($h['type'] ?? '') !== 'A') continue;

                $host = strtolower(trim((string)($h['host'] ?? '')));

                if ($updateRoot && $host === '@') {
                    $h['address'] = $newIp;
                    continue;
                }

                if (isset($labelsMap[$host])) {
                    $h['address'] = $newIp;
                }
            }
            unset($h);

            // upsert (гарантия одного A на label)
            $hosts = $this->mergeDnsHostsUpsertSubA($hosts, $labels, $newIp, 300);

            $nc->setHosts($sld, $tld, $hosts);
            $dnsOk = true;

        } catch (Throwable $e) {
            $dnsErr = $e->getMessage();
        }

        if ($dnsOk) {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='ok', last_error=NULL WHERE site_id=? AND from_catalog=1")
                ->execute([$siteId]);
        } else {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='error', last_error=? WHERE site_id=? AND from_catalog=1")
                ->execute([$dnsErr, $siteId]);
            @error_log('[DNS update-ip] site_id=' . $siteId . ' err=' . $dnsErr);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteCatalog(): void
    {
        $this->requireAuth();
        $this->deleteCatalogInternal(true, true);
    }

    public function deleteCatalogDns(): void
    {
        $this->requireAuth();
        $this->deleteCatalogInternal(true, false);
    }

    private function deleteCatalogInternal(bool $doDns, bool $doDb): void
    {
        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $pdo = DB::pdo();
        $site = $this->loadSite($siteId);

        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        $catalog = $pdo->query("SELECT label FROM subdomain_catalog WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $labelsSet = [];
        foreach ($catalog as $c) {
            $l = strtolower(trim((string)($c['label'] ?? '')));
            if ($l !== '') $labelsSet[$l] = true;
        }
        $labels = array_keys($labelsSet);

        if ($doDns) {
            try {
                $acc = $this->loadRegistrarAccountForSite($site);
                if ($acc) {
                    $nc = $this->makeNamecheapClientFromRegistrarAccount($acc);
                    [$sld, $tld] = $nc->splitSldTld($domain);

                    $existingRaw = $nc->getHosts($sld, $tld);
                    $existing = $this->normalizeNamecheapHosts($existingRaw);

                    $this->guardHostsBeforeSet($domain, $existing, $existingRaw);

                    $merged = $this->mergeDnsHostsRemoveLabels($existing, $labels);

                    if (!empty($merged)) {
                        $nc->setHosts($sld, $tld, $merged);
                    } else {
                        throw new RuntimeException('Namecheap: merged hosts became empty (skip setHosts)');
                    }
                }
            } catch (Throwable $e) {
                @error_log('[DNS delete-catalog] site_id=' . $siteId . ' err=' . $e->getMessage());
            }
        }

        if ($doDb) {
            $pdo->prepare("DELETE FROM site_subdomains WHERE site_id=? AND from_catalog=1")->execute([$siteId]);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function toggleOne(): void
    {
        $this->requireAuth();

        $subId = (int)($_GET['sub_id'] ?? 0);
        if ($subId > 0) {
            DB::pdo()->prepare("UPDATE site_subdomains SET enabled=IF(enabled=1,0,1) WHERE id=?")->execute([$subId]);
        }

        $siteId = (int)($_GET['id'] ?? 0);
        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteOne(): void
    {
        $this->requireAuth();

        $subId = (int)($_GET['sub_id'] ?? 0);
        if ($subId > 0) {
            DB::pdo()->prepare("DELETE FROM site_subdomains WHERE id=?")->execute([$subId]);
        }

        $siteId = (int)($_GET['id'] ?? 0);
        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteAll(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        DB::pdo()->prepare("DELETE FROM site_subdomains WHERE site_id=?")->execute([$siteId]);

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    /* -------------------- SSL -------------------- */

    private function ensureWildcardSelfSignedIfNeeded(array $site): bool
    {
        // ВСТАВЬ СВОЮ РЕАЛИЗАЦИЮ (у тебя она уже есть).
        // Этот файл DNS-часть чинит, SSL не трогаем.
        return false;
    }
}
