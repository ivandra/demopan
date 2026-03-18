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

    private function aiSettingsColumns(): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }

        $cols = [];
        try {
            $st = DB::pdo()->query("SHOW COLUMNS FROM ai_settings");
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $f = strtolower(trim((string)($r['Field'] ?? '')));
                if ($f !== '') {
                    $cols[$f] = true;
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }

        return $cols;
    }

    private function aiSettingsHas(string $column): bool
    {
        $cols = $this->aiSettingsColumns();
        return isset($cols[strtolower($column)]);
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

        $row = $row ?: [];

        if (!array_key_exists('global_meta_title_template', $row)) {
            $row['global_meta_title_template'] = '';
        }
        if (!array_key_exists('global_meta_h1_template', $row)) {
            $row['global_meta_h1_template'] = '';
        }
        if (!array_key_exists('global_meta_description_template', $row)) {
            $row['global_meta_description_template'] = '';
        }

        return $row;
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

        $metaPromptRoot  = trim((string)($_POST['meta_prompt_root'] ?? ''));
        $metaPromptSub   = trim((string)($_POST['meta_prompt_sub'] ?? ''));
        $textPromptRoot  = trim((string)($_POST['text_prompt_root'] ?? ''));
        $textPromptSub   = trim((string)($_POST['text_prompt_sub'] ?? ''));
        $pagePrompt      = trim((string)($_POST['page_prompt'] ?? ''));
        $pageMetaPrompt  = trim((string)($_POST['page_meta_prompt'] ?? ''));

        $globalMetaTitleTemplate = trim((string)($_POST['global_meta_title_template'] ?? ''));
        $globalMetaH1Template = trim((string)($_POST['global_meta_h1_template'] ?? ''));
        $globalMetaDescriptionTemplate = trim((string)($_POST['global_meta_description_template'] ?? ''));

        if ($provider === '') {
            $provider = 'deepseek';
        }
        if ($model === '') {
            $model = 'deepseek-chat';
        }
        if ($temperature < 0) {
            $temperature = 0;
        }
        if ($temperature > 2) {
            $temperature = 2;
        }
        if ($maxTokens < 100) {
            $maxTokens = 100;
        }
        if ($maxTokens > 8000) {
            $maxTokens = 8000;
        }

        $row = $this->loadRow();
        $apiKeyEnc = (string)($row['api_key_enc'] ?? '');
        if ($apiKey !== '') {
            $apiKeyEnc = Crypto::encrypt($apiKey);
        }

        $set = [
            'provider' => $provider,
            'api_key_enc' => $apiKeyEnc,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'meta_prompt_root' => $metaPromptRoot,
            'meta_prompt_sub' => $metaPromptSub,
            'text_prompt_root' => $textPromptRoot,
            'text_prompt_sub' => $textPromptSub,
            'page_prompt' => $pagePrompt,
            'page_meta_prompt' => $pageMetaPrompt,
        ];

        if ($this->aiSettingsHas('global_meta_title_template')) {
            $set['global_meta_title_template'] = $globalMetaTitleTemplate;
        }
        if ($this->aiSettingsHas('global_meta_h1_template')) {
            $set['global_meta_h1_template'] = $globalMetaH1Template;
        }
        if ($this->aiSettingsHas('global_meta_description_template')) {
            $set['global_meta_description_template'] = $globalMetaDescriptionTemplate;
        }

        // legacy-поля сохраняем как есть, чтобы не ломать старые инсталляции.
        if ($this->aiSettingsHas('prompt_v1')) {
            $set['prompt_v1'] = (string)($row['prompt_v1'] ?? '');
        }
        if ($this->aiSettingsHas('prompt_v2')) {
            $set['prompt_v2'] = (string)($row['prompt_v2'] ?? '');
        }

        $parts = [];
        $values = [];
        foreach ($set as $column => $value) {
            if (!$this->aiSettingsHas($column)) {
                continue;
            }
            $parts[] = $column . ' = ?';
            $values[] = $value;
        }
        $values[] = 1;

        DB::pdo()->prepare("UPDATE ai_settings SET " . implode(', ', $parts) . " WHERE id = ? LIMIT 1")
            ->execute($values);

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
        $currentLabel = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));

        $resolver = new SiteConfigResolver(DB::pdo());
        $labels = $resolver->listLabels($siteId, true);
        if (!in_array($currentLabel, $labels, true)) {
            $currentLabel = '_default';
        }

        $currentCfg = $resolver->getResolvedConfig($siteId, $currentLabel);
        $entityAi = $this->loadEntityAiSettings($siteId, $currentLabel, $site);
        $pagePaths = $this->extractPagePaths($currentCfg);
        $resolvedMirrorUrl = $this->makeEntityUrl($site, $currentLabel, (string)($currentCfg['promolink'] ?? ''));

        $this->view('ai/site', [
            'site' => $site,
            'ai' => $row,
            'labels' => $labels,
            'currentLabel' => $currentLabel,
            'currentCfg' => $currentCfg,
            'entityAi' => $entityAi,
            'pagePaths' => $pagePaths,
            'resolvedMirrorUrl' => $resolvedMirrorUrl,
            'runOptions' => $this->loadRunOptions($siteId),
        ]);
    }

    public function generateMeta(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();

            $this->generateMetaForLabel($siteId, $site, $row, '_default');

            $_SESSION['wm_log'][] = 'AI root meta generated';
            $this->redirect('/sites/subcfg?id=' . $siteId . '&label=_default');
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSubdomains(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();

            $st = DB::pdo()->prepare("
                SELECT label
                FROM site_subdomains
                WHERE site_id = ?
                  AND label <> '_default'
                  AND enabled = 1
                ORDER BY label ASC
            ");
            $st->execute([$siteId]);
            $subs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $done = 0;
            $errors = 0;

            foreach ($subs as $sub) {
                $label = trim((string)($sub['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                try {
                    $this->generateMetaForLabel($siteId, $site, $row, $label);
                    $done++;
                } catch (Throwable $e) {
                    $errors++;
                    hub_log('AI_SUBDOMAIN_META_ERROR', [
                        'site_id' => $siteId,
                        'label' => $label,
                        'err' => $e->getMessage(),
                    ]);
                }
            }

            $_SESSION['wm_log'][] = "AI subdomains meta done={$done}, errors={$errors}";
            $this->redirect('/sites/ai?id=' . $siteId);
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSubMeta(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? ''));

            if ($siteId <= 0 || $label === '') {
                throw new RuntimeException('Некорректный site_id или label');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();

            $this->generateMetaForLabel($siteId, $site, $row, $label);

            $_SESSION['wm_log'][] = 'AI sub meta generated: ' . $label;
            $this->redirect('/sites/subcfg?id=' . $siteId . '&label=' . urlencode($label));
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    private function generateMetaForLabel(int $siteId, array $site, array $row, string $label): void
    {
        $pdo = DB::pdo();
        $label = $this->normalizeAiLabel($label);

        $domain = trim((string)($site['domain'] ?? ''));
        if ($domain === '') {
            throw new RuntimeException('Пустой домен сайта');
        }

        $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);
        $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
        $entityAi = $this->loadEntityAiSettings($siteId, $label, $site);
        $vars = $this->buildPromptVars($site, $label, $cfg, $entityAi);

        $prompt = trim((string)(
            $label === '_default'
                ? ($row['meta_prompt_root'] ?? '')
                : ($row['meta_prompt_sub'] ?? '')
        ));

        if ($prompt === '') {
            $prompt = 'Ты SEO-копирайтер. Верни только JSON без markdown и без пояснений. Формат: {"title":"","h1":"","description":"","keywords":""}';
        }

        $prompt = $this->replacePromptVars($prompt, $vars);

        $userPrompt = "Сгенерируй SEO-мета для сущности {$fqdn}.
";
        $userPrompt .= "Бренд: " . (string)($vars['{BRAND}'] ?? '') . "
";
        $userPrompt .= "Label: {$label}
";
        $userPrompt .= "Host: {$fqdn}

";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $client = $this->aiClient();
        $result = $client->simpleText(
            'Ты SEO-копирайтер. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            (int)($row['max_tokens'] ?? 1200)
        );

        $json = $this->cleanAiJson($result);
        $json = $this->applyMetaTemplates($json, $row, $vars);

        $cfg['title'] = trim((string)($json['title'] ?? ''));
        $cfg['h1'] = trim((string)($json['h1'] ?? ''));
        $cfg['description'] = trim((string)($json['description'] ?? ''));
        $cfg['keywords'] = trim((string)($json['keywords'] ?? ''));
        $cfg['domain'] = $domain;
        $cfg['label'] = $label;

        $this->saveSubCfgSafe($siteId, $label, $cfg);

        if ($label === '_default') {
            $rootCfg = $cfg;
            unset($rootCfg['label']);
            $rootJson = json_encode($rootCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $pdo->prepare("
                INSERT INTO site_default_configs (site_id, config_json)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    config_json = VALUES(config_json),
                    updated_at = CURRENT_TIMESTAMP
            ")->execute([$siteId, $rootJson]);

            $pdo->prepare("
                INSERT INTO site_configs (site_id, json)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    json = VALUES(json),
                    updated_at = CURRENT_TIMESTAMP
            ")->execute([$siteId, $rootJson]);
        }
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
        $label = $this->normalizeAiLabel($label);

        if ($label === '_default') {
            $st = $pdo->prepare("
                SELECT config_json
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

        $safeLabel = $this->normalizeAiLabel($label);
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

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $res = $this->generateHomeTextForLabel($siteId, $site, $row, $client, '_default');

            $_SESSION['wm_log'][] = 'AI root text generated: ' . $res['file'];
            hub_log('AI_ROOT_TEXT_OK', [
                'site_id' => $siteId,
                'file' => $res['file'],
                'path' => $res['path'],
            ]);

            $this->redirect('/sites/texts?id=' . $siteId . '&label=_default');
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSubText(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? ''));

            if ($siteId <= 0 || $label === '') {
                throw new RuntimeException('Некорректный site_id или label');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $res = $this->generateHomeTextForLabel($siteId, $site, $row, $client, $label);

            $_SESSION['wm_log'][] = 'AI sub text generated: ' . $res['fqdn'] . ' -> ' . $res['file'];
            hub_log('AI_SUB_TEXT_OK', [
                'site_id' => $siteId,
                'label' => $label,
                'fqdn' => $res['fqdn'],
                'file' => $res['file'],
                'path' => $res['path'],
            ]);

            $this->redirect('/sites/texts?id=' . $siteId . '&label=' . urlencode($label));
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateAllSubTexts(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();

            $st = DB::pdo()->prepare("
                SELECT label
                FROM site_subdomains
                WHERE site_id = ?
                  AND enabled = 1
                  AND label <> '_default'
                ORDER BY label ASC
            ");
            $st->execute([$siteId]);
            $subs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $done = 0;
            $errors = 0;

            foreach ($subs as $sub) {
                $label = trim((string)($sub['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                try {
                    $this->generateHomeTextForLabel($siteId, $site, $row, $client, $label);
                    $done++;
                } catch (Throwable $e) {
                    $errors++;
                    hub_log('AI_SUB_TEXT_BATCH_ERROR', [
                        'site_id' => $siteId,
                        'label' => $label,
                        'err' => $e->getMessage(),
                    ]);
                }
            }

            $_SESSION['wm_log'][] = "AI all sub texts done={$done}, errors={$errors}";
            $this->redirect('/sites/ai?id=' . $siteId);
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
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
        $label = $this->normalizeAiLabel($label);

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
        $label = $this->normalizeAiLabel($label);

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
        $label = $this->normalizeAiLabel($label);

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
        $label = $this->normalizeAiLabel($label);

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
        $site = $this->loadSiteOrFail($siteId);
        $buildDir = $this->resolveBuildDir($site);

        return rtrim($buildDir, '/\\') . '/subs/' . $this->normalizeAiLabel($label) . '/texts';
    }

    private function normalizePagePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    private function makePageSlug(string $path): string
    {
        if ($path === '/') {
            return 'home';
        }

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
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));
            $path   = $this->normalizePagePath((string)($_GET['path'] ?? '/'));

            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();

            $domain = (string)$site['domain'];
            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
            $page = is_array($pages[$path] ?? null) ? $pages[$path] : [];

            $json = $this->generatePageMetaData($siteId, $site, $row, $label, $path, $cfg, $page);

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
            $this->saveSubCfgSafe($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI meta страницы сгенерированы: {$label} {$path}";
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
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));
            $path   = $this->normalizePagePath((string)($_GET['path'] ?? '/'));

            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();

            $domain = (string)$site['domain'];
            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
            $page = is_array($pages[$path] ?? null) ? $pages[$path] : [];

            if (empty($page['text_file'])) {
                $page['text_file'] = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
            }

            $res = $this->generatePageTextData($siteId, $site, $row, $label, $path, $cfg, $page);

            $dir = $this->textsDirForLabel($siteId, $label);
            Paths::ensureDir($dir);
            $fullPath = rtrim($dir, '/\\') . '/' . basename($res['text_file']);
            file_put_contents($fullPath, $res['html']);

            $page['text_file'] = $res['text_file'];
            $pages[$path] = $page;
            $cfg['pages'] = $pages;
            $this->saveSubCfgSafe($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI текст страницы сгенерирован: {$label} {$path}";
            $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode(basename($res['text_file'])));
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSelectedPages(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));
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

            $domain = (string)$site['domain'];
            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];

            if (!$pages) {
                throw new RuntimeException('В pages нет страниц');
            }

            $overwriteAll = $this->shouldOverwriteAll($siteId);
            $dir = $this->textsDirForLabel($siteId, $label);
            Paths::ensureDir($dir);

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
                    $metaJson = $this->generatePageMetaData($siteId, $site, $row, $label, $path, $cfg, $page);

                    $oldTitle = (string)($page['title'] ?? '');
                    $oldH1 = (string)($page['h1'] ?? '');
                    $oldDescription = (string)($page['description'] ?? '');
                    $oldKeywords = (string)($page['keywords'] ?? '');

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
                    $textRes = $this->generatePageTextData($siteId, $site, $row, $label, $path, $cfg, $page);
                    $page['text_file'] = $textRes['text_file'];

                    file_put_contents(
                        rtrim($dir, '/\\') . '/' . basename((string)$textRes['text_file']),
                        $textRes['html']
                    );
                }

                $done++;
            }
            unset($page);

            $cfg['pages'] = $pages;
            $this->saveSubCfgSafe($siteId, $label, $cfg);

            $_SESSION['wm_log'][] = "AI selected pages generated ({$done})";
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
        $label = $this->normalizeAiLabel($label);

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
        return '';
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

        $overwriteMode = trim((string)($_POST['overwrite_mode'] ?? 'fill_empty'));
        if (!in_array($overwriteMode, ['fill_empty', 'overwrite_all'], true)) {
            $overwriteMode = 'fill_empty';
        }

        $_SESSION[$this->sessionOptionsKey($siteId)] = [
            'overwrite_mode' => $overwriteMode,
        ];

        $_SESSION['wm_log'][] = 'AI run options saved';
        $this->redirect('/sites/ai?id=' . $siteId . '&label=' . urlencode((string)($_GET['label'] ?? '_default')));
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

        $this->redirect('/sites/ai?id=' . $siteId . '&label=' . urlencode((string)($_GET['label'] ?? '_default')));
    }

    private function normalizeAiLabel(string $label): string
    {
        $label = trim(strtolower($label));
        if ($label === '' || $label === '_default') {
            return '_default';
        }

        $label = preg_replace('~[^a-z0-9\-]+~', '', $label);
        return $label !== '' ? $label : '_default';
    }

    private function loadCatalogBrandName(string $label): string
    {
        $label = $this->normalizeAiLabel($label);

        if ($label === '_default') {
            return '';
        }

        $st = DB::pdo()->prepare("
            SELECT brand_name
            FROM subdomain_catalog
            WHERE label = ?
            LIMIT 1
        ");
        $st->execute([$label]);

        return trim((string)($st->fetchColumn() ?: ''));
    }

    private function fallbackBrandName(array $site, string $label): string
    {
        $label = $this->normalizeAiLabel($label);

        if ($label === '_default') {
            $domain = trim((string)($site['domain'] ?? ''));
            $root = preg_replace('~\..*$~', '', $domain);
            $root = trim((string)$root);
            return $root !== '' ? ucfirst($root) : '';
        }

        $catalogBrand = $this->loadCatalogBrandName($label);
        if ($catalogBrand !== '') {
            return $catalogBrand;
        }

        return ucfirst($label);
    }

    private function loadEntityAiSettings(int $siteId, string $label, array $site): array
    {
        $label = $this->normalizeAiLabel($label);

        $st = DB::pdo()->prepare("
            SELECT *
            FROM site_ai_label_settings
            WHERE site_id = ? AND label = ?
            LIMIT 1
        ");
        $st->execute([$siteId, $label]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $defaults = [
            'site_id' => $siteId,
            'label' => $label,
            'brand_name' => $this->fallbackBrandName($site, $label),
            'brand_count' => 5,
            'text_symbols' => 4000,
            'link_registration_path' => '',
            'link_slots_path' => '',
            'link_bonuses_path' => '',
            'required_phrases' => '',
            'forbidden_phrases' => '',
            'extra_instruction' => '',
        ];

        if (!$row) {
            return $defaults;
        }

        return array_merge($defaults, $row);
    }

    public function saveEntitySettings(): void
    {
        $this->requireAuth();

        $siteId = (int)($_GET['id'] ?? 0);
        $label = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));

        if ($siteId <= 0) {
            $this->redirect('/sites');
            return;
        }

        $brandName = trim((string)($_POST['brand_name'] ?? ''));
        $brandCount = max(0, (int)($_POST['brand_count'] ?? 5));
        $textSymbols = max(500, (int)($_POST['text_symbols'] ?? 4000));

        $linkRegistrationPath = trim((string)($_POST['link_registration_path'] ?? ''));
        $linkSlotsPath = trim((string)($_POST['link_slots_path'] ?? ''));
        $linkBonusesPath = trim((string)($_POST['link_bonuses_path'] ?? ''));

        $requiredPhrases = trim((string)($_POST['required_phrases'] ?? ''));
        $forbiddenPhrases = trim((string)($_POST['forbidden_phrases'] ?? ''));
        $extraInstruction = trim((string)($_POST['extra_instruction'] ?? ''));

        DB::pdo()->prepare("
            INSERT INTO site_ai_label_settings (
                site_id,
                label,
                brand_name,
                brand_count,
                text_symbols,
                link_registration_path,
                link_slots_path,
                link_bonuses_path,
                required_phrases,
                forbidden_phrases,
                extra_instruction
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                brand_name = VALUES(brand_name),
                brand_count = VALUES(brand_count),
                text_symbols = VALUES(text_symbols),
                link_registration_path = VALUES(link_registration_path),
                link_slots_path = VALUES(link_slots_path),
                link_bonuses_path = VALUES(link_bonuses_path),
                required_phrases = VALUES(required_phrases),
                forbidden_phrases = VALUES(forbidden_phrases),
                extra_instruction = VALUES(extra_instruction),
                updated_at = CURRENT_TIMESTAMP
        ")->execute([
            $siteId,
            $label,
            $brandName,
            $brandCount,
            $textSymbols,
            $linkRegistrationPath,
            $linkSlotsPath,
            $linkBonusesPath,
            $requiredPhrases,
            $forbiddenPhrases,
            $extraInstruction,
        ]);

        $_SESSION['wm_log'][] = 'AI entity settings saved: ' . $label;
        $this->redirect('/sites/ai?id=' . $siteId . '&label=' . urlencode($label));
    }

    private function extractPagePaths(array $cfg): array
    {
        $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
        $out = array_keys($pages);
        $out = array_map('strval', $out);
        sort($out);
        return $out;
    }

    private function normalizeInnerPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function buildEntityHost(array $site, string $label): string
    {
        $domain = trim((string)($site['domain'] ?? ''));
        $label = $this->normalizeAiLabel($label);

        if ($label === '_default') {
            return $domain;
        }

        return $label . '.' . $domain;
    }

    private function makeEntityUrl(array $site, string $label, string $path): string
    {
        $path = $this->normalizeInnerPath($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        $host = $this->buildEntityHost($site, $label);
        return 'https://' . $host . $path;
    }

    private function buildPromptVars(array $site, string $label, array $cfg, array $entityAi): array
    {
        $label = $this->normalizeAiLabel($label);

        $brand = trim((string)($entityAi['brand_name'] ?? ''));
        $brandCount = (string)((int)($entityAi['brand_count'] ?? 5));
        $symbols = (string)((int)($entityAi['text_symbols'] ?? 4000));

        $domain = trim((string)($site['domain'] ?? ''));
        $host = $this->buildEntityHost($site, $label);

        $linkRegistration = $this->makeEntityUrl($site, $label, (string)($entityAi['link_registration_path'] ?? ''));
        $linkSlots = $this->makeEntityUrl($site, $label, (string)($entityAi['link_slots_path'] ?? ''));
        $linkBonuses = $this->makeEntityUrl($site, $label, (string)($entityAi['link_bonuses_path'] ?? ''));

        $promolink = trim((string)($cfg['promolink'] ?? ''));
        $linkMirror = $this->makeEntityUrl($site, $label, $promolink);

        return [
            '{BRAND}' => $brand,
            '{BRAND_COUNT}' => $brandCount,
            '{SYMBOLS}' => $symbols,
            '{DOMAIN}' => $domain,
            '{HOST}' => $host,
            '{LABEL}' => $label,
            '{LINK_REGISTRATION}' => $linkRegistration,
            '{LINK_SLOTS}' => $linkSlots,
            '{LINK_BONUSES}' => $linkBonuses,
            '{LINK_MIRROR}' => $linkMirror,
        ];
    }

    private function buildPagePromptVars(array $site, string $label, array $cfg, array $entityAi, string $pagePath, string $textFile, array $page = []): array
    {
        $vars = $this->buildPromptVars($site, $label, $cfg, $entityAi);

        $vars['{PAGE_PATH}'] = $pagePath;
        $vars['{PAGE_URL}'] = $this->makeEntityUrl($site, $label, $pagePath);
        $vars['{PAGE_TEXT_FILE}'] = basename($textFile);
        $vars['{PAGE_TITLE}'] = (string)($page['title'] ?? '');
        $vars['{PAGE_H1}'] = (string)($page['h1'] ?? '');
        $vars['{PAGE_DESCRIPTION}'] = (string)($page['description'] ?? '');
        $vars['{PAGE_KEYWORDS}'] = (string)($page['keywords'] ?? '');

        return $vars;
    }

    private function replacePromptVars(string $text, array $vars): string
    {
        if ($text === '') {
            return '';
        }

        return strtr($text, $vars);
    }

    private function buildEntityExtraBlock(array $entityAi): string
    {
        $parts = [];

        $required = trim((string)($entityAi['required_phrases'] ?? ''));
        if ($required !== '') {
            $parts[] = "Обязательные вхождения / фразы:\n" . $required;
        }

        $forbidden = trim((string)($entityAi['forbidden_phrases'] ?? ''));
        if ($forbidden !== '') {
            $parts[] = "Запрещенные слова / фразы:\n" . $forbidden;
        }

        $extra = trim((string)($entityAi['extra_instruction'] ?? ''));
        if ($extra !== '') {
            $parts[] = "Дополнительная инструкция:\n" . $extra;
        }

        if (!$parts) {
            return '';
        }

        return "\n\n" . implode("\n\n", $parts);
    }

    private function applyMetaTemplates(array $json, array $aiRow, array $vars): array
    {
        $titleTpl = trim((string)($aiRow['global_meta_title_template'] ?? ''));
        $h1Tpl = trim((string)($aiRow['global_meta_h1_template'] ?? ''));
        $descTpl = trim((string)($aiRow['global_meta_description_template'] ?? ''));

        if ($titleTpl !== '') {
            $json['title'] = $this->replacePromptVars($titleTpl, $vars);
        }

        if ($h1Tpl !== '') {
            $json['h1'] = $this->replacePromptVars($h1Tpl, $vars);
        }

        if ($descTpl !== '') {
            $json['description'] = $this->replacePromptVars($descTpl, $vars);
        }

        return $json;
    }

    private function generateHomeTextForLabel(int $siteId, array $site, array $row, DeepseekClient $client, string $label): array
    {
        $label = $this->normalizeAiLabel($label);

        $domain = (string)($site['domain'] ?? '');
        $fqdn = ($label === '_default') ? $domain : ($label . '.' . $domain);

        $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
        $entityAi = $this->loadEntityAiSettings($siteId, $label, $site);
        $vars = $this->buildPromptVars($site, $label, $cfg, $entityAi);

        $prompt = trim((string)(
            $label === '_default'
                ? ($row['text_prompt_root'] ?? '')
                : ($row['text_prompt_sub'] ?? '')
        ));

        if ($prompt === '') {
            $prompt = 'Ты профессиональный SEO-копирайтер для iGaming. Верни только HTML-фрагмент без markdown и без пояснений.';
        }

        $prompt = $this->replacePromptVars($prompt, $vars);

        $userPrompt = "Сгенерируй HTML-текст для главной страницы сущности {$fqdn}.\n";
        $userPrompt .= "Бренд: " . (string)($vars['{BRAND}'] ?? '') . "\n";
        $userPrompt .= "Объём: " . (string)($vars['{SYMBOLS}'] ?? '') . "\n";
        $userPrompt .= "Ссылки для подстановки:\n";
        $userPrompt .= "- registration: " . (string)($vars['{LINK_REGISTRATION}'] ?? '') . "\n";
        $userPrompt .= "- slots: " . (string)($vars['{LINK_SLOTS}'] ?? '') . "\n";
        $userPrompt .= "- bonuses: " . (string)($vars['{LINK_BONUSES}'] ?? '') . "\n";
        $userPrompt .= "- mirror: " . (string)($vars['{LINK_MIRROR}'] ?? '') . "\n\n";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $result = $client->simpleText(
            'Ты профессиональный SEO-копирайтер для iGaming. Верни только HTML-фрагмент без markdown и без пояснений.',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            (int)($row['max_tokens'] ?? 1200)
        );

        $html = $this->cleanupAiText($result);
        if ($html === '') {
            throw new RuntimeException('AI вернул пустой текст');
        }

        $textFile = $this->detectHomeTextFile($cfg);
        $path = $this->writeSubTextFile($site, $label, $textFile, $html);

        return [
            'fqdn' => $fqdn,
            'file' => $textFile,
            'path' => $path,
        ];
    }

    private function generatePageMetaData(int $siteId, array $site, array $row, string $label, string $pagePath, array $cfg, array $page): array
    {
        $label = $this->normalizeAiLabel($label);

        $entityAi = $this->loadEntityAiSettings($siteId, $label, $site);
        $textFile = (string)($page['text_file'] ?? (($pagePath === '/') ? 'home.php' : ($this->makePageSlug($pagePath) . '.php')));
        $vars = $this->buildPagePromptVars($site, $label, $cfg, $entityAi, $pagePath, $textFile, $page);

        $prompt = trim((string)($row['page_meta_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = 'Ты SEO-редактор. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}';
        }

        $prompt = $this->replacePromptVars($prompt, $vars);

        $userPrompt = "Сгенерируй SEO meta для страницы.\n";
        $userPrompt .= "Хост: " . (string)($vars['{HOST}'] ?? '') . "\n";
        $userPrompt .= "Бренд: " . (string)($vars['{BRAND}'] ?? '') . "\n";
        $userPrompt .= "Страница: {$pagePath}\n\n";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $result = $this->aiClient()->simpleText(
            'Ты SEO-редактор. Верни строго JSON без markdown и пояснений: {"title":"","h1":"","description":"","keywords":""}',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            (int)($row['max_tokens'] ?? 1200)
        );

        $json = $this->cleanAiJson($result);
        return $this->applyMetaTemplates($json, $row, $vars);
    }

    private function generatePageTextData(int $siteId, array $site, array $row, string $label, string $pagePath, array $cfg, array $page): array
    {
        $label = $this->normalizeAiLabel($label);

        $entityAi = $this->loadEntityAiSettings($siteId, $label, $site);
        $textFile = (string)($page['text_file'] ?? (($pagePath === '/') ? 'home.php' : ($this->makePageSlug($pagePath) . '.php')));
        $vars = $this->buildPagePromptVars($site, $label, $cfg, $entityAi, $pagePath, $textFile, $page);

        $prompt = trim((string)($row['page_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = 'Ты веб-копирайтер. Верни только HTML-фрагмент без markdown и без пояснений.';
        }

        $prompt = $this->replacePromptVars($prompt, $vars);

        $userPrompt = "Сгенерируй HTML-текст для страницы {$pagePath}.\n";
        $userPrompt .= "Хост: " . (string)($vars['{HOST}'] ?? '') . "\n";
        $userPrompt .= "Бренд: " . (string)($vars['{BRAND}'] ?? '') . "\n\n";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $html = $this->aiClient()->simpleText(
            'Ты веб-копирайтер. Верни только HTML-фрагмент без markdown и без пояснений.',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            (int)($row['max_tokens'] ?? 1200)
        );

        $html = $this->cleanupAiText($html);
        if ($html === '') {
            throw new RuntimeException('AI вернул пустой текст страницы');
        }

        return [
            'text_file' => basename($textFile),
            'html' => $html,
        ];
    }

    private function generateAllPagesInternal(int $siteId, string $label): void
    {
        $label = $this->normalizeAiLabel($label);

        $site = $this->loadSiteOrFail($siteId);
        $row = $this->loadRow();

        $domain = (string)$site['domain'];
        $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
        $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];

        if (!$pages) {
            throw new RuntimeException('В pages нет страниц для генерации');
        }

        $overwriteAll = $this->shouldOverwriteAll($siteId);
        $dir = $this->textsDirForLabel($siteId, $label);
        Paths::ensureDir($dir);

        foreach ($pages as $path => &$page) {
            $path = $this->normalizePagePath((string)$path);

            if (!is_array($page)) {
                $page = [];
            }

            if (empty($page['text_file'])) {
                $page['text_file'] = ($path === '/') ? 'home.php' : ($this->makePageSlug($path) . '.php');
            }

            $metaJson = $this->generatePageMetaData($siteId, $site, $row, $label, $path, $cfg, $page);

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

            $textRes = $this->generatePageTextData($siteId, $site, $row, $label, $path, $cfg, $page);
            $page['text_file'] = $textRes['text_file'];

            file_put_contents(
                rtrim($dir, '/\\') . '/' . basename($textRes['text_file']),
                $textRes['html']
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
            $label  = $this->normalizeAiLabel((string)($_GET['label'] ?? '_default'));

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