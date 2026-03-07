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
}