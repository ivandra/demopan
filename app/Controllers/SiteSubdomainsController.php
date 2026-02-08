<?php
// app/Controllers/SiteSubdomainsController.php

class SiteSubdomainsController extends Controller
{
    /* -------------------- helpers -------------------- */

    private function requireAuth(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
            exit;
        }
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

    private function resolveDnsA(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') return [];

        $ips = [];
        $recs = @dns_get_record($domain, DNS_A);
        if (is_array($recs)) {
            foreach ($recs as $r) {
                $ip = (string)($r['ip'] ?? '');
                if ($ip !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip)) {
                    $ips[] = $ip;
                }
            }
        }
        return array_values(array_unique($ips));
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
        SELECT
            id,
            provider,
            is_sandbox,
            username,
            api_user,
            api_key_enc,
            client_ip,
            is_default,
            username AS title
        FROM registrar_accounts
        WHERE provider='namecheap'
        ORDER BY is_default DESC, is_sandbox ASC, id ASC
    ");
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
     * Upsert:
     * 1) Удаляем любые записи для host из labels (любого типа).
     * 2) Добавляем ровно одну A-запись на нужный IP.
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

            if (isset($labelsSet[$host])) continue;

            $out[] = $h;
        }

        return $out;
    }

    /* -------------------- IP helpers -------------------- */

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

    private function normalizePostedIp(): string
    {
        $ip = trim((string)($_POST['ip'] ?? ''));
        if ($ip !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip)) return $ip;
        return '';
    }

    private function pickServerIpForSite(array $site): string
    {
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

    /* -------------------- Labels normalize for applyBatch -------------------- */

    private function normalizeLabels(array $labelsArr, string $labelsText): array
    {
        $out = [];

        foreach ($labelsArr as $x) {
            $out[] = (string)$x;
        }

        $labelsText = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $labelsText);
        foreach (preg_split('~[,\s]+~', $labelsText, -1, PREG_SPLIT_NO_EMPTY) as $x) {
            $out[] = (string)$x;
        }

        $res = [];
        foreach ($out as $raw) {
            $lb = strtolower(trim($raw));
            if ($lb === '') continue;

            if ($lb === '_default') {
                $res[] = '_default';
                continue;
            }

            if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $lb)) {
                continue;
            }

            $res[] = $lb;
        }

        $res = array_values(array_unique($res));
        sort($res);
        return $res;
    }

    private function getSiteLabels(int $siteId): array
    {
        $st = DB::pdo()->prepare("SELECT label FROM site_subdomains WHERE site_id=? ORDER BY label");
        $st->execute([$siteId]);
        return array_map(fn($r) => (string)$r['label'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /* -------------------- actions -------------------- */

    public function form(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $catalog = DB::pdo()
            ->query("SELECT * FROM subdomain_catalog WHERE is_active=1 ORDER BY label ASC")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = DB::pdo()->prepare("SELECT * FROM site_subdomains WHERE site_id=? ORDER BY label ASC");
        $stmt->execute([$siteId]);
        $siteSubs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $registrarAccounts = $this->listNamecheapAccounts();

        $serverIps = $this->getAvailableIpsForSite($site);
        $dnsA = $this->resolveDnsA((string)($site['domain'] ?? ''));

        // ВАЖНО: имена переменных строго под app/Views/sites/subdomains.php
        $this->view('sites/subdomains', compact(
            'siteId', 'site', 'catalog', 'siteSubs', 'registrarAccounts', 'serverIps', 'dnsA'
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
            return;
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

    /**
     * APPLY: привести список сабов к выбранному (labels[] + labels_text).
     * - _default всегда остается
     * - для template-multy синхронизируем папки subs/<label>/
     * - если apply_dns=1 => применяем DNS сразу
     */
    public function applyBatch(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $before = $this->getSiteLabels($siteId);

        $labels = $this->normalizeLabels($_POST['labels'] ?? [], (string)($_POST['labels_text'] ?? ''));

        // _default всегда должен существовать
        $labelsMap = ['_default' => true];
        foreach ($labels as $l) {
            $labelsMap[$l] = true;
        }
        $labels = array_keys($labelsMap);
        sort($labels);

        $pdo = DB::pdo();
        $pdo->beginTransaction();

        // 1) удаляем то, чего больше нет (кроме _default)
        $keepNoDefault = array_values(array_filter($labels, fn($x) => $x !== '_default'));

        if (count($keepNoDefault) > 0) {
            $in = implode(',', array_fill(0, count($keepNoDefault), '?'));
            $params = array_merge([$siteId], $keepNoDefault);

            $sql = "DELETE FROM site_subdomains
                    WHERE site_id=?
                      AND label <> '_default'
                      AND label NOT IN ($in)";
            $pdo->prepare($sql)->execute($params);

            // configs тоже чистим
            $sql2 = "DELETE FROM site_subdomain_configs
                     WHERE site_id=?
                       AND label <> '_default'
                       AND label NOT IN ($in)";
            $pdo->prepare($sql2)->execute($params);
        } else {
            $pdo->prepare("DELETE FROM site_subdomains WHERE site_id=? AND label <> '_default'")->execute([$siteId]);
            $pdo->prepare("DELETE FROM site_subdomain_configs WHERE site_id=? AND label <> '_default'")->execute([$siteId]);
        }

        // 2) upsert текущего набора (все включаем)
        $stIns = $pdo->prepare("
            INSERT INTO site_subdomains(site_id,label,enabled)
            VALUES(?,?,1)
            ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)
        ");

        foreach ($labels as $lb) {
            $stIns->execute([$siteId, $lb]);
        }

        $pdo->commit();

        $after = $this->getSiteLabels($siteId);

        // 3) Синхронизация папок (ТОЛЬКО template-multy)
        if (($site['template'] ?? '') === 'template-multy') {
            require_once __DIR__ . '/../Services/SubdomainProvisioner.php';
            $prov = new SubdomainProvisioner();

            $added   = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            foreach ($added as $lb) {
                if ($lb === '') continue;
                $prov->ensureForSite($siteId, $lb);
            }
            foreach ($removed as $lb) {
                if ($lb === '' || $lb === '_default') continue;
                $prov->deleteFolderForSite($siteId, $lb);
            }
        }

        // 4) опционально: сразу применить DNS (если галка)
        if (!empty($_POST['apply_dns'])) {
            $this->applyDnsForSite($siteId);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function updateIp(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $this->applyDnsForSite($siteId);

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteCatalog(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $before = $this->getSiteLabels($siteId);

        DB::pdo()->prepare("DELETE FROM site_subdomains WHERE site_id=? AND label <> '_default'")->execute([$siteId]);
        DB::pdo()->prepare("DELETE FROM site_subdomain_configs WHERE site_id=? AND label <> '_default'")->execute([$siteId]);

        $after = $this->getSiteLabels($siteId);

        if (($site['template'] ?? '') === 'template-multy') {
            require_once __DIR__ . '/../Services/SubdomainProvisioner.php';
            $prov = new SubdomainProvisioner();
            $removed = array_values(array_diff($before, $after));
            foreach ($removed as $lb) {
                if ($lb === '' || $lb === '_default') continue;
                $prov->deleteFolderForSite($siteId, $lb);
            }
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteCatalogDns(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/NamecheapClient.php';
        require_once __DIR__ . '/../Services/Crypto.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        // Берем сабы именно этого сайта (как и написано на кнопке во вьюхе)
        $st = DB::pdo()->prepare("SELECT label FROM site_subdomains WHERE site_id=? AND label <> '_default' ORDER BY label");
        $st->execute([$siteId]);
        $labels = array_map(fn($r) => (string)$r['label'], $st->fetchAll(PDO::FETCH_ASSOC));

        if (empty($labels)) {
            $this->redirect('/sites/subdomains?id=' . $siteId);
            return;
        }

        $acc = $this->loadRegistrarAccountForSite($site);
        if (!$acc) die('registrar account not found');

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

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function toggleOne(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);

        // совместимость: если вдруг осталась старая ссылка по sub_id
        $subId = (int)($_GET['sub_id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));

        if ($subId > 0) {
            DB::pdo()->prepare("UPDATE site_subdomains SET enabled=IF(enabled=1,0,1) WHERE id=?")->execute([$subId]);
        } elseif ($siteId > 0 && $label !== '') {
            DB::pdo()->prepare("
                UPDATE site_subdomains
                   SET enabled=IF(enabled=1,0,1)
                 WHERE site_id=? AND label=?
            ")->execute([$siteId, $label]);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteOne(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);

        $subId = (int)($_GET['sub_id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));

        if ($subId > 0) {
            DB::pdo()->prepare("DELETE FROM site_subdomains WHERE id=?")->execute([$subId]);
        } elseif ($siteId > 0 && $label !== '' && $label !== '_default') {
            DB::pdo()->prepare("DELETE FROM site_subdomains WHERE site_id=? AND label=?")->execute([$siteId, $label]);
            DB::pdo()->prepare("DELETE FROM site_subdomain_configs WHERE site_id=? AND label=?")->execute([$siteId, $label]);

            $site = $this->loadSite($siteId);
            if (($site['template'] ?? '') === 'template-multy') {
                require_once __DIR__ . '/../Services/SubdomainProvisioner.php';
                (new SubdomainProvisioner())->deleteFolderForSite($siteId, $label);
            }
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteAll(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        DB::pdo()->prepare("DELETE FROM site_subdomains WHERE site_id=?")->execute([$siteId]);
        DB::pdo()->prepare("DELETE FROM site_subdomain_configs WHERE site_id=?")->execute([$siteId]);

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    /* -------------------- DNS core (used by updateIp and applyBatch/apply_dns) -------------------- */

    private function applyDnsForSite(int $siteId): void
    {
        require_once __DIR__ . '/../Services/NamecheapClient.php';
        require_once __DIR__ . '/../Services/Crypto.php';

        $pdo = DB::pdo();

        $site = $this->loadSite($siteId);
        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        $updateRoot = (int)($_POST['update_root'] ?? 0) === 1;

        // enabled сабы (кроме _default)
        $st = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id=? AND enabled=1 AND label <> '_default' ORDER BY label");
        $st->execute([$siteId]);
        $labels = array_map(fn($r) => (string)$r['label'], $st->fetchAll(PDO::FETCH_ASSOC));

        if (empty($labels) && !$updateRoot) {
            // Нечего делать
            return;
        }

        $dnsOk = false;
        $dnsErr = '';

        try {
            $acc = $this->loadRegistrarAccountForSite($site);
            if (!$acc) throw new RuntimeException('registrar account not found');

            $doDns = function (array $accRow) use ($domain, $site, $labels, $updateRoot, $siteId): void {
                $nc = $this->makeNamecheapClientFromRegistrarAccount($accRow);
                [$sld, $tld] = $nc->splitSldTld($domain);

                $existingRaw = $nc->getHosts($sld, $tld);
                $hosts = $this->normalizeNamecheapHosts($existingRaw);

                $this->guardHostsBeforeSet($domain, $hosts, $existingRaw);

                // 1) IP: POST -> sites.vps_ip -> rootA(@) -> dns_get_record -> server fallback
                $ip = $this->normalizePostedIp();

                if ($ip === '') {
                    $manual = trim((string)($site['vps_ip'] ?? ''));
                    if ($manual !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $manual)) {
                        $ip = $manual;
                    }
                }

                if ($ip === '') {
                    $ip = $this->detectRootAFromHosts($hosts);
                }

                if ($ip === '') {
                    $dnsA = $this->resolveDnsA($domain);
                    if (!empty($dnsA)) $ip = (string)$dnsA[0];
                }

                if ($ip === '') {
                    $ip = $this->pickServerIpForSite($site);
                }

                if ($ip === '') {
                    throw new RuntimeException('cannot determine target ip (no POST ip, no vps_ip, no @ A, no DNS A, no server ip)');
                }

                // сохраним в sites.vps_ip, чтобы не гадать в следующий раз
                DB::pdo()->prepare("UPDATE sites SET vps_ip=? WHERE id=?")->execute([$ip, $siteId]);

                // 2) Upsert sub A
                if (!empty($labels)) {
                    $hosts = $this->mergeDnsHostsUpsertSubA($hosts, $labels, $ip, 300);
                }

                // 3) update root @ A если нужно
                if ($updateRoot) {
                    $hosts = $this->mergeDnsHostsUpsertSubA($hosts, ['@'], $ip, 300);
                }

                $nc->setHosts($sld, $tld, $hosts);
            };

            try {
                $doDns($acc);
                $dnsOk = true;
            } catch (Throwable $e1) {
                $dnsErr = $e1->getMessage();

                if ($this->isNamecheapAccountMismatchError($dnsErr)) {
                    $found = $this->detectRegistrarAccountForDomain($domain);
                    if ($found) {
                        $rid = (int)($found['id'] ?? 0);
                        $pdo->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?")->execute([$rid, $siteId]);

                        try {
                            $doDns($found);
                            $dnsOk = true;
                            $dnsErr = '';
                            @error_log('[DNS] retry OK site_id=' . $siteId . ' registrar_account_id=' . $rid);
                        } catch (Throwable $e2) {
                            $dnsOk = false;
                            $dnsErr = $e2->getMessage();
                            @error_log('[DNS] retry FAIL site_id=' . $siteId . ' err=' . $dnsErr);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $dnsOk = false;
            $dnsErr = $e->getMessage();
        }

        // статус в site_subdomains (если колонки существуют — ок; если нет, можно убрать эти UPDATE)
        if ($dnsOk) {
            @DB::pdo()->prepare("UPDATE site_subdomains SET dns_status='ok', last_error=NULL WHERE site_id=?")->execute([$siteId]);
        } else {
            @DB::pdo()->prepare("UPDATE site_subdomains SET dns_status='error', last_error=? WHERE site_id=?")->execute([$dnsErr, $siteId]);
            @error_log('[DNS apply] site_id=' . $siteId . ' err=' . $dnsErr);
        }
    }

    /* -------------------- SSL -------------------- */

    private function ensureWildcardSelfSignedIfNeeded(array $site): bool
    {
        // ВСТАВЬ СВОЮ РЕАЛИЗАЦИЮ (у тебя она уже есть).
        // Этот контроллер DNS-часть чинит, SSL не трогаем.
        return false;
    }
}
