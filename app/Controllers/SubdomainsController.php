<?php

class SubdomainsController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) $this->redirect('/login');
    }

    public function index(): void
    {
        $this->requireAuth();

        $rows = DB::pdo()->query("SELECT * FROM subdomain_catalog ORDER BY id DESC")->fetchAll();
        $this->view('subdomains/index', ['rows' => $rows]);
    }

    public function bulkAdd(): void
    {
        $this->requireAuth();

        $raw = (string)($_POST['labels'] ?? '');
        $raw = trim($raw);

        if ($raw === '') {
            $this->redirect('/subdomains');
        }

        $parts = preg_split('~[,\s]+~u', $raw);
        $labels = [];

        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            if ($p === '') continue;

            // допустимы только "label" без точек
            if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $p)) {
                continue;
            }

            $labels[$p] = true;
        }

        if (empty($labels)) $this->redirect('/subdomains');

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("INSERT IGNORE INTO subdomain_catalog(label,is_active) VALUES(?,1)");

        foreach (array_keys($labels) as $l) {
            $stmt->execute([$l]);
        }

        $this->redirect('/subdomains');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("DELETE FROM subdomain_catalog WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }

    public function toggle(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("UPDATE subdomain_catalog SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }
	
	private function getNamecheapEndpoint(bool $sandbox): string
{
    return $sandbox
        ? (string)config('namecheap.endpoint_sandbox', 'https://api.sandbox.namecheap.com/xml.response')
        : (string)config('namecheap.endpoint_live',    'https://api.namecheap.com/xml.response');
}

private function loadRegistrarAccountForSite(array $site): ?array
{
    $pdo = DB::pdo();

    $id = (int)($site['registrar_account_id'] ?? 0);
    if ($id > 0) {
        $st = $pdo->prepare("SELECT * FROM registrar_accounts WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) return $row;
    }

    $st = $pdo->query("SELECT * FROM registrar_accounts WHERE provider='namecheap' AND is_default=1 LIMIT 1");
    $row = $st ? $st->fetch() : null;

    return $row ?: null;
}

private function makeNamecheapClientFromRegistrarAccount(array $acc): NamecheapClient
{
    $sandbox = ((int)($acc['is_sandbox'] ?? 1) === 1);
    $endpoint = $this->getNamecheapEndpoint($sandbox);

    $apiKeyEnc = (string)($acc['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') throw new RuntimeException('registrar_accounts.api_key_enc empty');

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $apiUser  = (string)($acc['api_user'] ?? '');
    $username = (string)($acc['username'] ?? '');
    $clientIp = (string)($acc['client_ip'] ?? '');

    if ($apiUser === '' || $username === '' || $clientIp === '') {
        throw new RuntimeException('registrar account fields missing (api_user/username/client_ip)');
    }

    return new NamecheapClient(
        $endpoint,
        $apiUser,
        $apiKey,
        $username,
        $clientIp,
        (int)config('namecheap.timeout', 30)
    );
}
private function pickServerIpForSite(array $site): string
{
    $serverId = (int)($site['fastpanel_server_id'] ?? 0);
    if ($serverId <= 0) return '';

    $server = $this->loadServer($serverId);

    // 1) extra_ips
    $extra = trim((string)($server['extra_ips'] ?? ''));
    if ($extra !== '') {
        $parts = preg_split('~[,\s]+~', $extra);
        foreach ($parts as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $v)) return $v;
        }
    }

    // 2) host as ip
    $host = (string)($server['host'] ?? '');
    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);
    $host = trim($host);

    if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $host)) return $host;

    return '';
}

private function mergeDnsHostsAddSubA(array $existingHosts, array $labels, string $ip): array
{
    $out = [];

    // 1) переносим все существующие записи как есть
    foreach ($existingHosts as $h) {
        if (!is_array($h)) continue;

        $name = (string)($h['@Name'] ?? $h['Name'] ?? '');
        $type = (string)($h['@Type'] ?? $h['Type'] ?? '');
        $addr = (string)($h['@Address'] ?? $h['Address'] ?? '');
        $ttl  = (int)($h['@TTL'] ?? $h['TTL'] ?? 300);

        if ($name === '' || $type === '') continue;

        $out[] = [
            'host'    => $name,
            'type'    => $type,
            'address' => $addr,
            'ttl'     => $ttl > 0 ? $ttl : 300,
            // опционально: MXPref если есть
            'mxpref'  => isset($h['@MXPref']) ? (int)$h['@MXPref'] : (isset($h['MXPref']) ? (int)$h['MXPref'] : null),
        ];
    }

    // 2) удаляем из out любые записи для наших labels (чтобы не было дублей и конфликтов)
    $labelsMap = [];
    foreach ($labels as $l) $labelsMap[strtolower(trim($l))] = true;

    $out = array_values(array_filter($out, function($r) use ($labelsMap) {
        $hn = strtolower(trim((string)($r['host'] ?? '')));
        return $hn === '' ? false : !isset($labelsMap[$hn]);
    }));

    // 3) добавляем/обновляем A для каждого label
    foreach ($labelsMap as $l => $_) {
        $out[] = [
            'host'    => $l,
            'type'    => 'A',
            'address' => $ip,
            'ttl'     => 300,
            'mxpref'  => null,
        ];
    }

    return $out;
}


}
