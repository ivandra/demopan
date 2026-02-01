<?php

class SiteController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    private function appRoot(): string
    {
        if (defined('APP_ROOT')) return rtrim(APP_ROOT, '/\\');
        // fallback: /public_html/app/Controllers -> ../../ = /public_html
        $root = realpath(__DIR__ . '/../../');
        return rtrim($root ?: (__DIR__ . '/../../'), '/\\');
    }

    private function storageRoot(): string
    {
        if (defined('STORAGE_ROOT')) return rtrim(STORAGE_ROOT, '/\\');
        return $this->appRoot() . '/storage';
    }

    private function log(string $msg, array $ctx = []): void
    {
        if (function_exists('hub_log')) {
            hub_log($msg, $ctx);
        }
    }

    // ----------------------------
    // Index (list sites)
    // ----------------------------
    public function index(): void
    {
        $this->requireAuth();

        $sites = DB::pdo()->query('SELECT * FROM sites ORDER BY id DESC')->fetchAll();

        $sslMap = $this->fetchSslStatusForSites($sites);

        foreach ($sites as &$s) {
            $id = (int)($s['id'] ?? 0);
            $st = $sslMap[$id] ?? [];

            $s['ssl_ready']    = (int)($st['ready'] ?? 0);
            $s['ssl_has_cert'] = (int)($st['has_cert'] ?? 0);
            $s['ssl_cert_id']  = (int)($st['cert_id'] ?? 0);
            $s['ssl_error']    = (string)($st['error'] ?? '');
        }
        unset($s);

        $this->view('sites/index', compact('sites'));
    }

    // ----------------------------
    // Create
    // ----------------------------
    public function createForm(): void
    {
        $this->requireAuth();

        require_once $this->appRoot() . '/app/Services/TemplateService.php';
        $templates = (new TemplateService())->listTemplates();

        $accounts = DB::pdo()->query("
            SELECT * FROM registrar_accounts
            WHERE provider='namecheap'
            ORDER BY is_sandbox ASC, id DESC
        ")->fetchAll();

        $this->view('sites/create', compact('templates', 'accounts'));
    }

    public function store(): void
    {
        $this->requireAuth();

        $pdo = DB::pdo();

        $domainInput = (string)($_POST['domain'] ?? '');
        $template    = trim((string)($_POST['template'] ?? 'default'));
        $domain      = $this->normalizeDomainInput($domainInput);

        if ($domain === '' || !$this->isValidDomain($domain)) {
            die('bad domain');
        }

        $registrarAccountId = (int)($_POST['registrar_account_id'] ?? 0);
        if ($registrarAccountId <= 0) {
            $registrarAccountId = 0;
        }

        $pdo->beginTransaction();

        try {
            $configPath = "storage/configs/site_" . time() . ".json";

            if ($registrarAccountId > 0) {
                $stmt = $pdo->prepare(
                    "INSERT INTO sites (domain, template, config_path, registrar_account_id)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$domain, $template, $configPath, $registrarAccountId]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO sites (domain, template, config_path)
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$domain, $template, $configPath]);
            }

            $siteId = (int)$pdo->lastInsertId();

            $cfg = $this->defaultConfig($domain);
            $stmt = $pdo->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
            $stmt->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);

            require_once $this->appRoot() . '/app/Services/TemplateService.php';

            $buildRel = 'storage/builds/site_' . $siteId;
            $buildAbs = $this->appRoot() . '/' . ltrim($buildRel, '/');

            $this->log('STORE.copyTemplate', [
                'siteId' => $siteId,
                'template' => $template,
                'buildRel' => $buildRel,
                'buildAbs' => $buildAbs,
                'APP_ROOT' => $this->appRoot(),
                'STORAGE_ROOT' => $this->storageRoot(),
            ]);

            (new TemplateService())->copyTemplate($template, $buildAbs);

            $stmt = $pdo->prepare("UPDATE sites SET build_path=? WHERE id=?");
            $stmt->execute([$buildRel, $siteId]);

            if ($template === 'template-multy') {
                $this->upsertSiteDefaultConfig($siteId, $cfg);
                $this->ensureSubdomainConfigExists($siteId, '_default');
            }

            $this->regenerateConfigPhp($siteId, $cfg, '_default');

            $pdo->commit();
            $this->redirect('/');
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo $e->getMessage();
        }
    }

    // ----------------------------
    // Domain check (простая)
    // ----------------------------
    public function checkDomain(): void
    {
        $this->requireAuth();

        header('Content-Type: application/json; charset=utf-8');

        $domainInput = (string)($_POST['domain'] ?? $_GET['domain'] ?? '');
        $domain = $this->normalizeDomainInput($domainInput);

        if ($domain === '' || !$this->isValidDomain($domain)) {
            echo json_encode(['ok' => false, 'error' => 'bad_domain', 'domain' => $domain], JSON_UNESCAPED_UNICODE);
            return;
        }

        $st = DB::pdo()->prepare("SELECT id FROM sites WHERE domain=? LIMIT 1");
        $st->execute([$domain]);
        $exists = (bool)$st->fetchColumn();

        echo json_encode(['ok' => true, 'domain' => $domain, 'exists' => $exists], JSON_UNESCAPED_UNICODE);
    }

    // ----------------------------
    // Edit + Update
    // ----------------------------
    public function editForm(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);

        $configTargetPath = $this->getConfigTargetPath($siteId);

        $pdo = DB::pdo();
        $st = $pdo->prepare("
            SELECT id, provider, is_sandbox, api_user, username, client_ip, is_default
            FROM registrar_accounts
            WHERE provider='namecheap'
            ORDER BY is_default DESC, is_sandbox ASC, id ASC
        ");
        $st->execute();
        $registrarAccounts = $st->fetchAll();

        $this->view('sites/edit', compact('site', 'cfg', 'configTargetPath', 'registrarAccounts'));
    }

    public function update(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);

        $cfg['domain'] = $this->normalizeDomainInput((string)($_POST['domain'] ?? (string)($cfg['domain'] ?? '')));
        if ($cfg['domain'] === '' || !$this->isValidDomain($cfg['domain'])) {
            die('bad domain');
        }

        $cfg['yandex_verification'] = trim((string)($_POST['yandex_verification'] ?? ''));
        $cfg['yandex_metrika']      = trim((string)($_POST['yandex_metrika'] ?? ''));
        $cfg['promolink']           = trim((string)($_POST['promolink'] ?? '/play'));

        $cfg['title']       = trim((string)($_POST['title'] ?? ''));
        $cfg['description'] = trim((string)($_POST['description'] ?? ''));
        $cfg['keywords']    = trim((string)($_POST['keywords'] ?? ''));
        $cfg['h1']          = trim((string)($_POST['h1'] ?? ''));

        $cfg['partner_override_url'] = trim((string)($_POST['partner_override_url'] ?? ''));
        $cfg['internal_reg_url']     = trim((string)($_POST['internal_reg_url'] ?? ''));
        $cfg['redirect_enabled']     = (int)($_POST['redirect_enabled'] ?? 0);
        $cfg['base_new_url']         = trim((string)($_POST['base_new_url'] ?? ''));
        $cfg['base_second_url']      = trim((string)($_POST['base_second_url'] ?? ''));

        $registrarAccountId = (int)($_POST['registrar_account_id'] ?? 0);
        if ($registrarAccountId <= 0) {
            $registrarAccountId = 0;
        } else {
            $chk = DB::pdo()->prepare("SELECT id FROM registrar_accounts WHERE id=? AND provider='namecheap' LIMIT 1");
            $chk->execute([$registrarAccountId]);
            if (!$chk->fetchColumn()) {
                $registrarAccountId = 0;
            }
        }

        DB::pdo()->prepare("UPDATE site_configs SET json=? WHERE site_id=?")
            ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $siteId]);

        DB::pdo()->prepare("UPDATE sites SET domain=? WHERE id=?")
            ->execute([$cfg['domain'], $siteId]);

        DB::pdo()->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?")
            ->execute([$registrarAccountId > 0 ? $registrarAccountId : null, $siteId]);

        $labelForRegen = (($site['template'] ?? '') === 'template-multy') ? '_default' : null;

        $this->regenerateConfigPhp($siteId, $cfg, $labelForRegen);

        $this->redirect('/sites/edit?id=' . $siteId);
    }

    // ----------------------------
    // Pages
    // ----------------------------
    public function pagesForm(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);
        $pages = $cfg['pages'] ?? [];
        if (!is_array($pages)) $pages = [];

        $label = $this->getLabelFromRequest('_default');

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $textsDir  = $this->getTextsDir($site, $label);
        $textFiles = $this->listTextFiles($textsDir);

        $used = [];
        foreach ($pages as $p) {
            $f = basename((string)($p['text_file'] ?? ''));
            if ($f !== '') $used[$f] = true;
        }

        $configTargetPath = $this->getConfigTargetPath($siteId);

        $this->view('sites/pages', compact('site', 'cfg', 'pages', 'textFiles', 'used', 'configTargetPath', 'label'));
    }

    public function pagesTextNew(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $newFile = (string)($_POST['new_file'] ?? '');
        if ($newFile === '') die('new_file required');

        $label = $this->getLabelFromRequest('_default');

        $site = $this->loadSite($siteId);

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $textsDir = $this->getTextsDir($site, $label);
        if ($textsDir === '' || !is_dir($textsDir)) {
            die('textsDir not found');
        }

        $safeFile = $this->sanitizeTextFilename($newFile);
        $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

        if (!is_file($path)) {
            file_put_contents($path, "<?php\n\n");
        }

        $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode($safeFile));
    }

    public function pagesUpdate(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);

        $label = $this->getLabelFromRequest('_default');

        $urls       = $_POST['url'] ?? [];
        $titles     = $_POST['title'] ?? [];
        $h1s        = $_POST['h1'] ?? [];
        $descs      = $_POST['description'] ?? [];
        $keys       = $_POST['keywords'] ?? [];
        $texts      = $_POST['text_file'] ?? [];
        $priorities = $_POST['priority'] ?? [];
        $sitemaps   = $_POST['sitemap'] ?? [];

        $newPages = [];

        foreach ($urls as $i => $url) {
            $url = trim((string)$url);
            if ($url === '') continue;

            if ($url[0] !== '/') $url = '/' . $url;

            $newPages[$url] = [
                'title'       => $this->inheritOrValue((string)($titles[$i] ?? '')),
                'h1'          => $this->inheritOrValue((string)($h1s[$i] ?? '')),
                'description' => $this->inheritOrValue((string)($descs[$i] ?? '')),
                'keywords'    => $this->inheritOrValue((string)($keys[$i] ?? '')),
                'text_file'   => basename(trim((string)($texts[$i] ?? 'home.php'))),
            ];

            $p = trim((string)($priorities[$i] ?? ''));
            if ($p !== '') {
                $newPages[$url]['priority'] = $p;
            }

            if (!isset($sitemaps[$i])) {
                $newPages[$url]['sitemap'] = false;
            }
        }

        $cfg['pages'] = $newPages;

        DB::pdo()->prepare("UPDATE site_configs SET json=? WHERE site_id=?")
            ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $siteId]);

        $regenLabel = (($site['template'] ?? '') === 'template-multy') ? $label : null;
        $this->regenerateConfigPhp($siteId, $cfg, $regenLabel);

        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
    }

    // ----------------------------
    // Texts
    // ----------------------------
    public function textsIndex(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $label = $this->getLabelFromRequest('_default');

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $configTargetPath = $this->getConfigTargetPath($siteId);
        $textsDir = $this->getTextsDir($site, $label);
        $files = $this->listTextFiles($textsDir);

        $this->view('texts/index', compact('site', 'files', 'configTargetPath', 'label'));
    }

    public function textsEdit(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        $file   = (string)($_GET['file'] ?? '');
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $label = $this->getLabelFromRequest('_default');

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $configTargetPath = $this->getConfigTargetPath($siteId);
        $textsDir = $this->getTextsDir($site, $label);

        $safeFile = $this->sanitizeTextFilename($file);
        $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

        if (!is_file($path)) {
            http_response_code(404);
            echo 'file not found';
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) $content = '';

        $this->view('texts/edit', compact('site', 'safeFile', 'content', 'configTargetPath', 'label'));
    }

    public function textsSave(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file    = (string)($_POST['file'] ?? '');
        $content = (string)($_POST['content'] ?? '');

        $site  = $this->loadSite($siteId);
        $label = $this->getLabelFromRequest('_default');

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $textsDir = $this->getTextsDir($site, $label);

        $safeFile = $this->sanitizeTextFilename($file);
        $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

        $tmp = $path . '.tmp_' . time();
        file_put_contents($tmp, $content);
        rename($tmp, $path);

        $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode($safeFile));
    }

    public function textsNew(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $newFile = (string)($_POST['new_file'] ?? '');

        $site  = $this->loadSite($siteId);
        $label = $this->getLabelFromRequest('_default');

        if (($site['template'] ?? '') === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';
            (new SubdomainProvisioner())->ensureForSite($siteId, $label);
        }

        $textsDir = $this->getTextsDir($site, $label);

        $safeFile = $this->sanitizeTextFilename($newFile);
        $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

        if (is_file($path)) {
            die('file already exists');
        }

        file_put_contents($path, "<?php\n\n");
        $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode($safeFile));
    }

    public function textsDelete(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file = (string)($_POST['file'] ?? '');

        $site  = $this->loadSite($siteId);
        $label = $this->getLabelFromRequest('_default');

        $textsDir = $this->getTextsDir($site, $label);

        $safeFile = $this->sanitizeTextFilename($file);
        $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

        if (is_file($path)) {
            @unlink($path);
        }

        $this->redirect('/sites/texts?id=' . $siteId . '&label=' . urlencode($label));
    }

    // ----------------------------
    // Build helper action
    // ----------------------------
    public function build(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);

        $label = $this->getLabelFromRequest('_default');

        $buildDir = $this->getBuildDir($site);
        $textsDir = $this->getTextsDir($site, (($site['template'] ?? '') === 'template-multy') ? $label : null);

        $pages = $cfg['pages'] ?? [];
        if (!is_array($pages)) $pages = [];

        $report = [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'created_texts' => [],
            'unused_texts' => [],
        ];

        $used = [];
        foreach ($pages as $url => $p) {
            $tf = (string)($p['text_file'] ?? '');
            $tf = basename(trim($tf));
            if ($tf === '') {
                $report['warnings'][] = "Страница {$url}: text_file пустой";
                continue;
            }

            if (!preg_match('~\.php$~i', $tf)) $tf .= '.php';

            $used[$tf] = true;

            $path = rtrim($textsDir, '/\\') . '/' . $tf;
            if (!is_file($path)) {
                $init = "<?php\n\n";
                if (file_put_contents($path, $init) === false) {
                    $report['ok'] = false;
                    $report['errors'][] = "Не удалось создать texts/{$tf} (проверь права)";
                } else {
                    $report['created_texts'][] = $tf;
                    $report['warnings'][] = "Создан отсутствующий файл texts/{$tf}";
                }
            }
        }

        $allTextFiles = $this->listTextFiles($textsDir);
        foreach ($allTextFiles as $f) {
            if (!isset($used[$f])) {
                $report['unused_texts'][] = $f;
            }
        }
        if (!empty($report['unused_texts'])) {
            $report['warnings'][] = 'Есть неиспользуемые texts-файлы: ' . implode(', ', $report['unused_texts']);
        }

        try {
            $regenLabel = (($site['template'] ?? '') === 'template-multy') ? $label : null;
            $this->regenerateConfigPhp($siteId, $cfg, $regenLabel);
        } catch (Throwable $e) {
            $report['ok'] = false;
            $report['errors'][] = 'Ошибка генерации config.php: ' . $e->getMessage();
        }

        $logDir = $this->storageRoot() . '/build_reports';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

        $ts = date('Ymd_His');
        $reportPath = $logDir . "/site_{$siteId}_{$ts}.json";
        @file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $configTargetPath = $this->getConfigTargetPath($siteId);
        $this->view('sites/build', compact('site', 'cfg', 'report', 'configTargetPath', 'label'));
    }

    // ----------------------------
    // Files editor
    // ----------------------------
    public function filesIndex(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);

        $allowed = $this->allowedSiteFiles();

        $files = [];
        foreach ($allowed as $f) {
            $path = rtrim($buildDir, '/\\') . '/' . $f;
            $files[] = [
                'name' => $f,
                'exists' => is_file($path),
                'size' => is_file($path) ? filesize($path) : 0,
            ];
        }

        $this->view('files/index', compact('site', 'files'));
    }

    public function filesEdit(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        $file   = (string)($_GET['file'] ?? '');
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);

        $safeFile = $this->sanitizeAllowedFile($file);
        $path = rtrim($buildDir, '/\\') . '/' . $safeFile;

        $content = '';
        if (is_file($path)) {
            $c = file_get_contents($path);
            $content = ($c === false) ? '' : $c;
        }

        $backups = [];
        $pattern = rtrim($buildDir, '/\\') . '/' . $safeFile . '.bak_*';
        foreach (glob($pattern) ?: [] as $bp) {
            $backups[] = basename($bp);
        }
        rsort($backups);

        $this->view('files/edit', compact('site', 'safeFile', 'content', 'backups'));
    }

    public function filesSave(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file    = (string)($_POST['file'] ?? '');
        $content = (string)($_POST['content'] ?? '');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);

        $safeFile = $this->sanitizeAllowedFile($file);
        $path = rtrim($buildDir, '/\\') . '/' . $safeFile;

        if (is_file($path)) {
            $ts = date('Ymd_His');
            $bak = $path . '.bak_' . $ts;
            @copy($path, $bak);
        }

        $tmp = $path . '.tmp_' . time();
        file_put_contents($tmp, $content);
        rename($tmp, $path);

        $this->redirect('/sites/files/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
    }

    public function filesRestore(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file   = (string)($_POST['file'] ?? '');
        $backup = (string)($_POST['backup'] ?? '');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);

        $safeFile = $this->sanitizeAllowedFile($file);

        if (strpos($backup, $safeFile . '.bak_') !== 0) {
            die('bad backup');
        }
        if (strpos($backup, '/') !== false || strpos($backup, '\\') !== false || strpos($backup, '..') !== false) {
            die('bad backup');
        }

        $src = rtrim($buildDir, '/\\') . '/' . $backup;
        $dst = rtrim($buildDir, '/\\') . '/' . $safeFile;

        if (!is_file($src)) {
            die('backup not found');
        }

        if (is_file($dst)) {
            $ts = date('Ymd_His');
            @copy($dst, $dst . '.bak_' . $ts);
        }

        @copy($src, $dst);

        $this->redirect('/sites/files/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
    }

    // ----------------------------
    // Delete / export
    // ----------------------------
    public function delete(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("SELECT * FROM sites WHERE id=?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) {
            $this->redirect('/');
        }

        $buildRel = (string)($site['build_path'] ?? '');
        $buildAbs = $buildRel !== '' ? ($this->appRoot() . '/' . ltrim($buildRel, '/')) : '';
        $generatedAbs = $this->storageRoot() . '/generated/site_' . $siteId;

        DB::pdo()->beginTransaction();

        try {
            DB::pdo()->prepare("DELETE FROM site_configs WHERE site_id=?")->execute([$siteId]);
            DB::pdo()->prepare("DELETE FROM sites WHERE id=?")->execute([$siteId]);

            DB::pdo()->commit();

            if ($buildAbs !== '' && is_dir($buildAbs)) {
                $this->rrmdir($buildAbs);
            }
            if (is_dir($generatedAbs)) {
                $this->rrmdir($generatedAbs);
            }

            $zipPath = $this->storageRoot() . '/zips/site_' . $siteId . '.zip';
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            $this->redirect('/');
        } catch (Throwable $e) {
            DB::pdo()->rollBack();
            http_response_code(500);
            echo $e->getMessage();
        }
    }

    public function exportZip(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("SELECT * FROM sites WHERE id=?");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) die('site not found');

        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') die('build not found');

        $buildAbs = $this->appRoot() . '/' . ltrim($buildRel, '/');

        require_once $this->appRoot() . '/app/Services/ZipService.php';

        $zipDir = $this->storageRoot() . '/zips';
        if (!is_dir($zipDir)) mkdir($zipDir, 0775, true);

        $zipPath = $zipDir . '/site_' . $siteId . '.zip';
        (new ZipService())->makeZip($buildAbs, $zipPath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="site_' . $siteId . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        exit;
    }

    // ----------------------------
    // Core helpers
    // ----------------------------
    private function inheritOrValue(string $v): string
    {
        $v = trim($v);
        return $v === '' ? '$inherit' : $v;
    }

    private function loadSiteAndConfig(int $siteId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) die('site not found');

        $stmt = DB::pdo()->prepare('SELECT json FROM site_configs WHERE site_id=?');
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();

        if (!$row) {
            $cfg = $this->defaultConfig((string)$site['domain']);
            $stmtIns = DB::pdo()->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
            $stmtIns->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
            return [$site, $cfg];
        }

        $cfg = json_decode((string)($row['json'] ?? ''), true);
        if (!is_array($cfg)) $cfg = [];

        return [$site, $cfg];
    }

    private function regenerateConfigPhp(int $siteId, array $cfg, ?string $label = null): void
    {
        $stmt = DB::pdo()->prepare("SELECT build_path, template, domain FROM sites WHERE id=?");
        $stmt->execute([$siteId]);
        $siteRow = $stmt->fetch();

        $template = (string)($siteRow['template'] ?? '');
        $domain   = (string)($siteRow['domain'] ?? ($cfg['domain'] ?? ''));

        if ($siteRow && !empty($siteRow['build_path'])) {
            $dir = $this->appRoot() . '/' . ltrim((string)$siteRow['build_path'], '/');
        } else {
            $dir = $this->storageRoot() . '/generated/site_' . $siteId;
            @mkdir($dir, 0775, true);
        }

        $this->log('REGEN.start', [
            'siteId' => $siteId,
            'template' => $template,
            'dir' => $dir,
            'label' => $label,
            'APP_ROOT' => $this->appRoot(),
            'STORAGE_ROOT' => $this->storageRoot(),
        ]);

        if ($template === 'template-multy') {
            require_once $this->appRoot() . '/app/Services/MultiSiteConfigWriter.php';
            require_once $this->appRoot() . '/app/Services/SubdomainProvisioner.php';

            $label = $this->normalizeSubLabel((string)($label ?? '_default'));

            $this->saveSiteDefaultConfig($siteId, $cfg);
            $subCfg = $this->ensureSubdomainConfigExists($siteId, $label);

            $prov = new SubdomainProvisioner();
            $prov->ensureForSite($siteId, $label);

            // writer
            $w = class_exists('App\\Services\\MultiSiteConfigWriter')
                ? new \App\Services\MultiSiteConfigWriter()
                : new MultiSiteConfigWriter();

            if (!method_exists($w, 'writeConfigDefaultPhp')) {
                throw new RuntimeException('MultiSiteConfigWriter::writeConfigDefaultPhp not found');
            }
            $w->writeConfigDefaultPhp($dir, $domain, $cfg);

            if (method_exists($w, 'writeSubConfigPhp')) {
                $w->writeSubConfigPhp($dir, $label, $subCfg, $cfg);
            } elseif (method_exists($w, 'writeSubConfig')) {
                $w->writeSubConfig(rtrim($dir, '/\\') . '/subs/' . $label, $subCfg, $cfg);
            } else {
                throw new RuntimeException('MultiSiteConfigWriter sub writer method not found');
            }

            $this->log('REGEN.done.multy', [
                'dir' => $dir,
                'config_default' => rtrim($dir, '/\\') . '/config.default.php',
                'sub_config' => rtrim($dir, '/\\') . '/subs/' . $label . '/config.php',
            ]);

            return;
        }

        // default templates
        require_once $this->appRoot() . '/app/Services/SiteConfigWriter.php';

        $this->log('REGEN.write.single', [
            'dir' => $dir,
            'target' => rtrim($dir, '/\\') . '/config.php',
        ]);

        (new SiteConfigWriter())->write($dir, $cfg);
    }

    private function defaultConfig(string $domain): array
    {
        return [
            'domain' => $domain,
            'yandex_verification' => '',
            'yandex_metrika' => '',
            'promolink' => '/reg',

            'title' => 'Новый сайт',
            'description' => '',
            'keywords' => '',
            'h1' => 'Добро пожаловать',

            'pages' => [
                '/' => [
                    'title' => '$inherit',
                    'h1' => '$inherit',
                    'description' => '$inherit',
                    'keywords' => '$inherit',
                    'text_file' => 'home.php',
                    'priority' => '1.0',
                ],
                '/404' => [
                    'title' => '404 — Страница не найдена',
                    'description' => 'Страница не найдена',
                    'keywords' => '',
                    'text_file' => '404.php',
                    'sitemap' => false,
                ],
            ],

            'partner_override_url' => '',
            'internal_reg_url' => '',
            'redirect_enabled' => 0,
            'base_new_url' => '',
            'base_second_url' => '',
        ];
    }

    private function loadSite(int $siteId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) die('site not found');
        return $site;
    }

    private function getTextsDir(array $site, ?string $label = null): string
    {
        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') return '';

        $buildAbs = $this->appRoot() . '/' . ltrim($buildRel, '/');
        $template = (string)($site['template'] ?? '');

        if ($template === 'template-multy') {
            if ($label === null) {
                $label = $this->getLabelFromRequest('_default');
            }
            $label = $this->normalizeSubLabel($label);
            return rtrim($buildAbs, '/\\') . '/subs/' . $label . '/texts';
        }

        return rtrim($buildAbs, '/\\') . '/texts';
    }

    private function normalizeSubLabel(string $label): string
    {
        $label = strtolower(trim($label));
        if ($label === '' || $label === '_default') return '_default';

        $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
        $label = trim($label, '-');

        return $label !== '' ? $label : '_default';
    }

    private function listTextFiles(string $textsDir): array
    {
        if ($textsDir === '' || !is_dir($textsDir)) return [];

        $items = scandir($textsDir);
        if ($items === false) return [];

        $files = [];
        foreach ($items as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = rtrim($textsDir, '/\\') . '/' . $f;
            if (is_file($path) && preg_match('~\.php$~i', $f)) {
                $files[] = $f;
            }
        }
        sort($files);
        return $files;
    }

    private function sanitizeTextFilename(string $name): string
    {
        $name = trim($name);
        $name = ltrim($name, '/\\');

        if ($name === '' || strlen($name) > 120) {
            die('bad filename');
        }
        if (!preg_match('~^[a-zA-Z0-9_\-\.]+$~', $name)) {
            die('bad filename');
        }
        if (strpos($name, '..') !== false) {
            die('bad filename');
        }
        if (!preg_match('~\.php$~i', $name)) {
            $name .= '.php';
        }
        return $name;
    }

    private function allowedSiteFiles(): array
    {
        return [
            'index.php',
            'config.php',
            'header.php',
            'footer.php',
            'guard.php',
            'robots.php',
            'sitemap.php',
            '.htaccess',
            'config.default.php',
        ];
    }

    private function getBuildDir(array $site): string
    {
        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') die('build_path empty');

        return $this->appRoot() . '/' . ltrim($buildRel, '/');
    }

    private function sanitizeAllowedFile(string $file): string
    {
        $file = trim($file);

        if ($file === '' || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
            die('bad file');
        }
        if (strpos($file, '..') !== false) {
            die('bad file');
        }

        $allowed = $this->allowedSiteFiles();
        if (!in_array($file, $allowed, true)) {
            die('file not allowed');
        }

        return $file;
    }

    private function getConfigTargetPath(int $siteId): string
    {
        $stmt = DB::pdo()->prepare("SELECT build_path, template FROM sites WHERE id=?");
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();

        $buildPath = (string)($row['build_path'] ?? '');
        $template  = (string)($row['template'] ?? '');

        $buildAbs = $this->appRoot() . '/' . ltrim($buildPath, '/');

        if ($template === 'template-multy') {
            return $buildAbs . '/config.default.php';
        }

        return $buildAbs . '/config.php';
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function fetchSslStatusForSites(array $sites): array
    {
        require_once $this->appRoot() . '/app/Services/Crypto.php';
        require_once $this->appRoot() . '/app/Services/FastpanelClient.php';

        $byServer = [];
        foreach ($sites as $s) {
            $localSiteId = (int)($s['id'] ?? 0);
            $serverId    = (int)($s['fastpanel_server_id'] ?? 0);
            $fpSiteId    = (int)($s['fp_site_id'] ?? 0);
            $created     = (int)($s['fp_site_created'] ?? 0) === 1;

            if ($localSiteId <= 0 || !$created || $serverId <= 0 || $fpSiteId <= 0) continue;

            $byServer[$serverId][] = [
                'local_site_id' => $localSiteId,
                'fp_site_id'    => $fpSiteId,
            ];
        }

        $result = [];

        foreach ($byServer as $serverId => $items) {
            try {
                $server = $this->loadServer((int)$serverId);
                $password = Crypto::decrypt((string)$server['password_enc']);

                $client = new FastpanelClient(
                    (string)$server['host'],
                    (bool)$server['verify_tls'],
                    (int)config('fastpanel.timeout', 20)
                );
                $client->login((string)$server['username'], $password);

                foreach ($items as $it) {
                    $localSiteId = (int)$it['local_site_id'];
                    $fpSiteId    = (int)$it['fp_site_id'];

                    try {
                        $remote = $client->site($fpSiteId);
                        $cert = $remote['certificate'] ?? null;

                        $certId = 0;
                        $enabled = false;

                        if (is_array($cert)) {
                            $certId  = (int)($cert['id'] ?? 0);
                            $enabled = (bool)($cert['enabled'] ?? false);
                        } elseif (is_numeric($cert)) {
                            $certId = (int)$cert;
                        }

                        $result[$localSiteId] = [
                            'ready'    => ($certId > 0 && $enabled) ? 1 : 0,
                            'has_cert' => ($certId > 0) ? 1 : 0,
                            'cert_id'  => $certId,
                            'error'    => '',
                        ];
                    } catch (Throwable $eSite) {
                        $result[$localSiteId] = [
                            'ready' => 0, 'has_cert' => 0, 'cert_id' => 0,
                            'error' => $eSite->getMessage(),
                        ];
                    }
                }
            } catch (Throwable $eServer) {
                foreach ($items as $it) {
                    $localSiteId = (int)$it['local_site_id'];
                    $result[$localSiteId] = [
                        'ready' => 0, 'has_cert' => 0, 'cert_id' => 0,
                        'error' => 'server error: ' . $eServer->getMessage(),
                    ];
                }
            }
        }

        return $result;
    }

    private function loadServer(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('server not found: ' . $id);
        return $row;
    }

    private function normalizeDomainInput(string $input): string
    {
        $s = trim($input);

        $s = preg_replace('~^https?://~i', '', $s);
        $s = preg_replace('~^www\.~i', '', $s);

        $parts = preg_split('~[/?#]~', $s, 2);
        $s = (string)($parts[0] ?? '');

        $s = strtolower(trim($s));
        $s = rtrim($s, "./");

        return $s;
    }

    private function isValidDomain(string $domain): bool
    {
        return (bool)preg_match('~^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)+$~i', $domain);
    }

    // ----------------------------
    // template-multy DB helpers
    // ----------------------------
    private function upsertSiteDefaultConfig(int $siteId, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = DB::pdo()->prepare("
            INSERT INTO site_default_configs (site_id, config_json)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
              config_json = VALUES(config_json),
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$siteId, $json]);
    }

    private function defaultSubdomainConfig(string $label): array
    {
        return [
            'label'   => $label,
            'logo'    => 'logo.png',
            'favicon' => 'favicon.png',
            'pages'   => [],
        ];
    }

    private function ensureSubdomainConfigExists(int $siteId, string $label): array
    {
        $label = trim($label) !== '' ? trim($label) : '_default';

        $stmt = DB::pdo()->prepare("SELECT config_json FROM site_subdomain_configs WHERE site_id=? AND label=? LIMIT 1");
        $stmt->execute([$siteId, $label]);
        $row = $stmt->fetch();

        if ($row && isset($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            return is_array($cfg) ? $cfg : $this->defaultSubdomainConfig($label);
        }

        $cfg = $this->defaultSubdomainConfig($label);
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ins = DB::pdo()->prepare("
            INSERT INTO site_subdomain_configs (site_id, label, config_json)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              config_json = VALUES(config_json),
              updated_at = CURRENT_TIMESTAMP
        ");
        $ins->execute([$siteId, $label, $json]);

        return $cfg;
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

    private function saveSiteDefaultConfig(int $siteId, array $cfg): void
    {
        $pdo = DB::pdo();
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $chk = $pdo->prepare("SELECT 1 FROM site_default_configs WHERE site_id=? LIMIT 1");
        $chk->execute([$siteId]);
        $exists = (bool)$chk->fetchColumn();

        if ($exists) {
            $up = $pdo->prepare("UPDATE site_default_configs SET config_json=?, updated_at=CURRENT_TIMESTAMP WHERE site_id=?");
            $up->execute([$json, $siteId]);
        } else {
            $ins = $pdo->prepare("INSERT INTO site_default_configs (site_id, config_json) VALUES (?, ?)");
            $ins->execute([$siteId, $json]);
        }
    }

    // ----------------------------
    // Label helper
    // ----------------------------
    private function getLabelFromRequest(string $fallback = '_default'): string
    {
        $label = (string)($_GET['label'] ?? $_POST['label'] ?? $fallback);
        return $this->normalizeSubLabel($label);
    }
}
