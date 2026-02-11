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

    private function logMsg(string $msg): void
    {
        @error_log($msg);
    }

    private function tableColumns(string $table): array
    {
        $pdo = DB::pdo();
        $cols = [];
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $f = (string)($r['Field'] ?? '');
                if ($f !== '') $cols[strtolower($f)] = true;
            }
        } catch (Throwable $e) {
            @error_log('[tableColumns] table=' . $table . ' err=' . $e->getMessage());
        }
        return $cols;
    }

    private function pickFirstExistingColumn(array $colsMap, array $candidates): string
    {
        foreach ($candidates as $c) {
            $lc = strtolower((string)$c);
            if (isset($colsMap[$lc])) return (string)$c;
        }
        return '';
    }

    private function setFolderStatusByLabel(int $siteId, string $label, string $status, ?string $error = null): void
    {
        $label = trim((string)$label);
        if ($label === '') return;

        DB::pdo()->prepare("
            UPDATE site_subdomains
               SET folder_status=?,
                   folder_error=?,
                   folder_updated_at=NOW()
             WHERE site_id=? AND label=?
             LIMIT 1
        ")->execute([$status, $error, $siteId, $label]);
    }

    /* -------------------- registrar helpers -------------------- */

    private function listNamecheapAccounts(): array
    {
        $pdo = DB::pdo();

        $cols = $this->tableColumns('registrar_accounts');

        $select = [];
        if (isset($cols['id'])) $select[] = 'id';
        if (isset($cols['provider'])) $select[] = 'provider';

        foreach (['is_sandbox', 'username', 'api_user', 'api_key_enc', 'client_ip', 'is_default'] as $f) {
            if (isset($cols[strtolower($f)])) $select[] = $f;
        }

        $titleCol = $this->pickFirstExistingColumn($cols, ['title', 'name', 'label', 'account_name', 'comment']);
        if ($titleCol !== '') $select[] = $titleCol;

        if (empty($select)) {
            $sql = "SELECT * FROM registrar_accounts WHERE provider='namecheap' ORDER BY id ASC";
        } else {
            $sql = "SELECT " . implode(', ', array_unique($select)) . "
                    FROM registrar_accounts
                    WHERE provider='namecheap'";

            $order = [];
            if (isset($cols['is_default'])) $order[] = 'is_default DESC';
            if (isset($cols['is_sandbox'])) $order[] = 'is_sandbox ASC';
            $order[] = 'id ASC';
            $sql .= " ORDER BY " . implode(', ', $order);
        }

        $st = $pdo->prepare($sql);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);

            $title = '';
            if ($titleCol !== '' && array_key_exists($titleCol, $r)) {
                $title = trim((string)$r[$titleCol]);
            }
            if ($title === '') $title = trim((string)($r['username'] ?? ''));
            if ($title === '') $title = trim((string)($r['api_user'] ?? ''));
            if ($title === '') $title = 'Account #' . $id;

            $out[] = [
                'id'          => $id,
                'provider'    => (string)($r['provider'] ?? 'namecheap'),
                'is_sandbox'  => (int)($r['is_sandbox'] ?? 0),
                'username'    => (string)($r['username'] ?? ''),
                'api_user'    => (string)($r['api_user'] ?? ''),
                'api_key_enc' => (string)($r['api_key_enc'] ?? ''),
                'client_ip'   => (string)($r['client_ip'] ?? ''),
                'is_default'  => (int)($r['is_default'] ?? 0),
                'title'       => $title,
            ];
        }

        @error_log('[NC listAccounts] rows=' . count($out));
        return $out;
    }

    private function loadRegistrarAccountById(int $id): ?array
    {
        if ($id <= 0) return null;

        $pdo = DB::pdo();
        $cols = $this->tableColumns('registrar_accounts');

        $select = [];
        foreach (['id','provider','is_sandbox','username','api_user','api_key_enc','client_ip','is_default'] as $f) {
            if (isset($cols[strtolower($f)])) $select[] = $f;
        }
        $titleCol = $this->pickFirstExistingColumn($cols, ['title','name','label','account_name','comment']);
        if ($titleCol !== '') $select[] = $titleCol;

        $sql = empty($select)
            ? "SELECT * FROM registrar_accounts WHERE id=? LIMIT 1"
            : "SELECT " . implode(', ', array_unique($select)) . " FROM registrar_accounts WHERE id=? LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $title = '';
        if ($titleCol !== '' && array_key_exists($titleCol, $row)) $title = trim((string)$row[$titleCol]);
        if ($title === '') $title = trim((string)($row['username'] ?? ''));
        if ($title === '') $title = trim((string)($row['api_user'] ?? ''));
        if ($title === '') $title = 'Account #' . (int)($row['id'] ?? $id);

        $row['title'] = $title;

        return $row;
    }

    private function loadRegistrarAccountForSite(array $site): ?array
    {
        $pdo = DB::pdo();

        $rid = (int)($site['registrar_account_id'] ?? 0);
        if ($rid > 0) {
            $row = $this->loadRegistrarAccountById($rid);
            if ($row) return $row;
        }

        $cols = $this->tableColumns('registrar_accounts');
        if (!isset($cols['provider'])) return null;

        $sql = "SELECT * FROM registrar_accounts WHERE provider='namecheap'";
        if (isset($cols['is_default'])) $sql .= " AND is_default=1";
        $sql .= " ORDER BY id ASC LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') $title = trim((string)($row['name'] ?? ''));
        if ($title === '') $title = trim((string)($row['label'] ?? ''));
        if ($title === '') $title = trim((string)($row['username'] ?? ''));
        if ($title === '') $title = trim((string)($row['api_user'] ?? ''));
        if ($title === '') $title = 'Account #' . (int)($row['id'] ?? 0);
        $row['title'] = $title;

        return $row;
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
        $timeout = 30;

        @error_log('[NC makeClient] acc_id=' . (int)($acc['id'] ?? 0)
            . ' api_user=' . (string)($acc['api_user'] ?? '')
            . ' username=' . (string)($acc['username'] ?? '')
            . ' endpoint=' . $endpoint
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

    private function guardHostsBeforeSet(string $domain, array $normalizedHosts, array $rawHosts = []): void
    {
        @error_log('[Namecheap getHosts] domain=' . $domain
            . ' raw_count=' . count($rawHosts)
            . ' norm_count=' . count($normalizedHosts)
            . ' sample=' . json_encode(array_slice($normalizedHosts, 0, 3), JSON_UNESCAPED_UNICODE)
        );

        if (count($normalizedHosts) < 2) {
            throw new RuntimeException(
                'Namecheap: suspicious normalized hosts count=' . count($normalizedHosts) . ' (skip setHosts to avoid wiping)'
            );
        }
    }

    private function mergeDnsHostsUpsertSubA(array $existing, array $labels, string $ip, int $ttl = 300): array
    {
        $labelsSet = [];
        foreach ($labels as $l) {
            $l = strtolower(trim((string)$l));
			if ($l === '' || $l === '_default') continue; // <--- защита
			$labelsSet[$l] = true;
        }

        $out = [];

        foreach ($existing as $h) {
            if (!is_array($h)) continue;

            $host = strtolower(trim((string)($h['host'] ?? '')));
            $type = strtoupper(trim((string)($h['type'] ?? '')));
            $addr = trim((string)($h['address'] ?? ''));

            if ($host === '' || $type === '' || $addr === '') continue;

            if (isset($labelsSet[$host])) {
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
        $siteSubs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $registrarAccounts = $this->listNamecheapAccounts();

        $serverIps = $this->getAvailableIpsForSite($site);

        $dnsA = [];
        try {
            require_once __DIR__ . '/../Services/Crypto.php';
            require_once __DIR__ . '/../Services/NamecheapClient.php';

            $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
            if ($domain !== '') {
                $acc = $this->loadRegistrarAccountForSite($site);
                if ($acc) {
                    $nc = $this->makeNamecheapClientFromRegistrarAccount($acc);
                    [$sld, $tld] = $nc->splitSldTld($domain);
                    $raw = $nc->getHosts($sld, $tld);
                    $norm = $this->normalizeNamecheapHosts($raw);
                    foreach ($norm as $h) {
                        if (strtolower((string)($h['host'] ?? '')) === '@' && strtoupper((string)($h['type'] ?? '')) === 'A') {
                            $ip = (string)($h['address'] ?? '');
                            if ($ip !== '') $dnsA[] = $ip;
                        }
                    }
                    $dnsA = array_values(array_unique($dnsA));
                }
            }
        } catch (Throwable $e) {
            @error_log('[form dnsA] site_id=' . $siteId . ' err=' . $e->getMessage());
        }

        // ВАЖНО: передаем siteId именно как $siteId, чтобы в форме не получалось id=0
        $this->view('sites/subdomains', [
            'siteId' => $siteId,
            'site' => $site,
            'catalog' => $catalog,
            'siteSubs' => $siteSubs,
            'registrarAccounts' => $registrarAccounts,
            'serverIps' => $serverIps,
            'dnsA' => $dnsA,
        ]);
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

        $st = DB::pdo()->prepare("SELECT id FROM registrar_accounts WHERE id=? LIMIT 1");
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
    require_once __DIR__ . '/../Services/SubdomainProvisioner.php';

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    $pdo = DB::pdo();

    $site = $this->loadSite($siteId);
    $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
    if ($domain === '') die('bad domain');

    // ---- 1) собираем выбранные labels из формы ----
    $labelsMap = [];

    // Чекбоксы
    if (!empty($_POST['labels']) && is_array($_POST['labels'])) {
        foreach ($_POST['labels'] as $l) {
            $l = strtolower(trim((string)$l));
            if ($l !== '') $labelsMap[$l] = true;
        }
    }

    // Быстрый ввод (через запятую/пробел)
    $labelsText = trim((string)($_POST['labels_text'] ?? ''));
    if ($labelsText !== '') {
        $parts = preg_split('~[,\s]+~u', $labelsText);
        if (is_array($parts)) {
            foreach ($parts as $p) {
                $p = strtolower(trim((string)$p));
                if ($p !== '') $labelsMap[$p] = true;
            }
        }
    }

    // _default всегда держим (служебная папка/шаблон)
    $labelsMap['_default'] = true;

    $labels = array_keys($labelsMap);
    sort($labels);

    if (empty($labels)) {
        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    // DNS/FP списки БЕЗ _default
    $labelsDns = array_values(array_filter($labels, function ($v) {
        return $v !== '_default';
    }));

    @error_log('[applyBatch] site_id=' . $siteId
        . ' labels_count=' . count($labels)
        . ' labelsDns_count=' . count($labelsDns)
        . ' labels_sample=' . json_encode(array_slice($labels, 0, 10), JSON_UNESCAPED_UNICODE)
        . ' labelsDns_sample=' . json_encode(array_slice($labelsDns, 0, 10), JSON_UNESCAPED_UNICODE)
    );

    // ---- 2) приводим site_subdomains к выбранному списку (add missing, delete лишние кроме _default) ----
    $existingRows = $pdo->prepare("SELECT id,label FROM site_subdomains WHERE site_id=?");
    $existingRows->execute([$siteId]);
    $existing = $existingRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $existMap = [];
    foreach ($existing as $r) {
        $lb = strtolower(trim((string)($r['label'] ?? '')));
        if ($lb !== '') $existMap[$lb] = (int)($r['id'] ?? 0);
    }

    $labelsSet = array_fill_keys($labels, true);

    // insert missing / upsert existing
    $ins = $pdo->prepare("
        INSERT INTO site_subdomains(
            site_id,label,fqdn,from_catalog,enabled,
            dns_status,ssl_status,last_error,
            folder_status,folder_error,folder_updated_at
        )
        VALUES(?,?,?,?,?,NULL,NULL,NULL,NULL,NULL,NULL)
        ON DUPLICATE KEY UPDATE
            fqdn=VALUES(fqdn),
            enabled=VALUES(enabled),
            updated_at=NOW()
    ");

    // fqdn для БД считаем для всех (включая _default), но это НЕ означает DNS/FP
    foreach ($labels as $l) {
        $fqdn = $l . '.' . $domain;
        // from_catalog оставляем 1, потому что список применен панелью
        $ins->execute([$siteId, $l, $fqdn, 1, 1]);
    }

    // delete лишние (кроме _default)
    foreach ($existMap as $lb => $id) {
        if ($lb === '_default') continue;
        if (!isset($labelsSet[$lb])) {
            $pdo->prepare("DELETE FROM site_subdomains WHERE id=? LIMIT 1")->execute([$id]);
        }
    }

    // Перечитаем актуальный список после синка
    $stmt = $pdo->prepare("SELECT * FROM site_subdomains WHERE site_id=? ORDER BY label ASC");
    $stmt->execute([$siteId]);
    $rowsAfter = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ---- 2.1) ПАПКИ subs/<label>/... (только template-multy) ----
    if (($site['template'] ?? '') === 'template-multy') {
        $prov = new SubdomainProvisioner();

        foreach ($rowsAfter as $r) {
            $lb = (string)($r['label'] ?? '');
            $lb = trim($lb);
            if ($lb === '') continue;

            try {
                // если у тебя есть метод setFolderStatusByLabel — используем его
                $this->setFolderStatusByLabel($siteId, $lb, 'processing', null);

                $res = $prov->ensureForSite($siteId, $lb);

                if (!is_array($res)) {
                    $this->setFolderStatusByLabel($siteId, $lb, 'error', 'Provisioner returned invalid response');
                    continue;
                }

                if ((int)($res['ok'] ?? 0) === 1) {
                    $this->setFolderStatusByLabel($siteId, $lb, 'ok', null);
                } else {
                    $err = (string)($res['error'] ?? 'unknown error');
                    $this->setFolderStatusByLabel($siteId, $lb, 'error', $err);
                }
            } catch (Throwable $e) {
                $this->setFolderStatusByLabel($siteId, $lb, 'error', $e->getMessage());
                @error_log('[folders] site_id=' . $siteId . ' label=' . $lb . ' err=' . $e->getMessage());
            }
        }
    } else {
        // не template-multy => папки не трогаем, и "ok" не ставим автоматически
        @error_log('[folders] site_id=' . $siteId . ' skip (not template-multy)');
    }

    // ---- 3) FastPanel aliases (БЕЗ _default) ----
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

            $fqdnsFp = [];
            foreach ($labelsDns as $l) {
                $fqdnsFp[] = $l . '.' . $domain;
            }

            @error_log('[FP] site_id=' . $siteId . ' aliases_count=' . count($fqdnsFp) . ' sample=' . json_encode(array_slice($fqdnsFp, 0, 5), JSON_UNESCAPED_UNICODE));
            if (!empty($fqdnsFp)) {
                $fp->addSiteAliases((int)$site['fp_site_id'], $fqdnsFp);
            }
        }
    }

    // ---- 4) DNS Namecheap (только если попросили галочкой apply_dns) ----
    $applyDns = (int)($_POST['apply_dns'] ?? 0) === 1;

    if ($applyDns) {
        $dnsOk = false;
        $dnsErr = '';

        try {
            $acc = $this->loadRegistrarAccountForSite($site);
            if (!$acc) {
                $dnsErr = 'registrar account not found (dns skipped)';
            } else {

                $doDns = function (array $accRow) use ($domain, $site, $labelsDns, &$dnsErr): bool {
                    $nc = $this->makeNamecheapClientFromRegistrarAccount($accRow);
                    [$sld, $tld] = $nc->splitSldTld($domain);

                    $existingRaw = $nc->getHosts($sld, $tld);
                    $existing = $this->normalizeNamecheapHosts($existingRaw);

                    $this->guardHostsBeforeSet($domain, $existing, $existingRaw);

                    // ---- ВАЖНО: IP берем от A(@) домена ----
                    $targetIp = $this->detectRootAFromHosts($existing);
                    if ($targetIp === '') {
                        $targetIp = $this->pickServerIpForSite($site);
                    }

                    if ($targetIp === '') {
                        $dnsErr = 'cannot determine target ip (no @ A and no vps ip fallback)';
                        return false;
                    }

                    @error_log('[DNS] domain=' . $domain . ' targetIp=' . $targetIp
                        . ' labelsDns_count=' . count($labelsDns)
                        . ' labelsDns_sample=' . json_encode(array_slice($labelsDns, 0, 10), JSON_UNESCAPED_UNICODE)
                    );

                    // Upsert sub A, но строго БЕЗ _default
                    $merged = $this->mergeDnsHostsUpsertSubA($existing, $labelsDns, $targetIp, 300);

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
            $pdo->prepare("UPDATE site_subdomains SET dns_status='ok', last_error=NULL WHERE site_id=?")
                ->execute([$siteId]);
        } else {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='skip', last_error=? WHERE site_id=?")
                ->execute([$dnsErr, $siteId]);
            @error_log('[DNS] site_id=' . $siteId . ' dnsOk=0 err=' . $dnsErr);
        }
    }

    // ---- 5) SSL ----
    try {
        if ($vpsOk) {
            $sslDone = $this->ensureWildcardSelfSignedIfNeeded($site);
            if ($sslDone) {
                $pdo->prepare("UPDATE site_subdomains SET ssl_status='ok', last_error=NULL WHERE site_id=?")
                    ->execute([$siteId]);
            }
        }
    } catch (Throwable $e) {
        @error_log('[SSL] site_id=' . $siteId . ' ' . $e->getMessage());
        $pdo->prepare("UPDATE site_subdomains SET ssl_status='error', last_error=? WHERE site_id=?")
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

        $pdo = DB::pdo();
        $subs = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id=? AND enabled=1");
        $subs->execute([$siteId]);
        $subRows = $subs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labelsSet = [];
        foreach ($subRows as $r) {
            $l = strtolower(trim((string)($r['label'] ?? '')));
            if ($l !== '') $labelsSet[$l] = true;
        }

        unset($labelsSet['_default']);
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

            $hosts = $this->mergeDnsHostsUpsertSubA($hosts, $labels, $newIp, 300);

            $nc->setHosts($sld, $tld, $hosts);
            $dnsOk = true;

        } catch (Throwable $e) {
            $dnsErr = $e->getMessage();
        }

        if ($dnsOk) {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='ok', last_error=NULL WHERE site_id=?")
                ->execute([$siteId]);
        } else {
            $pdo->prepare("UPDATE site_subdomains SET dns_status='error', last_error=? WHERE site_id=?")
                ->execute([$dnsErr, $siteId]);
            @error_log('[DNS update-ip] site_id=' . $siteId . ' err=' . $dnsErr);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteCatalog(): void
    {
        $this->requireAuth();
        $this->deleteCatalogInternal(true, true, true);
    }

    public function deleteCatalogDns(): void
    {
        $this->requireAuth();
        $this->deleteCatalogInternal(true, false, false);
    }

    private function deleteCatalogInternal(bool $doDns, bool $doDb, bool $doFolders): void
    {
        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/NamecheapClient.php';
        require_once __DIR__ . '/../Services/SubdomainProvisioner.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $pdo = DB::pdo();
        $site = $this->loadSite($siteId);

        $domain = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($domain === '') die('bad domain');

        $st = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id=?");
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labels = [];
        foreach ($rows as $r) {
            $l = strtolower(trim((string)($r['label'] ?? '')));
            if ($l !== '' && $l !== '_default') $labels[] = $l;
        }
        $labels = array_values(array_unique($labels));

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

        if ($doFolders && ($site['template'] ?? '') === 'template-multy') {
            $prov = new SubdomainProvisioner();
            foreach ($labels as $lb) {
                try {
                    $prov->deleteFolderForSite($siteId, $lb);
                } catch (Throwable $e) {
                    @error_log('[folders delete] site_id=' . $siteId . ' label=' . $lb . ' err=' . $e->getMessage());
                }
            }
        }

        if ($doDb) {
            $pdo->prepare("DELETE FROM site_subdomains WHERE site_id=? AND label<>'_default'")->execute([$siteId]);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function toggleOne(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $label = strtolower(trim((string)($_POST['label'] ?? '')));
        if ($label !== '') {
            DB::pdo()->prepare("UPDATE site_subdomains SET enabled=IF(enabled=1,0,1) WHERE site_id=? AND label=? LIMIT 1")
                ->execute([$siteId, $label]);
        }

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function deleteOne(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/SubdomainProvisioner.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $label = strtolower(trim((string)($_POST['label'] ?? '')));
        if ($label !== '' && $label !== '_default') {
            DB::pdo()->prepare("DELETE FROM site_subdomains WHERE site_id=? AND label=? LIMIT 1")->execute([$siteId, $label]);

            $site = $this->loadSite($siteId);
            if (($site['template'] ?? '') === 'template-multy') {
                $prov = new SubdomainProvisioner();
                try {
                    $prov->deleteFolderForSite($siteId, $label);
                } catch (Throwable $e) {
                    @error_log('[deleteOne folder] site_id=' . $siteId . ' label=' . $label . ' err=' . $e->getMessage());
                }
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

        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    /* -------------------- SSL -------------------- */

    private function ensureWildcardSelfSignedIfNeeded(array $site): bool
    {
        // ВСТАВЬ СВОЮ РЕАЛИЗАЦИЮ (у тебя она уже есть).
        // Этот файл DNS-часть и папки чинит, SSL не трогаем.
        return false;
    }
}
