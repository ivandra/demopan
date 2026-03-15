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

        return $this->view('webmaster/site', [
            'site' => $site,
            'desired' => $desired,
            'rowMap' => $rowMap,
        ]);
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

		$log[] = "OK recrawl: label={$label}, urls=" . count($urls);
		$log[] = "RESULT: sent={$res['sent']} success={$res['success']} failed={$res['failed']}";
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
            $log[] = "OK {$lb}: sent=" . count($urls);
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
        $tg->send($text);
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

        echo json_encode([
            'ok' => true,
            'checked' => $checked,
            'verified' => $verified,
            'notified' => $notified,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
}
	
}
