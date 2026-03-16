<?php
// app/Services/YandexWebmasterService.php

class YandexWebmasterService
{
	
	// === PAGES TABLE CONFIG (подстрой под себя) ===
// Примеры возможных имен: site_pages, pages, site_routes, sites_pages
private const PAGES_TABLE = 'site_pages';

// колонки
private const PAGES_COL_SITE_ID    = 'site_id';
private const PAGES_COL_LABEL      = 'label';      // если label нет, поставь '' и фильтр отключим
private const PAGES_COL_URL        = 'url';        // '/new', '/404', '/'
private const PAGES_COL_IN_SITEMAP = 'in_sitemap'; // 0/1, если нет колонки - фильтр отключим

    /**
     * API host (версию добавляем в path: /v4/...)
     */
    const API_HOST = 'https://api.webmaster.yandex.net';

    /** @var int */
    private $accountId = 0;

    /** @var object|null */
    private $crypto = null;

    public function __construct($accountId = null)
    {
        $this->accountId = $accountId ? (int)$accountId : (int)$this->getDefaultAccountId();

        if (class_exists('Crypto')) {
            try {
                $this->crypto = new Crypto();
            } catch (Throwable $e) {
                $this->crypto = null;
            }
        }
    }

    /* =========================================================
       SETTINGS / TOKENS
       ========================================================= */

    public function getSettings(): array
    {
        $row = DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->prepare("SELECT * FROM webmaster_settings WHERE id = 1 LIMIT 1");
            $st->execute();
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        });

        if (!$row) {
            return [
                'id' => 1,
                'oauth_client_id' => '',
                'access_token' => '',
                'token_expires_at' => null,
            ];
        }

        $token = '';
        if (!empty($row['access_token_enc'])) {
            $token = (string)$this->maybeDecrypt((string)$row['access_token_enc']);
        }

        return [
            'id' => (int)($row['id'] ?? 1),
            'oauth_client_id' => (string)($row['oauth_client_id'] ?? ''),
            'access_token' => (string)$token,
            'token_expires_at' => !empty($row['token_expires_at']) ? (string)$row['token_expires_at'] : null,
        ];
    }

    public function saveSettings(string $clientId, string $accessToken, ?string $expiresAt): void
    {
        $clientId = trim((string)$clientId);
        $accessToken = trim((string)$accessToken);

        $enc = $accessToken !== '' ? (string)$this->maybeEncrypt($accessToken) : '';

        DB::withReconnect(function(PDO $pdo) use ($clientId, $enc, $expiresAt) {
            $st = $pdo->prepare("
                INSERT INTO webmaster_settings (id, oauth_client_id, access_token_enc, token_expires_at)
                VALUES (1, :client_id, :token_enc, :expires_at)
                ON DUPLICATE KEY UPDATE
                    oauth_client_id = VALUES(oauth_client_id),
                    access_token_enc = VALUES(access_token_enc),
                    token_expires_at = VALUES(token_expires_at),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $st->execute([
                ':client_id'  => $clientId,
                ':token_enc'  => $enc,
                ':expires_at' => ($expiresAt !== null && trim((string)$expiresAt) !== '') ? $expiresAt : null,
            ]);
        });
    }

    public function isTokenExpired(?string $expiresAt): bool
    {
        if (!$expiresAt) return false;
        $ts = strtotime($expiresAt);
        if ($ts === false) return false;
        return $ts <= time();
    }

    private function getDefaultAccountId(): int
    {
        // если таблицы webmaster_accounts нет — вернем 0 и уйдем в фолбэк webmaster_settings
        try {
            return DB::withReconnect(function(PDO $pdo) {
                $st = $pdo->query("SELECT id FROM webmaster_accounts WHERE provider='yandex' ORDER BY is_default DESC, id ASC LIMIT 1");
                $id = $st->fetchColumn();
                return $id ? (int)$id : 0;
            });
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function getAccessToken(): string
    {
        // 1) webmaster_accounts (если есть и заполнено)
        if ($this->accountId > 0) {
            try {
                $row = DB::withReconnect(function(PDO $pdo) {
                    $st = $pdo->prepare("SELECT access_token_enc FROM webmaster_accounts WHERE id=:id LIMIT 1");
                    $st->execute([':id' => $this->accountId]);
                    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
                });

                $enc = $row ? (string)($row['access_token_enc'] ?? '') : '';
                $token = trim((string)$this->maybeDecrypt($enc));
                if ($token !== '') return $token;
            } catch (Throwable $e) {
                // игнор, идем в фолбэк
            }
        }

        // 2) фолбэк: webmaster_settings (как у тебя было в рабочей версии)
        $settings = $this->getSettings();
        $token2 = trim((string)($settings['access_token'] ?? ''));
        if ($token2 !== '') return $token2;

        throw new RuntimeException('Yandex access token is empty (no token in webmaster_accounts and webmaster_settings)');
    }

    private function maybeDecrypt(string $value): string
    {
        $value = (string)$value;
        if ($value === '') return '';

        // instance ->decrypt
        if ($this->crypto && method_exists($this->crypto, 'decrypt')) {
            try { return (string)$this->crypto->decrypt($value); } catch (Throwable $e) {}
        }

        // static Crypto::decrypt
        if (class_exists('Crypto') && method_exists('Crypto', 'decrypt')) {
            try { return (string)Crypto::decrypt($value); } catch (Throwable $e) {}
        }

        return $value;
    }

    private function maybeEncrypt(string $value): string
    {
        $value = (string)$value;
        if ($value === '') return '';

        // instance ->encrypt
        if ($this->crypto && method_exists($this->crypto, 'encrypt')) {
            try { return (string)$this->crypto->encrypt($value); } catch (Throwable $e) {}
        }

        // static Crypto::encrypt
        if (class_exists('Crypto') && method_exists('Crypto', 'encrypt')) {
            try { return (string)Crypto::encrypt($value); } catch (Throwable $e) {}
        }

        // если шифрования нет — вернем как есть (чтобы не ломать проект)
        return $value;
    }

    /* =========================================================
       CONTROLLER-REQUIRED METHODS
       ========================================================= */

    public function getWebmasterHostsRows(int $siteId): array
    {
        return DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("SELECT * FROM webmaster_hosts WHERE site_id = :sid ORDER BY label ASC");
            $st->execute([':sid' => $siteId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

  public function upsertWebmasterHost(
    int $siteId,
    string $label,
    string $hostUrl,
    ?string $hostId,
    ?string $verType,
    ?string $verUin,
    ?string $verFile,
    ?string $verContent,
    int $fileWritten
): void {
    DB::withReconnect(function(PDO $pdo) use (
        $siteId, $label, $hostUrl, $hostId, $verType, $verUin, $verFile, $verContent, $fileWritten
    ) {
        $writtenAt = ((int)$fileWritten === 1) ? date('Y-m-d H:i:s') : null;

        $st = $pdo->prepare("
            INSERT INTO webmaster_hosts
            (
                site_id,
                label,
                host_url,
                host_id,
                verification_type,
                verification_uin,
                verification_file,
                verification_content,
                file_written,
                file_written_at,
                last_sync_at,
                created_at,
                updated_at
            )
            VALUES
            (
                :site_id,
                :label,
                :host_url,
                :host_id,
                :vtype,
                :vuin,
                :vfile,
                :vcontent,
                :file_written,
                :file_written_at,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                host_url = VALUES(host_url),
                host_id = VALUES(host_id),
                verification_type = VALUES(verification_type),
                verification_uin = VALUES(verification_uin),
                verification_file = VALUES(verification_file),
                verification_content = VALUES(verification_content),
                file_written = VALUES(file_written),
                file_written_at = CASE
                    WHEN VALUES(file_written) = 1 THEN VALUES(file_written_at)
                    ELSE file_written_at
                END,
                last_sync_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
        ");

        $st->execute([
            ':site_id'        => $siteId,
            ':label'          => $label,
            ':host_url'       => $hostUrl,
            ':host_id'        => $hostId,
            ':vtype'          => $verType,
            ':vuin'           => $verUin,
            ':vfile'          => $verFile,
            ':vcontent'       => $verContent,
            ':file_written'   => (int)$fileWritten,
            ':file_written_at'=> $writtenAt,
        ]);
    });
}

    public function markVerified(int $siteId, string $label): void
    {
        DB::withReconnect(function(PDO $pdo) use ($siteId, $label) {
            $st = $pdo->prepare("
                UPDATE webmaster_hosts
                SET verified_at = NOW(), updated_at = CURRENT_TIMESTAMP
                WHERE site_id = :sid AND label = :label
                LIMIT 1
            ");
            $st->execute([
                ':sid' => $siteId,
                ':label' => $label,
            ]);
        });
    }

    /**
     * Основной домен + enabled сабы
     */
    public function getDesiredHostsForSite(int $siteId): array
    {
        $site = $this->getSiteRow($siteId);
        if (!$site) {
            throw new RuntimeException('Site not found: id=' . $siteId);
        }

        $domain = trim((string)($site['domain'] ?? ''));
        if ($domain === '') {
            throw new RuntimeException('Site domain is empty: id=' . $siteId);
        }

        $hosts = [];
        $hosts[] = ['label' => '', 'host_url' => 'https://' . $domain];

        $subs = $this->getSiteSubdomains($siteId);
        foreach ($subs as $r) {
            $label = (string)($r['label'] ?? '');
            if ($label === '') continue;

            $hosts[] = ['label' => $label, 'host_url' => 'https://' . $label . '.' . $domain];
        }

        return $hosts;
    }

    /**
     * GET /v4/user
     */
    public function getUserId(): string
    {
        $j = $this->apiRequest('GET', '/v4/user', [], null);

        if (isset($j['user_id'])) return (string)$j['user_id'];
        if (isset($j['data']['user_id'])) return (string)$j['data']['user_id'];

        throw new RuntimeException('Webmaster API: cannot extract user_id from /v4/user response');
    }

    /**
     * GET /v4/user/{userId}/hosts
     */
    public function getHosts(string $userId): array
    {
        return $this->apiRequest('GET', '/v4/user/' . rawurlencode($userId) . '/hosts', [], null);
    }

    /**
     * POST /v4/user/{userId}/hosts  body: {host_url}
     */
    public function addHost(string $userId, string $hostUrl): array
    {
        return $this->apiRequest(
            'POST',
            '/v4/user/' . rawurlencode($userId) . '/hosts',
            [],
            ['host_url' => $hostUrl]
        );
    }

    /**
     * GET /v4/user/{userId}/hosts/{hostId}/verification
     */
    public function checkVerification(string $userId, string $hostId): array
    {
        return $this->apiRequest(
            'GET',
            '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/verification',
            [],
            null
        );
    }

    /**
     * Основной verify: POST /v4/user/{userId}/hosts/{hostId}/verification/{type}
     * Фолбэк: POST /v4/user/{userId}/hosts/{hostId}/verification?verification_type=HTML_FILE
     */
    public function verifyHost(string $userId, string $hostId, string $type = 'HTML_FILE'): array
    {
        $type = (string)$type;

        try {
            return $this->apiRequest(
                'POST',
                '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/verification/' . rawurlencode($type),
                [],
                null
            );
        } catch (Throwable $e) {
            return $this->apiRequest(
                'POST',
                '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/verification',
                ['verification_type' => $type],
                null
            );
        }
    }

    /**
     * Вытаскиваем file/content для HTML_FILE максимально устойчиво
     */
    public function getHtmlFileVerifier(string $userId, string $hostId): array
{
    $info = $this->checkVerification($userId, $hostId);

    // 1) Попробуем найти HTML_FILE verifier в applicable_verifiers
    $app = null;
    if (isset($info['applicable_verifiers']) && is_array($info['applicable_verifiers'])) {
        $app = $info['applicable_verifiers'];
    } elseif (isset($info['data']['applicable_verifiers']) && is_array($info['data']['applicable_verifiers'])) {
        $app = $info['data']['applicable_verifiers'];
    }

    if (is_array($app)) {
        foreach ($app as $v) {
            $type = (string)($v['verification_type'] ?? '');
            if ($type !== 'HTML_FILE') continue;

            $uin = (string)($v['verification_uin'] ?? '');
            $file = (string)($v['verification_file'] ?? ($v['file'] ?? ($v['file_name'] ?? '')));
            $content = (string)($v['verification_content'] ?? ($v['content'] ?? ''));

            // если file пустой, но uin есть — соберем имя
            if ($file === '' && $uin !== '') {
                $file = 'yandex_' . $uin . '.html';
            }

            // если content пустой, но uin есть — соберем правильный HTML
            if ($content === '' && $uin !== '') {
                $content = $this->buildHtmlFileVerificationContent($uin);
            }

            if ($file !== '' && $content !== '') {
                return [
                    'type' => 'HTML_FILE',
                    'uin' => $uin,
                    'file' => $file,
                    'content' => $content,
                    'raw' => $info,
                ];
            }
        }
    }

    // 2) Фолбэк: берем verification_uin и генерим file+content корректно
    $uin = null;
    if (isset($info['verification_uin'])) $uin = (string)$info['verification_uin'];
    if (isset($info['data']['verification_uin'])) $uin = (string)$info['data']['verification_uin'];

    if ($uin) {
        $file = 'yandex_' . $uin . '.html';
        $content = $this->buildHtmlFileVerificationContent($uin);

        return [
            'type' => 'HTML_FILE',
            'uin' => $uin,
            'file' => $file,
            'content' => $content,
            'raw' => $info,
        ];
    }

    throw new RuntimeException('Cannot extract HTML_FILE verifier from checkVerification response');
}

	private function buildHtmlFileVerificationContent(string $uin): string
	{
		$uin = trim($uin);
		return "<html>\n<head>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n</head>\n<body>Verification: {$uin}</body>\n</html>\n";
	}

    /**
     * Пишем файл в build:
     * - main:   <build>/public_html/<file>
     * - sub:    <build>/subs/<label>/public_html/<file>
     */
    public function writeVerificationFileToBuild(int $siteId, string $label, string $fileName, string $content): string
    {
        $buildAbs = $this->resolveBuildAbsFromSite($siteId);

        $target = $buildAbs;
        if ($label !== '') {
            $target .= '/subs/' . $label;
        }

        $pub = $target . '/public_html';
        if (!is_dir($pub)) {
            // если нет public_html, пробуем в корень
            $pub = $target;
        }

        if (!is_dir($pub)) {
            @mkdir($pub, 0777, true);
        }

        $full = rtrim($pub, '/') . '/' . $fileName;

        $ok = @file_put_contents($full, $content);
        if ($ok === false) {
            throw new RuntimeException('Cannot write verification file: ' . $full);
        }

        return $full;
    }

    /**
     * Возвращает host_id по host_url.
     * Если в Яндексе нет — добавляет.
     */
    public function getOrCreateHostId(string $userId, string $hostUrl): string
    {
        $hosts = $this->getHosts($userId);

        $hostId = $this->findHostIdInHostsResponse($hosts, $hostUrl);
        if ($hostId !== null) {
            return $hostId;
        }

        $res = $this->addHost($userId, $hostUrl);

        $newId = null;
        if (isset($res['host_id'])) $newId = (string)$res['host_id'];
        if (isset($res['data']['host_id'])) $newId = (string)$res['data']['host_id'];
        if (isset($res['data']['host']['host_id'])) $newId = (string)$res['data']['host']['host_id'];

        if ($newId) return $newId;

        // если ответ странный — перечитать список
        $hosts2 = $this->getHosts($userId);
        $hostId2 = $this->findHostIdInHostsResponse($hosts2, $hostUrl);
        if ($hostId2 !== null) return $hostId2;

        throw new RuntimeException('Cannot get host_id after addHost for ' . $hostUrl);
    }

    /* =========================================================
       INTERNAL DB HELPERS
       ========================================================= */

    private function getSiteRow(int $siteId): ?array
    {
        return DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("SELECT * FROM sites WHERE id=:id LIMIT 1");
            $st->execute([':id' => $siteId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        });
    }

    private function getSiteSubdomains(int $siteId): array
    {
        return DB::withReconnect(function(PDO $pdo) use ($siteId) {
           $st = $pdo->prepare("
				SELECT label
				FROM site_subdomains
				WHERE site_id=:sid AND enabled=1 AND label <> '_default'
				ORDER BY label ASC
			");
            $st->execute([':sid' => $siteId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    private function resolveBuildAbsFromSite(int $siteId): string
    {
        $site = $this->getSiteRow($siteId);
        if (!$site) throw new RuntimeException("Site not found");

        $buildPath = (string)($site['build_path'] ?? '');
        if ($buildPath === '') {
            throw new RuntimeException("build_path is empty. Run Build for this site first.");
        }

        if (!defined('APP_ROOT')) {
            throw new RuntimeException("APP_ROOT is not defined");
        }

        $buildAbs = $buildPath;

		if (isset($buildPath[0]) && $buildPath[0] !== '/') {
			$buildAbs = rtrim(APP_ROOT, '/') . '/storage/' . ltrim($buildPath, '/');
		}

        if (!is_dir($buildAbs)) {
            throw new RuntimeException("Build dir not found: " . $buildAbs);
        }

        return rtrim($buildAbs, '/');
    }

    private function findHostIdInHostsResponse(array $resp, string $hostUrl): ?string
    {
        $need = $this->normalizeHostUrl($hostUrl);

        $list = [];
        if (isset($resp['data']['hosts']) && is_array($resp['data']['hosts'])) $list = $resp['data']['hosts'];
        elseif (isset($resp['hosts']) && is_array($resp['hosts'])) $list = $resp['hosts'];

        foreach ($list as $h) {
            $url = '';
            if (isset($h['host_url'])) $url = (string)$h['host_url'];
            elseif (isset($h['unicode_host_url'])) $url = (string)$h['unicode_host_url'];

            $id = null;
            if (isset($h['host_id'])) $id = (string)$h['host_id'];

            if ($url !== '' && $id !== null) {
                if ($this->normalizeHostUrl($url) === $need) {
                    return $id;
                }
            }
        }

        return null;
    }

    private function normalizeHostUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $url = function_exists('mb_strtolower') ? mb_strtolower($url) : strtolower($url);
        $url = rtrim($url, '/');
        return $url;
    }

    /* =========================================================
       HTTP
       ========================================================= */

    private function apiRequest(string $method, string $path, array $query = [], $jsonBody = null): array
    {
        $token = $this->getAccessToken();

        $url = rtrim(self::API_HOST, '/') . $path;
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $headers = [
            'Authorization: OAuth ' . $token,
            'Accept: application/json',
        ];

        if ($jsonBody !== null) {
            $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		// DEBUG TEMP
		error_log("WM {$method} {$url} BODY=" . ($jsonBody !== null ? $payload : ''));

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Webmaster API CURL error: ' . $err);
        }

        if ($raw === '' || $raw === null) {
            if ($http >= 200 && $http < 300) return [];
            throw new RuntimeException('Webmaster API HTTP ' . $http . '; empty body');
        }

        $data = json_decode($raw, true);

        if ($http < 200 || $http >= 300) {
            $msg = 'Webmaster API HTTP ' . $http;

            if (is_array($data)) {
                if (isset($data['error_message'])) $msg .= '; ' . $data['error_message'];
                elseif (isset($data['message'])) $msg .= '; ' . $data['message'];
                elseif (isset($data['error'])) $msg .= '; ' . $data['error'];
            } else {
                $msg .= '; ' . trim(substr($raw, 0, 500));
            }

            throw new RuntimeException($msg);
        }

        return is_array($data) ? $data : [];
    }
	
public function recrawlUrl(string $userId, string $hostId, string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'empty url'];
    }

    return $this->apiRequest(
        'POST',
        '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/recrawl/queue',
        [],
        ['url' => $url]
    );
}

/**
 * Массовый recrawl: слать по одному url, т.к. API так устроен.
 * Возвращаем сводку: ok/err + сколько ушло.
 */
public function recrawlUrls(string $userId, string $hostId, array $urls): array
{
    $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));
    if (!$urls) {
        return ['ok' => false, 'message' => 'empty url_list'];
    }

    $ok = 0;
    $err = 0;
    $errors = [];

    foreach ($urls as $u) {
        try {
            $this->recrawlUrl($userId, $hostId, $u);
            $ok++;
        } catch (Throwable $e) {
            $err++;
            // не раздуваем лог: максимум 10 ошибок
            if (count($errors) < 10) {
                $errors[] = $u . ' :: ' . $e->getMessage();
            }
        }
    }

    return [
        'ok' => ($err === 0),
        'sent' => count($urls),
        'success' => $ok,
        'failed' => $err,
        'errors' => $errors,
    ];
}

public function addSitemap(string $userId, string $hostId, string $sitemapUrl): array
{
    // Яндекс ждёт поле "url" (НЕ sitemap_url)
    $payload = ['url' => $sitemapUrl];

    // 1) основной endpoint
    try {
        return $this->apiRequest(
            'POST',
            '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/user-added-sitemaps',
            [],
            $payload
        );
    } catch (Throwable $e) {
        // 2) фолбэк на старый вариант (у некоторых аккаунтов/версий)
        return $this->apiRequest(
            'POST',
            '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/sitemaps',
            [],
            $payload
        );
    }
}


public function getSitemaps(string $userId, string $hostId): array
{
    // сначала user-added (то, что ты добавил руками/через API)
    try {
        return $this->apiRequest(
            'GET',
            '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/user-added-sitemaps',
            [],
            null
        );
    } catch (Throwable $e) {
        // фолбэк на общий список/статусы
        return $this->apiRequest(
            'GET',
            '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId) . '/sitemaps',
            [],
            null
        );
    }
}

public function confirmRobots(string $userId, string $hostId): array
{
    $base = '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId);

    $tries = [
        ['POST', $base . '/robots'],
        ['POST', $base . '/robots/confirm'],
        ['POST', $base . '/robots.txt'],
    ];

    $last = null;
    foreach ($tries as $t) {
        try {
            return $this->apiRequest($t[0], $t[1], [], null);
        } catch (Throwable $e) {
            $last = $e;
        }
    }
    throw $last ?: new RuntimeException('robots confirm failed');
}

public function getRobots(string $userId, string $hostId): array
{
    $base = '/v4/user/' . rawurlencode($userId) . '/hosts/' . rawurlencode($hostId);

    $tries = [
        ['GET', $base . '/robots'],
        ['GET', $base . '/robots.txt'],
    ];

    $last = null;
    foreach ($tries as $t) {
        try {
            return $this->apiRequest($t[0], $t[1], [], null);
        } catch (Throwable $e) {
            $last = $e;
        }
    }
    throw $last ?: new RuntimeException('robots get failed');
}

public function saveSitemapStatus(int $siteId, string $label, string $sitemapUrl, ?string $addedAt = null): void
{
    DB::withReconnect(function(PDO $pdo) use ($siteId, $label, $sitemapUrl, $addedAt) {
        $st = $pdo->prepare("
            UPDATE webmaster_hosts
            SET sitemap_url = :url,
                sitemap_added_at = " . ($addedAt ? ":at" : "NOW()") . ",
                updated_at = CURRENT_TIMESTAMP
            WHERE site_id = :sid AND label = :label
            LIMIT 1
        ");
        $params = [':url'=>$sitemapUrl, ':sid'=>$siteId, ':label'=>$label];
        if ($addedAt) $params[':at'] = $addedAt;
        $st->execute($params);
    });
}

public function saveRobotsStatus(int $siteId, string $label, string $robotsUrl, ?string $confirmedAt = null): void
{
    DB::withReconnect(function(PDO $pdo) use ($siteId, $label, $robotsUrl, $confirmedAt) {
        $st = $pdo->prepare("
            UPDATE webmaster_hosts
            SET robots_url = :url,
                robots_confirmed_at = " . ($confirmedAt ? ":at" : "NOW()") . ",
                updated_at = CURRENT_TIMESTAMP
            WHERE site_id = :sid AND label = :label
            LIMIT 1
        ");
        $params = [':url'=>$robotsUrl, ':sid'=>$siteId, ':label'=>$label];
        if ($confirmedAt) $params[':at'] = $confirmedAt;
        $st->execute($params);
    });
}

public function saveRecrawlStatus(int $siteId, string $label, int $count): void
{
    DB::withReconnect(function(PDO $pdo) use ($siteId, $label, $count) {
        $st = $pdo->prepare("
            UPDATE webmaster_hosts
            SET last_recrawl_at = NOW(),
                last_recrawl_count = :cnt,
                updated_at = CURRENT_TIMESTAMP
            WHERE site_id = :sid AND label = :label
            LIMIT 1
        ");
        $st->execute([':cnt'=>$count, ':sid'=>$siteId, ':label'=>$label]);
    });
}


public function getPagesUrls(int $siteId, string $label, bool $onlyInSitemap = true): array
{
    $tbl  = self::PAGES_TABLE;
    $cSid = self::PAGES_COL_SITE_ID;
    $cLbl = self::PAGES_COL_LABEL;
    $cUrl = self::PAGES_COL_URL;
    $cSm  = self::PAGES_COL_IN_SITEMAP;

    return DB::withReconnect(function(PDO $pdo) use ($siteId, $label, $onlyInSitemap, $tbl, $cSid, $cLbl, $cUrl, $cSm) {

        // Базовый SQL
        $sql = "SELECT {$cUrl} AS url FROM {$tbl} WHERE {$cSid} = :sid";

        $params = [':sid' => $siteId];

        // Фильтр по label, если колонка реально есть
        // Если у тебя label в pages нет вообще — поставь PAGES_COL_LABEL = '' и фильтр выключится
        if ($cLbl !== '') {
            $sql .= " AND {$cLbl} = :lbl";
            $params[':lbl'] = $label;
        }

        // Только те, что помечены "In sitemap"
        // Если колонки нет — поставь PAGES_COL_IN_SITEMAP = '' и фильтр выключится
        if ($onlyInSitemap && $cSm !== '') {
            $sql .= " AND {$cSm} = 1";
        }

        $sql .= " ORDER BY {$cUrl} ASC";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $urls = [];
        foreach ($rows as $r) {
            $u = trim((string)($r['url'] ?? ''));
            if ($u === '') continue;
            // нормализуем как path
            if ($u[0] !== '/') $u = '/' . $u;
            $urls[] = $u;
        }

        // уникализируем
        $urls = array_values(array_unique($urls));
        return $urls;
    });
}

public function getPagesUrlsForSiteLabel(int $siteId, string $label): array
{
    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT id, domain, template FROM sites WHERE id=:id LIMIT 1");
        $st->execute([':id' => $siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });

    if (!$site) {
        throw new RuntimeException("Site not found: " . $siteId);
    }

    $domain = (string)($site['domain'] ?? '');
	$label  = $this->normalizeSubLabel($label); // '' -> _default

	$defaultCfg = $this->loadSiteDefaultConfig($siteId, $domain);
	$subCfg     = $this->ensureSubdomainConfigExists($siteId, $label, $defaultCfg);

	$pages = $subCfg['pages'] ?? [];

    if (!is_array($pages)) $pages = [];

    $out = [];

    // pages может быть map: ['/path' => [...]]
    foreach ($pages as $k => $v) {
        $url = '';

        if (is_string($k) && $k !== '' && $k[0] === '/') {
            $url = $k;
        } elseif (is_array($v) && isset($v['url'])) {
            $url = (string)$v['url'];
        }

        $url = trim($url);
        if ($url === '') continue;

        if ($url[0] !== '/' && !preg_match('~^https?://~i', $url)) {
            $url = '/' . $url;
        }

        $out[] = $url;
    }

    // уникально + порядок
    $seen = [];
    $uniq = [];
    foreach ($out as $u) {
        if (isset($seen[$u])) continue;
        $seen[$u] = 1;
        $uniq[] = $u;
    }

    return $uniq;
}

private function normalizeSubLabel(string $label): string
{
    $label = strtolower(trim($label));
    if ($label === '' || $label === '_default') return '_default';

    $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
    $label = trim($label, '-');

    return $label !== '' ? $label : '_default';
}

    private function loadSiteDefaultConfig(int $siteId, string $domain): array
    {
        $pdo = DB::pdo();

        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch();

        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (is_array($cfg)) {
                if (empty($cfg['domain'])) $cfg['domain'] = $domain;
                return $cfg;
            }
        }

        // fallback: site_configs
        $st = $pdo->prepare("SELECT json FROM site_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch();

        if ($row && isset($row['json'])) {
            $cfg = json_decode((string)$row['json'], true);
            if (is_array($cfg)) {
                if (empty($cfg['domain'])) $cfg['domain'] = $domain;
                return $cfg;
            }
        }

        return $this->defaultConfig($domain);
    }
	
	private function ensureSubdomainConfigExists(int $siteId, string $label, ?array $defaultCfg = null): array
    {
        $label = $this->normalizeSubLabel($label);

        $stmt = DB::pdo()->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id=? AND label=? LIMIT 1");
        $stmt->execute([$siteId, $label]);
        $row = $stmt->fetch();

        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (is_array($cfg)) return $cfg;
        }

        if ($defaultCfg === null) {
            $site = $this->loadSite($siteId);
            $defaultCfg = $this->loadSiteDefaultConfig($siteId, (string)($site['domain'] ?? ''));
        }

        $cfg = $defaultCfg;
        $cfg['label'] = $label;
        if (empty($cfg['logo'])) $cfg['logo'] = 'assets/logo.webp';
        if (empty($cfg['favicon'])) $cfg['favicon'] = 'assets/favicon.png';

        $this->saveSubdomainConfig($siteId, $label, $cfg);
        return $cfg;
    }
 private function loadSiteConfigFromSiteConfigs(int $siteId, string $domain): array
    {
        $st = DB::pdo()->prepare('SELECT json FROM site_configs WHERE site_id=?');
        $st->execute([$siteId]);
        $row = $st->fetch();

        if (!$row) {
            $cfg = $this->defaultConfig($domain);
            $ins = DB::pdo()->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
            $ins->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
            return $cfg;
        }

        $cfg = json_decode((string)($row['json'] ?? ''), true);
        if (!is_array($cfg)) $cfg = [];
        if (empty($cfg['domain'])) $cfg['domain'] = $domain;

        return $cfg;
    }
	
	    private function saveSubdomainConfig(int $siteId, string $label, array $cfg): void
    {
        $label = $this->normalizeSubLabel($label);
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ins = DB::pdo()->prepare("
            INSERT INTO site_subdomain_configs (site_id, label, config_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              config_json = VALUES(config_json),
              updated_at = CURRENT_TIMESTAMP
        ");
        $ins->execute([$siteId, $label, $json]);
    }
	
	
	public function getPagesUrlsText(int $siteId, string $label): string
{
    $label = $this->normalizeLabel($label);

    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT id, domain, template FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });
    if (!$site) throw new RuntimeException("site not found");

   $pages = $this->loadPagesFromSubdomainConfig($siteId, $label);
	if ($pages === null) {
		$pages = $this->loadPagesFromDefaultConfig($siteId);
	}

    $paths = [];
    foreach (($pages ?: []) as $path => $meta) {
        $path = (string)$path;
        if ($path === '' || $path[0] !== '/') continue;

        // обычно /404 не надо
        if ($path === '/404') continue;

        // если хочешь только те, что в sitemap:
        // if (is_array($meta) && array_key_exists('sitemap', $meta) && $meta['sitemap'] === false) continue;

        $paths[] = $path;
    }

    sort($paths);

    // отдавать можно относительные, но для recrawl мы ниже все равно сделаем абсолютные
    return implode("\n", $paths);
}

public function normalizeRecrawlUrls(int $siteId, string $label, string $rawText): array
{
    $label = $this->normalizeLabel($label);
    $hostUrl = $this->getHostUrlByLabel($siteId, $label);
    if (!$hostUrl) throw new RuntimeException("host url not found for label=" . $label);

    $hostUrl = rtrim($hostUrl, '/');

    $lines = preg_split('~\r?\n~', $rawText);
    $out = [];

    foreach (($lines ?: []) as $line) {
        $u = trim((string)$line);
        if ($u === '') continue;

        // если относительный
        if ($u[0] === '/') {
            $u = $hostUrl . $u;
        }

        // минимальная валидация
        if (!preg_match('~^https?://~i', $u)) continue;

        $out[] = $u;
    }

    // уникализация
    $out = array_values(array_unique($out));

    // лимит на всякий случай (у Яндекса дневной лимит; лучше не слать сразу 5000)
    if (count($out) > 200) {
        $out = array_slice($out, 0, 200);
    }

    return $out;
}


public function getHostIdByLabel(int $siteId, string $label): ?string
{
    $label = $this->normalizeLabel($label);

    $rows = $this->getWebmasterHostsRows($siteId);
    foreach ($rows as $r) {
        if ((string)($r['label'] ?? '') === $label) {
            $id = (string)($r['host_id'] ?? '');
            return $id !== '' ? $id : null;
        }
    }
    return null;
}

private function getHostUrlByLabel(int $siteId, string $label): ?string
{
    $label = $this->normalizeLabel($label);

    $desired = $this->getDesiredHostsForSite($siteId);
    foreach ($desired as $h) {
        if ((string)($h['label'] ?? '') === $label) {
            $u = (string)($h['host_url'] ?? '');
            return $u !== '' ? $u : null;
        }
    }
    return null;
}

private function normalizeLabel(string $label): string
{
    $label = trim($label);
    if ($label === 'ALL') return 'ALL';
    // root label = ''
    return $label;
}

private function loadPagesFromDefaultConfig(int $siteId): ?array
{
    return DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $json = (string)($st->fetchColumn() ?: '');
        if ($json === '') return null;

        $cfg = json_decode($json, true);
        if (!is_array($cfg)) return null;

        $pages = $cfg['pages'] ?? null;
        return is_array($pages) ? $pages : null;
    });
}

private function loadPagesFromSubdomainConfig(int $siteId, string $label): ?array
{
    return DB::withReconnect(function(PDO $pdo) use ($siteId, $label) {
        $st = $pdo->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id=? AND label=? LIMIT 1");
        $st->execute([$siteId, $label]);
        $json = (string)($st->fetchColumn() ?: '');
        if ($json === '') return null;

        $cfg = json_decode($json, true);
        if (!is_array($cfg)) return null;

        $pages = $cfg['pages'] ?? null;
        return is_array($pages) ? $pages : null;
    });
}

private function loadPagesFromSiteConfigs(int $siteId): ?array
{
    return DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT json FROM site_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $json = (string)($st->fetchColumn() ?: '');
        if ($json === '') return null;

        $cfg = json_decode($json, true);
        if (!is_array($cfg)) return null;

        $pages = $cfg['pages'] ?? null;
        return is_array($pages) ? $pages : null;
    });
}

private function apiPostJson(string $path, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: OAuth ' . $this->getAccessToken(),
    ];

    return $this->httpJson('POST', $this->apiBase() . $path, $body, $headers);
}

public function fetchUrlHead(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);

    return ['http' => $http, 'final_url' => $final, 'err' => $err];
}

}
