<?php

class WebmasterController extends Controller
{
    private YandexWebmasterService $wm;

    public function __construct()
    {
        $this->wm = new YandexWebmasterService();
    }

    public function index()
    {
        $settings = $this->wm->getSettings();

        $sites = DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->query("SELECT * FROM sites ORDER BY id DESC");
            return $st->fetchAll() ?: [];
        });

        return $this->view('webmaster/index', [
            'settings' => $settings,
            'sites' => $sites,
        ]);
    }

    public function site()
    {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            http_response_code(400);
            echo "Bad site id";
            return;
        }

        $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("SELECT * FROM sites WHERE id = :id LIMIT 1");
            $st->execute([':id' => $siteId]);
            return $st->fetch();
        });

        if (!$site) {
            http_response_code(404);
            echo "Site not found";
            return;
        }

        $desired = $this->wm->getDesiredHostsForSite($siteId);
        $rows = $this->wm->getWebmasterHostsRows($siteId);

        // map label => row
        $rowMap = [];
        foreach ($rows as $r) {
            $rowMap[(string)($r['label'] ?? '')] = $r;
        }

       require_once Paths::appRoot() . '/app/Services/WebmasterPublishStateService.php';
		$wmDeployState = (new WebmasterPublishStateService())->getState($siteId);
        $indexStatusMap = (new YandexIndexWatchService())->getStatusesForSite($siteId, $desired);
        $cronState = $this->getCronState();
        $indexWatchLogTail = $this->getIndexWatchLogTailForSite($siteId, 120);
        $searchApiService = new YandexSearchApiService();
        $searchApiStatusMap = $searchApiService->getStatusesForSite($siteId, $desired);
        $searchApiCronState = $searchApiService->getCronState();
        $robotsSummaryMap = $this->buildRobotsSummaryMap($siteId, $desired, $rowMap);
        $indexSummaryRows = $this->buildIndexSummaryRows($desired, $indexStatusMap, $searchApiStatusMap);

		return $this->view('webmaster/site', [
			'site' => $site,
			'desired' => $desired,
			'rowMap' => $rowMap,
			'wmDeployState' => $wmDeployState,
            'indexStatusMap' => $indexStatusMap,
            'cronState' => $cronState,
            'indexWatchLogTail' => $indexWatchLogTail,
            'searchApiStatusMap' => $searchApiStatusMap,
            'searchApiCronState' => $searchApiCronState,
            'robotsSummaryMap' => $robotsSummaryMap,
            'indexSummaryRows' => $indexSummaryRows,
		]);
    }


    private function buildRobotsSummaryMap(int $siteId, array $desired, array $rowMap): array
    {
        $out = [];
        $userId = '';
        try {
            $userId = (string)$this->wm->getUserId();
        } catch (Throwable $e) {
            $userId = '';
        }

        foreach ($desired as $hrow) {
            $label = (string)($hrow['label'] ?? '');
            $hostUrl = (string)($hrow['host_url'] ?? '');
            $row = $rowMap[$label] ?? [];
            $robotsUrl = (string)($row['robots_url'] ?? '');
            $robotsAt = (string)($row['robots_confirmed_at'] ?? '');
            $isVerified = ((string)($row['verified_at'] ?? '') !== '');
            $hostId = (string)($row['host_id'] ?? '');
            $source = '';

            if ($robotsUrl !== '' || $robotsAt !== '') {
                $source = 'db';
            } elseif ($userId !== '' && $hostId !== '') {
                try {
                    $apiRobots = $this->wm->getRobots($userId, $hostId);
                    $apiUrl = '';
                    if (is_array($apiRobots)) {
                        $candidates = [
                            $apiRobots['url'] ?? null,
                            $apiRobots['data']['url'] ?? null,
                            $apiRobots['robots_url'] ?? null,
                            $apiRobots['data']['robots_url'] ?? null,
                            $apiRobots['content']['url'] ?? null,
                        ];
                        foreach ($candidates as $candidate) {
                            if (is_string($candidate) && trim($candidate) !== '') {
                                $apiUrl = trim($candidate);
                                break;
                            }
                        }
                    }
                    if ($apiUrl !== '') {
                        $robotsUrl = $apiUrl;
                        $robotsAt = $robotsAt !== '' ? $robotsAt : date('Y-m-d H:i:s');
                        $source = 'api';
                        $this->wm->saveRobotsStatus($siteId, $label, $robotsUrl, $robotsAt);
                    }
                } catch (Throwable $e) {
                    // ignore, use fallback below
                }
            }

            if ($robotsUrl === '' && $isVerified) {
                $robotsUrl = rtrim($hostUrl, '/') . '/robots.txt';
                $source = $source !== '' ? $source : 'fallback';
            }

            $out[$label] = [
                'has_robots' => ($robotsUrl !== '' || $robotsAt !== ''),
                'robots_url' => $robotsUrl,
                'robots_at' => $robotsAt,
                'source' => $source,
            ];
        }

        return $out;
    }

    private function buildIndexSummaryRows(array $desired, array $indexStatusMap, array $searchApiStatusMap): array
    {
        $rows = [];
        foreach ($desired as $hrow) {
            $label = (string)($hrow['label'] ?? '');
            $hostUrl = (string)($hrow['host_url'] ?? '');
            $idx = $indexStatusMap[$label] ?? [];
            $search = $searchApiStatusMap[$label] ?? [];
            $rows[] = [
                'label' => $label,
                'host_url' => $hostUrl,
                'webmaster_index_status' => (string)($idx['yandex_index_status'] ?? 'unknown'),
                'pages_in_search' => (int)($idx['yandex_pages_in_search'] ?? 0),
                'pages_added' => (int)($idx['yandex_pages_added'] ?? 0),
                'webmaster_last_checked_at' => (string)($idx['yandex_index_last_checked_at'] ?? ''),
                'webmaster_detected_at' => (string)($idx['yandex_index_detected_at'] ?? ''),
                'redirect_auto_enabled_at' => (string)($idx['redirect_auto_enabled_at'] ?? ''),
                'config_sync_status' => (string)($idx['config_sync_status'] ?? 'idle'),
                'config_sync_last_at' => (string)($idx['config_sync_last_at'] ?? ''),
                'config_sync_error' => (string)($idx['config_sync_error'] ?? ''),
                'search_api_status' => (string)($search['search_api_status'] ?? 'idle'),
                'search_api_last_checked_at' => (string)($search['search_api_last_checked_at'] ?? ''),
                'search_api_indexed_at' => (string)($search['search_api_indexed_at'] ?? ''),
                'search_api_result_count' => (int)($search['search_api_result_count'] ?? 0),
                'search_api_next_check_at' => (string)($search['search_api_next_check_at'] ?? ''),
                'search_api_error' => (string)($search['search_api_error'] ?? ''),
            ];
        }
        return $rows;
    }

    public function checkIndex()
    {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            http_response_code(400);
            echo 'Bad site id';
            return;
        }

        $targetLabel = (string)($_POST['label'] ?? 'ALL');
        $log = [];
        try {
            $service = new YandexIndexWatchService();
            $results = $service->checkNow($siteId, $targetLabel !== '' ? $targetLabel : 'ALL');
            foreach ($results as $item) {
                $label = (string)($item['label'] ?? '');
                $host  = (string)($item['host'] ?? '');
                $check = is_array($item['check'] ?? null) ? $item['check'] : [];
                $status = !empty($check['indexed']) ? 'indexed' : (!empty($check['ok']) ? 'not_indexed' : 'error');
                $method = (string)($check['method'] ?? '');
                $log[] = 'INDEX ' . ($label === '' ? 'root' : $label) . ' :: ' . $host . ' :: ' . $status . ($method !== '' ? (' :: method=' . $method) : '');

                $debugLines = $check['debug'] ?? [];
                if (is_array($debugLines)) {
                    foreach ($debugLines as $dbg) {
                        $dbg = trim((string)$dbg);
                        if ($dbg !== '') {
                            $log[] = '  ' . $dbg;
                        }
                    }
                }

                if (!empty($item['redirect_changed'])) {
                    $syncInfo = '';
                    if (is_array($item['sync'] ?? null)) {
                        $syncInfo = !empty($item['sync']['ok'])
                            ? (' :: sync=' . implode(', ', (array)($item['sync']['uploaded'] ?? [])))
                            : (' :: sync_error=' . (string)($item['sync']['error'] ?? ''));
                    }
                    $log[] = 'AUTO redirect_enabled=1 for ' . ($label === '' ? 'root' : $label) . $syncInfo;
                }
            }
            if (!$log) {
                $log[] = 'Нет хостов для проверки индекса.';
            }
        } catch (Throwable $e) {
            $log[] = 'FATAL index: ' . $e->getMessage();
        }

        $_SESSION['wm_log'] = array_merge($_SESSION['wm_log'] ?? [], $log);
        header('Location: /webmaster/site?id=' . $siteId);
        exit;
    }

    public function manualSyncConfigs()
    {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            http_response_code(400);
            echo 'Bad site id';
            return;
        }

        $targetLabel = (string)($_POST['label'] ?? 'ALL');
        $log = [];

        try {
            require_once Paths::appRoot() . '/app/Services/ConfigSyncService.php';

            $syncService = new ConfigSyncService();
            $desired = $this->wm->getDesiredHostsForSite($siteId);
            $hostMap = [];
            foreach ($desired as $hrow) {
                $hostMap[(string)($hrow['label'] ?? '')] = (string)($hrow['host_url'] ?? '');
            }

            $done = 0;
            $errors = 0;

            foreach ($desired as $hrow) {
                $label = (string)($hrow['label'] ?? '');
                if ($targetLabel !== 'ALL' && $targetLabel !== $label) {
                    continue;
                }

                $cfgLabel = ($label === '' ? '_default' : $label);
                $hostUrl = (string)($hrow['host_url'] ?? '');

                try {
                    $sync = $syncService->syncLabelConfigFiles($siteId, $cfgLabel);
                    $uploaded = implode(', ', (array)($sync['uploaded'] ?? []));
                    $this->saveManualConfigSyncStatus($siteId, $label, $hostUrl, 'done', $uploaded, '');
                    $log[] = 'MANUAL SYNC ' . ($label === '' ? 'root' : $label) . ' :: ' . $hostUrl . ' :: ' . $uploaded;
                    $done++;
                } catch (Throwable $e) {
                    $this->saveManualConfigSyncStatus($siteId, $label, $hostUrl, 'error', '', $e->getMessage());
                    $log[] = 'MANUAL SYNC ERR ' . ($label === '' ? 'root' : $label) . ' :: ' . $hostUrl . ' :: ' . $e->getMessage();
                    $errors++;
                }
            }

            if ($done > 0) {
                $this->sendManualConfigSyncTelegram($siteId, $done, $errors);
            }

            if ($done === 0 && $errors === 0) {
                $log[] = 'Нет конфигов для ручной выгрузки.';
            }
        } catch (Throwable $e) {
            $log[] = 'FATAL manual sync: ' . $e->getMessage();
        }

        $_SESSION['wm_log'] = array_merge($_SESSION['wm_log'] ?? [], $log);
        header('Location: /webmaster/site?id=' . $siteId);
        exit;
    }

    private function saveManualConfigSyncStatus(int $siteId, string $label, string $hostUrl, string $status, string $context, string $error): void
    {
        DB::pdo()->prepare("
            INSERT INTO webmaster_hosts (site_id, label, host_url, config_sync_status, config_sync_context, config_sync_error, config_sync_last_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                host_url = VALUES(host_url),
                config_sync_status = VALUES(config_sync_status),
                config_sync_context = VALUES(config_sync_context),
                config_sync_error = VALUES(config_sync_error),
                config_sync_last_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
        ")->execute([$siteId, $label, $hostUrl, $status, $context, $error]);
    }

    private function sendManualConfigSyncTelegram(int $siteId, int $done, int $errors): void
    {
        try {
            $tg = new TelegramService();
            $text  = "🟦 <b>Ручная выгрузка config на VPS</b>
";
            $text .= 'Сайт ID: <b>' . (int)$siteId . "</b>
";
            $text .= 'Успешно выгружено: <b>' . (int)$done . "</b>
";
            $text .= 'Ошибок: <b>' . (int)$errors . "</b>
";
            $text .= 'Панель: https://hub.seotop-one.ru/webmaster/site?id=' . (int)$siteId;
            $tg->send($text, 'manual_sync');
        } catch (Throwable $e) {
            @error_log('[TG manual config sync] ' . $e->getMessage());
        }
    }

    public function sync()
    {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            http_response_code(400);
            echo "Bad site id";
            return;
        }

        $log = [];
        try {
            $userId = $this->wm->getUserId();
            $desired = $this->wm->getDesiredHostsForSite($siteId);

            foreach ($desired as $h) {
                $label = (string)$h['label'];
                $hostUrl = (string)$h['host_url'];

                try {
                    $hostId = $this->wm->getOrCreateHostId($userId, $hostUrl);

                    // получить verifier HTML_FILE
                    $ver = $this->wm->getHtmlFileVerifier($userId, $hostId);

                    // записать файл в build
                    $writtenPath = $this->wm->writeVerificationFileToBuild(
                        $siteId,
                        $label,
                        (string)$ver['file'],
                        (string)$ver['content']
                    );

                    // сохранить в БД
                    $this->wm->upsertWebmasterHost(
                        $siteId,
                        $label,
                        $hostUrl,
                        $hostId,
                        (string)$ver['type'],
                        (string)$ver['uin'],
                        (string)$ver['file'],
                        (string)$ver['content'],
                        1
                    );

                    $log[] = "OK: {$hostUrl} :: host_id={$hostId} :: wrote={$writtenPath}";
                } catch (Throwable $e) {
                    $log[] = "ERR: {$hostUrl} :: " . $e->getMessage();

                    // все равно апсертим хотя бы hostUrl (чтобы видеть проблему в таблице)
                    try {
                        $this->wm->upsertWebmasterHost(
                            $siteId,
                            $label,
                            $hostUrl,
                            null,
                            null,
                            null,
                            null,
                            null,
                            0
                        );
                    } catch (Throwable $ignore) {}
                }
            }

        } catch (Throwable $e) {
            $log[] = "FATAL: " . $e->getMessage();
        }

        $_SESSION['wm_log'] = $log;
        header("Location: /webmaster/site?id=" . $siteId);
        exit;
    }
	
	public function connect()
{
    $settings = $this->wm->getSettings();
    $saved = false;
    $error = '';

    // Если форма отправлена
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            $clientId = trim((string)($_POST['oauth_client_id'] ?? ''));
            $token    = trim((string)($_POST['access_token'] ?? ''));
            $expires  = trim((string)($_POST['token_expires_at'] ?? ''));

            $this->wm->saveSettings(
                $clientId,
                $token,
                $expires !== '' ? $expires : null
            );

            $saved = true;
            $settings = $this->wm->getSettings(); // перечитать, чтобы отобразить уже сохраненное
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    return $this->view('webmaster/connect', [
        'settings' => $settings,
        'saved'    => $saved,
        'error'    => $error,
    ]);
}

    public function verify()
    {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            http_response_code(400);
            echo "Bad site id";
            return;
        }

        $log = [];
        try {
            $userId = $this->wm->getUserId();
            $rows = $this->wm->getWebmasterHostsRows($siteId);

            foreach ($rows as $r) {
                $label = (string)($r['label'] ?? '');
                $hostUrl = (string)($r['host_url'] ?? '');
                $hostId = (string)($r['host_id'] ?? '');

                if ($hostId === '') {
                    $log[] = "SKIP: {$hostUrl} :: host_id empty";
                    continue;
                }

                try {
    $res = $this->wm->verifyHost($userId, $hostId, 'HTML_FILE');

    // перепроверим состояние
    $chk = $this->wm->checkVerification($userId, $hostId);

    $state = '';
    if (isset($chk['verification_state'])) $state = (string)$chk['verification_state'];
    elseif (isset($chk['data']['verification_state'])) $state = (string)$chk['data']['verification_state'];

    $log[] = "OK verify: {$hostUrl} :: state={$state} :: " . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $wasVerified = !empty($r['verified_at']);

	if ($state === 'VERIFIED' || $state === 'SUCCESS') {
		$this->wm->markVerified($siteId, $label);

		if (!$wasVerified) {
			$this->sendVerifiedTelegram($siteId, $label, $hostUrl);
		}
	}

} catch (Throwable $e) {
    $log[] = "ERR verify: {$hostUrl} :: " . $e->getMessage();
}
            }

        } catch (Throwable $e) {
            $log[] = "FATAL: " . $e->getMessage();
        }

        $_SESSION['wm_log'] = $log;
        header("Location: /webmaster/site?id=" . $siteId);
        exit;
    }
	
	
	public function recrawl()
{
    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        http_response_code(400);
        echo "Bad site id";
        return;
    }

    $label = (string)($_POST['label'] ?? '');
    $raw   = (string)($_POST['urls'] ?? '');

    $log = [];
    try {
        $userId = $this->wm->getUserId();

        // выберем hostId по label (root = пустая строка)
        $hostId = $this->wm->getHostIdByLabel($siteId, $label);
        if (!$hostId) {
            throw new RuntimeException("host_id not found for label=" . $label);
        }

        $urls = $this->wm->normalizeRecrawlUrls($siteId, $label, $raw);

        if (!$urls) {
            throw new RuntimeException("urls empty after normalize");
        }

        $res = $this->wm->recrawlUrls($userId, (string)$hostId, $urls);
		$this->wm->saveRecrawlStatus($siteId, $label, count($urls));
		$reset = $this->wm->disableRedirectAndResetIndexState($siteId, $label);

		$log[] = "OK recrawl: label={$label}, urls=" . count($urls);
		$log[] = "RESULT: sent={$res['sent']} success={$res['success']} failed={$res['failed']}";
		$log[] = "REDIRECT RESET: status=" . (string)($reset['sync_status'] ?? '') . (
		    !empty($reset['sync_context']) ? (' :: ' . (string)$reset['sync_context']) : ''
		) . (!empty($reset['sync_error']) ? (' :: error=' . (string)$reset['sync_error']) : '');
		if (!empty($res['errors'])) {
			$log[] = "ERRORS:\n" . implode("\n", $res['errors']);
		}

    } catch (Throwable $e) {
        $log[] = "FATAL recrawl: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}


public function sitemapAdd()
{
    $siteId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($siteId <= 0) { http_response_code(400); echo "Bad site id"; return; }

    $label = (string)($_POST['label'] ?? 'ALL'); // '' root, конкретный, или ALL
    $log = [];

    try {
        $userId  = $this->wm->getUserId();
        $desired = $this->wm->getDesiredHostsForSite($siteId);

        foreach ($desired as $h) {
            $rLabel  = (string)($h['label'] ?? '');
            $hostUrl = (string)($h['host_url'] ?? '');

            if ($label !== 'ALL' && $rLabel !== $label) continue;

            $hostId = $this->wm->getHostIdByLabel($siteId, $rLabel);
            if (!$hostId) {
                $log[] = "SKIP sitemap: {$hostUrl} :: host_id empty (run sync first)";
                continue;
            }

            $override   = trim((string)($_POST['sitemap_url'] ?? ''));
            $sitemapUrl = $override !== '' ? $override : (rtrim($hostUrl, '/') . '/sitemap.xml');

            // 1) HEAD-check (как диагностика, но НЕ блокируем API из-за self-signed)
            $check = $this->httpHead($sitemapUrl);

            $log[] = "SITEMAP CHECK: label={$rLabel} url={$sitemapUrl} http={$check['http']} final={$check['final_url']}";
            if (!empty($check['err'])) {
                $log[] = "SITEMAP CURL ERR: " . $check['err'];
            }

            // Если self-signed или вообще http=0 — это warning, но не стоп
            $httpOk = ($check['http'] >= 200 && $check['http'] < 400);
            if (!$httpOk) {
                // Если это не self-signed — можно оставить как жесткий стоп, но ты просил массово:
                // оставим просто предупреждением и всё равно попробуем API
                $log[] = "WARN sitemap (http-check): {$hostUrl} :: http={$check['http']} (will try API anyway)";
            }

            // 2) PRECHECK: если sitemap уже есть в Яндексе — считаем успехом и не делаем POST
            try {
                $existing = $this->wm->getSitemaps($userId, (string)$hostId);
                if ($this->sitemapExistsInResponse($existing, $sitemapUrl)) {
                    $this->wm->saveSitemapStatus($siteId, $rLabel, $sitemapUrl);
                    $log[] = "OK sitemap (already exists via GET): {$hostUrl} :: {$sitemapUrl}";
                    continue;
                }
            } catch (Throwable $e) {
                $log[] = "WARN sitemapGet before add: {$hostUrl} :: " . $e->getMessage();
            }

            // 3) ADD via API
            try {
                $res = $this->wm->addSitemap($userId, (string)$hostId, $sitemapUrl);

                $this->wm->saveSitemapStatus($siteId, $rLabel, $sitemapUrl);

                $log[] = "OK sitemap (api): {$hostUrl} :: {$sitemapUrl}";
                $log[] = json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

            } catch (Throwable $e) {
                $msg = $e->getMessage();

                // Если уже добавлен — ок
                if (stripos($msg, 'SITEMAP_ALREADY_ADDED') !== false) {
                    $this->wm->saveSitemapStatus($siteId, $rLabel, $sitemapUrl);
                    $log[] = "OK sitemap (already added): {$hostUrl} :: {$sitemapUrl}";
                    continue;
                }

                // ВАЖНО: твой кейс — 405 Method not allowed.
                // Делаем GET и если там sitemap появился/уже есть — считаем успехом.
                if (strpos($msg, 'HTTP 405') !== false || stripos($msg, 'Method not allowed') !== false) {
                    try {
                        $existing2 = $this->wm->getSitemaps($userId, (string)$hostId);
                        if ($this->sitemapExistsInResponse($existing2, $sitemapUrl)) {
                            $this->wm->saveSitemapStatus($siteId, $rLabel, $sitemapUrl);
                            $log[] = "OK sitemap (405 but exists via GET): {$hostUrl} :: {$sitemapUrl}";
                            continue;
                        }
                    } catch (Throwable $e2) {
                        $log[] = "WARN sitemapGet after 405 failed: {$hostUrl} :: " . $e2->getMessage();
                    }
                }

                $log[] = "ERR sitemap (api): {$hostUrl} :: {$msg}";
            }
        }

    } catch (Throwable $e) {
        $log[] = "FATAL sitemap: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}

/**
 * Проверяет, есть ли нужный sitemapUrl в ответе getSitemaps().
 */
private function sitemapExistsInResponse(array $resp, string $sitemapUrl): bool
{
    $need = rtrim(trim($sitemapUrl), '/');

    // разные форматы: data.user_added_sitemaps / user_added_sitemaps / data.sitemaps / sitemaps
    $candidates = [];

    if (isset($resp['data']['user_added_sitemaps']) && is_array($resp['data']['user_added_sitemaps'])) {
        $candidates = $resp['data']['user_added_sitemaps'];
    } elseif (isset($resp['user_added_sitemaps']) && is_array($resp['user_added_sitemaps'])) {
        $candidates = $resp['user_added_sitemaps'];
    } elseif (isset($resp['data']['sitemaps']) && is_array($resp['data']['sitemaps'])) {
        $candidates = $resp['data']['sitemaps'];
    } elseif (isset($resp['sitemaps']) && is_array($resp['sitemaps'])) {
        $candidates = $resp['sitemaps'];
    }

    foreach ($candidates as $item) {
        if (!is_array($item)) continue;

        $u = '';
        if (isset($item['url'])) $u = (string)$item['url'];
        elseif (isset($item['sitemap_url'])) $u = (string)$item['sitemap_url'];

        $u = rtrim(trim($u), '/');
        if ($u !== '' && $u === $need) return true;
    }

    return false;
}

public function robotsConfirm()
{
    $siteId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($siteId <= 0) { http_response_code(400); echo "Bad site id"; return; }

    $label = (string)($_POST['label'] ?? 'ALL');
    $log = [];

    try {
        $desired = $this->wm->getDesiredHostsForSite($siteId);

        foreach ($desired as $h) {
            $rLabel  = (string)($h['label'] ?? '');
            $hostUrl = (string)($h['host_url'] ?? '');

            if ($label !== 'ALL' && $rLabel !== $label) continue;

            // robotsUrl (override поддерживаем)
            $override = trim((string)($_POST['robots_url'] ?? ''));
            $robotsUrl = $override !== '' ? $override : (rtrim($hostUrl, '/') . '/robots.txt');

            // HEAD-check robots.txt (без API Яндекса)
            $check = $this->httpHead($robotsUrl);

            $log[] = "ROBOTS CHECK: label={$rLabel} url={$robotsUrl} http={$check['http']} final={$check['final_url']}";
            if (!empty($check['err'])) {
                $log[] = "ROBOTS CURL ERR: " . $check['err'];
            }

            // считаем успехом 2xx/3xx (и редиректы тоже ок)
            if ($check['http'] >= 200 && $check['http'] < 400) {
                $this->wm->saveRobotsStatus($siteId, $rLabel, $robotsUrl);
                $log[] = "OK robots (http-check): {$hostUrl}";
            } else {
                $log[] = "ERR robots (http-check): {$hostUrl} :: http={$check['http']}";
            }
        }

    } catch (Throwable $e) {
        $log[] = "FATAL robots: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}

/**
 * HEAD запрос с редиректами, чтобы проверить доступность файла.
 * Возвращает http код и финальный URL.
 */
private function httpHead(string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['http' => 0, 'final_url' => $url, 'err' => 'curl_init failed'];
    }

    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    curl_exec($ch);

    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err   = (string)curl_error($ch);

    curl_close($ch);

    return ['http' => $http, 'final_url' => $final, 'err' => $err];
}

public function pagesUrls()
{
    $siteId = (int)($_GET['id'] ?? 0);
    $label  = (string)($_GET['label'] ?? '');

    if ($siteId <= 0) {
        http_response_code(400);
        echo "Bad site id";
        return;
    }

    try {
        header('Content-Type: text/plain; charset=utf-8');
        $txt = $this->wm->getPagesUrlsText($siteId, $label);
        echo $txt;
    } catch (Throwable $e) {
        http_response_code(500);
        echo "ERR: " . $e->getMessage();
    }
}

public function recrawlFromPages()
{
    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        http_response_code(400);
        echo "Bad site id";
        return;
    }

    $label = (string)($_POST['label'] ?? 'ALL');
    $log = [];

    try {
        $userId = $this->wm->getUserId();

        $labels = [];
        if ($label === 'ALL') {
            $desired = $this->wm->getDesiredHostsForSite($siteId);
            foreach ($desired as $h) {
                $labels[] = (string)($h['label'] ?? '');
            }
        } else {
            $labels[] = $label; // '' = root
        }

        foreach ($labels as $lb) {
            $hostId = $this->wm->getHostIdByLabel($siteId, $lb);
            if (!$hostId) {
                $log[] = "SKIP {$lb}: host_id empty";
                continue;
            }

            $rawUrlsText = $this->wm->getPagesUrlsText($siteId, $lb);
            $urls = $this->wm->normalizeRecrawlUrls($siteId, $lb, $rawUrlsText);

            if (!$urls) {
                $log[] = "SKIP {$lb}: no urls";
                continue;
            }

            $res = $this->wm->recrawlUrls($userId, (string)$hostId, $urls);
			$this->wm->saveRecrawlStatus($siteId, $lb, count($urls));
			$reset = $this->wm->disableRedirectAndResetIndexState($siteId, $lb);
            $log[] = "OK {$lb}: sent=" . count($urls);
            $log[] = "RESET {$lb}: status=" . (string)($reset['sync_status'] ?? '') . (!empty($reset['sync_context']) ? (' :: ' . (string)$reset['sync_context']) : '') . (!empty($reset['sync_error']) ? (' :: error=' . (string)$reset['sync_error']) : '');
        }

    } catch (Throwable $e) {
        $log[] = "FATAL recrawlFromPages: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}

public function sitemapGet()
{
    $siteId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($siteId <= 0) { http_response_code(400); echo "Bad site id"; return; }

    $label = trim((string)($_POST['label'] ?? 'ALL'));
    $log = [];

    try {
        $userId = $this->wm->getUserId();
        $rows = $this->wm->getWebmasterHostsRows($siteId);

        foreach ($rows as $r) {
            $rLabel = (string)($r['label'] ?? '');
            if ($label !== 'ALL' && $rLabel !== $label) continue;

            $hostId  = (string)($r['host_id'] ?? '');
            $hostUrl = (string)($r['host_url'] ?? '');
            if ($hostId === '') { $log[] = "SKIP sitemapGet: {$hostUrl} :: host_id empty"; continue; }

            $res = $this->wm->getSitemaps($userId, $hostId);
            $log[] = "OK sitemapGet: {$hostUrl} :: " . json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    } catch (Throwable $e) {
        $log[] = "FATAL sitemapGet: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}

public function robotsGet()
{
    $siteId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($siteId <= 0) { http_response_code(400); echo "Bad site id"; return; }

    $label = trim((string)($_POST['label'] ?? 'ALL'));
    $log = [];

    try {
        $userId = $this->wm->getUserId();
        $rows = $this->wm->getWebmasterHostsRows($siteId);

        foreach ($rows as $r) {
            $rLabel = (string)($r['label'] ?? '');
            if ($label !== 'ALL' && $rLabel !== $label) continue;

            $hostId  = (string)($r['host_id'] ?? '');
            $hostUrl = (string)($r['host_url'] ?? '');
            if ($hostId === '') { $log[] = "SKIP robotsGet: {$hostUrl} :: host_id empty"; continue; }

            $res = $this->wm->getRobots($userId, $hostId);
            $log[] = "OK robotsGet: {$hostUrl} :: " . json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    } catch (Throwable $e) {
        $log[] = "FATAL robotsGet: " . $e->getMessage();
    }

    $_SESSION['wm_log'] = $log;
    header("Location: /webmaster/site?id=" . $siteId);
    exit;
}





public function searchApi()
{
    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        http_response_code(400);
        echo 'Bad site id';
        return;
    }

    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT * FROM sites WHERE id = :id LIMIT 1");
        $st->execute([':id' => $siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });
    if (!$site) {
        http_response_code(404);
        echo 'Site not found';
        return;
    }

    $wm = new YandexWebmasterService();
    $searchApi = new YandexSearchApiService();
    $desiredHosts = $wm->getDesiredHostsForSite($siteId);
    $statuses = $searchApi->getStatusesForSite($siteId, $desiredHosts);
    $cronState = $searchApi->getCronState();
    $logTail = $searchApi->getLogTailForSite($siteId, 120);
    $diag = [];

    return $this->view('webmaster/search-api', [
        'site' => $site,
        'settings' => $searchApi->getSettings(),
        'statuses' => $statuses,
        'cronState' => $cronState,
        'logTail' => $logTail,
        'diag' => $diag,
        'desiredHosts' => $desiredHosts,
    ]);
}

public function searchApiRun()
{
    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        http_response_code(400);
        echo 'Bad site id';
        return;
    }

    $mode = (string)($_POST['mode'] ?? 'run_manual');
    $log = [];
    try {
        $service = new YandexSearchApiService();

        if ($mode === 'save_settings') {
            $service->saveSettings([
                'enabled' => (int)($_POST['enabled'] ?? 0),
                'endpoint_xml' => (string)($_POST['endpoint_xml'] ?? ''),
                'endpoint_json' => (string)($_POST['endpoint_json'] ?? ''),
                'user' => (string)($_POST['user'] ?? ''),
                'key' => (string)($_POST['key'] ?? ''),
                'query_interval_minutes' => (int)($_POST['query_interval_minutes'] ?? 30),
                'recheck_after_detect_minutes' => (int)($_POST['recheck_after_detect_minutes'] ?? 1440),
                'max_pages_per_run' => (int)($_POST['max_pages_per_run'] ?? 1),
            ]);
            $log[] = 'Настройки XMLStock сохранены.';
            $repair = $service->repairRedirectsForIndexedSite($siteId, 'ALL');
            foreach ($repair as $item) {
                $log[] = 'REPAIR ' . (($item['label'] ?? '') === '' ? 'root' : (string)$item['label']) . ' :: ' . (string)($item['host_url'] ?? '') . ' :: ' . (string)($item['status'] ?? '');
                if (!empty($item['sync']['error'])) {
                    $log[] = '  sync_error: ' . (string)$item['sync']['error'];
                } elseif (!empty($item['sync']['uploaded'])) {
                    $log[] = '  sync_uploaded: ' . implode(', ', (array)$item['sync']['uploaded']);
                }
            }
        } else {
            $label = (string)($_POST['label'] ?? 'ALL');
            $results = $service->runManualCheck($siteId, $label !== '' ? $label : 'ALL');
            foreach ($results as $item) {
                $log[] = 'SEARCH API ' . (($item['label'] ?? '') === '' ? 'root' : (string)$item['label']) . ' :: ' . (string)($item['host_url'] ?? '') . ' :: ' . (string)($item['status'] ?? '');
                if (!empty($item['error'])) {
                    $log[] = '  error: ' . (string)$item['error'];
                }
                if (!empty($item['found_urls'])) {
                    $log[] = '  urls=' . count((array)$item['found_urls']);
                }
                if (!empty($item['redirect_changed'])) {
                    $log[] = '  redirect_enabled=1';
                    if (!empty($item['sync']['error'])) {
                        $log[] = '  sync_error: ' . (string)$item['sync']['error'];
                    } elseif (!empty($item['sync']['uploaded'])) {
                        $log[] = '  sync_uploaded: ' . implode(', ', (array)$item['sync']['uploaded']);
                    }
                }
            }
            $repair = $service->repairRedirectsForIndexedSite($siteId, $label !== '' ? $label : 'ALL');
            foreach ($repair as $item) {
                $log[] = 'REPAIR ' . (($item['label'] ?? '') === '' ? 'root' : (string)$item['label']) . ' :: ' . (string)($item['host_url'] ?? '') . ' :: ' . (string)($item['status'] ?? '');
                if (!empty($item['sync']['error'])) {
                    $log[] = '  sync_error: ' . (string)$item['sync']['error'];
                } elseif (!empty($item['sync']['uploaded'])) {
                    $log[] = '  sync_uploaded: ' . implode(', ', (array)$item['sync']['uploaded']);
                }
            }
        }
    } catch (Throwable $e) {
        $log[] = 'FATAL search-api: ' . $e->getMessage();
    }

    $_SESSION['wm_log'] = array_merge($_SESSION['wm_log'] ?? [], $log);
    header('Location: /webmaster/search-api?id=' . $siteId);
    exit;
}

public function techIndex()
{
    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        http_response_code(400);
        echo 'Bad site id';
        return;
    }

    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT * FROM sites WHERE id = :id LIMIT 1");
        $st->execute([':id' => $siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });
    if (!$site) {
        http_response_code(404);
        echo 'Site not found';
        return;
    }

    $targetLabel = (string)($_GET['label'] ?? 'ALL');
    $desiredHosts = $this->wm->getDesiredHostsForSite($siteId);
    $diag = [];
    $error = '';
    try {
        $diag = (new YandexIndexWatchService())->runTechDiagnostics($siteId, $targetLabel !== '' ? $targetLabel : 'ALL');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    return $this->view('webmaster/index-tech', [
        'site' => $site,
        'targetLabel' => $targetLabel,
        'diag' => $diag,
        'desiredHosts' => $desiredHosts,
        'error' => $error,
    ]);
}



private function sendVerifiedTelegram(int $siteId, string $label, string $hostUrl): void
{
    try {
        require_once Paths::appRoot() . '/app/Services/TelegramService.php';

        $safeHost = htmlspecialchars($hostUrl, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label === '' ? '_default' : $label, ENT_QUOTES, 'UTF-8');

        $text  = "✅ <b>Яндекс подтвердил хост</b>\n";
        $text .= "Сайт ID: <b>{$siteId}</b>\n";
        $text .= "Label: <b>{$safeLabel}</b>\n";
        $text .= "Host: <b>{$safeHost}</b>\n";
        $text .= "Панель: https://hub.seotop-one.ru/webmaster/site?id={$siteId}";

        $tg = new TelegramService();
        $tg->send($text, 'manual_sync');
    } catch (Throwable $e) {
        @error_log('[TG webmaster verify] ' . $e->getMessage());
    }
}

public function cron()
{
    header('Content-Type: application/json; charset=utf-8');

    $checked = 0;
    $verified = 0;
    $notified = 0;
    $errors = [];

    try {
        $userId = $this->wm->getUserId();
        $tg = new TelegramService();

        $rows = DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->prepare("
                SELECT
                    wh.id,
                    wh.site_id,
                    wh.label,
                    wh.host_url,
                    wh.host_id,
                    wh.verified_at,
                    wh.verified_notified_at,
                    s.domain
                FROM webmaster_hosts wh
                INNER JOIN sites s ON s.id = wh.site_id
                WHERE wh.host_id IS NOT NULL
                  AND wh.host_id <> ''
                  AND wh.verified_notified_at IS NULL
                ORDER BY COALESCE(wh.last_sync_at, '1970-01-01 00:00:00') ASC, wh.id ASC
                LIMIT 50
            ");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });

        foreach ($rows as $r) {
            $checked++;

            $id      = (int)($r['id'] ?? 0);
            $siteId  = (int)($r['site_id'] ?? 0);
            $label   = (string)($r['label'] ?? '');
            $hostUrl = trim((string)($r['host_url'] ?? ''));
            $hostId  = trim((string)($r['host_id'] ?? ''));
            $domain  = trim((string)($r['domain'] ?? ''));

            if ($id <= 0 || $siteId <= 0 || $hostId === '') {
                continue;
            }

            try {
                $chk = $this->wm->checkVerification($userId, $hostId);

                $state = '';
                if (isset($chk['verification_state'])) {
                    $state = (string)$chk['verification_state'];
                } elseif (isset($chk['data']['verification_state'])) {
                    $state = (string)$chk['data']['verification_state'];
                }

                DB::withReconnect(function(PDO $pdo) use ($id) {
                    $st = $pdo->prepare("
                        UPDATE webmaster_hosts
                        SET last_sync_at = NOW(),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                        LIMIT 1
                    ");
                    $st->execute([$id]);
                });

                if ($state === 'VERIFIED' || $state === 'SUCCESS') {
                    DB::withReconnect(function(PDO $pdo) use ($id) {
                        $st = $pdo->prepare("
                            UPDATE webmaster_hosts
                            SET verified_at = COALESCE(verified_at, NOW()),
                                last_sync_at = NOW(),
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                            LIMIT 1
                        ");
                        $st->execute([$id]);
                    });

                    $verified++;

                    $safeDomain = htmlspecialchars($domain, ENT_QUOTES, 'UTF-8');
                    $safeHost   = htmlspecialchars($hostUrl, ENT_QUOTES, 'UTF-8');

                    $labelText = ($label !== '' && $label !== '_default')
                        ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                        : 'root';

                    $msg = "✅ <b>Яндекс подтвердил хост</b>\n"
                         . "Сайт: <code>{$safeDomain}</code>\n"
                         . "Хост: <code>{$safeHost}</code>\n"
                         . "Label: <code>{$labelText}</code>";

                    $sent = $tg->send($msg);

                    if ($sent) {
                        DB::withReconnect(function(PDO $pdo) use ($id) {
                            $st = $pdo->prepare("
                                UPDATE webmaster_hosts
                                SET verified_notified_at = NOW(),
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE id = ?
                                LIMIT 1
                            ");
                            $st->execute([$id]);
                        });

                        $notified++;
                    }
                }

            } catch (Throwable $e) {
                $errors[] = [
                    'id' => $id,
                    'host' => $hostUrl,
                    'error' => $e->getMessage(),
                ];

                @error_log('[WM cron] host=' . $hostUrl . ' err=' . $e->getMessage());
            }
        }

        $indexWatch = (new YandexIndexWatchService())->runCron(25);
        $searchApiWatch = (new YandexSearchApiService())->runCron(25);
        $this->saveCronState(true, $checked, $verified, $notified, count($errors), '');

        echo json_encode([
            'ok' => true,
            'verification' => [
                'checked' => $checked,
                'verified' => $verified,
                'notified' => $notified,
                'errors' => $errors,
            ],
            'index_watch' => $indexWatch,
            'search_api_watch' => $searchApiWatch,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;

    } catch (Throwable $e) {
        $this->saveCronState(false, $checked, $verified, $notified, count($errors) + 1, $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
}


private function ensureCronStateTable(): void
{
    DB::withReconnect(function(PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS webmaster_cron_state (
                id INT NOT NULL PRIMARY KEY,
                last_run_at DATETIME DEFAULT NULL,
                last_ok TINYINT(1) NOT NULL DEFAULT 0,
                last_checked INT NOT NULL DEFAULT 0,
                last_verified INT NOT NULL DEFAULT 0,
                last_notified INT NOT NULL DEFAULT 0,
                last_errors INT NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    });
}

private function getCronState(): array
{
    $this->ensureCronStateTable();

    return DB::withReconnect(function(PDO $pdo) {
        $st = $pdo->prepare("SELECT * FROM webmaster_cron_state WHERE id = 1 LIMIT 1");
        $st->execute();
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    });
}

private function saveCronState(bool $ok, int $checked, int $verified, int $notified, int $errorsCount, string $errorMessage = ''): void
{
    $this->ensureCronStateTable();

    DB::withReconnect(function(PDO $pdo) use ($ok, $checked, $verified, $notified, $errorsCount, $errorMessage) {
        $st = $pdo->prepare("
            INSERT INTO webmaster_cron_state
                (id, last_run_at, last_ok, last_checked, last_verified, last_notified, last_errors, last_error, updated_at)
            VALUES
                (1, NOW(), :last_ok, :last_checked, :last_verified, :last_notified, :last_errors, :last_error, NOW())
            ON DUPLICATE KEY UPDATE
                last_run_at = VALUES(last_run_at),
                last_ok = VALUES(last_ok),
                last_checked = VALUES(last_checked),
                last_verified = VALUES(last_verified),
                last_notified = VALUES(last_notified),
                last_errors = VALUES(last_errors),
                last_error = VALUES(last_error),
                updated_at = NOW()
        ");
        $st->execute([
            ':last_ok' => $ok ? 1 : 0,
            ':last_checked' => $checked,
            ':last_verified' => $verified,
            ':last_notified' => $notified,
            ':last_errors' => $errorsCount,
            ':last_error' => $errorMessage !== '' ? $errorMessage : null,
        ]);
    });
}

private function getIndexWatchLogTailForSite(int $siteId, int $limit = 120): array
{
    $limit = max(1, min($limit, 400));
    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare('SELECT domain FROM sites WHERE id=? LIMIT 1');
        $st->execute([$siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });

    $domain = trim((string)($site['domain'] ?? ''));
    $needles = [];
    if ($domain !== '') {
        $needles[] = 'site=' . $siteId;
        $needles[] = $domain;
        $rows = $this->wm->getDesiredHostsForSite($siteId);
        foreach ($rows as $row) {
            $u = trim((string)($row['host_url'] ?? ''));
            if ($u !== '') {
                $needles[] = preg_replace('~^https?://~i', '', $u);
            }
        }
    }

    $file = Paths::storage('logs/yandex_index_watch.log');
    if (!is_file($file)) {
        return [];
    }

    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return [];
    }

    $filtered = [];
    while (($line = fgets($fh)) !== false) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        $matched = false;
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($line, $needle) !== false) {
                $matched = true;
                break;
            }
        }
        if ($matched || empty($needles)) {
            $filtered[] = $line;
            if (count($filtered) > $limit) {
                array_shift($filtered);
            }
        }
    }
    fclose($fh);

    return $filtered;
}

}
