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
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
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
            return;
        }

        $pdo = DB::pdo();

        $st = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $st->execute([$siteId]);
        $site = $st->fetch(PDO::FETCH_ASSOC);

        if (!$site) {
            $this->redirect('/sites');
            return;
        }

        $row = $this->loadRow();

        $this->view('ai/site', [
            'site' => $site,
            'ai' => $row,
            'runOptions' => $this->loadRunOptions($siteId),
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

        $prompt = trim((string)($row['meta_prompt_root'] ?? ''));
        if ($prompt === '') {
            $prompt = trim((string)($row['prompt_v1'] ?? ''));
        }
        if ($prompt === '') {
            $prompt = 'Ты SEO-копирайтер. Верни строго JSON без пояснений и без markdown: {"title":"","h1":"","description":"","keywords":"","text_html":""}';
        }

        $client = new DeepseekClient($apiKey);

        $domain = (string)($site['domain'] ?? '');

        $userPrompt = "Сайт: {$domain}. Сгенерируй SEO-мета данные для главной страницы. Верни строго JSON с полями: title, h1, description, keywords, text_html";
        $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

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

        $newTitle       = (string)($json['title'] ?? '');
        $newH1          = (string)($json['h1'] ?? '');
        $newDescription = (string)($json['description'] ?? '');
        $newKeywords    = (string)($json['keywords'] ?? '');

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
        if ($siteId <= 0) {
            $this->redirect('/sites');
            return;
        }

        $pdo = DB::pdo();

        $st = $pdo->prepare("SELECT * FROM sites WHERE id=? LIMIT 1");
        $st->execute([$siteId]);
        $site = $st->fetch(PDO::FETCH_ASSOC);

        if (!$site) {
            $this->redirect('/sites');
            return;
        }

        $row = $this->loadRow();

        $apiKey = Crypto::decrypt((string)$row['api_key_enc']);
        $client = new DeepseekClient($apiKey);

        $prompt = (string)($row['meta_prompt_sub'] ?? '');
        if ($prompt === '') {
            $prompt = (string)($row['prompt_v1'] ?? '');
        }

        $domain = (string)$site['domain'];

        $st = $pdo->prepare("
            SELECT *
            FROM site_subdomains
            WHERE site_id=?
              AND label <> '_default'
        ");
        $st->execute([$siteId]);

        $subs = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subs as $sub) {
            $label = (string)$sub['label'];

            $userPrompt = "Сайт: {$domain}\nПоддомен: {$label}.{$domain}\nСгенерируй SEO мета.";
            $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

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
            $end = strrpos($result, '}');

            if ($start !== false && $end !== false) {
                $result = substr($result, $start, $end - $start + 1);
            }

            $json = json_decode($result, true);

            if (!$json) {
                continue;
            }

            $st = $pdo->prepare("
                SELECT *
                FROM site_subdomain_configs
                WHERE site_id=? AND label=?
                LIMIT 1
            ");
            $st->execute([$siteId, $label]);

            $cfg = $st->fetch(PDO::FETCH_ASSOC);

            $config = [];

            if ($cfg && !empty($cfg['config_json'])) {
                $decoded = json_decode((string)$cfg['config_json'], true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }

            $config['title'] = $json['title'] ?? '';
            $config['h1'] = $json['h1'] ?? '';
            $config['description'] = $json['description'] ?? '';
            $config['keywords'] = $json['keywords'] ?? '';

            $jsonCfg = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($cfg) {
                $pdo->prepare("
                    UPDATE site_subdomain_configs
                    SET config_json=?
                    WHERE site_id=? AND label=?
                ")->execute([$jsonCfg, $siteId, $label]);
            } else {
                $pdo->prepare("
                    INSERT INTO site_subdomain_configs
                    (site_id,label,config_json)
                    VALUES (?,?,?)
                ")->execute([$siteId, $label, $jsonCfg]);
            }
        }

        $_SESSION['wm_log'][] = 'AI subdomains generated';
        $this->redirect('/sites/subdomains?id=' . $siteId);
    }

    public function generateSubMeta(): void
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
            return;
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
        $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

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
        $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'text');

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
        $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'text');

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
                $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'text');

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
            SELECT *
            FROM site_subdomain_configs
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

    private function aiClient(): DeepseekClient
    {
        $row = $this->loadRow();

        $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
        if ($apiKeyEnc === '') {
            throw new RuntimeException('AI API key пустой');
        }

        $apiKey = Crypto::decrypt($apiKeyEnc);
        return new DeepseekClient($apiKey);
    }

    private function cleanAiJson(string $result): array
    {
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
            throw new RuntimeException('AI вернул не JSON: ' . $result);
        }

        return $json;
    }

    private function loadSiteOrFail(int $siteId): array
    {
        $st = DB::pdo()->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$siteId]);
        $site = $st->fetch(PDO::FETCH_ASSOC);

        if (!$site) {
            throw new RuntimeException('Сайт не найден');
        }

        return $site;
    }

    private function loadSubCfgOrCreate(int $siteId, string $label, string $domain): array
    {
        $pdo = DB::pdo();

        if ($label === '_default') {
            $st = $pdo->prepare("
                SELECT *
                FROM site_subdomain_configs
                WHERE site_id = ? AND label = '_default'
                LIMIT 1
            ");
            $st->execute([$siteId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['config_json'])) {
                $cfg = json_decode((string)$row['config_json'], true);
                if (is_array($cfg)) {
                    return $cfg;
                }
            }

            $st = $pdo->prepare("SELECT * FROM site_default_configs WHERE site_id = ? LIMIT 1");
            $st->execute([$siteId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['config_json'])) {
                $cfg = json_decode((string)$row['config_json'], true);
                if (is_array($cfg)) {
                    $cfg['label'] = '_default';
                    $cfg['domain'] = $domain;
                    return $cfg;
                }
            }

            $st = $pdo->prepare("SELECT * FROM site_configs WHERE site_id = ? LIMIT 1");
            $st->execute([$siteId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['json'])) {
                $cfg = json_decode((string)$row['json'], true);
                if (is_array($cfg)) {
                    $cfg['label'] = '_default';
                    $cfg['domain'] = $domain;
                    return $cfg;
                }
            }
        } else {
            $st = $pdo->prepare("
                SELECT *
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
        }

        return [
            'domain' => $domain,
            'label' => $label,
            'pages' => [],
        ];
    }

    private function saveSubCfg(int $siteId, string $label, array $cfg): void
    {
        $pdo = DB::pdo();

        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $st = $pdo->prepare("
            SELECT id
            FROM site_subdomain_configs
            WHERE site_id = ? AND label = ?
            LIMIT 1
        ");
        $st->execute([$siteId, $label]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

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

    private function textsDirForLabel(int $siteId, string $label): string
    {
        return Paths::storage('builds/site_' . $siteId . '/subs/' . $label . '/texts');
    }

    private function normalizePagePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        if ($path !== '/') $path = rtrim($path, '/');
        return $path;
    }

    private function makePageSlug(string $path): string
    {
        if ($path === '/') return 'home';
        $slug = trim($path, '/');
        $slug = str_replace('/', '-', $slug);
        $slug = preg_replace('~[^a-z0-9\-_]+~i', '-', $slug);
        $slug = trim((string)$slug, '-');
        return $slug !== '' ? strtolower($slug) : 'page';
    }

    public function generatePageMeta(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = trim((string)($_GET['label'] ?? '_default'));
            $path   = $this->normalizePagePath((string)($_GET['path'] ?? '/'));

            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $domain = (string)$site['domain'];
            $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
            $page = is_array($pages[$path] ?? null) ? $pages[$path] : [];

            $prompt = trim((string)($row['page_meta_prompt'] ?? ''));
            if ($prompt === '') {
                $prompt = 'Ты SEO-редактор. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}';
            }

            $userPrompt = "Домен: {$domain}\n";
            $userPrompt .= "Текущий хост: {$fqdn}\n";
            $userPrompt .= "Label: {$label}\n";
            $userPrompt .= "Страница: {$path}\n";
            $userPrompt .= "Сгенерируй SEO meta для этой страницы. Верни строго JSON: title, h1, description, keywords";
            $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

            $result = $client->simpleText(
                $prompt,
                $userPrompt,
                (string)$row['model'],
                (float)$row['temperature'],
                (int)$row['max_tokens']
            );

            $json = $this->cleanAiJson($result);

            $overwriteAll = $this->shouldOverwriteAll($siteId);

            $oldTitle = (string)($page['title'] ?? '');
            $oldH1 = (string)($page['h1'] ?? '');
            $oldDescription = (string)($page['description'] ?? '');
            $oldKeywords = (string)($page['keywords'] ?? '');

            $newTitle = (string)($json['title'] ?? '$inherit');
            $newH1 = (string)($json['h1'] ?? '$inherit');
            $newDescription = (string)($json['description'] ?? '$inherit');
            $newKeywords = (string)($json['keywords'] ?? '$inherit');

            $page['title'] = ($overwriteAll || $oldTitle === '' || $oldTitle === '$inherit') ? $newTitle : $oldTitle;
            $page['h1'] = ($overwriteAll || $oldH1 === '' || $oldH1 === '$inherit') ? $newH1 : $oldH1;
            $page['description'] = ($overwriteAll || $oldDescription === '' || $oldDescription === '$inherit') ? $newDescription : $oldDescription;
            $page['keywords'] = ($overwriteAll || $oldKeywords === '' || $oldKeywords === '$inherit') ? $newKeywords : $oldKeywords;

            if (empty($page['text_file'])) {
                $page['text_file'] = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
            }

            $pages[$path] = $page;
            $cfg['pages'] = $pages;

            $this->saveSubCfg($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI meta страницы сгенерированы: {$fqdn} {$path}";
            $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generatePageText(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = trim((string)($_GET['label'] ?? '_default'));
            $path   = $this->normalizePagePath((string)($_GET['path'] ?? '/'));

            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $domain = (string)$site['domain'];
            $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
            $page = is_array($pages[$path] ?? null) ? $pages[$path] : [];

            $textFile = (string)($page['text_file'] ?? '');
            if ($textFile === '') {
                $textFile = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
                $page['text_file'] = $textFile;
            }

            $prompt = trim((string)($row['page_prompt'] ?? ''));
            if ($prompt === '') {
                $prompt = 'Ты веб-копирайтер. Верни только готовый HTML-фрагмент для body без markdown, без пояснений, без ```.';
            }

            $userPrompt = "Домен: {$domain}\n";
            $userPrompt .= "Текущий хост: {$fqdn}\n";
            $userPrompt .= "Label: {$label}\n";
            $userPrompt .= "Страница: {$path}\n";
            $userPrompt .= "Файл: {$textFile}\n";
            $userPrompt .= "Сгенерируй HTML-текст для этой страницы. Нужны абзацы, списки при необходимости, без <html>, <head>, <body>.";
            $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'pages');

            $html = $client->simpleText(
                $prompt,
                $userPrompt,
                (string)$row['model'],
                (float)$row['temperature'],
                (int)$row['max_tokens']
            );

            $html = trim($html);

            if (strpos($html, '```') !== false) {
                $html = preg_replace('/```html/i', '', $html);
                $html = str_replace('```', '', $html);
                $html = trim($html);
            }

            $dir = $this->textsDirForLabel($siteId, $label);
            Paths::ensureDir($dir);

            $fullPath = rtrim($dir, '/\\') . '/' . basename($textFile);

            file_put_contents($fullPath, $html);

            $pages[$path] = $page;
            $cfg['pages'] = $pages;
            $this->saveSubCfg($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI текст страницы сгенерирован: {$fqdn} {$path}";
            $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode(basename($textFile)));
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSelectedPages(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = trim((string)($_GET['label'] ?? '_default'));
            $mode   = trim((string)($_POST['mode'] ?? 'all'));

            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            if (!in_array($mode, ['meta', 'text', 'all'], true)) {
                $mode = 'all';
            }

            $selected = $_POST['selected_urls'] ?? [];
            if (!is_array($selected) || !$selected) {
                throw new RuntimeException('Не выбраны страницы');
            }

            $selectedMap = [];
            foreach ($selected as $u) {
                $u = $this->normalizePagePath((string)$u);
                $selectedMap[$u] = true;
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $domain = (string)$site['domain'];
            $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];

            if (!$pages) {
                throw new RuntimeException('В pages нет страниц');
            }

            $pageMetaPrompt = trim((string)($row['page_meta_prompt'] ?? ''));
            if ($pageMetaPrompt === '') {
                $pageMetaPrompt = 'Ты SEO-редактор. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}';
            }

            $pageTextPrompt = trim((string)($row['page_prompt'] ?? ''));
            if ($pageTextPrompt === '') {
                $pageTextPrompt = 'Ты веб-копирайтер. Верни только готовый HTML-фрагмент для body без markdown, без пояснений, без ```.';
            }

            $dir = $this->textsDirForLabel($siteId, $label);
            Paths::ensureDir($dir);

            $overwriteAll = $this->shouldOverwriteAll($siteId);
            $done = 0;

            foreach ($pages as $path => &$page) {
                $path = $this->normalizePagePath((string)$path);

                if (!isset($selectedMap[$path])) {
                    continue;
                }

                if (!is_array($page)) {
                    $page = [];
                }

                if (empty($page['text_file'])) {
                    $page['text_file'] = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
                }

                if ($mode === 'meta' || $mode === 'all') {
                    $oldTitle = (string)($page['title'] ?? '');
                    $oldH1 = (string)($page['h1'] ?? '');
                    $oldDescription = (string)($page['description'] ?? '');
                    $oldKeywords = (string)($page['keywords'] ?? '');

                    $metaPrompt = "Домен: {$domain}\n";
                    $metaPrompt .= "Текущий хост: {$fqdn}\n";
                    $metaPrompt .= "Label: {$label}\n";
                    $metaPrompt .= "Страница: {$path}\n";
                    $metaPrompt .= "Сгенерируй SEO meta для этой страницы. Верни строго JSON: title, h1, description, keywords";
                    $metaPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

                    $metaResult = $client->simpleText(
                        $pageMetaPrompt,
                        $metaPrompt,
                        (string)$row['model'],
                        (float)$row['temperature'],
                        (int)$row['max_tokens']
                    );

                    $metaJson = $this->cleanAiJson($metaResult);

                    $newTitle = (string)($metaJson['title'] ?? '$inherit');
                    $newH1 = (string)($metaJson['h1'] ?? '$inherit');
                    $newDescription = (string)($metaJson['description'] ?? '$inherit');
                    $newKeywords = (string)($metaJson['keywords'] ?? '$inherit');

                    $page['title'] = ($overwriteAll || $oldTitle === '' || $oldTitle === '$inherit') ? $newTitle : $oldTitle;
                    $page['h1'] = ($overwriteAll || $oldH1 === '' || $oldH1 === '$inherit') ? $newH1 : $oldH1;
                    $page['description'] = ($overwriteAll || $oldDescription === '' || $oldDescription === '$inherit') ? $newDescription : $oldDescription;
                    $page['keywords'] = ($overwriteAll || $oldKeywords === '' || $oldKeywords === '$inherit') ? $newKeywords : $oldKeywords;
                }

                if ($mode === 'text' || $mode === 'all') {
                    $textPrompt = "Домен: {$domain}\n";
                    $textPrompt .= "Текущий хост: {$fqdn}\n";
                    $textPrompt .= "Label: {$label}\n";
                    $textPrompt .= "Страница: {$path}\n";
                    $textPrompt .= "Файл: {$page['text_file']}\n";
                    $textPrompt .= "Сгенерируй HTML-текст для этой страницы. Нужны абзацы, списки при необходимости, без <html>, <head>, <body>.";
                    $textPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'pages');

                    $html = $client->simpleText(
                        $pageTextPrompt,
                        $textPrompt,
                        (string)$row['model'],
                        (float)$row['temperature'],
                        (int)$row['max_tokens']
                    );

                    $html = trim($html);

                    if (strpos($html, '```') !== false) {
                        $html = preg_replace('/```html/i', '', $html);
                        $html = str_replace('```', '', $html);
                        $html = trim($html);
                    }

                    file_put_contents(
                        rtrim($dir, '/\\') . '/' . basename((string)$page['text_file']),
                        $html
                    );
                }

                $done++;
            }
            unset($page);

            $cfg['pages'] = $pages;
            $this->saveSubCfgSafe($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI selected pages generated ({$done}): {$fqdn}";
            $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
        } catch (Throwable $e) {
            hub_log('AI_GENERATE_SELECTED_PAGES_ERROR', [
                'site_id' => (int)($_GET['id'] ?? 0),
                'label'   => (string)($_GET['label'] ?? '_default'),
                'err'     => $e->getMessage(),
            ]);
            die($this->h($e->getMessage()));
        }
    }

    private function saveSubCfgSafe(int $siteId, string $label, array $cfg): void
    {
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $this->saveSubCfg($siteId, $label, $cfg);
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            if (
                stripos($msg, 'server has gone away') !== false ||
                stripos($msg, 'packets out of order') !== false ||
                stripos($msg, 'lost connection') !== false
            ) {
                hub_log('AI_DB_RECONNECT', [
                    'site_id' => $siteId,
                    'label' => $label,
                    'err' => $msg,
                ]);

                if (!method_exists('DB', 'reconnect')) {
                    throw $e;
                }

                $pdo = DB::reconnect();

                $st = $pdo->prepare("
                    SELECT id
                    FROM site_subdomain_configs
                    WHERE site_id = ? AND label = ?
                    LIMIT 1
                ");
                $st->execute([$siteId, $label]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

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

                return;
            }

            throw $e;
        }
    }

    private function sessionOptionsKey(int $siteId): string
    {
        return 'ai_run_options_site_' . $siteId;
    }

    private function defaultRunOptions(): array
    {
        return [
            'text_length' => 'medium',
            'required_phrases' => '',
            'forbidden_phrases' => '',
            'sitewide_link_url' => '',
            'sitewide_link_anchor' => '',
            'cta_text' => '',
            'extra_instruction' => '',
            'overwrite_mode' => 'fill_empty',
        ];
    }

    private function loadRunOptions(int $siteId): array
    {
        $defaults = $this->defaultRunOptions();
        $raw = $_SESSION[$this->sessionOptionsKey($siteId)] ?? [];

        if (!is_array($raw)) {
            return $defaults;
        }

        return array_merge($defaults, $raw);
    }

    private function buildRunOptionsBlock(array $opts, string $target): string
    {
        $parts = [];

        $length = trim((string)($opts['text_length'] ?? 'medium'));
        if ($target === 'text' || $target === 'pages') {
            if ($length === 'short') {
                $parts[] = 'Объём текста: короткий, примерно 1200–1800 символов без пробелов.';
            } elseif ($length === 'long') {
                $parts[] = 'Объём текста: большой, примерно 3500–6000 символов без пробелов.';
            } else {
                $parts[] = 'Объём текста: средний, примерно 2000–3500 символов без пробелов.';
            }
        }

        $required = trim((string)($opts['required_phrases'] ?? ''));
        if ($required !== '') {
            $parts[] = "Обязательно используй следующие вхождения/фразы:\n" . $required;
        }

        $forbidden = trim((string)($opts['forbidden_phrases'] ?? ''));
        if ($forbidden !== '') {
            $parts[] = "Не используй следующие слова/фразы:\n" . $forbidden;
        }

        $url = trim((string)($opts['sitewide_link_url'] ?? ''));
        $anchor = trim((string)($opts['sitewide_link_anchor'] ?? ''));
        if (($target === 'text' || $target === 'pages') && $url !== '' && $anchor !== '') {
            $parts[] = 'Добавь в текст одну ссылку: URL = ' . $url . ', анкор = "' . $anchor . '".';
        }

        $cta = trim((string)($opts['cta_text'] ?? ''));
        if (($target === 'text' || $target === 'pages') && $cta !== '') {
            $parts[] = 'В конце текста добавь мягкий CTA: ' . $cta;
        }

        $extra = trim((string)($opts['extra_instruction'] ?? ''));
        if ($extra !== '') {
            $parts[] = "Дополнительные требования:\n" . $extra;
        }

        if (empty($parts)) {
            return '';
        }

        return "\n\nДополнительные параметры генерации:\n" . implode("\n", $parts);
    }

    private function shouldOverwriteAll(int $siteId): bool
    {
        $opts = $this->loadRunOptions($siteId);
        return (string)($opts['overwrite_mode'] ?? 'fill_empty') === 'overwrite_all';
    }

    public function saveRunOptions(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            $this->redirect('/sites');
            return;
        }

        $textLength = trim((string)($_POST['text_length'] ?? 'medium'));
        if (!in_array($textLength, ['short', 'medium', 'long'], true)) {
            $textLength = 'medium';
        }

        $overwriteMode = trim((string)($_POST['overwrite_mode'] ?? 'fill_empty'));
        if (!in_array($overwriteMode, ['fill_empty', 'overwrite_all'], true)) {
            $overwriteMode = 'fill_empty';
        }

        $_SESSION[$this->sessionOptionsKey($siteId)] = [
            'text_length' => $textLength,
            'required_phrases' => trim((string)($_POST['required_phrases'] ?? '')),
            'forbidden_phrases' => trim((string)($_POST['forbidden_phrases'] ?? '')),
            'sitewide_link_url' => trim((string)($_POST['sitewide_link_url'] ?? '')),
            'sitewide_link_anchor' => trim((string)($_POST['sitewide_link_anchor'] ?? '')),
            'cta_text' => trim((string)($_POST['cta_text'] ?? '')),
            'extra_instruction' => trim((string)($_POST['extra_instruction'] ?? '')),
            'overwrite_mode' => $overwriteMode,
        ];

        $_SESSION['wm_log'][] = 'AI run options saved';
        $this->redirect('/sites/ai?id=' . $siteId);
    }

    public function resetRunOptions(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            $this->redirect('/sites');
            return;
        }

        unset($_SESSION[$this->sessionOptionsKey($siteId)]);
        $_SESSION['wm_log'][] = 'AI run options reset';

        $this->redirect('/sites/ai?id=' . $siteId);
    }
	
	private function normalizeSelectedLabels(int $siteId, array $labelsRaw): array
{
    $labels = [];

    foreach ($labelsRaw as $lb) {
        $lb = trim((string)$lb);
        if ($lb === '') continue;
        $labels[$lb] = true;
    }

    if (!$labels) {
        return [];
    }

    $allowed = ['_default' => true];

    $st = DB::pdo()->prepare("
        SELECT label
        FROM site_subdomains
        WHERE site_id = ?
    ");
    $st->execute([$siteId]);

    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $lb = trim((string)($r['label'] ?? ''));
        if ($lb !== '') {
            $allowed[$lb] = true;
        }
    }

    $result = [];
    foreach (array_keys($labels) as $lb) {
        if (isset($allowed[$lb])) {
            $result[] = $lb;
        }
    }

    sort($result);
    return $result;
}

private function generateMetaForLabel(int $siteId, array $site, array $row, string $label): void
{
    $pdo = DB::pdo();

    if ($label === '_default') {
        $domain = (string)($site['domain'] ?? '');

        $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
        if ($apiKeyEnc === '') {
            throw new RuntimeException('AI API key пустой');
        }

        $apiKey = Crypto::decrypt($apiKeyEnc);

        $prompt = trim((string)($row['meta_prompt_root'] ?? ''));
        if ($prompt === '') {
            $prompt = trim((string)($row['prompt_v1'] ?? ''));
        }
        if ($prompt === '') {
            $prompt = 'Ты SEO-копирайтер. Верни строго JSON без пояснений и без markdown: {"title":"","h1":"","description":"","keywords":""}';
        }

        $client = new DeepseekClient($apiKey);

        $userPrompt = "Сайт: {$domain}. Сгенерируй SEO-мета данные для главной страницы. Верни строго JSON с полями: title, h1, description, keywords";
        $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

        $result = $client->simpleText(
            $prompt,
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            (int)($row['max_tokens'] ?? 1200)
        );

        $json = $this->cleanAiJson($result);

        $newTitle       = (string)($json['title'] ?? '');
        $newH1          = (string)($json['h1'] ?? '');
        $newDescription = (string)($json['description'] ?? '');
        $newKeywords    = (string)($json['keywords'] ?? '');

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
            ")->execute([$defaultJson, $siteId]);
        } else {
            $pdo->prepare("
                INSERT INTO site_default_configs (site_id, config_json)
                VALUES (?, ?)
            ")->execute([$siteId, $defaultJson]);
        }

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
            ")->execute([$subJson, $siteId]);
        } else {
            $pdo->prepare("
                INSERT INTO site_subdomain_configs (site_id, label, config_json)
                VALUES (?, '_default', ?)
            ")->execute([$siteId, $subJson]);
        }

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
            ")->execute([$siteCfgJson, $siteId]);
        } else {
            $pdo->prepare("
                INSERT INTO site_configs (site_id, json)
                VALUES (?, ?)
            ")->execute([$siteId, $siteCfgJson]);
        }

        return;
    }

    $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
    if ($apiKeyEnc === '') {
        throw new RuntimeException('AI API key пустой');
    }

    $apiKey = Crypto::decrypt($apiKeyEnc);

    $prompt = (string)($row['meta_prompt_sub'] ?? '');
    if ($prompt === '') {
        $prompt = (string)($row['prompt_v1'] ?? '');
    }
    if ($prompt === '') {
        $prompt = 'Ты SEO-копирайтер. Верни строго JSON без пояснений и без markdown: {"title":"","h1":"","description":"","keywords":""}';
    }

    $domain = (string)($site['domain'] ?? '');
    $fqdn = $label . '.' . $domain;

    $userPrompt = "Основной домен: {$domain}\nПоддомен: {$fqdn}\nLabel: {$label}\nСгенерируй SEO-мета.";
    $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

    $client = new DeepseekClient($apiKey);

    $result = $client->simpleText(
        $prompt,
        $userPrompt,
        (string)$row['model'],
        (float)$row['temperature'],
        (int)$row['max_tokens']
    );

    $json = $this->cleanAiJson($result);

    $st = $pdo->prepare("
        SELECT *
        FROM site_subdomain_configs
        WHERE site_id = ?
          AND label = ?
        LIMIT 1
    ");
    $st->execute([$siteId, $label]);
    $cfgRow = $st->fetch(PDO::FETCH_ASSOC);

    $config = json_decode((string)($cfgRow['config_json'] ?? '{}'), true);
    if (!is_array($config)) {
        $config = [];
    }

    $overwriteAll = $this->shouldOverwriteAll($siteId);

    if ($overwriteAll || empty($config['title']) || $config['title'] === '$inherit') {
        $config['title'] = (string)($json['title'] ?? '');
    }
    if ($overwriteAll || empty($config['h1']) || $config['h1'] === '$inherit') {
        $config['h1'] = (string)($json['h1'] ?? '');
    }
    if ($overwriteAll || empty($config['description']) || $config['description'] === '$inherit') {
        $config['description'] = (string)($json['description'] ?? '');
    }
    if ($overwriteAll || empty($config['keywords']) || $config['keywords'] === '$inherit') {
        $config['keywords'] = (string)($json['keywords'] ?? '');
    }

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
}

public function generateSelectedMeta(): void
{
    $this->requireAuth();

    try {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            throw new RuntimeException('Некорректный site_id');
        }

        $labels = $this->normalizeSelectedLabels($siteId, (array)($_POST['labels'] ?? []));
        if (!$labels) {
            throw new RuntimeException('Не выбраны label');
        }

        $site = $this->loadSiteOrFail($siteId);
        $row = $this->loadRow();

        $done = 0;
        $errors = 0;

        foreach ($labels as $label) {
            try {
                $this->generateMetaForLabel($siteId, $site, $row, $label);
                $done++;
            } catch (Throwable $e) {
                $errors++;
                hub_log('AI_SELECTED_META_ERROR', [
                    'site_id' => $siteId,
                    'label'   => $label,
                    'err'     => $e->getMessage(),
                ]);
            }
        }

        $_SESSION['wm_log'][] = "AI selected meta done={$done}, errors={$errors}";
        $this->redirect('/sites/ai?id=' . $siteId);
    } catch (Throwable $e) {
        die($this->h($e->getMessage()));
    }
}

public function generateSelectedTexts(): void
{
    $this->requireAuth();

    try {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            throw new RuntimeException('Некорректный site_id');
        }

        $labels = $this->normalizeSelectedLabels($siteId, (array)($_POST['labels'] ?? []));
        if (!$labels) {
            throw new RuntimeException('Не выбраны label');
        }

        $site = $this->loadSiteOrFail($siteId);
        $row = $this->loadRow();

        $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
        if ($apiKeyEnc === '') {
            throw new RuntimeException('AI API key пустой');
        }

        $apiKey = Crypto::decrypt($apiKeyEnc);
        $client = new DeepseekClient($apiKey);

        $done = 0;
        $errors = 0;

        foreach ($labels as $label) {
            try {
                $cfg = $this->loadSubConfig($siteId, $label);
                $domain = (string)($site['domain'] ?? '');
                $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

                $prompt = ($label === '_default')
                    ? trim((string)($row['text_prompt_root'] ?? ''))
                    : trim((string)($row['text_prompt_sub'] ?? ''));

                if ($prompt === '') {
                    $prompt = "Ты SEO-копирайтер. Верни только готовый HTML-фрагмент для вставки в body. Без markdown, без тройных кавычек, без пояснений. Используй <p>, <h2>, <ul>, <li> при необходимости.";
                }

                $title = (string)($cfg['title'] ?? '');
                $h1 = (string)($cfg['h1'] ?? '');
                $description = (string)($cfg['description'] ?? '');
                $keywords = (string)($cfg['keywords'] ?? '');
                $textFile = $this->detectHomeTextFile($cfg);

                if ($label === '_default') {
                    $userPrompt = "Сайт: {$domain}\n";
                    $userPrompt .= "Это основной домен.\n";
                } else {
                    $userPrompt = "Основной домен: {$domain}\n";
                    $userPrompt .= "Поддомен: {$fqdn}\n";
                    $userPrompt .= "Label: {$label}\n";
                }

                $userPrompt .= "Title: {$title}\n";
                $userPrompt .= "H1: {$h1}\n";
                $userPrompt .= "Description: {$description}\n";
                $userPrompt .= "Keywords: {$keywords}\n";
                $userPrompt .= "Нужно сгенерировать уникальный SEO-текст для главной страницы.\n";
                $userPrompt .= "Верни только HTML-фрагмент для файла {$textFile}.";
                $userPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'text');

                $result = $client->simpleText(
                    $prompt,
                    $userPrompt,
                    (string)($row['model'] ?? 'deepseek-chat'),
                    (float)($row['temperature'] ?? 0.7),
                    (int)($row['max_tokens'] ?? 1200)
                );

                $html = $this->cleanupAiText($result);
                if ($html === '') {
                    throw new RuntimeException('AI вернул пустой текст');
                }

                $this->writeSubTextFile($site, $label, $textFile, $html);
                $done++;
            } catch (Throwable $e) {
                $errors++;
                hub_log('AI_SELECTED_TEXT_ERROR', [
                    'site_id' => $siteId,
                    'label'   => $label,
                    'err'     => $e->getMessage(),
                ]);
            }
        }

        $_SESSION['wm_log'][] = "AI selected texts done={$done}, errors={$errors}";
        $this->redirect('/sites/ai?id=' . $siteId);
    } catch (Throwable $e) {
        die($this->h($e->getMessage()));
    }
}

public function generateSelectedLabelsPages(): void
{
    $this->requireAuth();

    try {
        $siteId = (int)($_GET['id'] ?? 0);
        if ($siteId <= 0) {
            throw new RuntimeException('Некорректный site_id');
        }

        $labels = $this->normalizeSelectedLabels($siteId, (array)($_POST['labels'] ?? []));
        if (!$labels) {
            throw new RuntimeException('Не выбраны label');
        }

        $done = 0;
        $errors = 0;

        foreach ($labels as $label) {
            try {
                $_GET['label'] = $label;
                $_GET['id'] = $siteId;
                $this->generateAllPagesInternal($siteId, $label);
                $done++;
            } catch (Throwable $e) {
                $errors++;
                hub_log('AI_SELECTED_PAGES_ERROR', [
                    'site_id' => $siteId,
                    'label'   => $label,
                    'err'     => $e->getMessage(),
                ]);
            }
        }

        $_SESSION['wm_log'][] = "AI selected pages done={$done}, errors={$errors}";
        $this->redirect('/sites/ai?id=' . $siteId);
    } catch (Throwable $e) {
        die($this->h($e->getMessage()));
    }
}

private function generateAllPagesInternal(int $siteId, string $label): void
{
    $site = $this->loadSiteOrFail($siteId);
    $row = $this->loadRow();
    $client = $this->aiClient();

    $domain = (string)$site['domain'];
    $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

    $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
    $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];

    if (!$pages) {
        throw new RuntimeException('В pages нет страниц для генерации');
    }

    $pageMetaPrompt = trim((string)($row['page_meta_prompt'] ?? ''));
    if ($pageMetaPrompt === '') {
        $pageMetaPrompt = 'Ты SEO-редактор. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}';
    }

    $pageTextPrompt = trim((string)($row['page_prompt'] ?? ''));
    if ($pageTextPrompt === '') {
        $pageTextPrompt = 'Ты веб-копирайтер. Верни только готовый HTML-фрагмент для body без markdown, без пояснений, без ```.';
    }

    $dir = $this->textsDirForLabel($siteId, $label);
    Paths::ensureDir($dir);

    $overwriteAll = $this->shouldOverwriteAll($siteId);

    foreach ($pages as $path => &$page) {
        if (!is_array($page)) {
            $page = [];
        }

        $path = $this->normalizePagePath((string)$path);

        if (empty($page['text_file'])) {
            $page['text_file'] = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
        }

        $metaPrompt = "Домен: {$domain}\n";
        $metaPrompt .= "Текущий хост: {$fqdn}\n";
        $metaPrompt .= "Label: {$label}\n";
        $metaPrompt .= "Страница: {$path}\n";
        $metaPrompt .= "Сгенерируй SEO meta для этой страницы. Верни строго JSON: title, h1, description, keywords";
        $metaPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'meta');

        $metaResult = $client->simpleText(
            $pageMetaPrompt,
            $metaPrompt,
            (string)$row['model'],
            (float)$row['temperature'],
            (int)$row['max_tokens']
        );

        $metaJson = $this->cleanAiJson($metaResult);

        if ($overwriteAll || empty($page['title']) || $page['title'] === '$inherit') {
            $page['title'] = (string)($metaJson['title'] ?? '$inherit');
        }
        if ($overwriteAll || empty($page['h1']) || $page['h1'] === '$inherit') {
            $page['h1'] = (string)($metaJson['h1'] ?? '$inherit');
        }
        if ($overwriteAll || empty($page['description']) || $page['description'] === '$inherit') {
            $page['description'] = (string)($metaJson['description'] ?? '$inherit');
        }
        if ($overwriteAll || empty($page['keywords']) || $page['keywords'] === '$inherit') {
            $page['keywords'] = (string)($metaJson['keywords'] ?? '$inherit');
        }

        $textPrompt = "Домен: {$domain}\n";
        $textPrompt .= "Текущий хост: {$fqdn}\n";
        $textPrompt .= "Label: {$label}\n";
        $textPrompt .= "Страница: {$path}\n";
        $textPrompt .= "Файл: {$page['text_file']}\n";
        $textPrompt .= "Сгенерируй HTML-текст для этой страницы. Нужны абзацы, списки при необходимости, без <html>, <head>, <body>.";
        $textPrompt .= $this->buildRunOptionsBlock($this->loadRunOptions($siteId), 'pages');

        $html = $client->simpleText(
            $pageTextPrompt,
            $textPrompt,
            (string)$row['model'],
            (float)$row['temperature'],
            (int)$row['max_tokens']
        );

        $html = trim($html);

        if (strpos($html, '```') !== false) {
            $html = preg_replace('/```html/i', '', $html);
            $html = str_replace('```', '', $html);
            $html = trim($html);
        }

        file_put_contents(
            rtrim($dir, '/\\') . '/' . basename((string)$page['text_file']),
            $html
        );
    }
    unset($page);

    $cfg['pages'] = $pages;
    $this->saveSubCfgSafe($siteId, $label, $cfg);
}

public function generateAllPages(): void
{
    $this->requireAuth();

    try {
        $siteId = (int)($_GET['id'] ?? 0);
        $label  = trim((string)($_GET['label'] ?? '_default'));

        if ($siteId <= 0) {
            throw new RuntimeException('Некорректный site_id');
        }

        $this->generateAllPagesInternal($siteId, $label);

        $site = $this->loadSiteOrFail($siteId);
        $fqdn = ($label === '_default')
            ? (string)$site['domain']
            : ($label . '.' . (string)$site['domain']);

        $_SESSION['wm_log'][] = "AI pages generated: {$fqdn}";
        $this->redirect('/sites/pages?id=' . $siteId . '&label=' . urlencode($label));
    } catch (Throwable $e) {
        hub_log('AI_GENERATE_ALL_PAGES_ERROR', [
            'site_id' => (int)($_GET['id'] ?? 0),
            'label'   => (string)($_GET['label'] ?? '_default'),
            'err'     => $e->getMessage(),
        ]);
        die($this->h($e->getMessage()));
    }
}
}