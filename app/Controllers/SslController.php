<?php

class SslController extends Controller
{
    /* -------------------- helpers -------------------- */

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) $this->redirect('/login');
    }

    private function h(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES);
    }

    private function normalizeDomain(string $input): string
    {
        $input = trim($input);
        if ($input === '') return '';

        $host = parse_url($input, PHP_URL_HOST);
        if ($host) {
            $host = (string)$host;
        } else {
            $host = preg_replace('~^https?://~i', '', $input);
            $host = preg_replace('~/.*$~', '', $host);
        }

        $host = trim((string)$host);
        $host = preg_replace('~:\d+$~', '', $host);
        $host = rtrim($host, '.');
        $host = strtolower($host);

        if ($host === '') return '';
        if (!preg_match('~^[a-z0-9][a-z0-9\.\-]*[a-z0-9]$~', $host)) return '';

        return $host;
    }

    private function loadSite(int $siteId): array
    {
        $st = DB::pdo()->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) die('site not found');
        return $row;
    }

    /**
     * Возвращает список доменов для site_id:
     * - root: label = '' => domain
     * - subs: label = 'xxx' => xxx.domain (только enabled=1, label <> '_default')
     *
     * Формат:
     * [
     *   ['label' => '', 'domain' => 'example.com', 'fqdn' => 'example.com'],
     *   ['label' => 'ru', 'domain' => 'example.com', 'fqdn' => 'ru.example.com'],
     * ]
     */
    private function getSiteDomains(int $siteId): array
    {
        $site = $this->loadSite($siteId);
        $root = $this->normalizeDomain((string)($site['domain'] ?? ''));
        if ($root === '') return [];

        $out = [];
        $out[] = ['label' => '', 'domain' => $root, 'fqdn' => $root];

        $st = DB::pdo()->prepare("
            SELECT label
            FROM site_subdomains
            WHERE site_id=?
              AND enabled=1
            ORDER BY label ASC
        ");
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $r) {
            $lb = strtolower(trim((string)($r['label'] ?? '')));
            if ($lb === '' || $lb === '_default') continue;
            $fqdn = $lb . '.' . $root;
            $out[] = ['label' => $lb, 'domain' => $root, 'fqdn' => $fqdn];
        }

        // unique
        $seen = [];
        $uniq = [];
        foreach ($out as $x) {
            $k = (string)$x['label'] . '|' . (string)$x['fqdn'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $uniq[] = $x;
        }

        return $uniq;
    }

    private function upsertSslChecksForSite(int $siteId, array $siteDomains): void
    {
        if (empty($siteDomains)) return;

        DB::withReconnect(function (PDO $pdo) use ($siteId, $siteDomains) {
            $ins = $pdo->prepare("
                INSERT INTO ssl_checks(site_id,label,domain,enabled,notify_tg,updated_at)
                VALUES(:site_id,:label,:domain,1,0,NULL)
                ON DUPLICATE KEY UPDATE
                  domain=VALUES(domain),
                  enabled=1
            ");

            foreach ($siteDomains as $d) {
                $label = (string)($d['label'] ?? '');
                $fqdn  = (string)($d['fqdn'] ?? '');
                if ($fqdn === '') continue;

                $ins->execute([
                    ':site_id' => $siteId,
                    ':label'   => $label,
                    ':domain'  => $fqdn,
                ]);
            }
        });
    }

    private function fetchSslChecksMapForSite(int $siteId): array
    {
        $rows = DB::pdo()->prepare("
            SELECT *
            FROM ssl_checks
            WHERE site_id=?
            ORDER BY label ASC
        ");
        $rows->execute([$siteId]);
        $all = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($all as $r) {
            $label = (string)($r['label'] ?? '');
            $map[$label] = $r;
        }
        return $map;
    }

    private function computeCronStatus(array $checksRows): array
    {
        // "статус крона" без отдельной таблицы:
        // считаем, что крон жив, если есть обновления <= 3 минут
        $last = '';
        foreach ($checksRows as $r) {
            $u = (string)($r['updated_at'] ?? '');
            if ($u !== '' && ($last === '' || $u > $last)) $last = $u;
        }

        $alive = false;
        if ($last !== '') {
            $ts = strtotime($last);
            if ($ts !== false) {
                $alive = (time() - $ts) <= 180; // 3 минуты
            }
        }

        return [
            'alive' => $alive,
            'last'  => $last,
        ];
    }

    /* -------------------- actions -------------------- */

    // GET /ssl (твой общий список)
    public function index(): void
    {
        $this->requireAuth();

        $pdo = DB::pdo();

        $rows = $pdo->query("
            SELECT *
            FROM ssl_checks
            ORDER BY enabled DESC, COALESCE(updated_at,'1970-01-01') DESC
            LIMIT 500
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('Content-Type: text/html; charset=utf-8');

        echo '<h2>Пинги для выпуска SSL</h2>';

        echo '<form method="post" action="/ssl/add" style="display:flex;gap:10px;align-items:center;margin-bottom:12px;">';
        echo '<input name="domain" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:8px;" placeholder="primer.ru или www.primer.ru">';
        echo '<button type="submit" style="padding:10px 16px;border-radius:8px;border:0;background:#2f80ed;color:#fff;">Добавить</button>';
        echo '<button type="submit" formaction="/ssl/delete-selected" formmethod="post" '
            . 'style="padding:10px 16px;border-radius:8px;border:1px solid #ffb3b3;background:#fff;color:#ff6b6b;" '
            . 'onclick="return confirm(\'Удалить выбранные?\')">Удалить выбранные</button>';
        echo '</form>';

        echo '<div style="color:#666;margin-bottom:14px;">Крон раз в минуту дергает /ssl/cron и проверяет HTTP и TLS на 443.</div>';

        echo '<form method="post" action="/ssl/delete-selected">';
        echo '<table border="0" cellpadding="10" cellspacing="0" style="width:100%;border-collapse:collapse;">';
        echo '<tr style="border-bottom:1px solid #eee;">'
            . '<th style="text-align:left;width:30px;"><input type="checkbox" onclick="document.querySelectorAll(\'.sslcb\').forEach(x=>x.checked=this.checked)"></th>'
            . '<th style="text-align:left;">Домен</th>'
            . '<th style="text-align:left;">Статус</th>'
            . '<th style="text-align:left;">HTTP</th>'
            . '<th style="text-align:left;">SSL</th>'
            . '<th style="text-align:left;">Обновлено</th>'
            . '<th style="text-align:left;">Действия</th>'
            . '</tr>';

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $domain = (string)($r['domain'] ?? '');
            $enabled = (int)($r['enabled'] ?? 0) === 1;
            $notify = (int)($r['notify_tg'] ?? 0) === 1;

            $http = (int)($r['http_code'] ?? 0);
            $httpsOk = (int)($r['https_ok'] ?? 0) === 1;

            $expires = (string)($r['ssl_expires_at'] ?? '');
            $issuer  = (string)($r['ssl_issuer'] ?? '');
            $subject = (string)($r['ssl_subject'] ?? '');
            $err     = (string)($r['ssl_error'] ?? '');
            $updated = (string)($r['updated_at'] ?? '');

            $statusBadge = $enabled
                ? '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#e8fff1;color:#0b6b3a;font-size:12px;">running</span>'
                : '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#f2f2f2;color:#666;font-size:12px;">stopped</span>';

            $sslBadge = $httpsOk
                ? '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#e8fff1;color:#0b6b3a;font-size:12px;">OK</span>'
                : '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#ffecec;color:#9b1c1c;font-size:12px;">FAIL</span>';

            $sslDetails = '';
            if ($httpsOk) {
                $sslDetails .= '<div style="color:#777;font-size:12px;margin-top:4px;">' . $this->h($expires) . '</div>';
                if ($issuer !== '')  $sslDetails .= '<div style="color:#888;font-size:12px;">issuer: ' . $this->h($issuer) . '</div>';
                if ($subject !== '') $sslDetails .= '<div style="color:#888;font-size:12px;">subject: ' . $this->h($subject) . '</div>';
            } else {
                if ($err !== '') $sslDetails .= '<div style="color:#c00;font-size:12px;margin-top:4px;">' . $this->h($err) . '</div>';
            }

            echo '<tr style="border-bottom:1px solid #f3f3f3;">';

            echo '<td><input class="sslcb" type="checkbox" name="ids[]" value="' . $id . '"></td>';
            echo '<td style="font-weight:600;">' . $this->h($domain) . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '<td>' . ($http > 0 ? (string)$http : '—') . '</td>';
            echo '<td>' . $sslBadge . $sslDetails . '</td>';
            echo '<td style="color:#666;">' . $this->h($updated) . '</td>';

            echo '<td style="white-space:nowrap;">';

            echo '<form method="post" action="/ssl/toggle" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" style="padding:8px 12px;border-radius:8px;border:0;'
                . ($enabled ? 'background:#eb5757;color:#fff;' : 'background:#27ae60;color:#fff;')
                . 'margin-right:6px;">'
                . ($enabled ? 'Стоп' : 'Старт')
                . '</button>'
                . '</form>';

            echo '<form method="post" action="/ssl/notify" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#fff;'
                . 'margin-right:6px;">'
                . ($notify ? 'TG: ON' : 'TG: OFF')
                . '</button>'
                . '</form>';

            echo '<form method="post" action="/ssl/delete" style="display:inline;" onsubmit="return confirm(\'Удалить домен?\')">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" style="padding:8px 12px;border-radius:8px;border:0;background:#eb5757;color:#fff;">Удалить</button>'
                . '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</form>';
    }

    // GET /ssl/site?id=123  (страница по сайту: root + enabled subs)
    public function site(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? ($_GET['site_id'] ?? 0));
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);

        $siteDomains = $this->getSiteDomains($siteId);

        // гарантируем, что записи ssl_checks существуют и enabled=1
        $this->upsertSslChecksForSite($siteId, $siteDomains);

        $map = $this->fetchSslChecksMapForSite($siteId);

        // собираем rows в порядке: root + subs
        $rows = [];
        $allOk = true;
        $lastUpdated = '';

        foreach ($siteDomains as $d) {
            $label = (string)($d['label'] ?? '');
            $fqdn  = (string)($d['fqdn'] ?? '');

            $r = $map[$label] ?? null;

            $enabled = $r ? ((int)($r['enabled'] ?? 0) === 1) : false;
            $httpsOk = $r ? ((int)($r['https_ok'] ?? 0) === 1) : false;
            $http    = $r ? (int)($r['http_code'] ?? 0) : 0;
            $upd     = $r ? (string)($r['updated_at'] ?? '') : '';

            if ($upd !== '' && ($lastUpdated === '' || $upd > $lastUpdated)) $lastUpdated = $upd;

            // ALL OK = у всех enabled=1 и https_ok=1
            if (!($enabled && $httpsOk)) $allOk = false;

            $rows[] = [
                'label' => $label,
                'fqdn'  => $fqdn,
                'row'   => $r,
                'enabled' => $enabled,
                'https_ok' => $httpsOk,
                'http_code' => $http,
                'updated_at' => $upd,
            ];
        }

        $cron = $this->computeCronStatus(array_map(fn($x) => $x['row'] ?: [], $rows));

        // View (в /app/Views/ssl/site.php)
        $this->view('ssl/site', [
            'site' => $site,
            'siteId' => $siteId,
            'rows' => $rows,
            'allOk' => $allOk,
            'lastUpdated' => $lastUpdated,
            'cronAlive' => (bool)($cron['alive'] ?? false),
            'cronLast' => (string)($cron['last'] ?? ''),
        ]);
    }

    // POST /ssl/site/check-now?id=123  (принудительная проверка только доменов site_id)
    public function siteCheckNow(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? ($_GET['site_id'] ?? 0));
        if ($siteId <= 0) die('bad id');

        try {
            $svc = new SslCheckService();

            if (method_exists($svc, 'upsertTargetsForSiteFromDb')) {
                $svc->upsertTargetsForSiteFromDb($siteId, true);
            } elseif (method_exists($svc, 'syncTargetsFromDb')) {
                $svc->syncTargetsFromDb($siteId, true);
            } else {
                $siteDomains = $this->getSiteDomains($siteId);
                $this->upsertSslChecksForSite($siteId, $siteDomains);
            }

            $rows = DB::withReconnect(function(PDO $pdo) use ($siteId) {
                $st = $pdo->prepare("
                    SELECT id, domain
                    FROM ssl_checks
                    WHERE site_id = ?
                      AND enabled = 1
                    ORDER BY COALESCE(updated_at, '1970-01-01 00:00:00') ASC, id ASC
                    LIMIT 300
                " );
                $st->execute([$siteId]);
                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            });

            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                $domain = trim((string)($r['domain'] ?? ''));
                if ($id <= 0 || $domain === '') {
                    continue;
                }

                try {
                    $res = $svc->checkDomain($domain);

                    DB::withReconnect(function(PDO $pdo) use ($id, $res) {
                        $st = $pdo->prepare("
                            UPDATE ssl_checks
                            SET
                              http_code = :http_code,
                              https_ok = :https_ok,
                              ssl_error = :ssl_error,
                              ssl_expires_at = :expires,
                              ssl_issuer = :issuer,
                              ssl_subject = :subject,
                              updated_at = NOW()
                            WHERE id = :id
                            LIMIT 1
                        " );
                        $st->execute([
                            ':http_code' => (int)($res['http_code'] ?? 0),
                            ':https_ok' => (int)($res['https_ok'] ?? 0),
                            ':ssl_error' => (string)($res['ssl_error'] ?? ''),
                            ':expires' => $res['ssl_expires_at'] ?? null,
                            ':issuer' => $res['ssl_issuer'] ?? null,
                            ':subject' => $res['ssl_subject'] ?? null,
                            ':id' => $id,
                        ]);
                    });
                } catch (Throwable $e) {
                    DB::withReconnect(function(PDO $pdo) use ($id, $e) {
                        $st = $pdo->prepare("
                            UPDATE ssl_checks
                            SET https_ok = 0, ssl_error = :err, updated_at = NOW()
                            WHERE id = :id
                            LIMIT 1
                        " );
                        $st->execute([
                            ':err' => $e->getMessage(),
                            ':id' => $id,
                        ]);
                    });
                }
            }
        } catch (Throwable $e) {
            @error_log('[ssl siteCheckNow] site_id=' . $siteId . ' err=' . $e->getMessage());
            $_SESSION['wm_log'][] = 'SSL check error: ' . $e->getMessage();
        }

        $this->redirect('/ssl/site?id=' . $siteId);
    }

    /* -------------------- CRUD (твой общий список) -------------------- */

    public function add(): void
    {
        $this->requireAuth();

        $domain = $this->normalizeDomain((string)($_POST['domain'] ?? ''));
        if ($domain === '') {
            $_SESSION['wm_log'][] = 'bad domain';
            $this->redirect('/ssl');
        }

        DB::withReconnect(function(PDO $pdo) use ($domain) {
            $st = $pdo->prepare("
                INSERT INTO ssl_checks(site_id,label,domain,enabled,notify_tg,updated_at)
                VALUES(0,:label,:domain,1,0,NULL)
                ON DUPLICATE KEY UPDATE
                  domain=VALUES(domain),
                  enabled=1
            ");
            $st->execute([
                ':label' => $domain,
                ':domain' => $domain,
            ]);
        });

        $this->redirect('/ssl');
    }

    public function toggle(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->redirect('/ssl');

        DB::withReconnect(function(PDO $pdo) use ($id) {
            $pdo->prepare("
                UPDATE ssl_checks
                SET enabled = IF(enabled=1,0,1)
                WHERE id = ?
                LIMIT 1
            ")->execute([$id]);
        });

        $this->redirect('/ssl');
    }

    public function notify(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->redirect('/ssl');

        DB::withReconnect(function(PDO $pdo) use ($id) {
            $pdo->prepare("
                UPDATE ssl_checks
                SET notify_tg = IF(notify_tg=1,0,1)
                WHERE id = ?
                LIMIT 1
            ")->execute([$id]);
        });

        $this->redirect('/ssl');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->redirect('/ssl');

        DB::withReconnect(function(PDO $pdo) use ($id) {
            $pdo->prepare("DELETE FROM ssl_checks WHERE id=? LIMIT 1")->execute([$id]);
        });

        $this->redirect('/ssl');
    }

    public function deleteSelected(): void
    {
        $this->requireAuth();

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) $this->redirect('/ssl');

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($ids)) $this->redirect('/ssl');

        DB::withReconnect(function(PDO $pdo) use ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM ssl_checks WHERE id IN ($in)")->execute($ids);
        });

        $this->redirect('/ssl');
    }

    // GET /ssl/cron
    public function cron(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $svc = new SslCheckService();
            $res = $svc->runOneCycle(50);

            $tg = new TelegramService();

            $toNotify = DB::withReconnect(function(PDO $pdo) {
                $st = $pdo->prepare("
                    SELECT id, domain, ssl_expires_at
                    FROM ssl_checks
                    WHERE notify_tg=1
                      AND https_ok=1
                      AND notified_at IS NULL
                      AND updated_at >= (NOW() - INTERVAL 10 MINUTE)
                    ORDER BY updated_at DESC
                    LIMIT 50
                ");
                $st->execute();
                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            });

            foreach ($toNotify as $r) {
                $id = (int)$r['id'];
                $domain = (string)$r['domain'];
                $exp = (string)($r['ssl_expires_at'] ?? '');

                $msg = "✅ SSL OK: <b>" . $this->h($domain) . "</b>";
                if ($exp !== '') $msg .= "\nexpires: <code>" . $this->h($exp) . "</code>";

                $sent = $tg->send($msg, 'ssl_ok');

                if ($sent) {
                    DB::withReconnect(function(PDO $pdo) use ($id) {
                        $pdo->prepare("UPDATE ssl_checks SET notified_at=NOW() WHERE id=? LIMIT 1")->execute([$id]);
                    });
                }
            }

            echo json_encode([
                'ok' => true,
                'checked' => (int)($res['checked'] ?? 0),
                'errors' => (int)($res['errors'] ?? 0),
                'notified' => count($toNotify),
            ], JSON_UNESCAPED_UNICODE);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
	
// POST /ssl/check-now?id=123
public function checkNow(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? ($_GET['site_id'] ?? 0));
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $svc = new SslCheckService();

    // 1) синхронизируем targets из БД (root + enabled=1 subdomains) и выключаем лишнее
    $svc->syncTargetsFromDb($siteId, true);

    $checked = 0;
    $errors  = 0;

    // 2) проверяем домены этого site_id прямо сейчас
    $rows = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("
            SELECT id, domain
            FROM ssl_checks
            WHERE site_id = ?
              AND enabled = 1
            ORDER BY COALESCE(updated_at,'1970-01-01') ASC
            LIMIT 300
        ");
        $st->execute([$siteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    });

    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        $domain = trim((string)($r['domain'] ?? ''));
        if ($id <= 0 || $domain === '') continue;

        try {
            $res = $svc->checkDomain($domain);

            DB::withReconnect(function(PDO $pdo) use ($id, $res) {
                $st = $pdo->prepare("
                    UPDATE ssl_checks
                    SET
                      http_code      = :http_code,
                      https_ok       = :https_ok,
                      ssl_error      = :ssl_error,
                      ssl_expires_at = :expires,
                      ssl_issuer     = :issuer,
                      ssl_subject    = :subject,
                      updated_at     = NOW(),
                      notified_at    = NULL
                    WHERE id = :id
                    LIMIT 1
                ");
                $st->execute([
                    ':http_code' => (int)($res['http_code'] ?? 0),
                    ':https_ok'  => (int)($res['https_ok'] ?? 0),
                    ':ssl_error' => (string)($res['ssl_error'] ?? ''),
                    ':expires'   => $res['ssl_expires_at'] ?? null,
                    ':issuer'    => $res['ssl_issuer'] ?? null,
                    ':subject'   => $res['ssl_subject'] ?? null,
                    ':id'        => $id,
                ]);
            });

            $checked++;
        } catch (Throwable $e) {
            $errors++;

            DB::withReconnect(function(PDO $pdo) use ($id, $e) {
                $st = $pdo->prepare("
                    UPDATE ssl_checks
                    SET https_ok=0,
                        ssl_error=:err,
                        updated_at=NOW()
                    WHERE id=:id
                    LIMIT 1
                ");
                $st->execute([
                    ':err' => $e->getMessage(),
                    ':id'  => $id,
                ]);
            });
        }
    }

    $_SESSION['wm_log'][] = "SSL checkNow: site_id={$siteId} checked={$checked} errors={$errors}";
    $this->redirect('/ssl/site?id=' . $siteId);
}

// GET /ssl/settings

private function ensureTelegramSettingsColumns(): void
{
    $tg = new TelegramService();
    $tg->ensureNotificationColumns();
}

public function settings(): void
{
    $this->requireAuth();
    $this->ensureTelegramSettingsColumns();

    $row = DB::withReconnect(function(PDO $pdo) {
        $st = $pdo->query("SELECT * FROM webmaster_settings ORDER BY id ASC LIMIT 1");
        return $st ? ($st->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    });

    $this->view('ssl/settings', [
        'row' => $row,
    ]);
}

// POST /ssl/settings
public function settingsSave(): void
{
    $this->requireAuth();
    $this->ensureTelegramSettingsColumns();

    $token = trim((string)($_POST['tg_bot_token'] ?? ''));
    $chat  = trim((string)($_POST['tg_chat_id'] ?? ''));

    $notifySslOk = isset($_POST['tg_notify_ssl_ok']) ? 1 : 0;
    $notifyXmlStockDetected = isset($_POST['tg_notify_xmlstock_detected']) ? 1 : 0;
    $notifyRedirectEnabled = isset($_POST['tg_notify_redirect_enabled']) ? 1 : 0;
    $notifyRedirectDisabled = isset($_POST['tg_notify_redirect_disabled']) ? 1 : 0;
    $notifyManualSync = isset($_POST['tg_notify_manual_sync']) ? 1 : 0;

    DB::withReconnect(function(PDO $pdo) use ($token, $chat, $notifySslOk, $notifyXmlStockDetected, $notifyRedirectEnabled, $notifyRedirectDisabled, $notifyManualSync) {

        $id = (int)($pdo->query("SELECT id FROM webmaster_settings ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

        if ($id > 0) {
            $st = $pdo->prepare("UPDATE webmaster_settings
                SET tg_bot_token=?,
                    tg_chat_id=?,
                    tg_notify_ssl_ok=?,
                    tg_notify_xmlstock_detected=?,
                    tg_notify_redirect_enabled=?,
                    tg_notify_redirect_disabled=?,
                    tg_notify_manual_sync=?
                WHERE id=?
                LIMIT 1");
            $st->execute([$token, $chat, $notifySslOk, $notifyXmlStockDetected, $notifyRedirectEnabled, $notifyRedirectDisabled, $notifyManualSync, $id]);
        } else {
            $st = $pdo->prepare("INSERT INTO webmaster_settings
                (tg_bot_token, tg_chat_id, tg_notify_ssl_ok, tg_notify_xmlstock_detected, tg_notify_redirect_enabled, tg_notify_redirect_disabled, tg_notify_manual_sync)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $st->execute([$token, $chat, $notifySslOk, $notifyXmlStockDetected, $notifyRedirectEnabled, $notifyRedirectDisabled, $notifyManualSync]);
        }
    });

    $_SESSION['wm_log'][] = 'TG settings saved';
    $this->redirect('/ssl/settings');
}

}