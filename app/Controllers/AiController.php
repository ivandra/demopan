<?php

class AiController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    private function h(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES);
    }

private function loadRow(): array
{
    $st = DB::pdo()->prepare("SELECT * FROM ai_settings WHERE id = 1 LIMIT 1");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        DB::pdo()->prepare("
            INSERT INTO ai_settings
            (
              id,
              provider,
              api_key_enc,
              model,
              temperature,
              max_tokens,
              prompt_v1,
              prompt_v2,
              meta_prompt_root,
              meta_prompt_sub,
              text_prompt_root,
              text_prompt_sub,
              page_prompt,
              page_meta_prompt
            )
            VALUES
            (
              1,
              'deepseek',
              '',
              'deepseek-chat',
              0.70,
              1200,
              '',
              '',
              '',
              '',
              '',
              '',
              '',
              ''
            )
        ")->execute();

        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
    }

    return $row ?: [];
}

    public function settings(): void
    {
        $this->requireAuth();

        $row = $this->loadRow();

        $apiKey = '';
        if (!empty($row['api_key_enc'])) {
            try {
                $apiKey = Crypto::decrypt((string)$row['api_key_enc']);
            } catch (Throwable $e) {
                $apiKey = '';
            }
        }

        $this->view('ai/settings', [
            'row' => $row,
            'apiKey' => $apiKey,
        ]);
    }

	 public function settingsSave(): void
	{
		$this->requireAuth();

		$provider        = trim((string)($_POST['provider'] ?? 'deepseek'));
		$apiKey          = trim((string)($_POST['api_key'] ?? ''));
		$model           = trim((string)($_POST['model'] ?? 'deepseek-chat'));
		$temperature     = (float)($_POST['temperature'] ?? 0.7);
		$maxTokens       = (int)($_POST['max_tokens'] ?? 1200);

		$promptV1        = trim((string)($_POST['prompt_v1'] ?? ''));
		$promptV2        = trim((string)($_POST['prompt_v2'] ?? ''));

		$metaPromptRoot  = trim((string)($_POST['meta_prompt_root'] ?? ''));
		$metaPromptSub   = trim((string)($_POST['meta_prompt_sub'] ?? ''));
		$textPromptRoot  = trim((string)($_POST['text_prompt_root'] ?? ''));
		$textPromptSub   = trim((string)($_POST['text_prompt_sub'] ?? ''));
		$pagePrompt      = trim((string)($_POST['page_prompt'] ?? ''));
		$pageMetaPrompt  = trim((string)($_POST['page_meta_prompt'] ?? ''));

		if ($provider === '') $provider = 'deepseek';
		if ($model === '') $model = 'deepseek-chat';
		if ($temperature < 0) $temperature = 0;
		if ($temperature > 2) $temperature = 2;
		if ($maxTokens < 100) $maxTokens = 100;
		if ($maxTokens > 8000) $maxTokens = 8000;

		$row = $this->loadRow();
		$apiKeyEnc = (string)($row['api_key_enc'] ?? '');

		if ($apiKey !== '') {
			$apiKeyEnc = Crypto::encrypt($apiKey);
		}

		DB::pdo()->prepare("
			UPDATE ai_settings
			SET
			  provider = ?,
			  api_key_enc = ?,
			  model = ?,
			  temperature = ?,
			  max_tokens = ?,
			  prompt_v1 = ?,
			  prompt_v2 = ?,
			  meta_prompt_root = ?,
			  meta_prompt_sub = ?,
			  text_prompt_root = ?,
			  text_prompt_sub = ?,
			  page_prompt = ?,
			  page_meta_prompt = ?
			WHERE id = 1
			LIMIT 1
		")->execute([
			$provider,
			$apiKeyEnc,
			$model,
			$temperature,
			$maxTokens,
			$promptV1,
			$promptV2,
			$metaPromptRoot,
			$metaPromptSub,
			$textPromptRoot,
			$textPromptSub,
			$pagePrompt,
			$pageMetaPrompt,
		]);

		$_SESSION['wm_log'][] = 'AI settings saved';
		$this->redirect('/ai/settings');
	}

    public function test(): void
    {
        $this->requireAuth();

        header('Content-Type: text/html; charset=utf-8');

        try {
            $row = $this->loadRow();

            $apiKey = '';
            if (!empty($row['api_key_enc'])) {
                $apiKey = Crypto::decrypt((string)$row['api_key_enc']);
            }

            if ($apiKey === '') {
                throw new RuntimeException('API key пустой');
            }

            $client = new DeepseekClient($apiKey);

            $text = $client->simpleText(
                'Ты отвечаешь кратко и строго по-русски.',
                'Напиши одну короткую фразу: "DeepSeek API подключен".',
                (string)($row['model'] ?? 'deepseek-chat'),
                (float)($row['temperature'] ?? 0.7),
                (int)($row['max_tokens'] ?? 1200)
            );

            echo '<h2>Проверка AI API</h2>';
            echo '<p><a href="/ai/settings">← Назад к настройкам</a></p>';
            echo '<div style="padding:12px;border-radius:8px;background:#e8fff1;color:#0b6b3a;border:1px solid #b7e7c9;margin-bottom:16px;">OK: API отвечает</div>';
            echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:12px;border-radius:8px;">' . $this->h($text) . '</pre>';

        } catch (Throwable $e) {
            echo '<h2>Проверка AI API</h2>';
            echo '<p><a href="/ai/settings">← Назад к настройкам</a></p>';
            echo '<div style="padding:12px;border-radius:8px;background:#ffecec;color:#9b1c1c;border:1px solid #f2b5b5;">Ошибка: ' . $this->h($e->getMessage()) . '</div>';
        }
    }
	
	public function site(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
    }

    $row = $this->loadRow();

    $this->view('ai/site', [
        'site' => $site,
        'ai' => $row
    ]);
}

public function generateMeta(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $row = $this->loadRow();

    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['prompt_v1'] ?? ''));
    if ($prompt === '') {
        $prompt = 'Ты SEO-копирайтер. Верни строго JSON без пояснений и без markdown: {"title":"","h1":"","description":"","keywords":"","text_html":""}';
    }

    $client = new DeepseekClient($apiKey);

    $domain = (string)($site['domain'] ?? '');

    $userPrompt = "Сайт: {$domain}. Сгенерируй SEO-мета данные для главной страницы. Верни строго JSON с полями: title, h1, description, keywords, text_html";

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)($row['model'] ?? 'deepseek-chat'),
        (float)($row['temperature'] ?? 0.7),
        (int)($row['max_tokens'] ?? 1200)
    );

    $result = trim($result);

    // убираем ```json ... ```
    if (strpos($result, '```') !== false) {
        $result = preg_replace('/```json/i', '', $result);
        $result = str_replace('```', '', $result);
    }

    // вытаскиваем JSON объект
    $start = strpos($result, '{');
    $end   = strrpos($result, '}');

    if ($start !== false && $end !== false && $end > $start) {
        $result = substr($result, $start, $end - $start + 1);
    }

    $json = json_decode($result, true);

    if (!is_array($json)) {
        die('AI вернул не JSON: ' . htmlspecialchars($result, ENT_QUOTES));
    }

    $newTitle       = (string)($json['title'] ?? '');
    $newH1          = (string)($json['h1'] ?? '');
    $newDescription = (string)($json['description'] ?? '');
    $newKeywords    = (string)($json['keywords'] ?? '');

    /*
     * 1. Обновляем site_default_configs
     */
    $st = $pdo->prepare("SELECT * FROM site_default_configs WHERE site_id = ? LIMIT 1");
    $st->execute([$siteId]);
    $defaultRow = $st->fetch(PDO::FETCH_ASSOC);

    $defaultCfg = [];
    if ($defaultRow && !empty($defaultRow['config_json'])) {
        $decoded = json_decode((string)$defaultRow['config_json'], true);
        if (is_array($decoded)) {
            $defaultCfg = $decoded;
        }
    }

    $defaultCfg['title'] = $newTitle;
    $defaultCfg['h1'] = $newH1;
    $defaultCfg['description'] = $newDescription;
    $defaultCfg['keywords'] = $newKeywords;
    $defaultCfg['domain'] = $domain;

    $defaultJson = json_encode($defaultCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($defaultRow) {
        $pdo->prepare("
            UPDATE site_default_configs
            SET config_json = ?
            WHERE site_id = ?
            LIMIT 1
        ")->execute([
            $defaultJson,
            $siteId
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO site_default_configs (site_id, config_json)
            VALUES (?, ?)
        ")->execute([
            $siteId,
            $defaultJson
        ]);
    }

    /*
     * 2. Обновляем site_subdomain_configs для _default
     */
    $st = $pdo->prepare("
        SELECT * 
        FROM site_subdomain_configs
        WHERE site_id = ? AND label = '_default'
        LIMIT 1
    ");
    $st->execute([$siteId]);
    $subRow = $st->fetch(PDO::FETCH_ASSOC);

    $subCfg = [];
    if ($subRow && !empty($subRow['config_json'])) {
        $decoded = json_decode((string)$subRow['config_json'], true);
        if (is_array($decoded)) {
            $subCfg = $decoded;
        }
    }

    $subCfg['title'] = $newTitle;
    $subCfg['h1'] = $newH1;
    $subCfg['description'] = $newDescription;
    $subCfg['keywords'] = $newKeywords;
    $subCfg['domain'] = $domain;
    $subCfg['label'] = '_default';

    $subJson = json_encode($subCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($subRow) {
        $pdo->prepare("
            UPDATE site_subdomain_configs
            SET config_json = ?
            WHERE site_id = ? AND label = '_default'
            LIMIT 1
        ")->execute([
            $subJson,
            $siteId
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO site_subdomain_configs (site_id, label, config_json)
            VALUES (?, '_default', ?)
        ")->execute([
            $siteId,
            $subJson
        ]);
    }

    /*
     * 3. Дополнительно обновляем site_configs,
     * чтобы не было рассинхрона с другими экранами
     */
    $st = $pdo->prepare("SELECT * FROM site_configs WHERE site_id = ? LIMIT 1");
    $st->execute([$siteId]);
    $siteCfgRow = $st->fetch(PDO::FETCH_ASSOC);

    $siteCfg = [];
    if ($siteCfgRow && !empty($siteCfgRow['json'])) {
        $decoded = json_decode((string)$siteCfgRow['json'], true);
        if (is_array($decoded)) {
            $siteCfg = $decoded;
        }
    }

    $siteCfg['title'] = $newTitle;
    $siteCfg['h1'] = $newH1;
    $siteCfg['description'] = $newDescription;
    $siteCfg['keywords'] = $newKeywords;
    $siteCfg['domain'] = $domain;

    $siteCfgJson = json_encode($siteCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($siteCfgRow) {
        $pdo->prepare("
            UPDATE site_configs
            SET json = ?
            WHERE site_id = ?
            LIMIT 1
        ")->execute([
            $siteCfgJson,
            $siteId
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO site_configs (site_id, json)
            VALUES (?, ?)
        ")->execute([
            $siteId,
            $siteCfgJson
        ]);
    }

    $_SESSION['wm_log'][] = 'AI meta generated for site #' . $siteId;

    $this->redirect('/sites/subcfg?id=' . $siteId);
}

public function generateSubdomains(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
    }

    $row = $this->loadRow();

    $apiKey = Crypto::decrypt($row['api_key_enc']);

    $client = new DeepseekClient($apiKey);

    $prompt = (string)($row['meta_prompt_root'] ?? '');
	if ($prompt === '') {
		$prompt = (string)($row['prompt_v1'] ?? '');
	}

    $domain = $site['domain'];

    $st = $pdo->prepare("
        SELECT *
        FROM site_subdomains
        WHERE site_id=?
        AND label <> '_default'
    ");

    $st->execute([$siteId]);

    $subs = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subs as $sub) {

        $label = $sub['label'];

        $userPrompt = "
        Сайт: {$domain}
        Поддомен: {$label}.{$domain}

        Сгенерируй SEO мета.
        ";

        $result = $client->simpleText(
            $prompt,
            $userPrompt,
            $row['model'],
            (float)$row['temperature'],
            (int)$row['max_tokens']
        );

        $result = trim($result);

        if (strpos($result,'```') !== false) {
            $result = preg_replace('/```json/i','',$result);
            $result = str_replace('```','',$result);
        }

        $start = strpos($result,'{');
        $end = strrpos($result,'}');

        if ($start!==false && $end!==false) {
            $result = substr($result,$start,$end-$start+1);
        }

        $json = json_decode($result,true);

        if (!$json) {
            continue;
        }

        $st = $pdo->prepare("
            SELECT *
            FROM site_subdomain_configs
            WHERE site_id=? AND label=?
            LIMIT 1
        ");

        $st->execute([$siteId,$label]);

        $cfg = $st->fetch(PDO::FETCH_ASSOC);

        $config = [];

        if ($cfg && !empty($cfg['config_json'])) {
            $config = json_decode($cfg['config_json'],true);
        }

        $config['title'] = $json['title'] ?? '';
        $config['h1'] = $json['h1'] ?? '';
        $config['description'] = $json['description'] ?? '';
        $config['keywords'] = $json['keywords'] ?? '';

        $jsonCfg = json_encode($config,JSON_UNESCAPED_UNICODE);

        if ($cfg) {

            $pdo->prepare("
                UPDATE site_subdomain_configs
                SET config_json=?
                WHERE site_id=? AND label=?
            ")->execute([$jsonCfg,$siteId,$label]);

        } else {

            $pdo->prepare("
                INSERT INTO site_subdomain_configs
                (site_id,label,config_json)
                VALUES (?,?,?)
            ")->execute([$siteId,$label,$jsonCfg]);

        }

    }

    $_SESSION['wm_log'][] = 'AI subdomains generated';

    $this->redirect('/sites/subdomains?id='.$siteId);
}

public function generateSubMeta(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    $label  = trim((string)($_GET['label'] ?? ''));

    if ($siteId <= 0 || $label === '') {
        $this->redirect('/sites');
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
    }

    $st = $pdo->prepare("
        SELECT *
        FROM site_subdomain_configs
        WHERE site_id = ?
          AND label = ?
        LIMIT 1
    ");
    $st->execute([$siteId, $label]);
    $cfgRow = $st->fetch(PDO::FETCH_ASSOC);

    if (!$cfgRow) {
        $_SESSION['wm_log'][] = 'AI: sub config not found';
        $this->redirect('/sites/subcfg?id=' . $siteId . '&label=' . urlencode($label));
    }

    $row = $this->loadRow();
    $apiKey = Crypto::decrypt((string)$row['api_key_enc']);

    $prompt = (string)($row['meta_prompt_sub'] ?? '');
    if ($prompt === '') {
        $prompt = (string)($row['prompt_v1'] ?? '');
    }

    $domain = (string)($site['domain'] ?? '');
    $fqdn = $label . '.' . $domain;

    $userPrompt = "Основной домен: {$domain}\nПоддомен: {$fqdn}\nLabel: {$label}\nСгенерируй SEO-мета.";

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)$row['model'],
        (float)$row['temperature'],
        (int)$row['max_tokens']
    );

    $result = trim($result);

    if (strpos($result, '```') !== false) {
        $result = preg_replace('/```json/i', '', $result);
        $result = str_replace('```', '', $result);
    }

    $start = strpos($result, '{');
    $end   = strrpos($result, '}');

    if ($start !== false && $end !== false) {
        $result = substr($result, $start, $end - $start + 1);
    }

    $json = json_decode($result, true);

    if (!is_array($json)) {
        die("AI вернул не JSON: " . htmlspecialchars($result));
    }

    $config = json_decode((string)($cfgRow['config_json'] ?? '{}'), true);
    if (!is_array($config)) {
        $config = [];
    }

    $config['title'] = (string)($json['title'] ?? '');
    $config['h1'] = (string)($json['h1'] ?? '');
    $config['description'] = (string)($json['description'] ?? '');
    $config['keywords'] = (string)($json['keywords'] ?? '');

    $pdo->prepare("
        UPDATE site_subdomain_configs
        SET config_json = ?
        WHERE site_id = ?
          AND label = ?
        LIMIT 1
    ")->execute([
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $siteId,
        $label
    ]);

    $_SESSION['wm_log'][] = "AI: sub meta generated for {$fqdn}";
    $this->redirect('/sites/subcfg?id=' . $siteId . '&label=' . urlencode($label));
}

private function resolveBuildDir(array $site): string
{
    $buildPath = trim((string)($site['build_path'] ?? ''));

    if ($buildPath === '') {
        $buildPath = 'builds/site_' . (int)($site['id'] ?? 0);
    }

    $buildPath = str_replace('\\', '/', $buildPath);
    $buildPath = ltrim($buildPath, '/');

    if (strpos($buildPath, 'storage/') === 0) {
        $buildPath = substr($buildPath, 8);
    }

    return rtrim(Paths::storage($buildPath), '/\\');
}

private function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

private function cleanupAiText(string $text): string
{
    $text = trim($text);

    if (strpos($text, '```') !== false) {
        $text = preg_replace('/```html/i', '', $text);
        $text = preg_replace('/```php/i', '', $text);
        $text = str_replace('```', '', $text);
    }

    return trim($text);
}

private function loadSubConfig(int $siteId, string $label): array
{
    $pdo = DB::pdo();

    if ($label === '_default') {
        $st = $pdo->prepare("SELECT config_json FROM site_default_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (is_array($cfg)) {
                return $cfg;
            }
        }

        $st = $pdo->prepare("SELECT json FROM site_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['json'])) {
            $cfg = json_decode((string)$row['json'], true);
            if (is_array($cfg)) {
                return $cfg;
            }
        }

        return [];
    }

    $st = $pdo->prepare("
        SELECT config_json
        FROM site_subdomain_configs
        WHERE site_id = ? AND label = ?
        LIMIT 1
    ");
    $st->execute([$siteId, $label]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['config_json'])) {
        $cfg = json_decode((string)$row['config_json'], true);
        if (is_array($cfg)) {
            return $cfg;
        }
    }

    return [];
}

private function detectHomeTextFile(array $cfg): string
{
    $pages = $cfg['pages'] ?? [];

    if (is_array($pages) && !empty($pages['/']['text_file'])) {
        return trim((string)$pages['/']['text_file']);
    }

    return 'home.php';
}

private function writeSubTextFile(array $site, string $label, string $fileName, string $content): string
{
    $buildDir = $this->resolveBuildDir($site);

    $safeLabel = trim($label);
    if ($safeLabel === '') {
        $safeLabel = '_default';
    }

    $fileName = basename(trim($fileName));
    if ($fileName === '') {
        $fileName = 'home.php';
    }

    $textsDir = $buildDir . '/subs/' . $safeLabel . '/texts';
    $this->ensureDir($textsDir);

    $fullPath = $textsDir . '/' . $fileName;

    file_put_contents($fullPath, $content);

    return $fullPath;
}

public function generateRootText(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $row = $this->loadRow();

    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['text_prompt_root'] ?? ''));
    if ($prompt === '') {
        $prompt = "Ты SEO-копирайтер. Верни только готовый HTML-фрагмент для вставки в body. Без markdown, без тройных кавычек, без пояснений. Используй <p>, <h2>, <ul>, <li> при необходимости.";
    }

    $cfg = $this->loadSubConfig($siteId, '_default');
    $domain = (string)($site['domain'] ?? '');
    $title = (string)($cfg['title'] ?? '');
    $h1 = (string)($cfg['h1'] ?? '');
    $description = (string)($cfg['description'] ?? '');
    $keywords = (string)($cfg['keywords'] ?? '');
    $textFile = $this->detectHomeTextFile($cfg);

    $userPrompt = "Сайт: {$domain}\n"
        . "Это основной домен.\n"
        . "Title: {$title}\n"
        . "H1: {$h1}\n"
        . "Description: {$description}\n"
        . "Keywords: {$keywords}\n"
        . "Нужно сгенерировать содержательный SEO-текст для главной страницы.\n"
        . "Верни только HTML-фрагмент для файла {$textFile}.";

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)($row['model'] ?? 'deepseek-chat'),
        (float)($row['temperature'] ?? 0.7),
        (int)($row['max_tokens'] ?? 1200)
    );

    $html = $this->cleanupAiText($result);

    if ($html === '') {
        die('AI вернул пустой текст');
    }

    $path = $this->writeSubTextFile($site, '_default', $textFile, $html);

    $_SESSION['wm_log'][] = 'AI root text generated: ' . $textFile;
    hub_log('AI_ROOT_TEXT_OK', [
        'site_id' => $siteId,
        'file' => $textFile,
        'path' => $path,
    ]);

    $this->redirect('/sites/texts?id=' . $siteId);
}

public function generateSubText(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    $label  = trim((string)($_GET['label'] ?? ''));

    if ($siteId <= 0 || $label === '') {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $row = $this->loadRow();

    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['text_prompt_sub'] ?? ''));
    if ($prompt === '') {
        $prompt = "Ты SEO-копирайтер. Верни только готовый HTML-фрагмент для вставки в body. Без markdown, без тройных кавычек, без пояснений. Используй <p>, <h2>, <ul>, <li> при необходимости.";
    }

    $cfg = $this->loadSubConfig($siteId, $label);
    $domain = (string)($site['domain'] ?? '');
    $fqdn = $label . '.' . $domain;
    $title = (string)($cfg['title'] ?? '');
    $h1 = (string)($cfg['h1'] ?? '');
    $description = (string)($cfg['description'] ?? '');
    $keywords = (string)($cfg['keywords'] ?? '');
    $textFile = $this->detectHomeTextFile($cfg);

    $userPrompt = "Основной домен: {$domain}\n"
        . "Поддомен: {$fqdn}\n"
        . "Label: {$label}\n"
        . "Title: {$title}\n"
        . "H1: {$h1}\n"
        . "Description: {$description}\n"
        . "Keywords: {$keywords}\n"
        . "Нужно сгенерировать уникальный SEO-текст для главной страницы поддомена.\n"
        . "Верни только HTML-фрагмент для файла {$textFile}.";

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)($row['model'] ?? 'deepseek-chat'),
        (float)($row['temperature'] ?? 0.7),
        (int)($row['max_tokens'] ?? 1200)
    );

    $html = $this->cleanupAiText($result);

    if ($html === '') {
        die('AI вернул пустой текст');
    }

    $path = $this->writeSubTextFile($site, $label, $textFile, $html);

    $_SESSION['wm_log'][] = 'AI sub text generated: ' . $fqdn . ' -> ' . $textFile;
    hub_log('AI_SUB_TEXT_OK', [
        'site_id' => $siteId,
        'label' => $label,
        'fqdn' => $fqdn,
        'file' => $textFile,
        'path' => $path,
    ]);

    $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
}

public function generateAllSubTexts(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $row = $this->loadRow();

    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['text_prompt_sub'] ?? ''));
    if ($prompt === '') {
        $prompt = "Ты SEO-копирайтер. Верни только готовый HTML-фрагмент для вставки в body. Без markdown, без тройных кавычек, без пояснений. Используй <p>, <h2>, <ul>, <li> при необходимости.";
    }

    $st = $pdo->prepare("
        SELECT label
        FROM site_subdomains
        WHERE site_id = ?
          AND enabled = 1
          AND label <> '_default'
        ORDER BY label ASC
    ");
    $st->execute([$siteId]);
    $subs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $client = new DeepseekClient($apiKey);
    $domain = (string)($site['domain'] ?? '');

    $done = 0;
    $errors = 0;

    foreach ($subs as $sub) {
        $label = trim((string)($sub['label'] ?? ''));
        if ($label === '') {
            continue;
        }

        try {
            $cfg = $this->loadSubConfig($siteId, $label);
            $fqdn = $label . '.' . $domain;
            $title = (string)($cfg['title'] ?? '');
            $h1 = (string)($cfg['h1'] ?? '');
            $description = (string)($cfg['description'] ?? '');
            $keywords = (string)($cfg['keywords'] ?? '');
            $textFile = $this->detectHomeTextFile($cfg);

            $userPrompt = "Основной домен: {$domain}\n"
                . "Поддомен: {$fqdn}\n"
                . "Label: {$label}\n"
                . "Title: {$title}\n"
                . "H1: {$h1}\n"
                . "Description: {$description}\n"
                . "Keywords: {$keywords}\n"
                . "Нужно сгенерировать уникальный SEO-текст для главной страницы поддомена.\n"
                . "Верни только HTML-фрагмент для файла {$textFile}.";

            $result = $client->simpleText(
                $prompt,
                $userPrompt,
                (string)($row['model'] ?? 'deepseek-chat'),
                (float)($row['temperature'] ?? 0.7),
                (int)($row['max_tokens'] ?? 1200)
            );

            $html = $this->cleanupAiText($result);
            if ($html === '') {
                throw new RuntimeException('empty AI text');
            }

            $this->writeSubTextFile($site, $label, $textFile, $html);
            $done++;
        } catch (Throwable $e) {
            $errors++;
            hub_log('AI_SUB_TEXT_ERROR', [
                'site_id' => $siteId,
                'label' => $label,
                'err' => $e->getMessage(),
            ]);
        }
    }

    $_SESSION['wm_log'][] = "AI sub texts generated: done={$done}, errors={$errors}";
    $this->redirect('/sites/subdomains?id=' . $siteId);
}

private function loadPagesConfig(int $siteId, string $label = '_default'): array
{
    $cfg = $this->loadSubConfig($siteId, $label);
    $pages = $cfg['pages'] ?? [];

    return is_array($pages) ? $pages : [];
}

private function loadSinglePageConfig(int $siteId, string $label, string $path): array
{
    $pages = $this->loadPagesConfig($siteId, $label);
    $page = $pages[$path] ?? [];

    return is_array($page) ? $page : [];
}

private function saveSinglePageConfig(int $siteId, string $label, string $path, array $pageCfg): void
{
    $pdo = DB::pdo();

    if ($label === '_default') {
        $st = $pdo->prepare("SELECT * FROM site_default_configs WHERE site_id = ? LIMIT 1");
        $st->execute([$siteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $cfg = [];
        if ($row && !empty($row['config_json'])) {
            $cfg = json_decode((string)$row['config_json'], true);
            if (!is_array($cfg)) {
                $cfg = [];
            }
        }

        if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
            $cfg['pages'] = [];
        }

        $cfg['pages'][$path] = $pageCfg;

        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($row) {
            $pdo->prepare("
                UPDATE site_default_configs
                SET config_json = ?
                WHERE site_id = ?
                LIMIT 1
            ")->execute([$json, $siteId]);
        } else {
            $pdo->prepare("
                INSERT INTO site_default_configs (site_id, config_json)
                VALUES (?, ?)
            ")->execute([$siteId, $json]);
        }

        // синхронно обновим site_configs тоже
        $st2 = $pdo->prepare("SELECT * FROM site_configs WHERE site_id = ? LIMIT 1");
        $st2->execute([$siteId]);
        $row2 = $st2->fetch(PDO::FETCH_ASSOC);

        $cfg2 = [];
        if ($row2 && !empty($row2['json'])) {
            $cfg2 = json_decode((string)$row2['json'], true);
            if (!is_array($cfg2)) {
                $cfg2 = [];
            }
        }

        if (!isset($cfg2['pages']) || !is_array($cfg2['pages'])) {
            $cfg2['pages'] = [];
        }

        $cfg2['pages'][$path] = $pageCfg;

        $json2 = json_encode($cfg2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($row2) {
            $pdo->prepare("
                UPDATE site_configs
                SET json = ?
                WHERE site_id = ?
                LIMIT 1
            ")->execute([$json2, $siteId]);
        } else {
            $pdo->prepare("
                INSERT INTO site_configs (site_id, json)
                VALUES (?, ?)
            ")->execute([$siteId, $json2]);
        }

        return;
    }

    $st = $pdo->prepare("
        SELECT * FROM site_subdomain_configs
        WHERE site_id = ? AND label = ?
        LIMIT 1
    ");
    $st->execute([$siteId, $label]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $cfg = [];
    if ($row && !empty($row['config_json'])) {
        $cfg = json_decode((string)$row['config_json'], true);
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }

    if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
        $cfg['pages'] = [];
    }

    $cfg['pages'][$path] = $pageCfg;

    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($row) {
        $pdo->prepare("
            UPDATE site_subdomain_configs
            SET config_json = ?
            WHERE site_id = ? AND label = ?
            LIMIT 1
        ")->execute([$json, $siteId, $label]);
    } else {
        $pdo->prepare("
            INSERT INTO site_subdomain_configs (site_id, label, config_json)
            VALUES (?, ?, ?)
        ")->execute([$siteId, $label, $json]);
    }
}

private function getFqdnForLabel(array $site, string $label): string
{
    $domain = trim((string)($site['domain'] ?? ''));

    if ($label === '_default' || $label === '') {
        return $domain;
    }

    return $label . '.' . $domain;
}

public function generatePageMeta(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    $label  = trim((string)($_GET['label'] ?? '_default'));
    $path   = trim((string)($_GET['path'] ?? '/'));

    if ($siteId <= 0 || $path === '') {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $pageCfg = $this->loadSinglePageConfig($siteId, $label, $path);
    if (!$pageCfg) {
        $_SESSION['wm_log'][] = 'AI: page config not found';
        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        return;
    }

    $row = $this->loadRow();
    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['page_meta_prompt'] ?? ''));
    if ($prompt === '') {
        $prompt = 'Ты SEO-редактор. Верни строго JSON без markdown: {"title":"","h1":"","description":"","keywords":""}';
    }

    $fqdn = $this->getFqdnForLabel($site, $label);
    $currentTitle = (string)($pageCfg['title'] ?? '');
    $currentH1 = (string)($pageCfg['h1'] ?? '');
    $currentDescription = (string)($pageCfg['description'] ?? '');
    $currentKeywords = (string)($pageCfg['keywords'] ?? '');
    $textFile = (string)($pageCfg['text_file'] ?? '');

    $userPrompt = "Хост: {$fqdn}\n"
        . "Страница: {$path}\n"
        . "Текущий title: {$currentTitle}\n"
        . "Текущий h1: {$currentH1}\n"
        . "Текущий description: {$currentDescription}\n"
        . "Текущие keywords: {$currentKeywords}\n"
        . "Файл текста: {$textFile}\n"
        . "Сгенерируй SEO-мета для этой страницы. Верни JSON с полями: title, h1, description, keywords.";

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)($row['model'] ?? 'deepseek-chat'),
        (float)($row['temperature'] ?? 0.7),
        (int)($row['max_tokens'] ?? 1200)
    );

    $result = trim($result);

    if (strpos($result, '```') !== false) {
        $result = preg_replace('/```json/i', '', $result);
        $result = str_replace('```', '', $result);
    }

    $start = strpos($result, '{');
    $end   = strrpos($result, '}');

    if ($start !== false && $end !== false && $end > $start) {
        $result = substr($result, $start, $end - $start + 1);
    }

    $json = json_decode($result, true);

    if (!is_array($json)) {
        die('AI вернул не JSON: ' . htmlspecialchars($result, ENT_QUOTES));
    }

    $pageCfg['title'] = (string)($json['title'] ?? '');
    $pageCfg['h1'] = (string)($json['h1'] ?? '');
    $pageCfg['description'] = (string)($json['description'] ?? '');
    $pageCfg['keywords'] = (string)($json['keywords'] ?? '');

    $this->saveSinglePageConfig($siteId, $label, $path, $pageCfg);

    $_SESSION['wm_log'][] = 'AI page meta generated: ' . $fqdn . ' ' . $path;
    $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
}

public function generatePageText(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    $label  = trim((string)($_GET['label'] ?? '_default'));
    $path   = trim((string)($_GET['path'] ?? '/'));

    if ($siteId <= 0 || $path === '') {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $pageCfg = $this->loadSinglePageConfig($siteId, $label, $path);
    if (!$pageCfg) {
        $_SESSION['wm_log'][] = 'AI: page config not found';
        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        return;
    }

    $textFile = basename(trim((string)($pageCfg['text_file'] ?? '')));
    if ($textFile === '') {
        $_SESSION['wm_log'][] = 'AI: page text_file пустой';
        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        return;
    }

    $row = $this->loadRow();
    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = trim((string)($row['page_prompt'] ?? ''));
    if ($prompt === '') {
        $prompt = 'Ты SEO-копирайтер. Верни только HTML-фрагмент для вставки в body. Без markdown, без пояснений, без тройных кавычек.';
    }

    $fqdn = $this->getFqdnForLabel($site, $label);

    $userPrompt = "Хост: {$fqdn}\n"
        . "Страница: {$path}\n"
        . "Title: " . (string)($pageCfg['title'] ?? '') . "\n"
        . "H1: " . (string)($pageCfg['h1'] ?? '') . "\n"
        . "Description: " . (string)($pageCfg['description'] ?? '') . "\n"
        . "Keywords: " . (string)($pageCfg['keywords'] ?? '') . "\n"
        . "Файл текста: {$textFile}\n"
        . "Сгенерируй SEO-текст для этой страницы и верни только HTML-фрагмент.";

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)($row['model'] ?? 'deepseek-chat'),
        (float)($row['temperature'] ?? 0.7),
        (int)($row['max_tokens'] ?? 1200)
    );

    $html = $this->cleanupAiText($result);

    if ($html === '') {
        die('AI вернул пустой текст');
    }

    $this->writeSubTextFile($site, $label, $textFile, $html);

    $_SESSION['wm_log'][] = 'AI page text generated: ' . $fqdn . ' ' . $path;
    $this->redirect('/sites/texts?id=' . $siteId . '&label=' . urlencode($label));
}

public function generateAllPages(): void
{
    $this->requireAuth();

    $siteId = (int)($_GET['id'] ?? 0);
    $label  = trim((string)($_GET['label'] ?? '_default'));

    if ($siteId <= 0) {
        $this->redirect('/sites');
        return;
    }

    $pdo = DB::pdo();

    $st = $pdo->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
    $st->execute([$siteId]);
    $site = $st->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        $this->redirect('/sites');
        return;
    }

    $pages = $this->loadPagesConfig($siteId, $label);
    if (empty($pages)) {
        $_SESSION['wm_log'][] = 'AI: pages пустые';
        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        return;
    }

    $row = $this->loadRow();
    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        die('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $pagePrompt = trim((string)($row['page_prompt'] ?? ''));
    if ($pagePrompt === '') {
        $pagePrompt = 'Ты SEO-копирайтер. Верни только HTML-фрагмент для вставки в body. Без markdown, без пояснений, без тройных кавычек.';
    }

    $pageMetaPrompt = trim((string)($row['page_meta_prompt'] ?? ''));
    if ($pageMetaPrompt === '') {
        $pageMetaPrompt = 'Ты SEO-редактор. Верни строго JSON без markdown: {"title":"","h1":"","description":"","keywords":""}';
    }

    $client = new DeepseekClient($apiKey);
    $fqdn = $this->getFqdnForLabel($site, $label);

    $done = 0;
    $errors = 0;

    foreach ($pages as $path => $pageCfg) {
        try {
            if (!is_array($pageCfg)) {
                $pageCfg = [];
            }

            $textFile = basename(trim((string)($pageCfg['text_file'] ?? '')));
            if ($textFile === '') {
                continue;
            }

            // META
            $metaPromptUser = "Хост: {$fqdn}\n"
                . "Страница: {$path}\n"
                . "Файл текста: {$textFile}\n"
                . "Сгенерируй SEO-мета для этой страницы. Верни JSON с полями: title, h1, description, keywords.";

            $metaResult = $client->simpleText(
                $pageMetaPrompt,
                $metaPromptUser,
                (string)($row['model'] ?? 'deepseek-chat'),
                (float)($row['temperature'] ?? 0.7),
                (int)($row['max_tokens'] ?? 1200)
            );

            $metaResult = trim($metaResult);

            if (strpos($metaResult, '```') !== false) {
                $metaResult = preg_replace('/```json/i', '', $metaResult);
                $metaResult = str_replace('```', '', $metaResult);
            }

            $start = strpos($metaResult, '{');
            $end   = strrpos($metaResult, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $metaResult = substr($metaResult, $start, $end - $start + 1);
            }

            $metaJson = json_decode($metaResult, true);
            if (is_array($metaJson)) {
                $pageCfg['title'] = (string)($metaJson['title'] ?? '');
                $pageCfg['h1'] = (string)($metaJson['h1'] ?? '');
                $pageCfg['description'] = (string)($metaJson['description'] ?? '');
                $pageCfg['keywords'] = (string)($metaJson['keywords'] ?? '');
            }

            // TEXT
            $textPromptUser = "Хост: {$fqdn}\n"
                . "Страница: {$path}\n"
                . "Title: " . (string)($pageCfg['title'] ?? '') . "\n"
                . "H1: " . (string)($pageCfg['h1'] ?? '') . "\n"
                . "Description: " . (string)($pageCfg['description'] ?? '') . "\n"
                . "Keywords: " . (string)($pageCfg['keywords'] ?? '') . "\n"
                . "Файл текста: {$textFile}\n"
                . "Сгенерируй SEO-текст для этой страницы и верни только HTML-фрагмент.";

            $textResult = $client->simpleText(
                $pagePrompt,
                $textPromptUser,
                (string)($row['model'] ?? 'deepseek-chat'),
                (float)($row['temperature'] ?? 0.7),
                (int)($row['max_tokens'] ?? 1200)
            );

            $html = $this->cleanupAiText($textResult);
            if ($html === '') {
                throw new RuntimeException('empty html');
            }

            $this->writeSubTextFile($site, $label, $textFile, $html);
            $this->saveSinglePageConfig($siteId, $label, (string)$path, $pageCfg);

            $done++;
        } catch (Throwable $e) {
            $errors++;
            hub_log('AI_PAGE_GEN_ERROR', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => (string)$path,
                'err' => $e->getMessage(),
            ]);
        }
    }

    $_SESSION['wm_log'][] = "AI pages generated: done={$done}, errors={$errors}";
    $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
}

}