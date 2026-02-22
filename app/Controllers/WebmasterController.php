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
                    $this->wm->markVerified($siteId, $label);
                    $log[] = "OK verify: {$hostUrl} :: " . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
}
