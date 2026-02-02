<?php

// app/Controllers/DeployController.php
class DeployController extends Controller
{
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

    public function form(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $servers = DB::pdo()->query("SELECT * FROM fastpanel_servers ORDER BY id DESC")->fetchAll();

        $serverId = (int)($_GET['server_id'] ?? 0);
        if ($serverId <= 0 && !empty($servers[0]['id'])) {
            $serverId = (int)$servers[0]['id'];
        }

        $ips = [];
        $ips_error = '';

        if ($serverId > 0) {
            $server = $this->loadServer($serverId);

            $extra = trim((string)($server['extra_ips'] ?? ''));
            if ($extra !== '') {
                $parts = preg_split('~[,\s]+~', $extra);
                foreach ($parts as $v) {
                    $v = trim($v);
                    if ($v === '') continue;
                    if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $v)) {
                        $ips[] = $v;
                    }
                }
            }
            $ips = array_values(array_unique($ips));

            if (empty($ips)) {
                $host = (string)$server['host'];
                $host = preg_replace('~^https?://~i', '', $host);
                $host = preg_replace('~:\d+$~', '', $host);
                $host = trim($host);

                if (preg_match('~^\d+\.\d+\.\d+\.\d+$~', $host)) {
                    $ips = [$host];
                    $ips_error = 'extra_ips пуст — использован IP из host';
                } else {
                    $ips_error = 'extra_ips пуст — список IP не задан';
                }
            }
        }

        $this->view('deploy/form', compact('site', 'servers', 'serverId', 'ips', 'ips_error'));
    }

    /**
     * Шаг A: создать сайт в Fastpanel (или найти существующий)
     * GET /deploy/create-site?id=6&server_id=1&ip=95.129.234.93
     */
    public function createSite(): void
    {
        $this->requireAuth();

        require_once Paths::appRoot() . '/app/Services/Crypto.php';
        require_once Paths::appRoot() . '/app/Services/FastpanelClient.php';

        $siteId   = (int)($_GET['id'] ?? 0);
        $serverId = (int)($_GET['server_id'] ?? 0);
        $ip       = trim((string)($_GET['ip'] ?? ''));

        if ($siteId <= 0) die('bad site id');
        if ($serverId <= 0) die('bad server id');
        if ($ip === '') die('ip required');

        $site   = $this->loadSite($siteId);
        $server = $this->loadServer($serverId);

        $domain = $this->normalizeDomain((string)$site['domain']);
        if ($domain === '') die('bad domain');

        $stmt = DB::pdo()->prepare("INSERT INTO deployments (site_id, server_id, status) VALUES (?, ?, 'creating_site')");
        $stmt->execute([$siteId, $serverId]);
        $deployId = (int)DB::pdo()->lastInsertId();

        $payload = null;
        $steps = [
            'stage'  => 'create_site',
            'domain' => $domain,
            'ip'     => $ip,
        ];

        try {
            $this->tryPingDb();

            $password = Crypto::decrypt((string)$server['password_enc']);

            $client = new FastpanelClient(
                (string)$server['host'],
                (bool)$server['verify_tls'],
                (int)config('fastpanel.timeout', 30)
            );
            $client->login((string)$server['username'], $password);

            // find existing
            $existingId = 0;
            $list = $client->simpleSites();
            if (is_array($list)) {
                foreach ($list as $row) {
                    if (!is_array($row)) continue;
                    if (!empty($row['domain']) && (string)$row['domain'] === $domain) {
                        $existingId = (int)($row['id'] ?? 0);
                        break;
                    }
                }
            }

            if ($existingId > 0) {
                $resp = $client->site($existingId);
                $steps['fastpanel_site'] = 'exists';
                $steps['fp_site_id'] = $existingId;
            } else {
                $payload = [
                    'type'            => 'php',
                    'domain'          => $domain,
                    'email_domain'    => false,
                    'admin_email'     => 'admin@' . $domain,
                    'charset'         => 'UTF-8',
                    'index_page'      => 'index.php index.html',
                    'ips'             => [['ip' => $ip]],
                    'handler'         => 'mpm_itk',
                    'handler_version' => '74',
                    'aliases'         => [['name' => 'www.' . $domain]],
                ];

                $resp = $client->createSite($payload);
                $steps['fastpanel_site'] = 'created';
                $steps['fp_site_id'] = (int)($resp['id'] ?? 0);
            }

            $fpSiteId = (int)($resp['id'] ?? $existingId);
            $indexDir = (string)($resp['index_dir'] ?? '');
            if ($fpSiteId <= 0) {
                throw new RuntimeException('Fastpanel: site id is empty in response');
            }
            if ($indexDir === '') {
                throw new RuntimeException('Fastpanel: index_dir is empty in response');
            }

            $steps['fp_index_dir'] = $indexDir;

            // сохраняем в sites
            $upd = DB::pdo()->prepare("
                UPDATE sites
                SET fp_site_created=1,
                    fp_site_id=?,
                    fp_index_dir=?,
                    fastpanel_server_id=?
                WHERE id=?
            ");
            $upd->execute([$fpSiteId, $indexDir, $serverId, $siteId]);

            $respShort = [
                'id'        => $fpSiteId,
                'domain'    => (string)($resp['domain'] ?? $domain),
                'index_dir' => $indexDir,
            ];

            $this->safeUpdateDeploymentDone($deployId, ['payload' => $payload, 'steps' => $steps], $respShort);
            $this->redirect('/deploy/report?id=' . $deployId);
            exit;

        } catch (Throwable $e) {
            try {
                $this->safeUpdateDeploymentError(
                    $deployId,
                    $e->getMessage(),
                    ['payload' => $payload, 'steps' => $steps]
                );
            } catch (Throwable $e2) {}

            $this->redirect('/deploy/report?id=' . $deployId);
            exit;
        }
    }

    private function ftpCanWrite(string $host, int $port, string $user, string $pass): bool
    {
        $conn = @ftp_connect($host, $port, 20);
        if (!$conn) return false;

        @ftp_set_option($conn, FTP_TIMEOUT_SEC, 30);

        if (!@ftp_login($conn, $user, $pass)) {
            @ftp_close($conn);
            return false;
        }

        // PASV после login
        @ftp_pasv($conn, true);

        // временный файл строго внутри storage
        $tmpDir = Paths::storage('tmp');
        Paths::ensureDir($tmpDir);

        $tmpLocal = $tmpDir . '/ftpchk_' . bin2hex(random_bytes(6)) . '.txt';
        if (@file_put_contents($tmpLocal, "ok\n") === false) {
            @ftp_close($conn);
            return false;
        }

        $remote = '__hub_write_test.txt';
        @ftp_delete($conn, $remote);

        $ok = @ftp_put($conn, $remote, $tmpLocal, FTP_BINARY);
        if ($ok) {
            @ftp_delete($conn, $remote);
        }

        @unlink($tmpLocal);
        @ftp_close($conn);

        return (bool)$ok;
    }

    public function updateFiles(): void
    {
        $this->requireAuth();

        require_once Paths::appRoot() . '/app/Services/Crypto.php';
        require_once Paths::appRoot() . '/app/Services/FastpanelClient.php';
        require_once Paths::appRoot() . '/app/Services/ZipService.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad site id');

        $site = $this->loadSite($siteId);

        if ((int)($site['fp_site_created'] ?? 0) !== 1 || empty($site['fp_site_id']) || empty($site['fp_index_dir']) || empty($site['fastpanel_server_id'])) {
            die('site not created in fastpanel. run createSite first');
        }

        $serverId = (int)$site['fastpanel_server_id'];
        $server = $this->loadServer($serverId);

        $stmt = DB::pdo()->prepare("INSERT INTO deployments (site_id, server_id, status) VALUES (?, ?, 'uploading_files')");
        $stmt->execute([$siteId, $serverId]);
        $deployId = (int)DB::pdo()->lastInsertId();

        $domain   = $this->normalizeDomain((string)$site['domain']);
        $indexDir = (string)$site['fp_index_dir'];
        $payload  = null;

        $steps = [
            'stage'     => 'update_files',
            'domain'    => $domain,
            'server_id' => $serverId,
            'index_dir' => $indexDir,
            'zip'       => [],
            'ftp'       => [],
            'unpack'    => [],
        ];

        try {
            $this->tryPingDb();

            $password = Crypto::decrypt((string)$server['password_enc']);

            $client = new FastpanelClient(
                (string)$server['host'],
                (bool)$server['verify_tls'],
                (int)config('fastpanel.timeout', 30)
            );
            $client->login((string)$server['username'], $password);

            // ownerId
            $ownerId = 1;
            try {
                $me = $client->me();
                $ownerId = (int)($me['id'] ?? 1);
                if ($ownerId <= 0) $ownerId = 1;
            } catch (Throwable $e) {
                $ownerId = 1;
            }

            // 1) ZIP build (build_path должен лежать внутри storage)
            $buildAbs = $this->resolveBuildAbsFromSite($site);

            if (!is_dir($buildAbs)) {
                throw new RuntimeException("Build directory not found: {$buildAbs}");
            }

            $zipDir = Paths::storage('zips');
            Paths::ensureDir($zipDir);

            $zipPath = Paths::storage("zips/site_{$siteId}.zip");

            (new ZipService())->makeZip($buildAbs, $zipPath);

            if (!is_file($zipPath) || filesize($zipPath) < 50) {
                throw new RuntimeException('zip build failed: ' . $zipPath);
            }

            $steps['zip']['path'] = $zipPath;
            $steps['zip']['size'] = (int)filesize($zipPath);

            // 2) FTP creds: reuse if OK, otherwise recreate immediately
            $ftpHost = $this->extractHost((string)$server['host']);
            $ftpPort = 21;

            $ftpUser = (string)($site['fp_ftp_user'] ?? 0);
            $ftpPass = '';
            if (!empty($site['fp_ftp_pass_enc'])) {
                $ftpPass = Crypto::decrypt((string)$site['fp_ftp_pass_enc']);
            }

            $needCreate = ((int)($site['fp_ftp_ready'] ?? 0) !== 1 || $ftpUser === '' || $ftpPass === '');

            // Если думаем что можем reused — проверим, что реально можем писать
            if (!$needCreate) {
                $canWrite = $this->ftpCanWrite($ftpHost, $ftpPort, $ftpUser, $ftpPass);
                $steps['ftp']['write_check'] = $canWrite ? 'ok' : 'failed';

                if (!$canWrite) {
                    // сбрасываем сломанный reused и пересоздаем прямо сейчас
                    DB::pdo()->prepare("
                        UPDATE sites
                        SET fp_ftp_ready=0,
                            fp_ftp_user=NULL,
                            fp_ftp_pass_enc=NULL,
                            fp_ftp_id=NULL
                        WHERE id=?
                    ")->execute([$siteId]);

                    $needCreate = true;
                    $ftpUser = '';
                    $ftpPass = '';
                }
            }

            if ($needCreate) {
                $baseUser = 'hub_site_' . $siteId;

                $created = null;
                $ftpId = 0;

                for ($try = 0; $try < 10; $try++) {
                    $candidate = ($try === 0) ? $baseUser : ($baseUser . '_' . ($try + 1));
                    $candidatePass = bin2hex(random_bytes(10));

                    try {
                        // ВАЖНО: ваш метод должен называться createFtpAccount
                        $created = $client->createFtpAccount([
                            'enabled'  => true,
                            'home_dir' => $indexDir,
                            'limit'    => 0,
                            'name'     => $candidate,
                            'owner'    => $ownerId,
                            'password' => $candidatePass,
                        ]);

                        $ftpUser = $candidate;
                        $ftpPass = $candidatePass;
                        $ftpId   = (int)($created['id'] ?? 0);
                        break;

                    } catch (Throwable $e) {
                        $msg = $e->getMessage();
                        if (stripos($msg, 'already exists') !== false) {
                            continue;
                        }
                        throw $e;
                    }
                }

                if ($ftpUser === '' || $ftpPass === '') {
                    throw new RuntimeException('Cannot create FTP account after retries (name conflicts)');
                }

                DB::pdo()->prepare("
                    UPDATE sites
                    SET fp_ftp_ready=1,
                        fp_ftp_user=?,
                        fp_ftp_pass_enc=?,
                        fp_ftp_id=?,
                        fp_ftp_last_ok=NOW()
                    WHERE id=?
                ")->execute([$ftpUser, Crypto::encrypt($ftpPass), $ftpId, $siteId]);

                $steps['ftp']['account'] = 'created';
                $steps['ftp']['id'] = $ftpId;

                usleep(2500000); // 2.5 sec
            } else {
                $steps['ftp']['account'] = 'reused';
                $steps['ftp']['id'] = (int)($site['fp_ftp_id'] ?? 0);
            }

            $steps['ftp']['host'] = $ftpHost;
            $steps['ftp']['port'] = $ftpPort;
            $steps['ftp']['user'] = $ftpUser;

            // 3) upload zip + upload unpacker
            $remoteZip = '__hub_build.zip';
            $remoteUnpacker = '__hub_unpack.php';

            $this->ftpUploadWithRetryReconnect($ftpHost, $ftpPort, $ftpUser, $ftpPass, $zipPath, $remoteZip);
            $steps['ftp']['zip_upload'] = 'ok';

            $secret = bin2hex(random_bytes(16));
            $unpackerBody = $this->buildUnpackerPhp($secret, $remoteZip);

            // временный unpacker строго внутри storage
            $tmpDir = Paths::storage('tmp');
            Paths::ensureDir($tmpDir);

            $tmpUnpacker = Paths::storage('tmp/hub_unpack_' . $siteId . '_' . $deployId . '.php');
            if (@file_put_contents($tmpUnpacker, $unpackerBody) === false) {
                throw new RuntimeException('cannot write temp unpacker: ' . $tmpUnpacker);
            }

            $this->ftpUploadWithRetryReconnect($ftpHost, $ftpPort, $ftpUser, $ftpPass, $tmpUnpacker, $remoteUnpacker);
            @unlink($tmpUnpacker);

            $steps['ftp']['unpacker_upload'] = 'ok';

            // 4) call unpacker by HTTP
            $unpackUrl = 'https://' . $domain . '/' . $remoteUnpacker . '?k=' . $secret;

            // если домен не резолвится — дергаем через temp proxy
            if (!$this->isResolvable($domain)) {
                $proxyBase = rtrim((string)config('fastpanel.temp_proxy_base', ''), '/');
                if ($proxyBase !== '') {
                    $unpackUrl = $proxyBase . '/' . rawurlencode($domain) . '/' . $remoteUnpacker . '?k=' . $secret;
                    $steps['unpack']['note'] = 'used temp proxy (domain not resolvable)';
                } else {
                    $steps['unpack']['note'] = 'domain not resolvable and temp_proxy_base not set';
                }
            }

            $steps['unpack']['url'] = $unpackUrl;

            $http = $this->httpGetJson($unpackUrl, 60);

            $steps['unpack']['http'] = [
                'ok'   => (bool)($http['ok'] ?? false),
                'http' => (int)($http['_http'] ?? 0),
                'err'  => (string)($http['error'] ?? ''),
                'msg'  => (string)($http['message'] ?? ''),
            ];

            if (!is_array($http) || ($http['ok'] ?? false) !== true) {
                throw new RuntimeException('Unpack failed: ' . json_encode($steps['unpack']['http'], JSON_UNESCAPED_UNICODE));
            }

            // 5) mark files ok
            DB::pdo()->prepare("UPDATE sites SET fp_files_ready=1, fp_files_last_ok=NOW() WHERE id=?")->execute([$siteId]);

            $respShort = [
                'site_id'   => (int)($site['fp_site_id'] ?? 0),
                'domain'    => $domain,
                'index_dir' => $indexDir,
                'ftp_user'  => $ftpUser,
            ];

            $this->safeUpdateDeploymentDone($deployId, ['payload' => $payload, 'steps' => $steps], $respShort);
            $this->redirect('/deploy/report?id=' . $deployId);
            exit;

        } catch (Throwable $e) {
            try {
                $this->safeUpdateDeploymentError($deployId, $e->getMessage(), ['payload' => $payload, 'steps' => $steps]);
            } catch (Throwable $e2) {}

            $this->redirect('/deploy/report?id=' . $deployId);
            exit;
        }
    }

    public function issueSslSelfSigned(): void
    {
        $this->requireAuth();

        require_once Paths::appRoot() . '/app/Services/Crypto.php';
        require_once Paths::appRoot() . '/app/Services/FastpanelClient.php';

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad site id');

        $site = $this->loadSite($siteId);

        if (empty($site['fp_site_created']) || (int)($site['fp_site_id'] ?? 0) <= 0) {
            die('Fastpanel site not created yet (run createSite first)');
        }

        $serverId = (int)($site['fastpanel_server_id'] ?? 0);
        if ($serverId <= 0) die('fastpanel_server_id is empty in sites');

        $fpSiteId = (int)$site['fp_site_id'];
        $server   = $this->loadServer($serverId);

        $domain = $this->normalizeDomain((string)$site['domain']);
        if ($domain === '') die('bad domain');

        // флаги (можно расширить UI)
        $httpsRedirect = (bool)($_POST['https_redirect'] ?? false);
        $hsts          = (bool)($_POST['hsts'] ?? false);
        $http2         = (bool)($_POST['http2'] ?? false);
        $http3         = (bool)($_POST['http3'] ?? false);

        $stmt = DB::pdo()->prepare("INSERT INTO deployments (site_id, server_id, status) VALUES (?, ?, 'issuing_ssl')");
        $stmt->execute([$siteId, $serverId]);
        $deployId = (int)DB::pdo()->lastInsertId();

        $payload = [
            'stage'          => 'issue_ssl_self_signed',
            'domain'         => $domain,
            'fp_site_id'     => $fpSiteId,
            'server_id'      => $serverId,
            'https_redirect' => $httpsRedirect,
            'hsts'           => $hsts,
            'http2'          => $http2,
            'http3'          => $http3,
        ];

        try {
            $this->tryPingDb();

            $password = Crypto::decrypt((string)$server['password_enc']);

            $client = new FastpanelClient(
                (string)$server['host'],
                (bool)$server['verify_tls'],
                (int)config('fastpanel.timeout', 30)
            );
            $client->login((string)$server['username'], $password);

            // 1) Быстро читаем текущее состояние сертификата на сайте
            $state = $client->getSiteCertificateState($fpSiteId);
            $currentCertId = (int)($state['cert_id'] ?? 0);
            $currentEnabled = (bool)($state['enabled'] ?? false);

            // Если уже enabled — помечаем в БД ready и выходим
            if ($currentCertId > 0 && $currentEnabled === true) {
                $this->updateSslState($siteId, true, $currentCertId, true, '');
                $this->safeUpdateDeploymentDone($deployId, $payload, [
                    'result' => 'already_enabled',
                    'site' => [
                        'id' => $fpSiteId,
                        'certificate' => $state['site']['certificate'] ?? null,
                    ],
                ]);
                $this->redirect('/deploy/report?id=' . $deployId);
                exit;
            }

            $result = '';
            $certId = 0;

            // 2) Если сертификат уже есть, но disabled — пробуем применить его (не создаем новый)
            if ($currentCertId > 0 && $currentEnabled === false) {
                $certId = $currentCertId;
                $result = 'has_certificate_but_disabled';
            } else {
                // 3) Иначе создаем новый self-signed
                $email = (string)config('fastpanel.ssl_email', '');
                if ($email === '') $email = 'admin@' . $domain;

                $certResp = $client->createSelfSignedCertificate(
                    $fpSiteId,
                    $email,
                    $domain,
                    'www.' . $domain,
                    2048,
                    365
                );

                $certId = (int)($certResp['id'] ?? 0);
                if ($certId <= 0) {
                    throw new RuntimeException('Fastpanel: certificate id is empty: ' . json_encode($certResp, JSON_UNESCAPED_UNICODE));
                }

                // Ждем только генерацию сертификата (очередь)
                $client->waitQueueSslCertificateJob($fpSiteId, $certId, 120, 2);

                $result = 'created_new_certificate';
            }

            // 4) Применяем сертификат к сайту (как в UI)
            $applyResp = $client->applyCertificateToSite(
                $fpSiteId,
                $certId,
                $httpsRedirect,
                $hsts,
                $http2,
                $http3,
                false
            );

            // 5) Сразу записываем "сертификат есть", но готовность подтверждаем отдельным быстрым GET
            $this->updateSslState($siteId, true, $certId, false, '');

            // 6) Быстрый re-check (один раз). Без поллинга => без 504.
            $after = $client->getSiteCertificateState($fpSiteId);
            $afterCertId = (int)($after['cert_id'] ?? 0);
            $afterEnabled = (bool)($after['enabled'] ?? false);

            $ready = ($afterCertId === $certId); // Вариант B: достаточно совпадения ID
            // Если хочешь строже: $ready = ($afterCertId === $certId && $afterEnabled === true);

            if ($ready) {
                $this->updateSslState($siteId, true, $certId, true, '');
            } else {
                $this->updateSslState($siteId, true, $certId, false, 'certificate applied but not confirmed yet');
            }

            $this->safeUpdateDeploymentDone($deployId, $payload, [
                'result' => $result,
                'certificate_id' => $certId,
                'apply' => $applyResp,
                'site' => [
                    'id' => $fpSiteId,
                    'certificate' => $after['site']['certificate'] ?? null,
                ],
            ]);

            $this->redirect('/deploy/report?id=' . $deployId);
            exit;

        } catch (Throwable $e) {
            try {
                $this->updateSslState($siteId, (isset($certId) && (int)$certId > 0), (int)($certId ?? 0), false, $e->getMessage());
            } catch (Throwable $e2) {}

            $this->safeUpdateDeploymentError($deployId, $e->getMessage(), $payload);
            $this->redirect('/deploy/report?id=' . $deployId);
            exit;
        }
    }

    /**
     * POST /deploy/reset?id=6
     */
    public function reset(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        DB::pdo()->prepare("
            UPDATE sites
            SET fp_site_created=0,
                fp_site_id=NULL,
                fp_index_dir=NULL,
                fastpanel_server_id=NULL,
                fp_ftp_ready=0,
                fp_ftp_user=NULL,
                fp_ftp_pass_enc=NULL,
                fp_ftp_id=NULL,
                fp_ftp_last_ok=NULL,
                fp_files_ready=0,
                fp_files_last_ok=NULL
            WHERE id=?
        ")->execute([$siteId]);

        $this->redirect('/deploy?id=' . $siteId);
        exit;
    }

    // ------------------- helpers -------------------

    private function normalizeDomain(string $domain): string
    {
        $domain = preg_replace('~^https?://~i', '', trim($domain));
        return rtrim($domain, '/');
    }

    private function extractHost(string $host): string
    {
        $h = preg_replace('~^https?://~i', '', trim($host));
        $h = preg_replace('~:\d+$~', '', $h);
        return $h;
    }

    private function ftpUploadWithRetryReconnect(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $localPath,
        string $remoteFilename
    ): void {
        if (!is_file($localPath)) {
            throw new RuntimeException('ftpUpload: local file not found: ' . $localPath);
        }

        $maxAttempts = 15;
        $lastErr = null;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $conn = null;

            try {
                $conn = @ftp_connect($host, $port, 30);
                if (!$conn) {
                    throw new RuntimeException("ftpUpload: cannot connect to $host:$port");
                }

                // таймауты на data-канал
                @ftp_set_option($conn, FTP_TIMEOUT_SEC, 90);

                if (!@ftp_login($conn, $user, $pass)) {
                    throw new RuntimeException("ftpUpload: login failed attempt $i");
                }

                // PASV включаем после логина
                @ftp_pasv($conn, true);

                @ftp_chdir($conn, '.');

                $tmp = $remoteFilename . '.part';
                @ftp_delete($conn, $tmp);

                $ok = @ftp_put($conn, $tmp, $localPath, FTP_BINARY);
                if (!$ok) {
                    throw new RuntimeException("ftp_put failed attempt $i");
                }

                @ftp_delete($conn, $remoteFilename);
                if (!@ftp_rename($conn, $tmp, $remoteFilename)) {
                    throw new RuntimeException("ftp_rename failed attempt $i");
                }

                $sz = @ftp_size($conn, $remoteFilename);
                if ($sz !== -1 && $sz < 50) {
                    throw new RuntimeException('remote file too small, size=' . $sz);
                }

                @ftp_close($conn);
                return;

            } catch (Throwable $e) {
                $lastErr = $e;
                if (is_resource($conn)) {
                    @ftp_close($conn);
                }
                usleep(700000 + ($i * 300000));
            }
        }

        throw new RuntimeException('ftpUpload: failed after retries. ' . ($lastErr ? $lastErr->getMessage() : ''));
    }

    private function buildUnpackerPhp(string $secret, string $zipName): string
    {
        $secretEsc = addslashes($secret);
        $zipEsc = addslashes($zipName);

        return <<<PHP
<?php
header('Content-Type: application/json; charset=utf-8');

\$k = isset(\$_GET['k']) ? (string)\$_GET['k'] : '';
if (\$k !== '{$secretEsc}') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

\$zipFile = __DIR__ . DIRECTORY_SEPARATOR . '{$zipEsc}';
if (!is_file(\$zipFile)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'zip not found','zip'=>\$zipFile], JSON_UNESCAPED_UNICODE);
    exit;
}

\$extracted = false;
\$errors = [];

if (class_exists('ZipArchive')) {
    \$za = new ZipArchive();
    if (\$za->open(\$zipFile) === true) {
        \$extracted = \$za->extractTo(__DIR__);
        \$za->close();
    } else {
        \$errors[] = 'ZipArchive open failed';
    }
} else {
    \$errors[] = 'ZipArchive not available';
}

if (!\$extracted) {
    if (function_exists('shell_exec')) {
        \$cmd = 'cd ' . escapeshellarg(__DIR__) . ' && unzip -o ' . escapeshellarg('{$zipEsc}');
        \$out = shell_exec(\$cmd . ' 2>&1');
        if (is_file(__DIR__ . '/index.php') || is_file(__DIR__ . '/index.html')) {
            \$extracted = true;
        } else {
            \$errors[] = 'shell unzip failed: ' . (string)\$out;
        }
    } else {
        \$errors[] = 'shell_exec disabled';
    }
}

if (!\$extracted) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'extract failed','details'=>\$errors], JSON_UNESCAPED_UNICODE);
    exit;
}

@unlink(\$zipFile);
@unlink(__FILE__);

echo json_encode(['ok'=>true,'message'=>'deployed'], JSON_UNESCAPED_UNICODE);
PHP;
    }

    private function httpGetJson(string $url, int $timeoutSec = 30): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => 'curl: ' . $err, '_http' => 0];
        }

        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'http' => $code, 'raw' => substr((string)$resp, 0, 500), '_http' => $code];
        }

        $json['_http'] = $code;
        return $json;
    }

    private function tryPingDb(): void
    {
        try {
            DB::withReconnect(function(PDO $pdo) {
                $pdo->query('SELECT 1');
                return true;
            });
        } catch (Throwable $e) {
            // игнор
        }
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

    public function report(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("SELECT * FROM deployments WHERE id=?");
        $stmt->execute([$id]);
        $deploy = $stmt->fetch();
        if (!$deploy) die('not found');

        $this->view('deploy/report', compact('deploy'));
    }

    private function loadSite(int $siteId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) die('site not found');
        return $site;
    }

    private function loadServer(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('server not found');
        return $row;
    }

    private function sslProbe(string $domain, int $timeoutSec = 6): array
    {
        $domain = preg_replace('~^https?://~i', '', trim($domain));
        $domain = rtrim($domain, '/');

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $domain,
            ],
        ]);

        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(
            "ssl://{$domain}:443",
            $errno,
            $errstr,
            $timeoutSec,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$fp) {
            return ['ok' => false, 'error' => "connect failed: {$errno} {$errstr}"];
        }

        $params = stream_context_get_params($fp);
        @fclose($fp);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['ok' => false, 'error' => 'no peer_certificate'];
        }

        $parsed = @openssl_x509_parse($cert);
        if (!is_array($parsed)) {
            return ['ok' => false, 'error' => 'openssl_x509_parse failed'];
        }

        $validTo = (int)($parsed['validTo_time_t'] ?? 0);
        $subject = $parsed['subject']['CN'] ?? '';
        $issuer  = $parsed['issuer']['CN'] ?? '';
        $sansRaw = $parsed['extensions']['subjectAltName'] ?? '';

        return [
            'ok' => true,
            'valid_to' => $validTo,
            'subject_cn' => (string)$subject,
            'issuer_cn' => (string)$issuer,
            'sans' => (string)$sansRaw,
        ];
    }

    private function waitSslReadyByProbe(string $domain, int $timeoutSec = 120, int $pollSec = 3): array
    {
        $t0 = time();
        $last = null;

        while (true) {
            $last = $this->sslProbe($domain, 6);

            if (($last['ok'] ?? false) === true) {
                return $last;
            }

            if ((time() - $t0) >= $timeoutSec) {
                throw new RuntimeException('SSL probe timeout: ' . json_encode($last, JSON_UNESCAPED_UNICODE));
            }

            sleep($pollSec);
        }
    }

    private function updateSslState(
        int $siteId,
        bool $hasCert,
        int $certId,
        bool $ready,
        string $error = ''
    ): void {
        DB::pdo()->prepare("
            UPDATE sites SET
                ssl_has_cert = ?,
                ssl_cert_id  = ?,
                ssl_ready    = ?,
                ssl_error    = ?,
                ssl_checked_at = NOW(),
                ssl_last_ok = CASE WHEN ? = 1 THEN NOW() ELSE ssl_last_ok END
            WHERE id = ?
        ")->execute([
            $hasCert ? 1 : 0,
            $certId > 0 ? $certId : null,
            $ready ? 1 : 0,
            $error,
            $ready ? 1 : 0,
            $siteId
        ]);
    }

    private function isResolvable(string $domain): bool
    {
        $domain = trim($domain);
        if ($domain === '') return false;

        if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $domain)) return true;

        $ip = @gethostbyname($domain);
        if ($ip === $domain) return false;

        return (bool)preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip);
    }

    /**
     * Правка 1.3: build_path -> абсолютный путь строго через Paths::storage()
     */
    private function resolveBuildAbsFromSite(array $site): string
    {
        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') {
            throw new RuntimeException('build_path empty for this site');
        }

        $rel = str_replace('\\', '/', $buildRel);
        $rel = ltrim($rel, '/');

        // legacy: "<storage_basename>/..."
        $storageBase = basename(rtrim(Paths::storage(''), "/\\"));
        if ($storageBase !== '' && stripos($rel, $storageBase . '/') === 0) {
            $sub = substr($rel, strlen($storageBase) + 1);
            $sub = ltrim($sub, '/');
            if ($sub === '') {
                throw new RuntimeException('build_path invalid (storage only): ' . $buildRel);
            }
            return Paths::storage($sub);
        }

        // если вдруг кто-то пишет просто "builds/site_123" (тоже допустим)
        if (preg_match('~^(builds|templates|configs|logs|zips|build_reports|tmp)/~i', $rel)) {
            return Paths::storage($rel);
        }

        throw new RuntimeException('build_path must be inside storage: ' . $buildRel);
    }
}
