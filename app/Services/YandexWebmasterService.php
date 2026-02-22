<?php
// app/Services/YandexWebmasterService.php

class YandexWebmasterService
{
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
            $st = $pdo->prepare("
                INSERT INTO webmaster_hosts
                (site_id, label, host_url, host_id, verification_type, verification_uin, verification_file, verification_content, file_written, last_sync_at, created_at, updated_at)
                VALUES
                (:site_id, :label, :host_url, :host_id, :vtype, :vuin, :vfile, :vcontent, :file_written, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    host_url = VALUES(host_url),
                    host_id = VALUES(host_id),
                    verification_type = VALUES(verification_type),
                    verification_uin = VALUES(verification_uin),
                    verification_file = VALUES(verification_file),
                    verification_content = VALUES(verification_content),
                    file_written = VALUES(file_written),
                    last_sync_at = NOW(),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $st->execute([
                ':site_id' => $siteId,
                ':label' => $label,
                ':host_url' => $hostUrl,
                ':host_id' => $hostId,
                ':vtype' => $verType,
                ':vuin' => $verUin,
                ':vfile' => $verFile,
                ':vcontent' => $verContent,
                ':file_written' => (int)$fileWritten,
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

        $app = null;
        if (isset($info['applicable_verifiers']) && is_array($info['applicable_verifiers'])) {
            $app = $info['applicable_verifiers'];
        } elseif (isset($info['data']['applicable_verifiers']) && is_array($info['data']['applicable_verifiers'])) {
            $app = $info['data']['applicable_verifiers'];
        }

        if (is_array($app)) {
            foreach ($app as $v) {
                $type = (string)($v['verification_type'] ?? '');
                if ($type === 'HTML_FILE') {
                    $file = (string)($v['verification_file'] ?? ($v['file'] ?? ($v['file_name'] ?? '')));
                    $content = (string)($v['verification_content'] ?? ($v['content'] ?? ''));
                    $uin = (string)($v['verification_uin'] ?? '');

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
        }

        $uin = null;
        if (isset($info['verification_uin'])) $uin = (string)$info['verification_uin'];
        if (isset($info['data']['verification_uin'])) $uin = (string)$info['data']['verification_uin'];

        if ($uin) {
            $file = 'yandex_' . $uin . '.html';
            $content = 'yandex-verification: ' . $file;

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
            $st = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id=:sid AND enabled=1 ORDER BY label ASC");
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
            $buildAbs = rtrim(APP_ROOT, '/') . '/' . ltrim($buildPath, '/');
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
            $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
}
