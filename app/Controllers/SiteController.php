<?php

class SiteController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    public function index(): void
{
    $this->requireAuth();

    $sites = DB::pdo()->query('SELECT * FROM sites ORDER BY id DESC')->fetchAll();

    // runtime SSL статусы из FastPanel (группируем по server_id, логин 1 раз на сервер)
    $sslMap = $this->fetchSslStatusForSites($sites);

    foreach ($sites as &$s) {
        $id = (int)($s['id'] ?? 0);
        $st = $sslMap[$id] ?? [];

        $s['ssl_ready']    = (int)($st['ready'] ?? 0);     // enabled==true
        $s['ssl_has_cert'] = (int)($st['has_cert'] ?? 0);  // certId>0
        $s['ssl_cert_id']  = (int)($st['cert_id'] ?? 0);
        $s['ssl_error']    = (string)($st['error'] ?? '');
    }
    unset($s);

    $this->view('sites/index', compact('sites'));
}




   public function createForm(): void
{
    $this->requireAuth();

    require_once __DIR__ . '/../Services/TemplateService.php';
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

    $domainInput = (string)($_POST['domain'] ?? '');
    $template    = trim((string)($_POST['template'] ?? 'default'));

    $domain = $this->normalizeDomainInput($domainInput);

    if ($domain === '' || !$this->isValidDomain($domain)) {
        die('bad domain');
    }

    DB::pdo()->beginTransaction();

    try {
        $configPath = "storage/configs/site_" . time() . ".json";

        $stmt = DB::pdo()->prepare(
            "INSERT INTO sites (domain, template, config_path)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$domain, $template, $configPath]);
        $siteId = (int)DB::pdo()->lastInsertId();

        $cfg = $this->defaultConfig($domain);
        $stmt = DB::pdo()->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
        $stmt->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);

        require_once __DIR__ . '/../Services/TemplateService.php';

        $buildRel = 'storage/builds/site_' . $siteId;
        $buildAbs = __DIR__ . '/../../' . $buildRel;

        (new TemplateService())->copyTemplate($template, $buildAbs);

        $stmt = DB::pdo()->prepare("UPDATE sites SET build_path=? WHERE id=?");
        $stmt->execute([$buildRel, $siteId]);

        $this->regenerateConfigPhp($siteId, $cfg);

        DB::pdo()->commit();
        $this->redirect('/');
    } catch (Throwable $e) {
        DB::pdo()->rollBack();
        http_response_code(500);
        echo $e->getMessage();
    }
}



public function editForm(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    [$site, $cfg] = $this->loadSiteAndConfig($siteId);

    $configTargetPath = $this->getConfigTargetPath($siteId);

    // --- NEW: registrar accounts list (Namecheap) ---
    $pdo = DB::pdo();
    $st = $pdo->prepare("
        SELECT id, provider, is_sandbox, api_user, username, client_ip, is_default
        FROM registrar_accounts
        WHERE provider='namecheap'
        ORDER BY is_default DESC, is_sandbox ASC, id ASC
    ");
    $st->execute();
    $registrarAccounts = $st->fetchAll();
    // --- /NEW ---

    $this->view('sites/edit', compact('site', 'cfg', 'configTargetPath', 'registrarAccounts'));
}




    public function update(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    [$site, $cfg] = $this->loadSiteAndConfig($siteId);

    // обновляем поля
    $cfg['domain'] = $this->normalizeDomainInput((string)($_POST['domain'] ?? (string)$cfg['domain']));

    if ($cfg['domain'] === '' || !$this->isValidDomain($cfg['domain'])) {
        die('bad domain');
    }

    $cfg['yandex_verification'] = trim($_POST['yandex_verification'] ?? '');
    $cfg['yandex_metrika']      = trim($_POST['yandex_metrika'] ?? '');
    $cfg['promolink']           = trim($_POST['promolink'] ?? '/play');

    $cfg['title']       = trim($_POST['title'] ?? '');
    $cfg['description'] = trim($_POST['description'] ?? '');
    $cfg['keywords']    = trim($_POST['keywords'] ?? '');
    $cfg['h1']          = trim($_POST['h1'] ?? '');

    $cfg['partner_override_url'] = trim($_POST['partner_override_url'] ?? '');
    $cfg['internal_reg_url']     = trim($_POST['internal_reg_url'] ?? '');
    $cfg['redirect_enabled']     = (int)($_POST['redirect_enabled'] ?? 0);
    $cfg['base_new_url']         = trim($_POST['base_new_url'] ?? '');
    $cfg['base_second_url']      = trim($_POST['base_second_url'] ?? '');

    // --- NEW: registrar account id ---
    $registrarAccountId = (int)($_POST['registrar_account_id'] ?? 0);
    if ($registrarAccountId <= 0) {
        $registrarAccountId = null; // снимем привязку
    } else {
        // optional: проверим что такой аккаунт существует
        $chk = DB::pdo()->prepare("SELECT id FROM registrar_accounts WHERE id=? AND provider='namecheap' LIMIT 1");
        $chk->execute([$registrarAccountId]);
        if (!$chk->fetchColumn()) {
            $registrarAccountId = null;
        }
    }
    // --- /NEW ---

    // сохраняем config json
    $stmt = DB::pdo()->prepare("UPDATE site_configs SET json=? WHERE site_id=?");
    $stmt->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $siteId]);

    // синхронизируем домен и в sites
    $stmt = DB::pdo()->prepare("UPDATE sites SET domain=? WHERE id=?");
    $stmt->execute([$cfg['domain'], $siteId]);

    // --- NEW: update registrar_account_id отдельно, чтобы не словить HY093 ---
    $stmt = DB::pdo()->prepare("UPDATE sites SET registrar_account_id=? WHERE id=?");
    $stmt->execute([$registrarAccountId, $siteId]);
    // --- /NEW ---

    // перегенерация config.php
    $this->regenerateConfigPhp($siteId, $cfg);

    $this->redirect('/sites/edit?id=' . $siteId);
}


    public function pagesForm(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		[$site, $cfg] = $this->loadSiteAndConfig($siteId);
		$pages = $cfg['pages'] ?? [];

		$textsDir = $this->getTextsDir($site);
		$textFiles = $this->listTextFiles($textsDir);

		$used = [];
		foreach ($pages as $p) {
			$f = basename((string)($p['text_file'] ?? ''));
			if ($f !== '') $used[$f] = true;
		}

		$configTargetPath = $this->getConfigTargetPath($siteId);

		$this->view('sites/pages', compact('site', 'cfg', 'pages', 'textFiles', 'used', 'configTargetPath'));

	}
	
	
	public function pagesTextNew(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$newFile = (string)($_POST['new_file'] ?? '');
		if ($newFile === '') die('new_file required');

		$site = $this->loadSite($siteId);
		$textsDir = $this->getTextsDir($site);

		$safeFile = $this->sanitizeTextFilename($newFile);
		$path = $textsDir . '/' . $safeFile;

		if (!is_file($path)) {
			file_put_contents($path, "<?php\n\n");
		}

		// открыть редактор
		$this->redirect('/sites/texts/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
	}


    public function pagesUpdate(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) die('bad id');

        [$site, $cfg] = $this->loadSiteAndConfig($siteId);

        $urls        = $_POST['url'] ?? [];
        $titles      = $_POST['title'] ?? [];
        $h1s         = $_POST['h1'] ?? [];
        $descs       = $_POST['description'] ?? [];
        $keys        = $_POST['keywords'] ?? [];
        $texts       = $_POST['text_file'] ?? [];
        $priorities  = $_POST['priority'] ?? [];
        $sitemaps    = $_POST['sitemap'] ?? [];

        $newPages = [];

        foreach ($urls as $i => $url) {
            $url = trim((string)$url);
            if ($url === '') continue;

            // нормализуем url
            if ($url[0] !== '/') $url = '/' . $url;

            $newPages[$url] = [
                'title'       => $this->inheritOrValue($titles[$i] ?? ''),
                'h1'          => $this->inheritOrValue($h1s[$i] ?? ''),
                'description' => $this->inheritOrValue($descs[$i] ?? ''),
                'keywords'    => $this->inheritOrValue($keys[$i] ?? ''),
                'text_file'   => basename(trim((string)($texts[$i] ?? 'home.php'))),
            ];

            $p = trim((string)($priorities[$i] ?? ''));
            if ($p !== '') {
                $newPages[$url]['priority'] = $p;
            }

            // sitemap checkbox: если НЕ отмечен — false
            if (!isset($sitemaps[$i])) {
                $newPages[$url]['sitemap'] = false;
            }
        }

        $cfg['pages'] = $newPages;

        $stmt = DB::pdo()->prepare("UPDATE site_configs SET json=? WHERE site_id=?");
        $stmt->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $siteId]);

        $this->regenerateConfigPhp($siteId, $cfg);

        $this->redirect('/sites/pages?id=' . $siteId);
    }
	
	public function textsIndex(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$site = $this->loadSite($siteId);
		$configTargetPath = $this->getConfigTargetPath($siteId);


		$textsDir = $this->getTextsDir($site);
		$files = $this->listTextFiles($textsDir);

		$this->view('texts/index', compact('site', 'files', 'configTargetPath'));

	}

	public function textsEdit(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		$file   = (string)($_GET['file'] ?? '');

		if ($siteId <= 0) die('bad id');

		$site = $this->loadSite($siteId);
		$configTargetPath = $this->getConfigTargetPath($siteId);
		$textsDir = $this->getTextsDir($site);

		$safeFile = $this->sanitizeTextFilename($file);
		$path = $textsDir . '/' . $safeFile;

		if (!is_file($path)) {
			http_response_code(404);
			echo 'file not found';
			return;
		}

		$content = file_get_contents($path);
		if ($content === false) $content = '';

		$this->view('texts/edit', compact('site', 'safeFile', 'content', 'configTargetPath'));

	}

	public function textsSave(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$file = (string)($_POST['file'] ?? '');
		$content = (string)($_POST['content'] ?? '');

		$site = $this->loadSite($siteId);
		$textsDir = $this->getTextsDir($site);

		$safeFile = $this->sanitizeTextFilename($file);
		$path = $textsDir . '/' . $safeFile;

		// атомарная запись
		$tmp = $path . '.tmp_' . time();
		file_put_contents($tmp, $content);
		rename($tmp, $path);

		$this->redirect('/sites/texts/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
	}

	public function textsNew(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$newFile = (string)($_POST['new_file'] ?? '');
		$site = $this->loadSite($siteId);
		$textsDir = $this->getTextsDir($site);

		$safeFile = $this->sanitizeTextFilename($newFile);

		$path = $textsDir . '/' . $safeFile;
		if (is_file($path)) {
			die('file already exists');
		}

		file_put_contents($path, "<?php\n\n");
		$this->redirect('/sites/texts/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
	}

	public function textsDelete(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$file = (string)($_POST['file'] ?? '');

		$site = $this->loadSite($siteId);
		$textsDir = $this->getTextsDir($site);

		$safeFile = $this->sanitizeTextFilename($file);
		$path = $textsDir . '/' . $safeFile;

		if (is_file($path)) {
			@unlink($path);
		}

		$this->redirect('/sites/texts?id=' . $siteId);
	}


    private function inheritOrValue(string $v): string
    {
        $v = trim($v);
        // если поле пустое — считаем inherit (как у тебя на главной)
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

		// если конфига нет — создаем дефолтный
		if (!$row) {
			$cfg = $this->defaultConfig((string)$site['domain']);
			$stmtIns = DB::pdo()->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?)");
			$stmtIns->execute([$siteId, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
			return [$site, $cfg];
		}

		$cfg = json_decode($row['json'], true);
		if (!is_array($cfg)) $cfg = [];

		return [$site, $cfg];
	}


	 private function regenerateConfigPhp(int $siteId, array $cfg): void
	{
		require_once __DIR__ . '/../Services/SiteConfigWriter.php';

		// Берем build_path из sites
		$stmt = DB::pdo()->prepare("SELECT build_path FROM sites WHERE id=?");
		$stmt->execute([$siteId]);
		$row = $stmt->fetch();

		if ($row && !empty($row['build_path'])) {
			// Пишем в build-папку (рядом с index.php)
			$dir = __DIR__ . '/../../' . ltrim((string)$row['build_path'], '/');
		} else {
			// Фоллбек (если build_path еще нет)
			$dir = __DIR__ . '/../../storage/generated/site_' . $siteId;
		}

		(new SiteConfigWriter())->write($dir, $cfg);
	}



    private function defaultConfig(string $domain): array
    {
        return [
            'domain' => $domain,
            'yandex_verification' => '',
            'yandex_metrika' => '',
            'promolink' => '/play',

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
	
	public function delete(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    // 1) грузим сайт заранее (нужны build_path, fp_site_id, server_id)
    $stmt = DB::pdo()->prepare("SELECT * FROM sites WHERE id=?");
    $stmt->execute([$siteId]);
    $site = $stmt->fetch();
    if (!$site) {
        $this->redirect('/');
    }

    // пути для локальной чистки (соберем заранее)
    $buildRel = (string)($site['build_path'] ?? '');
    $buildAbs = $buildRel !== '' ? (__DIR__ . '/../../' . ltrim($buildRel, '/')) : '';
    $generatedAbs = __DIR__ . '/../../storage/generated/site_' . $siteId;

    // 2) СНАЧАЛА удаляем на сервере (FastPanel)
    $serverId = (int)($site['fastpanel_server_id'] ?? 0);
    $fpSiteId = (int)($site['fp_site_id'] ?? 0);
    $created  = (int)($site['fp_site_created'] ?? 0) === 1;

    if ($created && $serverId > 0 && $fpSiteId > 0) {
        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/FastpanelClient.php';

        // грузим сервер
        $st = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
        $st->execute([$serverId]);
        $server = $st->fetch();
        if (!$server) {
            http_response_code(500);
            echo "FastPanel server not found (id={$serverId}). Site not deleted.";
            return;
        }

        try {
            $password = Crypto::decrypt((string)$server['password_enc']);

            $client = new FastpanelClient(
                (string)$server['host'],
                (bool)$server['verify_tls'],
                (int)config('fastpanel.timeout', 30)
            );
            $client->login((string)$server['username'], $password);

            // удаляем сайт в FastPanel
            $client->deleteSite($fpSiteId);

        } catch (Throwable $e) {
            // ВАЖНО: если не удалилось на сервере — не удаляем локально,
            // чтобы не потерять состояние и можно было повторить.
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "FastPanel delete failed: " . $e->getMessage();
            return;
        }
    }

    // 3) Теперь удаляем в панели (БД) + чистим локальные папки
    DB::pdo()->beginTransaction();

    try {
        // Если site_configs с FK ON DELETE CASCADE — можно только sites.
        // Но безопаснее явно:
        DB::pdo()->prepare("DELETE FROM site_configs WHERE site_id=?")->execute([$siteId]);
        DB::pdo()->prepare("DELETE FROM sites WHERE id=?")->execute([$siteId]);

        DB::pdo()->commit();

        // 4) удалить локальные build / generated папки
        if ($buildAbs !== '' && is_dir($buildAbs)) {
            $this->rrmdir($buildAbs);
        }
        if (is_dir($generatedAbs)) {
            $this->rrmdir($generatedAbs);
        }

        // 5) опционально: удалить zip (если хочешь чистить storage/zips)
        $zipPath = __DIR__ . '/../../storage/zips/site_' . $siteId . '.zip';
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
	
	public function exportZip(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		$stmt = DB::pdo()->prepare("SELECT * FROM sites WHERE id=?");
		$stmt->execute([$siteId]);
		$site = $stmt->fetch();
		if (!$site) die('site not found');

		$buildRel = $site['build_path'] ?? '';
		if ($buildRel === '') die('build not found');

		$buildAbs = __DIR__ . '/../../' . ltrim($buildRel, '/');

		require_once __DIR__ . '/../Services/ZipService.php';

		$zipDir = __DIR__ . '/../../storage/zips';
		if (!is_dir($zipDir)) mkdir($zipDir, 0775, true);

		$zipPath = $zipDir . '/site_' . $siteId . '.zip';
		(new ZipService())->makeZip($buildAbs, $zipPath);

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="site_' . $siteId . '.zip"');
		header('Content-Length: ' . filesize($zipPath));
		readfile($zipPath);
		exit;
	}


	private function loadSite(int $siteId): array
	{
		$stmt = DB::pdo()->prepare('SELECT * FROM sites WHERE id=?');
		$stmt->execute([$siteId]);
		$site = $stmt->fetch();
		if (!$site) die('site not found');
		return $site;
	}

	private function getTextsDir(array $site): string
	{
		$buildRel = (string)($site['build_path'] ?? '');
		if ($buildRel === '') die('build_path empty');

		$buildAbs = __DIR__ . '/../../' . ltrim($buildRel, '/');
		$textsDir = $buildAbs . '/texts';

		if (!is_dir($textsDir)) {
			// если вдруг в шаблоне нет texts, создадим
			mkdir($textsDir, 0775, true);
		}

		return $textsDir;
	}

	private function listTextFiles(string $textsDir): array
	{
		$items = scandir($textsDir);
		if ($items === false) return [];

		$files = [];
		foreach ($items as $f) {
			if ($f === '.' || $f === '..') continue;
			$path = $textsDir . '/' . $f;
			if (is_file($path) && preg_match('~\.php$~i', $f)) {
				$files[] = $f;
			}
		}
		sort($files);
		return $files;
	}

	/**
	 * Разрешаем только простые имена вида: home.php, play.php, 404.php
	 * Запрещаем слеши, точки типа ../ и любые странные символы.
	 */
	private function sanitizeTextFilename(string $name): string
	{
		$name = trim($name);

		// часто пользователи вводят "/home.php"
		$name = ltrim($name, '/\\');

		// базовая валидация
		if ($name === '' || strlen($name) > 120) {
			die('bad filename');
		}

		// только латиница/цифры/подчеркивание/дефис/точка
		if (!preg_match('~^[a-zA-Z0-9_\-\.]+$~', $name)) {
			die('bad filename');
		}

		// запрет на ../
		if (str_contains($name, '..')) {
			die('bad filename');
		}

		// принудительно .php
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
		];
	}
	
	private function getBuildDir(array $site): string
	{
		$buildRel = (string)($site['build_path'] ?? '');
		if ($buildRel === '') die('build_path empty');

		return __DIR__ . '/../../' . ltrim($buildRel, '/');
	}
	
	private function sanitizeAllowedFile(string $file): string
	{
		$file = trim($file);

		// запрещаем слеши и traversal
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
			$path = $buildDir . '/' . $f;
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
		$path = $buildDir . '/' . $safeFile;

		$content = '';
		if (is_file($path)) {
			$c = file_get_contents($path);
			$content = ($c === false) ? '' : $c;
		}

		// найти бэкапы
		$backups = [];
		$pattern = $buildDir . '/' . $safeFile . '.bak_*';
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

		$file = (string)($_POST['file'] ?? '');
		$content = (string)($_POST['content'] ?? '');

		$site = $this->loadSite($siteId);
		$buildDir = $this->getBuildDir($site);

		$safeFile = $this->sanitizeAllowedFile($file);
		$path = $buildDir . '/' . $safeFile;

		// backup если файл существует
		if (is_file($path)) {
			$ts = date('Ymd_His');
			$bak = $path . '.bak_' . $ts;
			@copy($path, $bak);
		}

		// атомарная запись
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

		$file = (string)($_POST['file'] ?? '');
		$backup = (string)($_POST['backup'] ?? '');

		$site = $this->loadSite($siteId);
		$buildDir = $this->getBuildDir($site);

		$safeFile = $this->sanitizeAllowedFile($file);

		// backup имя: "<file>.bak_YYYYMMDD_HHMMSS"
		if (strpos($backup, $safeFile . '.bak_') !== 0) {
			die('bad backup');
		}
		if (strpos($backup, '/') !== false || strpos($backup, '\\') !== false || strpos($backup, '..') !== false) {
			die('bad backup');
		}

		$src = $buildDir . '/' . $backup;
		$dst = $buildDir . '/' . $safeFile;

		if (!is_file($src)) {
			die('backup not found');
		}

		// перед восстановлением делаем бэкап текущего
		if (is_file($dst)) {
			$ts = date('Ymd_His');
			@copy($dst, $dst . '.bak_' . $ts);
		}

		@copy($src, $dst);

		$this->redirect('/sites/files/edit?id=' . $siteId . '&file=' . rawurlencode($safeFile));
	}

	private function getConfigTargetPath(int $siteId): string
	{
		$stmt = DB::pdo()->prepare("SELECT build_path FROM sites WHERE id=?");
		$stmt->execute([$siteId]);
		$row = $stmt->fetch();

		if ($row && !empty($row['build_path'])) {
			return rtrim((string)$row['build_path'], '/') . '/config.php';
		}

		return 'storage/generated/site_' . $siteId . '/config.php';
	}

	public function build(): void
	{
		$this->requireAuth();

		$siteId = (int)($_GET['id'] ?? 0);
		if ($siteId <= 0) die('bad id');

		[$site, $cfg] = $this->loadSiteAndConfig($siteId);

		// куда пишем/где лежит сайт
		$buildDir = $this->getBuildDir($site);
		$textsDir = $this->getTextsDir($site);

		$pages = $cfg['pages'] ?? [];
		if (!is_array($pages)) $pages = [];

		$report = [
			'ok' => true,
			'errors' => [],
			'warnings' => [],
			'created_texts' => [],
			'unused_texts' => [],
		];

		// 1) Проверка text_file в pages
		$used = [];
		foreach ($pages as $url => $p) {
			$tf = (string)($p['text_file'] ?? '');
			$tf = basename(trim($tf));
			if ($tf === '') {
				$report['warnings'][] = "Страница {$url}: text_file пустой";
				continue;
			}

			// нормализуем расширение
			if (!preg_match('~\.php$~i', $tf)) $tf .= '.php';

			$used[$tf] = true;

			$path = $textsDir . '/' . $tf;
			if (!is_file($path)) {
				// создаем отсутствующий файл (чтобы не ломался сайт)
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

		// 2) Найти неиспользуемые тексты
		$allTextFiles = $this->listTextFiles($textsDir);
		foreach ($allTextFiles as $f) {
			if (!isset($used[$f])) {
				$report['unused_texts'][] = $f;
			}
		}

		if (!empty($report['unused_texts'])) {
			$report['warnings'][] = 'Есть неиспользуемые texts-файлы: ' . implode(', ', $report['unused_texts']);
		}

		// 3) Перегенерировать config.php
		try {
			$this->regenerateConfigPhp($siteId, $cfg);
		} catch (Throwable $e) {
			$report['ok'] = false;
			$report['errors'][] = 'Ошибка генерации config.php: ' . $e->getMessage();
		}

		// 4) Сохранить отчет в storage (и показать)
		$logDir = __DIR__ . '/../../storage/build_reports';
		if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

		$ts = date('Ymd_His');
		$reportPath = $logDir . "/site_{$siteId}_{$ts}.json";
		@file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

		// Показать отчет
		$configTargetPath = $this->getConfigTargetPath($siteId);
		$this->view('sites/build', compact('site', 'cfg', 'report', 'configTargetPath'));
	}
	
	public function resetFastpanelState(): void
{
    $this->requireAuth();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) die('bad id');

    $stmt = DB::pdo()->prepare("
        UPDATE sites SET
            fp_site_created = 0,
            fp_site_id = NULL,
            fp_index_dir = NULL,
            fp_ftp_ready = 0,
            fp_ftp_user = NULL,
            fp_ftp_last_ok = NULL,
            fp_files_ready = 0,
            fp_files_last_ok = NULL
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $this->redirect('/');
}
private function loadServer(int $id): array
{
    $stmt = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('server not found: ' . $id);
    return $row;
}

private function fetchSslStatusForSites(array $sites): array
{
    require_once __DIR__ . '/../Services/Crypto.php';
    require_once __DIR__ . '/../Services/FastpanelClient.php';

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

public function checkDomain(): void
{
    $this->requireAuth();

    $domainInput = trim((string)($_POST['domain'] ?? ''));
    $template    = trim((string)($_POST['template'] ?? 'template-basic'));

    require_once __DIR__ . '/../Services/TemplateService.php';
    $templates = (new TemplateService())->listTemplates();
	
	$accounts = DB::pdo()->query("
		SELECT * FROM registrar_accounts
		WHERE provider='namecheap'
		ORDER BY is_sandbox ASC, id DESC
	")->fetchAll();


    if ($domainInput === '') {
        $this->view('sites/create', [
            'templates' => $templates,
            'domain'    => $domainInput,
            'template'  => $template,
			'accounts'  => $accounts,
			'registrar_account_id' => (int)($_POST['registrar_account_id'] ?? 0),
            'checkResult' => ['error' => 'Введите домен'],
        ]);
        return;
    }

    $domain = $this->normalizeDomainInput($domainInput);

    if ($domain === '' || !$this->isValidDomain($domain)) {
        $this->view('sites/create', [
            'templates' => $templates,
            'domain'    => $domainInput,
            'template'  => $template,
			'accounts'  => $accounts,
			'registrar_account_id' => (int)($_POST['registrar_account_id'] ?? 0),
            'checkResult' => [
                'domain' => $domain,
                'error'  => 'Некорректныи формат домена',
            ],
        ]);
        return;
    }

   $accountId = (int)($_POST['registrar_account_id'] ?? 0);

	$acc = null;
	if ($accountId > 0) {
		$st = DB::pdo()->prepare("SELECT * FROM registrar_accounts WHERE id=? AND provider='namecheap'");
		$st->execute([$accountId]);
		$acc = $st->fetch();
	}

	if (!$acc) {
		// fallback: последний (чтобы не падало)
		$acc = DB::pdo()->query("
			SELECT * FROM registrar_accounts
			WHERE provider='namecheap'
			ORDER BY id DESC
			LIMIT 1
		")->fetch();
	}

    if (!$acc) {
        $this->view('sites/create', [
            'templates' => $templates,
            'domain'    => $domainInput,
            'template'  => $template,
			'accounts'  => $accounts,
			'registrar_account_id' => (int)($_POST['registrar_account_id'] ?? 0),
            'checkResult' => [
                'domain' => $domain,
                'error'  => 'Не наиден аккаунт регистратора (сначала добавьте Namecheap account)',
            ],
        ]);
        return;
    }

    require_once __DIR__ . '/../Services/Crypto.php';
    require_once __DIR__ . '/../Services/NamecheapClient.php';

    try {
        $isSandbox = (int)($acc['is_sandbox'] ?? 1) === 1;
        $endpoint  = $isSandbox ? (string)config('namecheap.endpoint_sandbox') : (string)config('namecheap.endpoint');
        $apiKey    = Crypto::decrypt((string)$acc['api_key_enc']);

        $client = new NamecheapClient(
            $endpoint,
            (string)$acc['api_user'],
            $apiKey,
            (string)$acc['username'],
            (string)$acc['client_ip'],
            (int)config('namecheap.timeout', 30)
        );

        $check = $client->checkDomain($domain);
        [, $tld] = $client->splitSldTld($domain);

        $decision = 'checked';
        $max = (float)config('namecheap.max_price_usd', 200);

        $price = 0.0;
        $variants = null;

        // premium: если премиум — берем PremiumRegistrationPrice (как раньше),
        // а variants можно попытаться тоже вытащить (но часто для premium оно не релевантно)
        if (!empty($check['premium'])) {
            $raw = $check['raw'] ?? [];
            $premiumPrice = $raw['@PremiumRegistrationPrice'] ?? null;
            if (is_numeric($premiumPrice)) {
                $price = (float)$premiumPrice;
            } else {
                // fallback
                $variants = $client->getPricingRegister1YVariants($tld);
                $price = (float)$variants['price'];
            }
        } else {
            // ВОТ ТУТ ГЛАВНОЕ: берем variants
            $variants = $client->getPricingRegister1YVariants($tld);
            $price = (float)$variants['price'];
        }

        if (($check['available'] ?? false) !== true) {
            $decision = 'unavailable';
        } elseif ($price > $max) {
            $decision = 'too_expensive';
        }

        $this->view('sites/create', [
            'templates' => $templates,
            'domain'    => $domainInput, // как ввели
            'template'  => $template,
			'accounts'  => $accounts,
			'registrar_account_id' => (int)($_POST['registrar_account_id'] ?? 0),
            'checkResult' => [
                'domain'    => $domain,
                'available' => (bool)($check['available'] ?? false),
                'premium'   => (bool)($check['premium'] ?? false),
                'price_usd' => $price,
                'decision'  => $decision,

                // ДОБАВИЛИ: variants/pricing для отображения
                'pricing' => $variants ? [
                    'price'         => $variants['price'],
                    'regular_price' => $variants['regular_price'],
                    'your_price'    => $variants['your_price'],
                    'coupon_price'  => $variants['coupon_price'],
                    'promo_code'    => $variants['promo_code'] ?? null,
                    'candidates'    => $variants['candidates'] ?? [],
                ] : null,
            ],
        ]);
    } catch (Throwable $e) {
        $this->view('sites/create', [
            'templates' => $templates,
            'domain'    => $domainInput,
            'template'  => $template,
			'accounts'  => $accounts,
			'registrar_account_id' => (int)($_POST['registrar_account_id'] ?? 0),
            'checkResult' => [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ],
        ]);
    }
}


private function normalizeDomainInput(string $input): string
{
    $s = trim($input);

    // убираем протокол
    $s = preg_replace('~^https?://~i', '', $s);

    // убираем www. (по желанию, я бы убирал)
    $s = preg_replace('~^www\.~i', '', $s);

    // отрезаем / ? # и все что дальше
    $parts = preg_split('~[/?#]~', $s, 2);
    $s = (string)($parts[0] ?? '');

    $s = strtolower(trim($s));
    $s = rtrim($s, "./");

    return $s;
}

private function isValidDomain(string $domain): bool
{
    // базовая проверка (без IDN/punycode)
    // пример валидных: a-b.com, testovoe.casino
    return (bool)preg_match('~^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)+$~i', $domain);
}

public function deleteRemote(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) die('bad id');

    $site = $this->loadSite($siteId);

    $serverId = (int)($site['fastpanel_server_id'] ?? 0);
    $fpSiteId = (int)($site['fp_site_id'] ?? 0);
    $created  = (int)($site['fp_site_created'] ?? 0) === 1;

    // 1) Сначала удаляем в FastPanel (если сайт реально создавался)
    if ($created && $serverId > 0 && $fpSiteId > 0) {
        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/FastpanelClient.php';

        $server = $this->loadServer($serverId); // у тебя такой helper уже есть в SiteController
        $password = Crypto::decrypt((string)$server['password_enc']);

        $client = new FastpanelClient(
            (string)$server['host'],
            (bool)$server['verify_tls'],
            (int)config('fastpanel.timeout', 30)
        );
        $client->login((string)$server['username'], $password);

        // Удаляем сайт на сервере
        $client->deleteSite($fpSiteId);

        // (опционально) удалить FTP-аккаунт, если ты хранишь его id
        // $ftpId = (int)($site['fp_ftp_id'] ?? 0);
        // if ($ftpId > 0) $client->deleteFtpAccount($ftpId);
    }

    // 2) Потом удаляем в панели (БД + storage как в твоем delete())
    $_GET['id'] = $siteId;
    $this->delete();
}


}


