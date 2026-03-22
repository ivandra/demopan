<?php

class SiteController extends Controller
{
    // ----------------------------
    // Auth + paths
    // ----------------------------
  protected function requireAuth(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['user_id'])) {
        $this->redirect('/login');
        exit;
    }
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
		$sslMonMap = $this->fetchSslMonitorStatusForSites($sites);

        foreach ($sites as &$s) {
            $id = (int)($s['id'] ?? 0);
            $st = $sslMap[$id] ?? [];
			
			$mon = $sslMonMap[$id] ?? [];

			$s['ssl_mon_total'] = (int)($mon['total'] ?? 0);
			$s['ssl_mon_ok']    = (int)($mon['ok'] ?? 0);
			$s['ssl_mon_all_ok']= (int)($mon['all_ok'] ?? 0);
			$s['ssl_mon_last']  = (string)($mon['last'] ?? '');

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

        require_once Paths::appRoot() . '/app/Services/TemplateService.php';
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

    $dnsA  = $this->resolveDnsA($domain);
    $vpsIp = $dnsA[0] ?? null;

    $fastpanelServerId = null;
    if ($vpsIp) {
        $fastpanelServerId = $this->findFastpanelServerIdByIp($vpsIp);
    }

    $this->log('STORE.autofill_dns', [
        'domain' => $domain,
        'dns_a' => $dnsA,
        'vps_ip' => $vpsIp,
        'fastpanel_server_id' => $fastpanelServerId,
    ]);

    $pdo->beginTransaction();

    try {
        $configPath = 'configs/site_' . time() . '.json';

        $stmt = $pdo->prepare("
            INSERT INTO sites (domain, template, config_path, registrar_account_id, vps_ip, fastpanel_server_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $domain,
            $template,
            $configPath,
            ($registrarAccountId > 0 ? $registrarAccountId : null),
            $vpsIp,
            $fastpanelServerId,
        ]);

        $siteId = (int)$pdo->lastInsertId();

        $cfg = $this->defaultConfig($domain);

        // legacy-слой оставляем
        $stmt = $pdo->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
        $stmt->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);

        require_once Paths::appRoot() . '/app/Services/TemplateService.php';

        $buildRel = 'builds/site_' . $siteId;
        $buildAbs = $this->toBuildAbs($buildRel);

        $this->log('STORE.copyTemplate', [
            'siteId' => $siteId,
            'template' => $template,
            'buildRel' => $buildRel,
            'buildAbs' => $buildAbs,
        ]);

        (new TemplateService())->copyTemplate($template, $buildAbs);

        $stmt = $pdo->prepare("UPDATE sites SET build_path=? WHERE id=?");
        $stmt->execute([$buildRel, $siteId]);

       
        $resolver = $this->cfgResolver();

// единая логика для всех шаблонов
$resolver->upsertSiteDefaultConfig($siteId, $cfg);
$resolver->ensureSubdomainConfigExists($siteId, '_default', $cfg);
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
    // Domain check
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
    $existsId = (int)($st->fetchColumn() ?: 0);

    // DNS A
    $dnsA = $this->resolveDnsA($domain);
    $vpsIp = $dnsA[0] ?? null;

    // подбор сервера (опционально)
    $serverId = null;
if ($vpsIp) {
    $serverId = $this->findFastpanelServerIdByIp($vpsIp);
}

    echo json_encode([
        'ok' => true,
        'domain' => $domain,
        'exists' => ($existsId > 0),
        'exists_id' => $existsId,
        'dns_a' => $dnsA,
        'vps_ip_guess' => $vpsIp,
        'fastpanel_server_id_guess' => $serverId,
    ], JSON_UNESCAPED_UNICODE);
}


    // ----------------------------
    // Edit + Update (базовый конфиг)
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

        require_once Paths::appRoot() . '/app/Services/PartnerSubIdService.php';
        $partnerSubId = (new PartnerSubIdService())->buildSubId((string)($cfg['domain'] ?? ''), '_default');

        $this->view('sites/edit', compact('site', 'cfg', 'configTargetPath', 'registrarAccounts', 'partnerSubId'));
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

    // SEO-поля больше не редактируются на экране /sites/edit.
    // Берём их из текущего конфига и не затираем пустыми значениями.
    $cfg['title']       = (string)($cfg['title'] ?? '');
    $cfg['description'] = (string)($cfg['description'] ?? '');
    $cfg['keywords']    = (string)($cfg['keywords'] ?? '');
    $cfg['h1']          = (string)($cfg['h1'] ?? '');

    require_once Paths::appRoot() . '/app/Services/PartnerSubIdService.php';
    $partnerService = new PartnerSubIdService();

    $partnerBaseUrls = [
        'partner_override_url' => trim((string)($_POST['partner_override_url'] ?? '')),
        'internal_reg_url'     => trim((string)($_POST['internal_reg_url'] ?? '')),
        'base_new_url'         => trim((string)($_POST['base_new_url'] ?? '')),
        'base_second_url'      => trim((string)($_POST['base_second_url'] ?? '')),
    ];

    $cfg['partner_override_url'] = $partnerService->applySubIdToUrl($partnerBaseUrls['partner_override_url'], (string)$cfg['domain'], '_default');
    $cfg['internal_reg_url']     = $partnerService->applySubIdToUrl($partnerBaseUrls['internal_reg_url'], (string)$cfg['domain'], '_default');
    $cfg['redirect_enabled']     = (int)($_POST['redirect_enabled'] ?? 0);
    $cfg['base_new_url']         = $partnerService->applySubIdToUrl($partnerBaseUrls['base_new_url'], (string)$cfg['domain'], '_default');
    $cfg['base_second_url']      = $partnerService->applySubIdToUrl($partnerBaseUrls['base_second_url'], (string)$cfg['domain'], '_default');
    $cfg['label']                = '_default';

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

    DB::pdo()->prepare("UPDATE sites SET domain=?, registrar_account_id=? WHERE id=?")
        ->execute([$cfg['domain'], ($registrarAccountId > 0 ? $registrarAccountId : null), $siteId]);

    // единая логика для всех шаблонов
$resolver = $this->cfgResolver();

// единая логика для всех шаблонов
$resolver->saveSiteDefaultConfig($siteId, $cfg);
$resolver->saveLegacySiteConfig($siteId, $cfg);
$resolver->ensureSubdomainConfigExists($siteId, '_default', $cfg);

$this->regenerateConfigPhp($siteId, $cfg, '_default');

    $labels = $resolver->listLabels($siteId, true);
    foreach ($labels as $label) {
        if ($label === '_default') {
            continue;
        }

        $subCfg = $resolver->getResolvedConfig($siteId, $label);
        foreach (['partner_override_url', 'internal_reg_url', 'base_new_url', 'base_second_url'] as $field) {
            $subCfg[$field] = $partnerService->applySubIdToUrl((string)($partnerBaseUrls[$field] ?? ''), (string)$cfg['domain'], $label);
        }
        $subCfg['domain'] = (string)$cfg['domain'];
        $subCfg['label'] = $label;

        $resolver->saveSubdomainConfig($siteId, $label, $subCfg);
        $this->regenerateConfigPhp($siteId, $cfg, $label);
    }

    $this->redirect('/sites/edit?id=' . $siteId);
}

    // ----------------------------
    // Pages (страницы храним в site_subdomain_configs по label)
    // ----------------------------
   public function pagesForm(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    $label = $this->getLabelFromRequest('_default');
    $site  = $this->loadSite($siteId);

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $resolver = $this->cfgResolver();

    $cfg    = $resolver->loadSiteDefaultConfig($siteId, (string)($site['domain'] ?? ''), $this->defaultConfig((string)($site['domain'] ?? '')));
    $subCfg = $resolver->ensureSubdomainConfigExists($siteId, $label, $cfg);

    $pages = $subCfg['pages'] ?? [];
    if (!is_array($pages)) $pages = [];

    $rootPage = is_array($pages['/'] ?? null) ? $pages['/'] : [];
    if (empty($rootPage['text_file'])) {
        $rootPage['text_file'] = 'home.php';
    }
    if (!isset($rootPage['priority'])) {
        $rootPage['priority'] = '1.0';
    }
    if (!array_key_exists('sitemap', $rootPage)) {
        $rootPage['sitemap'] = true;
    }
    $rootPage['title'] = (string)($subCfg['title'] ?? '');
    $rootPage['h1'] = (string)($subCfg['h1'] ?? '');
    $rootPage['description'] = (string)($subCfg['description'] ?? '');
    $rootPage['keywords'] = (string)($subCfg['keywords'] ?? '');
    $pages['/'] = $rootPage;

    $textsDir  = $this->getTextsDir($site, $label);
    $textFiles = $this->listTextFiles($textsDir);

    $used = [];
    foreach ($pages as $p) {
        $f = basename((string)($p['text_file'] ?? ''));
        if ($f !== '') $used[$f] = true;
    }

    $configTargetPath = $this->getConfigTargetPath($siteId, $label);

    $this->view('sites/pages', compact('site', 'cfg', 'pages', 'textFiles', 'used', 'configTargetPath', 'label', 'subCfg'));
}

    public function pagesTextNew(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    $newFile = (string)($_POST['new_file'] ?? '');
    if ($newFile === '') die('new_file required');

    $label = $this->getLabelFromRequest('_default');
    $site  = $this->loadSite($siteId);

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $textsDir = $this->getTextsDir($site, $label);
    if ($textsDir === '' || !is_dir($textsDir)) {
        die('textsDir not found');
    }

    $safeFile = $this->sanitizeTextFilename($newFile);
    $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

    Paths::ensureDir($textsDir);
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

    $label = $this->getLabelFromRequest('_default');
    $site  = $this->loadSite($siteId);

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

        $isRootPage = ($url === '/');

        $newPages[$url] = [
            'title'       => $isRootPage ? '$inherit' : $this->inheritOrValue((string)($titles[$i] ?? '')),
            'h1'          => $isRootPage ? '$inherit' : $this->inheritOrValue((string)($h1s[$i] ?? '')),
            'description' => $isRootPage ? '$inherit' : $this->inheritOrValue((string)($descs[$i] ?? '')),
            'keywords'    => $isRootPage ? '$inherit' : $this->inheritOrValue((string)($keys[$i] ?? '')),
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

 $resolver = $this->cfgResolver();

$defaultCfg = $resolver->loadSiteDefaultConfig(
    $siteId,
    (string)($site['domain'] ?? ''),
    $this->defaultConfig((string)($site['domain'] ?? ''))
);

$subCfg = $resolver->ensureSubdomainConfigExists($siteId, $label, $defaultCfg);
$subCfg['pages'] = $newPages;
$resolver->saveSubdomainConfig($siteId, $label, $subCfg);

// root = _default
if ($label === '_default') {
    $defaultCfg['pages'] = $newPages;
    $resolver->saveSiteDefaultConfig($siteId, $defaultCfg);
    $resolver->saveLegacySiteConfig($siteId, $defaultCfg);
}

    $this->regenerateConfigPhp($siteId, $defaultCfg, $label);

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

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $configTargetPath = $this->getConfigTargetPath($siteId, $label);
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

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $configTargetPath = $this->getConfigTargetPath($siteId, $label);
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

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $textsDir = $this->getTextsDir($site, $label);

    $safeFile = $this->sanitizeTextFilename($file);
    $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

    Paths::ensureDir(dirname($path));
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

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $textsDir = $this->getTextsDir($site, $label);

    $safeFile = $this->sanitizeTextFilename($newFile);
    $path = rtrim($textsDir, '/\\') . '/' . $safeFile;

    if (is_file($path)) {
        die('file already exists');
    }

    Paths::ensureDir($textsDir);
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

    $site = $this->loadSite($siteId);
    $label = $this->getLabelFromRequest('_default');

    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';
    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $resolver = $this->cfgResolver();

$defaultCfg = $resolver->loadSiteDefaultConfig(
    $siteId,
    (string)($site['domain'] ?? ''),
    $this->defaultConfig((string)($site['domain'] ?? ''))
);
$buildDir = $this->getBuildDir($site);
$textsDir = $this->getTextsDir($site, $label);

$subCfg = $resolver->ensureSubdomainConfigExists($siteId, $label, $defaultCfg);
$pages = $subCfg['pages'] ?? [];
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
            Paths::ensureDir(dirname($path));
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
        $this->regenerateConfigPhp($siteId, $defaultCfg, $label);
    } catch (Throwable $e) {
        $report['ok'] = false;
        $report['errors'][] = 'Ошибка генерации config.php: ' . $e->getMessage();
    }

    $logDir = Paths::storage('build_reports');
    Paths::ensureDir($logDir);
    $ts = date('Ymd_His');
    $reportPath = $logDir . "/site_{$siteId}_{$ts}.json";
    Paths::ensureDir(dirname($reportPath));
    @file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $configTargetPath = $this->getConfigTargetPath($siteId, $label);
    $cfg = $defaultCfg;

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
        $scope = $this->getFilesScopeFromRequest('root');
        $labelsForFiles = $this->loadSiteLabelsForFiles($siteId);
        $label = $this->resolveRequestedFilesLabel($labelsForFiles, (string)($_GET['label'] ?? '_default'));

        $files = [];
        if ($scope === 'assets') {
            $assetsDir = rtrim($buildDir, '/\\') . '/subs/' . $label . '/assets';
            foreach ($this->allowedAssetFiles() as $f) {
                $path = $assetsDir . '/' . $f;
                if (!is_file($path)) {
                    continue;
                }
                $files[] = [
                    'name' => $f,
                    'exists' => true,
                    'size' => (int)filesize($path),
                ];
            }
        } elseif ($scope === 'sub') {
            $subDir = rtrim($buildDir, '/\\') . '/subs/' . $label;
            $items = [];
            if (is_file($subDir . '/config.php')) {
                $items[] = 'config.php';
            }
            foreach (glob($subDir . '/yandex_*.html') ?: [] as $p) {
                if (is_file($p)) {
                    $items[] = basename($p);
                }
            }
            $items = array_values(array_unique($items));
            sort($items, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($items as $f) {
                $path = $subDir . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'exists' => true,
                    'size' => (int)filesize($path),
                ];
            }
        } else {
            foreach ($this->allowedSiteFiles() as $f) {
                $path = rtrim($buildDir, '/\\') . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'exists' => is_file($path),
                    'size' => is_file($path) ? (int)filesize($path) : 0,
                ];
            }
        }

        $this->view('files/index', compact('site', 'files', 'scope', 'label', 'labelsForFiles'));
    }

    public function filesEdit(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        $file = (string)($_GET['file'] ?? '');
        if ($siteId <= 0) die('bad id');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);
        $scope = $this->getFilesScopeFromRequest('root');
        $labelsForFiles = $this->loadSiteLabelsForFiles($siteId);
        $label = $this->resolveRequestedFilesLabel($labelsForFiles, (string)($_GET['label'] ?? '_default'));

        $safeFile = $this->sanitizeSiteFileByScope($scope, $file);
        $path = $this->resolveSiteFilePath($buildDir, $scope, $label, $safeFile);
        $isBinary = $this->isBinarySiteFile($scope, $safeFile);

        $content = '';
        $previewDataUri = '';
        if (is_file($path)) {
            if ($isBinary) {
                $blob = @file_get_contents($path);
                if ($blob !== false) {
                    $previewDataUri = 'data:' . $this->guessMimeByExtension($safeFile) . ';base64,' . base64_encode($blob);
                }
            } else {
                $c = @file_get_contents($path);
                $content = ($c === false) ? '' : $c;
            }
        }

        $backups = [];
        foreach (glob($path . '.bak_*') ?: [] as $bp) {
            $backups[] = basename($bp);
        }
        rsort($backups);

        $this->view('files/edit', compact('site', 'safeFile', 'content', 'backups', 'scope', 'label', 'isBinary', 'previewDataUri'));
    }

    public function filesSave(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file = (string)($_POST['file'] ?? '');
        $content = (string)($_POST['content'] ?? '');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);
        $scope = $this->getFilesScopeFromRequest('root');
        $labelsForFiles = $this->loadSiteLabelsForFiles($siteId);
        $label = $this->resolveRequestedFilesLabel($labelsForFiles, (string)($_POST['label'] ?? '_default'));

        $safeFile = $this->sanitizeSiteFileByScope($scope, $file);
        $path = $this->resolveSiteFilePath($buildDir, $scope, $label, $safeFile);
        $isBinary = $this->isBinarySiteFile($scope, $safeFile);

        if (is_file($path)) {
            @copy($path, $path . '.bak_' . date('Ymd_His'));
        }

        Paths::ensureDir(dirname($path));

        if ($isBinary) {
            if (!isset($_FILES['upload']) || !is_array($_FILES['upload'])) {
                die('upload missing');
            }
            $tmpName = (string)($_FILES['upload']['tmp_name'] ?? '');
            $err = (int)($_FILES['upload']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
                die('upload failed');
            }
            $blob = @file_get_contents($tmpName);
            if ($blob === false) {
                die('upload read failed');
            }
            $tmp = $path . '.tmp_' . time();
            file_put_contents($tmp, $blob);
            rename($tmp, $path);
        } else {
            $tmp = $path . '.tmp_' . time();
            file_put_contents($tmp, $content);
            rename($tmp, $path);
        }

        $this->redirect('/sites/files/edit?id=' . $siteId . '&scope=' . rawurlencode($scope) . '&label=' . rawurlencode($label) . '&file=' . rawurlencode($safeFile));
    }

    public function filesRestore(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        $file = (string)($_POST['file'] ?? '');
        $backup = (string)($_POST['backup'] ?? '');

        $site = $this->loadSite($siteId);
        $buildDir = $this->getBuildDir($site);
        $scope = $this->getFilesScopeFromRequest('root');
        $labelsForFiles = $this->loadSiteLabelsForFiles($siteId);
        $label = $this->resolveRequestedFilesLabel($labelsForFiles, (string)($_POST['label'] ?? '_default'));

        $safeFile = $this->sanitizeSiteFileByScope($scope, $file);
        if (strpos($backup, $safeFile . '.bak_') !== 0 || strpos($backup, '/') !== false || strpos($backup, '\\') !== false || strpos($backup, '..') !== false) {
            die('bad backup');
        }

        $dst = $this->resolveSiteFilePath($buildDir, $scope, $label, $safeFile);
        $src = dirname($dst) . '/' . $backup;
        if (!is_file($src)) {
            die('backup not found');
        }
        if (is_file($dst)) {
            @copy($dst, $dst . '.bak_' . date('Ymd_His'));
        }
        @copy($src, $dst);

        $this->redirect('/sites/files/edit?id=' . $siteId . '&scope=' . rawurlencode($scope) . '&label=' . rawurlencode($label) . '&file=' . rawurlencode($safeFile));
    }

    // ----------------------------
    // Delete / export
    // ----------------------------
public function delete(): void
{
    $this->requireAuth();

    require_once Paths::appRoot() . '/app/Services/Crypto.php';
    require_once Paths::appRoot() . '/app/Services/FastpanelClient.php';
    require_once Paths::appRoot() . '/app/Services/NamecheapClient.php';

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        $this->redirect('/sites');
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
    }

    $domain = trim((string)($site['domain'] ?? ''));

    // --- 1) соберём labels поддоменов (чтобы чистить DNS) ---
    $stSubs = $pdo->prepare("
        SELECT label
        FROM site_subdomains
        WHERE site_id = ?
          AND label <> ''
          AND label <> '_default'
    ");
    $stSubs->execute([$id]);

    $subLabels = [];
    foreach ($stSubs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $lb = trim((string)($r['label'] ?? ''));
        if ($lb !== '') $subLabels[] = $lb;
    }

// --- 2) DNS cleanup (best-effort) ---
// --- 2) DNS cleanup (best-effort) ---
try {
    $domain = trim((string)($site['domain'] ?? ''));
    if ($domain === '' || empty($subLabels)) {
        hub_log('SITE_DELETE_DNS_SKIP', [
            'site_id' => $id,
            'domain'  => $domain,
            'labels'  => count($subLabels),
            'reason'  => 'no_domain_or_no_labels',
        ]);
    } else {

        // account: приоритет — site.registrar_account_id, иначе дефолтный prod, иначе любой
        $accId = (int)($site['registrar_account_id'] ?? 0);
        $acc = null;

        if ($accId > 0) {
            $accSt = $pdo->prepare("SELECT * FROM registrar_accounts WHERE id = ? LIMIT 1");
            $accSt->execute([$accId]);
            $acc = $accSt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$acc) {
            $accSt = $pdo->prepare("SELECT * FROM registrar_accounts WHERE provider='namecheap' AND is_default=1 AND is_sandbox=0 ORDER BY id DESC LIMIT 1");
            $accSt->execute();
            $acc = $accSt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$acc) {
            $accSt = $pdo->prepare("SELECT * FROM registrar_accounts WHERE provider='namecheap' ORDER BY is_default DESC, is_sandbox ASC, id DESC LIMIT 1");
            $accSt->execute();
            $acc = $accSt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$acc) {
            hub_log('SITE_DELETE_DNS_NO_ACCOUNT', ['site_id' => $id, 'domain' => $domain]);
        } else {

            $sandbox = (int)($acc['is_sandbox'] ?? 1) === 1;

            // создаём клиента корректно (endpoint фиксирован)
            $nc = $this->makeNamecheapClientFromAccount($acc);

            // splitSldTld: делаем максимально “живуче” под разные реализации
            $parts = $nc->splitSldTld($domain);

            $sld = null;
            $tld = null;

            if (is_array($parts)) {
                if (isset($parts['sld'], $parts['tld'])) {
                    $sld = (string)$parts['sld'];
                    $tld = (string)$parts['tld'];
                } elseif (count($parts) >= 2) {
                    $vals = array_values($parts);
                    $sld = (string)$vals[0];
                    $tld = (string)$vals[1];
                }
            }

            if (!$sld || !$tld) {
                hub_log('SITE_DELETE_DNS_SKIP', [
                    'site_id' => $id,
                    'domain'  => $domain,
                    'labels'  => count($subLabels),
                    'reason'  => 'splitSldTld_failed',
                    'parts'   => $parts,
                ]);
            } else {

                $hosts = $nc->getHosts($sld, $tld);

                // удаляем записи: label и www.label
                $remove = [];
                foreach ($subLabels as $lb) {
                    $remove[$lb] = true;
                    $remove['www.' . $lb] = true;
                }

                $filtered = [];
                $removed = [];

                foreach ($hosts as $h) {
                    $hostName = (string)($h['host'] ?? '');
                    if ($hostName !== '' && isset($remove[$hostName])) {
                        $removed[] = $hostName;
                        continue;
                    }
                    $filtered[] = $h;
                }

                if (!empty($removed)) {
                    $nc->setHosts($sld, $tld, $filtered);

                    hub_log('SITE_DELETE_DNS_OK', [
                        'site_id' => $id,
                        'domain'  => $domain,
                        'sandbox' => $sandbox ? 1 : 0,
                        'removed' => $removed,
                        'count'   => count($removed),
                    ]);
                } else {
                    hub_log('SITE_DELETE_DNS_NOTHING_TO_REMOVE', [
                        'site_id' => $id,
                        'domain'  => $domain,
                        'sandbox' => $sandbox ? 1 : 0,
                        'labels'  => $subLabels,
                    ]);
                }
            }
        }
    }
} catch (Throwable $e) {
    hub_log('SITE_DELETE_DNS_ERROR', [
        'site_id' => $id,
        'err'     => $e->getMessage(),
    ]);
}

    // --- 3) FastPanel delete site (best-effort) ---
    try {
        $fpSiteId = (int)($site['fp_site_id'] ?? 0);
        $serverId = (int)($site['fastpanel_server_id'] ?? 0);

        if ($fpSiteId > 0 && $serverId > 0) {
            $srvSt = $pdo->prepare("SELECT * FROM fastpanel_servers WHERE id = ? LIMIT 1");
            $srvSt->execute([$serverId]);
            $srv = $srvSt->fetch(PDO::FETCH_ASSOC);

            if ($srv) {
                $host = (string)($srv['host'] ?? '');
                $user = (string)($srv['username'] ?? '');
                $pass = Crypto::decrypt((string)($srv['password_enc'] ?? ''));
                $verifyTls = (int)($srv['verify_tls'] ?? 0) === 1;

                $fp = new FastpanelClient($host, $verifyTls);
                $fp->login($user, $pass);

                $res = $fp->deleteSite($fpSiteId);

                hub_log('SITE_DELETE_FASTPANEL_OK', [
                    'site_id'   => $id,
                    'fp_site_id'=> $fpSiteId,
                    'server_id' => $serverId,
                    'res'       => $res,
                ]);
            } else {
                hub_log('SITE_DELETE_FASTPANEL_SKIP_NO_SERVER', ['site_id' => $id, 'server_id' => $serverId]);
            }
        } else {
            hub_log('SITE_DELETE_FASTPANEL_SKIP', ['site_id' => $id, 'fp_site_id' => $fpSiteId, 'server_id' => $serverId]);
        }
    } catch (Throwable $e) {
        hub_log('SITE_DELETE_FASTPANEL_ERROR', ['site_id' => $id, 'err' => $e->getMessage()]);
    }

    // --- 4) удалить локальный build (best-effort) ---
    try {
        $buildPath = trim((string)($site['build_path'] ?? ''));
        $abs = $buildPath !== '' ? Paths::storage($buildPath) : Paths::storage('builds/site_' . $id);
        if (is_dir($abs)) {
            // в твоём проекте есть rrmdir(), rmDir мог быть удалён/переименован
            if (method_exists($this, 'rrmdir')) {
                $this->rrmdir($abs);
            } elseif (method_exists($this, 'rmDir')) {
                $this->rmDir($abs);
            } else {
                // last resort (очень простой рекурсивный удалятор)
                $it = new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS);
                $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($ri as $file) {
                    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
                }
                @rmdir($abs);
            }
        }
    } catch (Throwable $e) {
        hub_log('SITE_DELETE_BUILD_ERROR', ['site_id' => $id, 'err' => $e->getMessage()]);
    }

    // --- 5) удалить сайт из БД (каскадом уйдут зависимые таблицы) ---
    $del = $pdo->prepare("DELETE FROM sites WHERE id = ? LIMIT 1");
    $del->execute([$id]);

    $this->redirect('/sites');
}

/**
 * Аккуратно вычисляет абсолютный путь к build-папке,
 * учитывая, что build_path может быть:
 * - builds/site_15
 * - storage/builds/site_9
 * - storage/builds/site_9/...
 */
private function resolveBuildAbsForDelete(array $site): string
{
    $id = (int)($site['id'] ?? 0);

    $buildRel = trim((string)($site['build_path'] ?? ''));
    if ($buildRel === '') {
        $buildRel = 'builds/site_' . $id;
    }

    $buildRel = str_replace('\\', '/', $buildRel);
    $buildRel = ltrim($buildRel, '/');

    // Если в БД лежит "storage/...." — убираем префикс, потому что Paths::storage()
    // сам уже указывает на storage-root.
    if (strpos($buildRel, 'storage/') === 0) {
        $buildRel = substr($buildRel, strlen('storage/'));
    }

    // минимальная защита от ../
    if (preg_match('~(^|/)\.\.(?:/|$)~', $buildRel)) {
        return '';
    }

    return rtrim(Paths::storage($buildRel), "/\\");
}

/**
 * Рекурсивное удаление папки.
 */
private function rmDir(string $dir): void
{
    if (!is_dir($dir)) return;

    $items = @scandir($dir);
    if ($items === false) return;

    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;

        $p = rtrim($dir, "/\\") . '/' . $name;

        if (is_dir($p)) {
            $this->rmDir($p);
        } else {
            @unlink($p);
        }
    }

    @rmdir($dir);
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

        $buildAbs = $this->toBuildAbs($buildRel);

        require_once Paths::appRoot() . '/app/Services/ZipService.php';

        $zipDir = Paths::storage('zips');
        Paths::ensureDir($zipDir);

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

/**
 * Возвращает базовый cfg из site_default_configs
 * (fallback: legacy site_configs).
 */
private function loadSiteAndConfig(int $siteId): array
{
    $stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
    $stmt->execute([$siteId]);
    $site = $stmt->fetch();
    if (!$site) die('site not found');

    $domain = (string)($site['domain'] ?? '');
    $cfg = $this->cfgResolver()->loadSiteDefaultConfig($siteId, $domain, $this->defaultConfig($domain));

    return [$site, $cfg];
}

/**
 * Генерация config.default.php и subs/<label>/config.php
 * для текущего label.
 */
private function regenerateConfigPhp(int $siteId, array $cfg, ?string $label = null): void
{
    $stmt = DB::pdo()->prepare("SELECT build_path, domain FROM sites WHERE id=?");
    $stmt->execute([$siteId]);
    $siteRow = $stmt->fetch();

    $domain = (string)($siteRow['domain'] ?? ($cfg['domain'] ?? ''));

    if ($siteRow && !empty($siteRow['build_path'])) {
        $dir = $this->toBuildAbs((string)$siteRow['build_path']);
    } else {
        $dir = Paths::storage('generated/site_' . $siteId);
        Paths::ensureDir($dir);
    }

    require_once Paths::appRoot() . '/app/Services/MultiSiteConfigWriter.php';
    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';

    $resolver = $this->cfgResolver();
    $label = $resolver->normalizeSubLabel((string)($label ?? '_default'));

    $resolver->saveSiteDefaultConfig($siteId, $cfg);
    $resolver->ensureSubdomainConfigExists($siteId, $label, $cfg);

    (new SubdomainProvisioner())->ensureForSite($siteId, $label);

    $w = new MultiSiteConfigWriter();

    $w->writeConfigDefaultPhp($dir, $domain, $cfg);

    $subCfg = $resolver->loadSubdomainConfig($siteId, $label);
    $w->writeSubConfigPhp($dir, $label, $subCfg, $cfg);

    $this->log('REGEN.done', [
        'dir' => $dir,
        'config_default' => rtrim($dir, '/\\') . '/config.default.php',
        'sub_config' => rtrim($dir, '/\\') . '/subs/' . $label . '/config.php',
        'label' => $label,
    ]);
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

    $buildAbs = $this->toBuildAbs($buildRel);

    if ($label === null) {
        $label = $this->getLabelFromRequest('_default');
    }
    $label = $this->normalizeSubLabel($label);

    return rtrim($buildAbs, '/\\') . '/subs/' . $label . '/texts';
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


    private function allowedAssetFiles(): array
    {
        return [
            'logo.png',
            'logo.webp',
            'logo.jpg',
            'logo.jpeg',
            'logo.svg',
            'favicon.ico',
            'favicon.png',
            'favicon.svg',
            'favicon.webp',
        ];
    }

    private function getFilesScopeFromRequest(string $fallback = 'root'): string
    {
        $scope = (string)($_GET['scope'] ?? $_POST['scope'] ?? $fallback);
        $scope = trim(strtolower($scope));
        if (!in_array($scope, ['root', 'sub', 'assets'], true)) {
            $scope = $fallback;
        }
        return $scope;
    }

    private function loadSiteLabelsForFiles(int $siteId): array
    {
        $labels = [];
        $st = DB::pdo()->prepare("SELECT label FROM site_subdomains WHERE site_id = ? AND enabled = 1 ORDER BY label ASC");
        $st->execute([$siteId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $lb) {
            $lb = trim((string)$lb);
            if ($lb !== '') {
                $labels[] = $lb;
            }
        }
        if (!in_array('_default', $labels, true)) {
            array_unshift($labels, '_default');
        }
        $labels = array_values(array_unique($labels));
        usort($labels, static function ($a, $b) {
            if ($a === '_default') return -1;
            if ($b === '_default') return 1;
            return strcasecmp($a, $b);
        });
        return $labels;
    }

    private function resolveRequestedFilesLabel(array $labels, string $requested): string
    {
        $requested = trim($requested);
        if ($requested !== '' && in_array($requested, $labels, true)) {
            return $requested;
        }
        return $labels[0] ?? '_default';
    }

    private function sanitizeSiteFileByScope(string $scope, string $file): string
    {
        $file = trim($file);
        if ($file === '' || strpos($file, '/') !== false || strpos($file, '\\') !== false || strpos($file, '..') !== false) {
            die('bad file');
        }

        if ($scope === 'assets') {
            if (!in_array($file, $this->allowedAssetFiles(), true)) {
                die('file not allowed');
            }
            return $file;
        }

        if ($scope === 'sub') {
            if ($file === 'config.php') {
                return $file;
            }
            if (preg_match('~^yandex_[A-Za-z0-9]+\.html$~', $file)) {
                return $file;
            }
            die('file not allowed');
        }

        return $this->sanitizeAllowedFile($file);
    }

    private function resolveSiteFilePath(string $buildDir, string $scope, string $label, string $file): string
    {
        if ($scope === 'assets') {
            return rtrim($buildDir, '/\\') . '/subs/' . $label . '/assets/' . $file;
        }
        if ($scope === 'sub') {
            return rtrim($buildDir, '/\\') . '/subs/' . $label . '/' . $file;
        }
        return rtrim($buildDir, '/\\') . '/' . $file;
    }

    private function isBinarySiteFile(string $scope, string $file): bool
    {
        if ($scope !== 'assets') {
            return false;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'], true);
    }

    private function guessMimeByExtension(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'png': return 'image/png';
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            case 'webp': return 'image/webp';
            case 'svg': return 'image/svg+xml';
            case 'ico': return 'image/x-icon';
            case 'html': return 'text/html; charset=UTF-8';
            default: return 'application/octet-stream';
        }
    }


    /**
     * build_path в БД хранится как относительный путь внутри storage (например: builds/site_123).
     * Для обратной совместимости также принимаем префикс "<storage_basename>/..." (если он уже есть в БД).
     */
    private function normalizeBuildRel(string $rel): string
    {
        $rel = trim($rel);
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');

        if ($rel === '') return '';

        // legacy: "<storage_basename>/builds/..."
        $storageBase = basename(rtrim(Paths::storage(''), "/\\"));
        if ($storageBase !== '' && strpos($rel, $storageBase . '/') === 0) {
            $rel = substr($rel, strlen($storageBase) + 1);
        }

        // защита от выходов наверх
        if (preg_match('~(^|/)\.\.(?:/|$)~', $rel)) {
            return '';
        }

        // ожидаем builds/...
        if (strpos($rel, 'builds/') !== 0) {
            return '';
        }

        return $rel;
    }

    private function toBuildAbs(string $buildRel): string
    {
        $rel = $this->normalizeBuildRel($buildRel);
        if ($rel === '') {
            die('build_path invalid');
        }

        return rtrim(Paths::storage($rel), "/\\");
    }

    private function getBuildDir(array $site): string
    {
        $buildRel = (string)($site['build_path'] ?? '');
        if ($buildRel === '') die('build_path empty');

        return $this->toBuildAbs($buildRel);
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

    private function getConfigTargetPath(int $siteId, ?string $label = null): string
{
    $stmt = DB::pdo()->prepare("SELECT build_path FROM sites WHERE id=?");
    $stmt->execute([$siteId]);
    $row = $stmt->fetch();

    $buildPath = (string)($row['build_path'] ?? '');
    $buildAbs = $this->toBuildAbs($buildPath);

    if ($label === null) {
        return $buildAbs . '/config.default.php';
    }

    $label = $this->normalizeSubLabel($label);
    return $buildAbs . '/subs/' . $label . '/config.php';
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

    // ----------------------------
    // SSL status
    // ----------------------------
    private function fetchSslStatusForSites(array $sites): array
    {
        require_once Paths::appRoot() . '/app/Services/Crypto.php';
        require_once Paths::appRoot() . '/app/Services/FastpanelClient.php';

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

    // ----------------------------
    // Domain helpers
    // ----------------------------
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
	
	private function resolveDnsA(string $domain): array
{
    $domain = strtolower(trim($domain));
    if ($domain === '') return [];

    $out = [];
    $recs = @dns_get_record($domain, DNS_A);
    if (is_array($recs)) {
        foreach ($recs as $r) {
            if (!is_array($r)) continue;
            $ip = trim((string)($r['ip'] ?? ''));
            if ($ip !== '' && preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $ip)) {
                $out[] = $ip;
            }
        }
    }

    $out = array_values(array_unique($out));
    return $out;
}

private function findServerIdByIp(string $ip): ?int
{
    $ip = trim($ip);
    if ($ip === '') return null;

    $pdo = DB::pdo();
    $rows = $pdo->query("SELECT id, host, extra_ips FROM fastpanel_servers")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $srv) {
        $ips = [];

        // extra_ips (CSV/пробелы/переносы)
        $extra = trim((string)($srv['extra_ips'] ?? ''));
        if ($extra !== '') {
            if (preg_match_all('~\b(?:\d{1,3}\.){3}\d{1,3}\b~', $extra, $m)) {
                foreach ($m[0] as $one) {
                    $one = trim((string)$one);
                    if ($one !== '') $ips[] = $one;
                }
            }
        }

        // fallback: host может быть IP или https://IP:port
        $host = trim((string)($srv['host'] ?? ''));
        if ($host !== '') {
            $h = preg_replace('~^https?://~i', '', $host);
            $h = preg_split('~[/?#]~', $h, 2)[0] ?? $h;
            $h = preg_replace('~:\d+$~', '', $h);
            $h = trim((string)$h);
            if (preg_match('~^(?:\d{1,3}\.){3}\d{1,3}$~', $h)) {
                $ips[] = $h;
            }
        }

        $ips = array_values(array_unique($ips));
        if (in_array($ip, $ips, true)) {
            return (int)$srv['id'];
        }
    }

    return null;
}


private function isIpv4(string $ip): bool
{
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

private function findFastpanelServerIdByIp(string $ip): ?int
{
    $ip = trim($ip);
    if (!$this->isIpv4($ip)) return null;

    $rows = DB::pdo()
        ->query("SELECT id, host, extra_ips FROM fastpanel_servers ORDER BY id ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $srv) {
        $ips = $this->extractIpsFromServerRow($srv);
        if (in_array($ip, $ips, true)) {
            return (int)$srv['id'];
        }
    }

    return null;
}

private function extractIpsFromServerRow(array $srv): array
{
    $ips = [];

    // host может быть "95.129.234.20:8888" или "https://95.129.234.20:8888"
    $host = trim((string)($srv['host'] ?? ''));
    if ($host !== '') {
        $hIp = $this->extractIpFromHost($host);
        if ($hIp) $ips[] = $hIp;
    }

    // extra_ips может быть CSV/пробелы/JSON/что угодно
    $extra = $srv['extra_ips'] ?? '';
    if ($extra !== null && trim((string)$extra) !== '') {
        $ips = array_merge($ips, $this->extractIpsFromMixed($extra));
    }

    // уникализация
    $ips = array_values(array_unique($ips));
    return $ips;
}

private function extractIpFromHost(string $host): ?string
{
    $h = trim($host);

    // если есть схема — parse_url
    if (preg_match('~^https?://~i', $h)) {
        $u = @parse_url($h);
        $candidate = (string)($u['host'] ?? '');
        if ($this->isIpv4($candidate)) return $candidate;
    }

    // без схемы: убираем "https://", путь, порт
    $h = preg_replace('~^[a-z]+://~i', '', $h);
    $h = preg_split('~[/:]~', $h, 2)[0] ?? $h;
    $h = trim((string)$h);

    if ($this->isIpv4($h)) return $h;

    // если вдруг host хранится доменом — попробуем A
    if ($h !== '' && preg_match('~^[a-z0-9][a-z0-9\.\-]+$~i', $h)) {
        $a = $this->resolveDnsA($h);
        return $a[0] ?? null;
    }

    return null;
}

private function extractIpsFromMixed($value): array
{
    $ips = [];

    // если вдруг уже массив
    if (is_array($value)) {
        foreach ($value as $v) {
            $v = trim((string)$v);
            if ($this->isIpv4($v)) $ips[] = $v;
        }
        return array_values(array_unique($ips));
    }

    $s = trim((string)$value);
    if ($s === '') return [];

    // попытка JSON
    if ($s[0] === '[' || $s[0] === '{') {
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            // массив строк
            if (array_is_list($decoded)) {
                foreach ($decoded as $v) {
                    $v = trim((string)$v);
                    if ($this->isIpv4($v)) $ips[] = $v;
                }
                return array_values(array_unique($ips));
            }

            // объект вида {"ips":[...]} или {"extra_ips":[...]} — поддержим популярные варианты
            foreach (['ips', 'extra_ips'] as $k) {
                if (isset($decoded[$k]) && is_array($decoded[$k])) {
                    foreach ($decoded[$k] as $v) {
                        $v = trim((string)$v);
                        if ($this->isIpv4($v)) $ips[] = $v;
                    }
                    return array_values(array_unique($ips));
                }
            }
        }
        // если JSON битый — просто упадем в regex ниже
    }

    // вытащим все IPv4 регекспом (CSV/пробелы/переносы)
    if (preg_match_all('~\b(?:\d{1,3}\.){3}\d{1,3}\b~', $s, $m)) {
        foreach ($m[0] as $ip) {
            $ip = trim((string)$ip);
            if ($this->isIpv4($ip)) $ips[] = $ip;
        }
    }

    return array_values(array_unique($ips));
}

    // ----------------------------
    // Label helper
    // ----------------------------
    private function getLabelFromRequest(string $fallback = '_default'): string
    {
        $label = (string)($_GET['label'] ?? $_POST['label'] ?? $fallback);
        return $this->normalizeSubLabel($label);
    }
	
	public function cloneForm(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        echo "no site id";
        return;
    }

    $site = $this->loadSite($siteId);
    if (!$site) {
        echo "site not found";
        return;
    }

    $pdo = DB::pdo();
    $st = $pdo->prepare("SELECT label FROM site_subdomain_configs WHERE site_id = ? ORDER BY label");
    $st->execute([$siteId]);

    $labelsMap = [
        '_default' => true,
    ];

    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $lb = trim((string)($r['label'] ?? ''));
        if ($lb !== '') {
            $labelsMap[$lb] = true;
        }
    }

    $labels = array_keys($labelsMap);
    sort($labels);

    $this->view('sites/clone', [
        'site' => $site,
        'siteId' => $siteId,
        'labels' => $labels,
    ]);
}

public function cloneDo(): void
{
    $this->requireAuth();

    require_once Paths::appRoot() . '/app/Services/SiteCloner.php';
    require_once Paths::appRoot() . '/app/Services/SubdomainProvisioner.php';

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        exit;
    }

    $newDomain = (string)($_POST['new_domain'] ?? '');
    $labels = (array)($_POST['labels'] ?? []);

    // если ничего не выбрали — переносим только _default
    if (!$labels) $labels = ['_default'];

    $cloner = new SiteCloner();
    $newSiteId = $cloner->cloneSite($siteId, $newDomain, $labels);

    // на всякий случай создаем/обновляем папки overlay + конфиги
    $prov = new SubdomainProvisioner();
    $prov->ensureForSite($newSiteId, '_default');
    foreach ($labels as $lb) {
        if ($lb === '_default') continue;
        $prov->ensureForSite($newSiteId, (string)$lb);
    }

    $this->redirect('/sites/clone/done?id=' . $newSiteId);
	return;
}

// ----------------------------
// SSL monitor status (ssl_checks)
// ----------------------------
private function fetchSslMonitorStatusForSites(array $sites): array
{
    $siteIds = [];
    foreach ($sites as $s) {
        $id = (int)($s['id'] ?? 0);
        if ($id > 0) $siteIds[] = $id;
    }
    $siteIds = array_values(array_unique($siteIds));
    if (empty($siteIds)) return [];

    $in = implode(',', array_fill(0, count($siteIds), '?'));

    $sql = "
        SELECT
            site_id,
            SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS total_enabled,
            SUM(CASE WHEN enabled=1 AND https_ok=1 THEN 1 ELSE 0 END) AS ok_enabled,
            MAX(updated_at) AS last_check
        FROM ssl_checks
        WHERE site_id IN ($in)
        GROUP BY site_id
    ";

    $st = DB::pdo()->prepare($sql);
    $st->execute($siteIds);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $map = [];
    foreach ($rows as $r) {
        $sid = (int)($r['site_id'] ?? 0);
        if ($sid <= 0) continue;

        $total = (int)($r['total_enabled'] ?? 0);
        $ok    = (int)($r['ok_enabled'] ?? 0);

        $map[$sid] = [
            'total' => $total,
            'ok'    => $ok,
            'all_ok' => ($total > 0 && $ok === $total) ? 1 : 0,
            'last'  => (string)($r['last_check'] ?? ''),
        ];
    }

    return $map;
}

private function loadOverviewData(int $siteId): array
{
    $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    });

    if (!$site) {
        return ['site' => null];
    }

$subStats = DB::withReconnect(function(PDO $pdo) use ($siteId) {
    $st = $pdo->prepare("
        SELECT
          COUNT(*) AS total_all,
          SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS enabled_all,
          SUM(CASE WHEN label <> '_default' THEN 1 ELSE 0 END) AS total_subs,
          SUM(CASE WHEN label <> '_default' AND enabled=1 THEN 1 ELSE 0 END) AS enabled_subs,
          SUM(CASE WHEN dns_status='ok' THEN 1 ELSE 0 END) AS dns_ok_all
        FROM site_subdomains
        WHERE site_id=?
    ");
    $st->execute([$siteId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_all'    => (int)($r['total_all'] ?? 0),
        'enabled_all'  => (int)($r['enabled_all'] ?? 0),
        'total_subs'   => (int)($r['total_subs'] ?? 0),
        'enabled_subs' => (int)($r['enabled_subs'] ?? 0),
        'dns_ok_all'   => (int)($r['dns_ok_all'] ?? 0),
    ];
});
	
	$dnsAudit = $this->fetchOverviewDnsAudit($site);
	
	require_once Paths::appRoot() . '/app/Services/WebmasterPublishStateService.php';
	$wmDeployState = (new WebmasterPublishStateService())->getState($siteId);

    $contentStats = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $defaultExists = false;
        $rootPages = 0;

        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id=? LIMIT 1");
        $st->execute([$siteId]);
        $defaultJson = $st->fetchColumn();

        if ($defaultJson !== false && $defaultJson !== null && $defaultJson !== '') {
            $defaultExists = true;
            $arr = json_decode((string)$defaultJson, true);
            if (is_array($arr) && !empty($arr['pages']) && is_array($arr['pages'])) {
                $rootPages = count($arr['pages']);
            }
        }

        $st = $pdo->prepare("
            SELECT label, config_json
            FROM site_subdomain_configs
            WHERE site_id=?
            ORDER BY label ASC
        ");
        $st->execute([$siteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $subCfgCount = 0;
        $labelsWithPages = 0;
        $pagesTotal = $rootPages;

        foreach ($rows as $row) {
            $label = (string)($row['label'] ?? '');
            $json  = (string)($row['config_json'] ?? '');
            if ($json === '') continue;

            if ($label !== '_default') {
                $subCfgCount++;
            }

            $cfg = json_decode($json, true);
            if (!is_array($cfg)) continue;

            $pages = $cfg['pages'] ?? null;
            if (is_array($pages) && !empty($pages)) {
                if ($label !== '_default') {
                    $labelsWithPages++;
                    $pagesTotal += count($pages);
                }
            }
        }

        return [
            'default_exists'    => $defaultExists ? 1 : 0,
            'root_pages'        => $rootPages,
            'sub_cfg_count'     => $subCfgCount,
            'labels_with_pages' => $labelsWithPages,
            'pages_total'       => $pagesTotal,
        ];
    });

    $buildStats = (function(array $site, int $siteId): array {
        $buildRel = (string)($site['build_path'] ?? '');
        $buildAbs = '';

        if ($buildRel !== '') {
            if (strpos($buildRel, 'builds/') === 0) {
                $buildAbs = Paths::storage($buildRel);
            } else {
                $buildAbs = Paths::appRoot() . '/' . ltrim($buildRel, '/\\');
            }
        }

        $zipAbs = Paths::storage('zips/site_' . $siteId . '.zip');

        return [
            'build_rel'    => $buildRel,
            'build_exists' => ($buildAbs !== '' && is_dir($buildAbs)) ? 1 : 0,
            'build_mtime'  => ($buildAbs !== '' && is_dir($buildAbs)) ? @date('Y-m-d H:i', filemtime($buildAbs)) : '',
            'zip_exists'   => is_file($zipAbs) ? 1 : 0,
            'zip_size'     => is_file($zipAbs) ? (int)filesize($zipAbs) : 0,
            'zip_mtime'    => is_file($zipAbs) ? @date('Y-m-d H:i', filemtime($zipAbs)) : '',
        ];
    })($site, $siteId);

    $deployStats = DB::withReconnect(function(PDO $pdo) use ($siteId, $site) {
        $st = $pdo->prepare("
            SELECT status, updated_at
            FROM deployments
            WHERE site_id=?
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute([$siteId]);
        $last = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'fp_site_created' => ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0) ? 1 : 0,
            'ftp_ready'       => (int)($site['fp_ftp_ready'] ?? 0) === 1 ? 1 : 0,
            'files_ready'     => (int)($site['fp_files_ready'] ?? 0) === 1 ? 1 : 0,
            'fp_site_id'      => (int)($site['fp_site_id'] ?? 0),
            'last_status'     => (string)($last['status'] ?? ''),
            'last_updated_at' => (string)($last['updated_at'] ?? ''),
        ];
    });

    $sslStats = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("
            SELECT
              SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS total_enabled,
              SUM(CASE WHEN enabled=1 AND https_ok=1 THEN 1 ELSE 0 END) AS ok_enabled,
              MAX(updated_at) AS last_check
            FROM ssl_checks
            WHERE site_id=? AND label <> '_default'
        ");
        $st->execute([$siteId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($r['total_enabled'] ?? 0),
            'ok'    => (int)($r['ok_enabled'] ?? 0),
            'last'  => (string)($r['last_check'] ?? ''),
        ];
    });

    $wmStats = DB::withReconnect(function(PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("
            SELECT
              COUNT(*) AS total_hosts,
              SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) AS verified_cnt,
              SUM(CASE WHEN sitemap_added_at IS NOT NULL THEN 1 ELSE 0 END) AS sitemap_cnt,
              SUM(CASE WHEN robots_confirmed_at IS NOT NULL THEN 1 ELSE 0 END) AS robots_cnt,
              MAX(updated_at) AS last_sync
            FROM webmaster_hosts
            WHERE site_id=? AND label <> '_default'
        ");
        $st->execute([$siteId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'     => (int)($r['total_hosts'] ?? 0),
            'verified'  => (int)($r['verified_cnt'] ?? 0),
            'sitemaps'  => (int)($r['sitemap_cnt'] ?? 0),
            'robots'    => (int)($r['robots_cnt'] ?? 0),
            'last_sync' => (string)($r['last_sync'] ?? ''),
        ];
    });

    return [
		'site'          => $site,
		'siteId'        => $siteId,
		'subStats'      => $subStats,
		'dnsAudit'      => $dnsAudit,
		'contentStats'  => $contentStats,
		'buildStats'    => $buildStats,
		'deployStats'   => $deployStats,
		'sslStats'      => $sslStats,
		'wmStats'       => $wmStats,
		'wmDeployState' => $wmDeployState,
	];
}

// GET /sites/overview?id=123
public function overview(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $data = $this->loadOverviewData($siteId);
    if (empty($data['site'])) {
        $this->redirect('/sites');
        return;
    }

    $data['freshClone'] = false;
    $this->view('sites/overview', $data);
}

// GET /sites/clone/done?id=123
public function cloneDone(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $data = $this->loadOverviewData($siteId);
    if (empty($data['site'])) {
        $_SESSION['wm_log'][] = "cloneDone: site not found id={$siteId}";
        $this->redirect('/sites');
        return;
    }

    $data['freshClone'] = true;
    $this->view('sites/overview', $data);
}

private function makeNamecheapClientFromAccount(array $acc): NamecheapClient
{
    $apiUser   = (string)($acc['api_user'] ?? '');
    $apiKeyEnc = (string)($acc['api_key_enc'] ?? '');
    $username  = (string)($acc['username'] ?? '');
    $clientIp  = (string)($acc['client_ip'] ?? '');
    $sandbox   = (int)($acc['is_sandbox'] ?? 1) === 1;

    $apiKey = Crypto::decrypt($apiKeyEnc);

    // ВАЖНО:
    // В твоей сборке NamecheapClient, судя по ошибке curl, ПЕРВЫЙ аргумент скорее всего baseUrl/host.
    // Поэтому задаём endpoint явно.
    $endpoint = $sandbox
        ? 'https://api.sandbox.namecheap.com/xml.response'
        : 'https://api.namecheap.com/xml.response';

    // Пробуем несколько наиболее вероятных сигнатур конструктора.
    // Если первая не подошла — упадёт TypeError, пойдём дальше.
    $ctors = [
        // (endpoint, apiUser, apiKey, username, clientIp)
        fn() => new NamecheapClient($endpoint, $apiUser, $apiKey, $username, $clientIp),

        // (endpoint, username, apiKey, apiUser, clientIp)
        fn() => new NamecheapClient($endpoint, $username, $apiKey, $apiUser, $clientIp),

        // (apiUser, apiKey, username, clientIp, sandbox) — старый вариант, если он у тебя был
        fn() => new NamecheapClient($apiUser, $apiKey, $username, $clientIp, $sandbox),

        // (apiUser, apiKey, username, clientIp) — без sandbox
        fn() => new NamecheapClient($apiUser, $apiKey, $username, $clientIp),
    ];

    $lastErr = null;

    foreach ($ctors as $make) {
        try {
            $nc = $make();

            // “Проверка на жизнь”: если метод есть — вызовем лёгкую операцию split
            // (если внутри он не делает запрос, ок).
            // Главное — вернуть объект без TypeError.
            return $nc;
        } catch (TypeError $e) {
            $lastErr = $e->getMessage();
            continue;
        }
    }

    throw new RuntimeException('Cannot construct NamecheapClient. Last error: ' . ($lastErr ?? 'unknown'));
}

private function cfgResolver(): SiteConfigResolver
{
    require_once Paths::appRoot() . '/app/Services/SiteConfigResolver.php';
    return new SiteConfigResolver(DB::pdo());
}


private function loadRegistrarAccountForSiteOverview(array $site): ?array
{
    $pdo = DB::pdo();

    $rid = (int)($site['registrar_account_id'] ?? 0);
    if ($rid > 0) {
        $st = $pdo->prepare("SELECT * FROM registrar_accounts WHERE id=? LIMIT 1");
        $st->execute([$rid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    $st = $pdo->prepare("
        SELECT *
        FROM registrar_accounts
        WHERE provider='namecheap'
        ORDER BY is_default DESC, is_sandbox ASC, id ASC
        LIMIT 1
    ");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

private function normalizeNamecheapHostsForOverview(array $hosts): array
{
    $out = [];

    foreach ($hosts as $h) {
        if (!is_array($h)) continue;

        $host = (string)($h['host'] ?? $h['HostName'] ?? $h['Name'] ?? '');
        $type = (string)($h['type'] ?? $h['RecordType'] ?? $h['Type'] ?? '');
        $addr = (string)($h['address'] ?? $h['Address'] ?? $h['Value'] ?? '');

        $host = strtolower(trim($host));
        $type = strtoupper(trim($type));
        $addr = trim($addr);

        if ($host === '' || $type === '' || $addr === '') continue;

        $out[] = [
            'host' => $host,
            'type' => $type,
            'address' => $addr,
        ];
    }

    return $out;
}

private function pickExpectedDnsIpForOverview(array $site): string
{
    $manual = trim((string)($site['vps_ip'] ?? ''));
    if ($this->isIpv4($manual)) {
        return $manual;
    }

    $serverId = (int)($site['fastpanel_server_id'] ?? 0);
    if ($serverId <= 0) {
        return '';
    }

    $st = DB::pdo()->prepare("SELECT id, host, extra_ips FROM fastpanel_servers WHERE id=? LIMIT 1");
    $st->execute([$serverId]);
    $srv = $st->fetch(PDO::FETCH_ASSOC);

    if (!$srv) {
        return '';
    }

    $ips = $this->extractIpsFromServerRow($srv);
    return $ips[0] ?? '';
}

private function splitNamecheapDomainParts(NamecheapClient $nc, string $domain): array
{
    $parts = $nc->splitSldTld($domain);

    $sld = '';
    $tld = '';

    if (is_array($parts)) {
        if (isset($parts['sld'], $parts['tld'])) {
            $sld = (string)$parts['sld'];
            $tld = (string)$parts['tld'];
        } elseif (count($parts) >= 2) {
            $vals = array_values($parts);
            $sld = (string)($vals[0] ?? '');
            $tld = (string)($vals[1] ?? '');
        }
    }

    if ($sld === '' || $tld === '') {
        throw new RuntimeException('splitSldTld failed for domain: ' . $domain);
    }

    return [$sld, $tld];
}

private function fetchOverviewDnsAudit(array $site): array
{
    $siteId = (int)($site['id'] ?? 0);
    $domain = trim((string)($site['domain'] ?? ''));

    $result = [
        'checked'         => 0,
        'ok_all'          => 0,
        'expected_ip'     => '',
        'root_ip'         => '',
        'root_ok'         => 0,
        'enabled_subs'    => 0,
        'ok_subs'         => 0,
        'missing_labels'  => [],
        'wrong_ip_labels' => [],
        'error'           => '',
    ];

    if ($siteId <= 0 || $domain === '') {
        $result['error'] = 'bad site data';
        return $result;
    }

    $labels = DB::withReconnect(function (PDO $pdo) use ($siteId) {
        $st = $pdo->prepare("
            SELECT label
            FROM site_subdomains
            WHERE site_id=?
              AND enabled=1
              AND label <> '_default'
            ORDER BY label ASC
        ");
        $st->execute([$siteId]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $lb = strtolower(trim((string)($row['label'] ?? '')));
            if ($lb !== '') $out[] = $lb;
        }
        return array_values(array_unique($out));
    });

    $result['enabled_subs'] = count($labels);
    $result['expected_ip'] = $this->pickExpectedDnsIpForOverview($site);

    try {
        require_once Paths::appRoot() . '/app/Services/Crypto.php';
        require_once Paths::appRoot() . '/app/Services/NamecheapClient.php';

        $acc = $this->loadRegistrarAccountForSiteOverview($site);
        if (!$acc) {
            throw new RuntimeException('Аккаунт Namecheap не найден');
        }

        $nc = $this->makeNamecheapClientFromAccount($acc);
        list($sld, $tld) = $this->splitNamecheapDomainParts($nc, $domain);

        $rawHosts = $nc->getHosts($sld, $tld);
        $hosts = $this->normalizeNamecheapHostsForOverview($rawHosts);

        $aMap = [];
        foreach ($hosts as $h) {
            if (($h['type'] ?? '') !== 'A') continue;

            $host = (string)$h['host'];
            $ip   = (string)$h['address'];

            if (!isset($aMap[$host])) {
                $aMap[$host] = [];
            }
            $aMap[$host][] = $ip;
        }

        $rootIps = $aMap['@'] ?? [];
        $result['root_ip'] = $rootIps[0] ?? '';

        if ($result['expected_ip'] !== '') {
            $result['root_ok'] = in_array($result['expected_ip'], $rootIps, true) ? 1 : 0;
        } else {
            $result['root_ok'] = !empty($rootIps) ? 1 : 0;
        }

        foreach ($labels as $lb) {
            $ips = $aMap[$lb] ?? [];

            if (empty($ips)) {
                $result['missing_labels'][] = $lb;
                continue;
            }

            if ($result['expected_ip'] !== '' && !in_array($result['expected_ip'], $ips, true)) {
                $result['wrong_ip_labels'][] = $lb;
                continue;
            }

            $result['ok_subs']++;
        }

        $result['checked'] = 1;
        $result['ok_all'] =
            $result['root_ok'] === 1
            && $result['ok_subs'] === $result['enabled_subs']
            && empty($result['missing_labels'])
            && empty($result['wrong_ip_labels'])
                ? 1 : 0;

    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}

private function detectLastBuildAt(int $siteId): ?string
{
    $latestTs = 0;

    $pattern = Paths::storage('build_reports/site_' . $siteId . '_*.json');
    $files = glob($pattern) ?: [];

    foreach ($files as $file) {
        $ts = (int)@filemtime($file);
        if ($ts > $latestTs) {
            $latestTs = $ts;
        }
    }

    // fallback: если build report не найден, пробуем ZIP
    if ($latestTs <= 0) {
        $zip = Paths::storage('zips/site_' . $siteId . '.zip');
        if (is_file($zip)) {
            $latestTs = (int)@filemtime($zip);
        }
    }

    return $latestTs > 0 ? date('Y-m-d H:i:s', $latestTs) : null;
}


private function getWebmasterPublishState(int $siteId, array $site): array
{
    $st = DB::pdo()->prepare("
        SELECT
            MAX(file_written_at) AS last_file_written_at,
            SUM(CASE WHEN file_written = 1 THEN 1 ELSE 0 END) AS written_cnt
        FROM webmaster_hosts
        WHERE site_id = ?
    ");
    $st->execute([$siteId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $writtenAt = (string)($row['last_file_written_at'] ?? '');
    $writtenCnt = (int)($row['written_cnt'] ?? 0);

    $buildAt  = (string)($this->detectLastBuildAt($siteId) ?? '');
    $deployAt = (string)($site['fp_files_last_ok'] ?? '');

    $writtenTs = $writtenAt !== '' ? (int)strtotime($writtenAt) : 0;
    $buildTs   = $buildAt !== '' ? (int)strtotime($buildAt) : 0;
    $deployTs  = $deployAt !== '' ? (int)strtotime($deployAt) : 0;

    $state = [
        'written_cnt' => $writtenCnt,
        'written_at'  => $writtenAt,
        'build_at'    => $buildAt,
        'deploy_at'   => $deployAt,
        'needs_build' => 0,
        'needs_deploy'=> 0,
        'ok'          => 0,
        'title'       => '',
        'message'     => '',
    ];

    if ($writtenCnt <= 0 || $writtenTs <= 0) {
        return $state;
    }

    if ($buildTs < $writtenTs) {
        $state['needs_build'] = 1;
        $state['title'] = 'Verification-файлы записаны, но Build ещё не выполнен';
        $state['message'] = 'После записи verification-файлов через Webmaster нужно заново сделать Build, иначе файлы не попадут в актуальную build-структуру сайта.';
        return $state;
    }

    if ($deployTs < $buildTs) {
        $state['needs_deploy'] = 1;
        $state['title'] = 'Build выполнен, но файлы ещё не опубликованы на VPS';
        $state['message'] = 'Verification-файлы уже попали в build, но ещё не выгружены на боевой сайт. Выполните публикацию на VPS.';
        return $state;
    }

    $state['ok'] = 1;
    $state['title'] = 'Verification-файлы актуальны';
    $state['message'] = 'Файлы подтверждения уже записаны, собраны и опубликованы на VPS.';
    return $state;
}


}

