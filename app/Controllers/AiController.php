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

    private function presetTableColumns(): array
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }

        $cols = [];
        try {
            $st = DB::pdo()->query("SHOW COLUMNS FROM ai_prompt_presets");
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

    private function presetTableHas(string $column): bool
    {
        $cols = $this->presetTableColumns();
        return isset($cols[strtolower($column)]);
    }

    private function buildPresetPayloadFromRow(array $row): array
    {
        return [
            'provider' => (string)($row['provider'] ?? 'deepseek'),
            'model' => (string)($row['model'] ?? 'deepseek-chat'),
            'temperature' => (float)($row['temperature'] ?? 0.7),
            'max_tokens' => (int)($row['max_tokens'] ?? 1200),
            'meta_prompt_root' => (string)($row['meta_prompt_root'] ?? ''),
            'meta_prompt_sub' => (string)($row['meta_prompt_sub'] ?? ''),
            'text_prompt_root' => (string)($row['text_prompt_root'] ?? ''),
            'text_prompt_sub' => (string)($row['text_prompt_sub'] ?? ''),
            'page_prompt' => (string)($row['page_prompt'] ?? ''),
            'page_meta_prompt' => (string)($row['page_meta_prompt'] ?? ''),
            'global_meta_title_template' => (string)($row['global_meta_title_template'] ?? ''),
            'global_meta_h1_template' => (string)($row['global_meta_h1_template'] ?? ''),
            'global_meta_description_template' => (string)($row['global_meta_description_template'] ?? ''),
            'prompt_v1' => (string)($row['prompt_v1'] ?? ''),
            'prompt_v2' => (string)($row['prompt_v2'] ?? ''),
        ];
    }

    private function extractPresetPayload(array $preset): array
    {
        $payloadJson = trim((string)($preset['payload_json'] ?? ''));
        if ($payloadJson !== '') {
            $payload = json_decode($payloadJson, true);
            if (is_array($payload)) {
                return $payload;
            }
        }

        return $this->buildPresetPayloadFromRow($preset);
    }

    private function ensurePresetTable(): void
    {
        DB::withReconnect(function(PDO $pdo) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_prompt_presets (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, description VARCHAR(255) NOT NULL DEFAULT '', payload_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            try {
                $pdo->exec("ALTER TABLE ai_prompt_presets ADD COLUMN payload_json LONGTEXT NULL AFTER description");
            } catch (Throwable $e) {
            }
        });
    }

    private function getPresets(): array
    {
        $this->ensurePresetTable();
        return DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->query("SELECT * FROM ai_prompt_presets ORDER BY title ASC, id DESC");
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    private function loadPresetById(int $id): ?array
    {
        $this->ensurePresetTable();
        return DB::withReconnect(function(PDO $pdo) use ($id) {
            $st = $pdo->prepare("SELECT * FROM ai_prompt_presets WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        });
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

    private function loadEffectiveAiRow(): array
    {
        // База для effective-row должна загружаться из ai_settings,
        // а не через рекурсивный вызов этого же метода.
        $row = $this->loadRow();

        $selectedPresetId = (int)($_GET['preset_id'] ?? 0);
        if ($selectedPresetId <= 0) {
            $selectedPresetId = (int)($_SESSION['ai_selected_preset_id'] ?? 0);
        }

        if ($selectedPresetId > 0) {
            $selectedPreset = $this->loadPresetById($selectedPresetId);
            if ($selectedPreset) {
                $payload = $this->extractPresetPayload($selectedPreset);
                foreach ($payload as $k => $v) {
                    if (is_string($k) && array_key_exists($k, $row)) {
                        $row[$k] = $v;
                    }
                }
            }
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

        $presets = $this->getPresets();
        $selectedPresetId = (int)($_GET['preset_id'] ?? 0);
        if ($selectedPresetId > 0) {
            $_SESSION['ai_selected_preset_id'] = $selectedPresetId;
        } elseif (!empty($_SESSION['ai_selected_preset_id'])) {
            $selectedPresetId = (int)$_SESSION['ai_selected_preset_id'];
        } elseif (!empty($presets[0]['id'])) {
            $selectedPresetId = (int)$presets[0]['id'];
        }

        $selectedPreset = null;
        if ($selectedPresetId > 0) {
            $selectedPreset = $this->loadPresetById($selectedPresetId);
            if ($selectedPreset) {
                $payload = $this->extractPresetPayload($selectedPreset);
                foreach ($payload as $k => $v) {
                    if (is_string($k) && array_key_exists($k, $row)) {
                        $row[$k] = $v;
                    }
                }
            }
        }

        $this->view('ai/settings', [
            'row' => $row,
            'apiKey' => $apiKey,
            'presets' => $presets,
            'selectedPresetId' => $selectedPresetId,
            'selectedPreset' => $selectedPreset,
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

        $action = trim((string)($_POST['preset_action'] ?? 'save_settings'));
        $newPresetTitle = trim((string)($_POST['new_preset_title'] ?? ''));
        $newPresetDescription = trim((string)($_POST['new_preset_description'] ?? ''));
        $editPresetTitle = trim((string)($_POST['edit_preset_title'] ?? ''));
        $editPresetDescription = trim((string)($_POST['edit_preset_description'] ?? ''));
        $presetId = (int)($_POST['preset_id'] ?? 0);

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

        if ($action === 'open_preset') {
            if ($presetId > 0) {
                $_SESSION['ai_selected_preset_id'] = $presetId;
                $this->redirect('/ai/settings?preset_id=' . $presetId);
            }
            unset($_SESSION['ai_selected_preset_id']);
            $this->redirect('/ai/settings');
            return;
        }

        if ($action === 'delete_preset' && $presetId > 0) {
            $this->ensurePresetTable();
            DB::pdo()->prepare("DELETE FROM ai_prompt_presets WHERE id=? LIMIT 1")->execute([$presetId]);
            if (((int)($_SESSION['ai_selected_preset_id'] ?? 0)) === $presetId) {
                unset($_SESSION['ai_selected_preset_id']);
            }
            $this->flash('success', 'Набор AI-шаблонов удален.');
            $this->redirect('/ai/settings');
            return;
        }

        if ($action === 'update_preset' && $presetId > 0) {
            $this->ensurePresetTable();
            if ($editPresetTitle === '') {
                $this->flash('error', 'Название набора не может быть пустым.');
                $this->redirect('/ai/settings?preset_id=' . $presetId);
                return;
            }
            DB::pdo()->prepare("UPDATE ai_prompt_presets SET title=?, description=? WHERE id=? LIMIT 1")->execute([$editPresetTitle, $editPresetDescription, $presetId]);
            $_SESSION['ai_selected_preset_id'] = $presetId;
            $this->flash('success', 'Название и описание набора обновлены.');
            $this->redirect('/ai/settings?preset_id=' . $presetId);
            return;
        }

        if ($action === 'save_preset' && $newPresetTitle !== '') {
            $this->ensurePresetTable();
            $payloadJson = json_encode($set, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($this->presetTableHas('provider')) {
                DB::pdo()->prepare("INSERT INTO ai_prompt_presets(title, description, provider, model, temperature, max_tokens, prompt_v1, prompt_v2, meta_prompt_root, meta_prompt_sub, text_prompt_root, text_prompt_sub, page_prompt, page_meta_prompt, global_meta_title_template, global_meta_h1_template, global_meta_description_template, payload_json) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $newPresetTitle,
                        $newPresetDescription,
                        (string)($set['provider'] ?? 'deepseek'),
                        (string)($set['model'] ?? 'deepseek-chat'),
                        (float)($set['temperature'] ?? 0.7),
                        (int)($set['max_tokens'] ?? 1200),
                        (string)($set['prompt_v1'] ?? ''),
                        (string)($set['prompt_v2'] ?? ''),
                        (string)($set['meta_prompt_root'] ?? ''),
                        (string)($set['meta_prompt_sub'] ?? ''),
                        (string)($set['text_prompt_root'] ?? ''),
                        (string)($set['text_prompt_sub'] ?? ''),
                        (string)($set['page_prompt'] ?? ''),
                        (string)($set['page_meta_prompt'] ?? ''),
                        (string)($set['global_meta_title_template'] ?? ''),
                        (string)($set['global_meta_h1_template'] ?? ''),
                        (string)($set['global_meta_description_template'] ?? ''),
                        $payloadJson,
                    ]);
            } else {
                DB::pdo()->prepare("INSERT INTO ai_prompt_presets(title, description, payload_json) VALUES(?, ?, ?)")
                    ->execute([$newPresetTitle, $newPresetDescription, $payloadJson]);
            }

            $newPresetId = (int)DB::pdo()->lastInsertId();
            if ($newPresetId > 0) {
                $_SESSION['ai_selected_preset_id'] = $newPresetId;
                $presetId = $newPresetId;
            }
            $this->flash('success', 'Набор AI-шаблонов сохранен как отдельный пресет.');
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

        if ($action === 'save_settings' && $presetId > 0) {
            $this->ensurePresetTable();
            $payloadJson = json_encode($set, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($this->presetTableHas('provider')) {
                DB::pdo()->prepare("UPDATE ai_prompt_presets SET provider=?, model=?, temperature=?, max_tokens=?, prompt_v1=?, prompt_v2=?, meta_prompt_root=?, meta_prompt_sub=?, text_prompt_root=?, text_prompt_sub=?, page_prompt=?, page_meta_prompt=?, global_meta_title_template=?, global_meta_h1_template=?, global_meta_description_template=?, payload_json=? WHERE id=? LIMIT 1")
                    ->execute([
                        (string)($set['provider'] ?? 'deepseek'),
                        (string)($set['model'] ?? 'deepseek-chat'),
                        (float)($set['temperature'] ?? 0.7),
                        (int)($set['max_tokens'] ?? 1200),
                        (string)($set['prompt_v1'] ?? ''),
                        (string)($set['prompt_v2'] ?? ''),
                        (string)($set['meta_prompt_root'] ?? ''),
                        (string)($set['meta_prompt_sub'] ?? ''),
                        (string)($set['text_prompt_root'] ?? ''),
                        (string)($set['text_prompt_sub'] ?? ''),
                        (string)($set['page_prompt'] ?? ''),
                        (string)($set['page_meta_prompt'] ?? ''),
                        (string)($set['global_meta_title_template'] ?? ''),
                        (string)($set['global_meta_h1_template'] ?? ''),
                        (string)($set['global_meta_description_template'] ?? ''),
                        $payloadJson,
                        $presetId,
                    ]);
            } else {
                DB::pdo()->prepare("UPDATE ai_prompt_presets SET payload_json=? WHERE id=? LIMIT 1")
                    ->execute([$payloadJson, $presetId]);
            }
            $_SESSION['ai_selected_preset_id'] = $presetId;
        }

        $this->flash('success', 'AI-настройки сохранены.');
        $_SESSION['wm_log'][] = 'AI settings saved';
        $redirectUrl = '/ai/settings';
        if ($presetId > 0) {
            $redirectUrl .= '?preset_id=' . $presetId;
        }
        $this->redirect($redirectUrl);
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
        $resolvedMirrorUrl = $this->normalizeInnerPath((string)($currentCfg['promolink'] ?? ''));

        $this->ensureAiQueueTables();
        $aiCron = $this->loadAiCronState();
        $aiQueue = $this->loadAiQueueOverview($siteId);

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
            'aiCron' => $aiCron,
            'aiQueue' => $aiQueue,
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
            $row = $this->loadEffectiveAiRow();

            $this->generateMetaForLabel($siteId, $site, $row, '_default');

            $this->flash('success', 'AI-мета для основного домена сгенерированы.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены SEO-данные сайта. Выгрузите актуальные данные на VPS.');
            $_SESSION['wm_log'][] = 'AI root meta generated';
            $this->redirect('/sites/subcfg?id=' . $siteId . '&label=_default');
        } catch (Throwable $e) {
            $this->logAiPageTextStage('error', [
                'site_id' => (int)($_GET['id'] ?? 0),
                'label' => (string)($_GET['label'] ?? '_default'),
                'path' => (string)($_GET['path'] ?? '/'),
                'err' => $e->getMessage(),
                'class' => get_class($e),
            ]);
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
            $row = $this->loadEffectiveAiRow();

            $resolver = new SiteConfigResolver(DB::pdo());
            $allLabels = $resolver->listLabels($siteId, true);

            $enabledMap = [];
            $st = DB::pdo()->prepare("SELECT label, enabled FROM site_subdomains WHERE site_id = ?");
            $st->execute([$siteId]);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $subRow) {
                $lb = $this->normalizeAiLabel((string)($subRow['label'] ?? ''));
                if ($lb !== '') {
                    $enabledMap[$lb] = (int)($subRow['enabled'] ?? 0) === 1;
                }
            }

            $labels = [];
            foreach ($allLabels as $label) {
                $label = $this->normalizeAiLabel((string)$label);
                if ($label === '_default') {
                    continue;
                }
                if (array_key_exists($label, $enabledMap) && !$enabledMap[$label]) {
                    continue;
                }
                $labels[] = $label;
            }
            $labels = array_values(array_unique($labels));

            $done = 0;
            $errors = 0;

            foreach ($labels as $label) {
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

            $this->flash($errors > 0 ? 'error' : 'success', $errors > 0 ? "AI-мета: выполнено {$done}, ошибок {$errors}." : "AI-мета для всех поддоменов сгенерированы: {$done}.");
            if ($done > 0) { (new PublishDirtyService())->markDirty($siteId, 'Изменены SEO-данные сайта. Выгрузите актуальные данные на VPS.'); }
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
            $row = $this->loadEffectiveAiRow();

            $this->generateMetaForLabel($siteId, $site, $row, $label);

            $this->flash('success', 'AI-мета для текущего поддомена сгенерированы.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены SEO-данные сайта. Выгрузите актуальные данные на VPS.');
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
        $textVars = $this->sanitizeTextPromptVars($vars);

        $prompt = trim((string)(
            $label === '_default'
                ? ($row['meta_prompt_root'] ?? '')
                : ($row['meta_prompt_sub'] ?? '')
        ));

        if ($prompt === '') {
            $prompt = 'Ты SEO-копирайтер. Верни только JSON без markdown и без пояснений. Формат: {"title":"","h1":"","description":"","keywords":""}';
        }

        $prompt = $this->replacePromptVars($prompt, $textVars);

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

            $this->flash('success', 'AI-текст для основного домена сгенерирован.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены тексты сайта. Выгрузите актуальные данные на VPS.');
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

            $this->flash('success', 'AI-текст для текущего поддомена сгенерирован.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены тексты сайта. Выгрузите актуальные данные на VPS.');
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

            $this->ensureAiQueueTables();
            $stats = $this->enqueueSubTextJobs($siteId);

            if ((int)($stats['queued'] ?? 0) > 0) {
                $this->flash('success', 'Генерация текстов поставлена в очередь: ' . (int)$stats['queued'] . ' задач. Крон обработает их по одной.');
            } else {
                $this->flash('error', 'Не найдено ни одного enabled поддомена для постановки в очередь.');
            }

            $_SESSION['wm_log'][] = 'AI sub text queue enqueued=' . (int)($stats['queued'] ?? 0);
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

            $labels = $_POST['labels'] ?? [];
            if (!is_array($labels) || !$labels) {
                throw new RuntimeException('Не выбрана ни одна метка');
            }

            $this->ensureAiQueueTables();
            $stats = $this->enqueueSelectedTextJobs($siteId, $labels);

            if ((int)($stats['queued'] ?? 0) > 0) {
                $this->flash('success', 'Тексты главной поставлены в очередь: ' . (int)$stats['queued'] . ' задач.');
            } else {
                $this->flash('error', 'Не удалось поставить задачи в очередь.');
            }

            $this->redirect('/sites/ai?id=' . $siteId);
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function generateSelectedPages(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $labels = $_POST['labels'] ?? [];
            if (!is_array($labels) || !$labels) {
                throw new RuntimeException('Не выбрана ни одна метка');
            }

            $this->ensureAiQueueTables();
            $stats = $this->enqueueSelectedPageJobs($siteId, $labels);

            if ((int)($stats['queued'] ?? 0) > 0) {
                $this->flash('success', 'Внутренние страницы поставлены в очередь: ' . (int)$stats['queued'] . ' задач. 404 не включается.');
            } else {
                $this->flash('error', 'Не найдено внутренних страниц для постановки в очередь.');
            }

            $this->redirect('/sites/ai?id=' . $siteId);
        } catch (Throwable $e) {
            hub_log('AI_ENQUEUE_SELECTED_PAGES_ERROR', ['site_id' => (int)($_GET['id'] ?? 0), 'err' => $e->getMessage()]);
            die($this->h($e->getMessage()));
        }
    }

    public function generateAllSubPages(): void
    {
        $this->requireAuth();

        try {
            $siteId = (int)($_GET['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('Некорректный site_id');
            }

            $this->ensureAiQueueTables();
            $stats = $this->enqueueAllSubPageJobs($siteId);

            if ((int)($stats['queued'] ?? 0) > 0) {
                $this->flash('success', 'Все внутренние страницы enabled label поставлены в очередь: ' . (int)$stats['queued'] . ' задач. 404 не включается.');
            } else {
                $this->flash('error', 'Не найдено внутренних страниц для очереди.');
            }

            $this->redirect('/sites/ai?id=' . $siteId);
        } catch (Throwable $e) {
            die($this->h($e->getMessage()));
        }
    }

    public function cron(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $this->ensureAiQueueTables();
            $result = $this->runAiQueueCycle();

            echo json_encode([
                'ok' => true,
                'processed' => (bool)($result['processed'] ?? false),
                'job' => $result['job'] ?? null,
                'message' => (string)($result['message'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }


    private function ensureAiQueueTables(): void
    {
        DB::withReconnect(function(PDO $pdo) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_queue_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL,
                label VARCHAR(64) NOT NULL,
                kind VARCHAR(32) NOT NULL,
                page_path VARCHAR(255) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'queued',
                tries INT NOT NULL DEFAULT 0,
                max_tries INT NOT NULL DEFAULT 1,
                error_text TEXT NULL,
                locked_at DATETIME NULL,
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_site_label_kind_page (site_id, label, kind, page_path),
                KEY idx_status_kind (status, kind, updated_at),
                KEY idx_site_kind (site_id, kind, status),
                KEY idx_site_label_page (site_id, label, page_path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            try {
                $pdo->exec("ALTER TABLE ai_queue_jobs ADD COLUMN page_path VARCHAR(255) NULL AFTER kind");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE ai_queue_jobs DROP INDEX ux_site_label_kind");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE ai_queue_jobs ADD UNIQUE KEY ux_site_label_kind_page (site_id, label, kind, page_path)");
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec("ALTER TABLE ai_queue_jobs ADD KEY idx_site_label_page (site_id, label, page_path)");
            } catch (Throwable $e) {
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_cron_state (
                id INT NOT NULL PRIMARY KEY,
                last_run_at DATETIME NULL,
                last_ok TINYINT(1) NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                last_job_site_id INT NULL,
                last_job_label VARCHAR(64) NOT NULL DEFAULT '',
                last_job_kind VARCHAR(32) NOT NULL DEFAULT '',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("INSERT IGNORE INTO ai_cron_state (id, last_ok, last_job_label, last_job_kind) VALUES (1, 0, '', '')");
        });
    }

    private function enqueueSubTextJobs(int $siteId): array
    {
        return DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id = ? AND enabled = 1 AND label <> '_default' ORDER BY label ASC");
            $st->execute([$siteId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $queued = 0;
            foreach ($rows as $row) {
                $label = $this->normalizeAiLabel((string)($row['label'] ?? ''));
                if ($label === '' || $label === '_default') {
                    continue;
                }
                $queued += $this->enqueueHomeTextJobRow($pdo, $siteId, $label);
            }

            return ['queued' => $queued];
        });
    }

    private function enqueueSelectedTextJobs(int $siteId, array $labels): array
    {
        $labels = $this->normalizeQueueLabels($labels);
        if (!$labels) {
            return ['queued' => 0];
        }

        return DB::withReconnect(function(PDO $pdo) use ($siteId, $labels) {
            $queued = 0;
            foreach ($labels as $label) {
                $queued += $this->enqueueHomeTextJobRow($pdo, $siteId, $label);
            }
            return ['queued' => $queued];
        });
    }

    private function enqueueAllSubPageJobs(int $siteId): array
    {
        $labels = DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("SELECT label FROM site_subdomains WHERE site_id = ? AND enabled = 1 ORDER BY CASE WHEN label='_default' THEN 0 ELSE 1 END, label ASC");
            $st->execute([$siteId]);
            return array_map(function(array $row) { return $this->normalizeAiLabel((string)($row['label'] ?? '')); }, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        });
        return $this->enqueueSelectedPageJobs($siteId, $labels);
    }

    private function enqueueSelectedPageJobs(int $siteId, array $labels): array
    {
        $labels = $this->normalizeQueueLabels($labels);
        if (!$labels) {
            return ['queued' => 0];
        }

        $site = $this->loadSiteOrFail($siteId);
        $domain = (string)($site['domain'] ?? '');
        $items = [];
        foreach ($labels as $label) {
            $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
            $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
            foreach ($pages as $path => $page) {
                $path = $this->normalizePagePath((string)$path);
                if ($path === '/' || $path === '/404') {
                    continue;
                }
                $items[] = [$label, $path];
            }
        }

        return DB::withReconnect(function(PDO $pdo) use ($siteId, $items) {
            $queued = 0;
            foreach ($items as $item) {
                [$label, $path] = $item;
                $queued += $this->enqueuePageJobRow($pdo, $siteId, $label, $path);
            }
            return ['queued' => $queued];
        });
    }

    private function normalizeQueueLabels(array $labels): array
    {
        $out = [];
        foreach ($labels as $label) {
            $label = $this->normalizeAiLabel((string)$label);
            if ($label === '') {
                continue;
            }
            $out[$label] = true;
        }
        return array_keys($out);
    }

    private function enqueueHomeTextJobRow(PDO $pdo, int $siteId, string $label): int
    {
        $kind = $label === '_default' ? 'root_home_text' : 'sub_home_text';
        $stmt = $pdo->prepare("INSERT INTO ai_queue_jobs (site_id, label, kind, page_path, status, tries, max_tries, error_text, locked_at, started_at, finished_at)
            VALUES (?, ?, ?, NULL, 'queued', 0, 1, NULL, NULL, NULL, NULL)
            ON DUPLICATE KEY UPDATE status='queued', tries=0, max_tries=1, error_text=NULL, locked_at=NULL, started_at=NULL, finished_at=NULL, updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$siteId, $label, $kind]);
        return 1;
    }

    private function enqueuePageJobRow(PDO $pdo, int $siteId, string $label, string $pagePath): int
    {
        $stmt = $pdo->prepare("INSERT INTO ai_queue_jobs (site_id, label, kind, page_path, status, tries, max_tries, error_text, locked_at, started_at, finished_at)
            VALUES (?, ?, 'page_bundle', ?, 'queued', 0, 1, NULL, NULL, NULL, NULL)
            ON DUPLICATE KEY UPDATE status='queued', tries=0, max_tries=1, error_text=NULL, locked_at=NULL, started_at=NULL, finished_at=NULL, updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$siteId, $label, $pagePath]);
        return 1;
    }

    private function loadAiCronState(): array
    {
        $this->ensureAiQueueTables();

        $row = DB::withReconnect(function(PDO $pdo) {
            $st = $pdo->query("SELECT * FROM ai_cron_state WHERE id = 1 LIMIT 1");
            return $st ? ($st->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        });

        $lastRun = (string)($row['last_run_at'] ?? '');
        $alive = false;
        if ($lastRun !== '') {
            $ts = strtotime($lastRun);
            $alive = $ts !== false && $ts >= (time() - 180);
        }
        $row['alive'] = $alive;
        return $row;
    }

    private function loadAiQueueOverview(int $siteId): array
    {
        $this->ensureAiQueueTables();

        return DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $jobStmt = $pdo->prepare("SELECT * FROM ai_queue_jobs WHERE site_id = ? ORDER BY updated_at DESC, id DESC LIMIT 100");
            $jobStmt->execute([$siteId]);
            $jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $summary = [
                'active_total' => 0,
                'queued' => 0,
                'running' => 0,
                'done' => 0,
                'error' => 0,
                'labels_total' => 0,
                'remaining' => 0,
            ];

            $labelsStmt = $pdo->prepare("SELECT COUNT(*) FROM site_subdomains WHERE site_id = ? AND enabled = 1 AND label <> '_default'");
            $labelsStmt->execute([$siteId]);
            $summary['labels_total'] = (int)$labelsStmt->fetchColumn();

            $items = [];
            foreach ($jobs as $job) {
                $status = (string)($job['status'] ?? 'queued');
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
                $items[] = [
                    'label' => (string)($job['label'] ?? ''),
                    'kind' => (string)($job['kind'] ?? ''),
                    'page_path' => (string)($job['page_path'] ?? ''),
                    'status' => $status,
                    'tries' => (int)($job['tries'] ?? 0),
                    'error_text' => (string)($job['error_text'] ?? ''),
                    'updated_at' => (string)($job['updated_at'] ?? ''),
                    'started_at' => (string)($job['started_at'] ?? ''),
                    'finished_at' => (string)($job['finished_at'] ?? ''),
                ];
            }

            $summary['active_total'] = (int)$summary['queued'] + (int)$summary['running'];
            $summary['remaining'] = $summary['active_total'];

            return [
                'summary' => $summary,
                'items' => $items,
                'has_active' => $summary['active_total'] > 0,
            ];
        });
    }

    private function updateAiCronState(bool $ok, array $job = [], string $error = ''): void
    {
        DB::withReconnect(function(PDO $pdo) use ($ok, $job, $error) {
            $pdo->prepare("UPDATE ai_cron_state SET last_run_at = NOW(), last_ok = ?, last_error = ?, last_job_site_id = ?, last_job_label = ?, last_job_kind = ?, updated_at = NOW() WHERE id = 1")
                ->execute([
                    $ok ? 1 : 0,
                    $error,
                    isset($job['site_id']) ? (int)$job['site_id'] : null,
                    (string)($job['label'] ?? ''),
                    (string)($job['kind'] ?? ''),
                ]);
        });
    }

    private function claimNextAiQueueJob(): ?array
    {
        return DB::withReconnect(function(PDO $pdo) {
            $pdo->beginTransaction();
            try {
                $st = $pdo->query("SELECT * FROM ai_queue_jobs
                    WHERE (kind IN ('sub_home_text','root_home_text','page_bundle'))
                      AND (
                        status = 'queued'
                        OR (status = 'running' AND locked_at IS NOT NULL AND locked_at < (NOW() - INTERVAL 30 MINUTE))
                      )
                    ORDER BY CASE WHEN status = 'running' THEN 0 ELSE 1 END, updated_at ASC, id ASC
                    LIMIT 1
                    FOR UPDATE");
                $job = $st ? ($st->fetch(PDO::FETCH_ASSOC) ?: null) : null;
                if (!$job) {
                    $pdo->commit();
                    return null;
                }

                $id = (int)$job['id'];
                $pdo->prepare("UPDATE ai_queue_jobs
                    SET status = 'running',
                        tries = tries + 1,
                        locked_at = NOW(),
                        started_at = IF(started_at IS NULL, NOW(), started_at),
                        error_text = NULL,
                        updated_at = NOW()
                    WHERE id = ? LIMIT 1")
                    ->execute([$id]);
                $pdo->commit();
                $job['status'] = 'running';
                $job['tries'] = (int)($job['tries'] ?? 0) + 1;
                return $job;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        });
    }

    private function finishAiQueueJob(int $id, bool $ok, string $error = ''): void
    {
        DB::withReconnect(function(PDO $pdo) use ($id, $ok, $error) {
            $pdo->prepare("UPDATE ai_queue_jobs
                SET status = ?, error_text = ?, locked_at = NULL, finished_at = NOW(), updated_at = NOW()
                WHERE id = ? LIMIT 1")
                ->execute([$ok ? 'done' : 'error', $error !== '' ? $error : null, $id]);
        });
    }

    private function runAiQueueCycle(): array
    {
        $job = $this->claimNextAiQueueJob();
        if (!$job) {
            $this->updateAiCronState(true, [], '');
            return ['processed' => false, 'message' => 'queue is empty'];
        }

        try {
            $siteId = (int)($job['site_id'] ?? 0);
            $label = $this->normalizeAiLabel((string)($job['label'] ?? ''));
            $kind = (string)($job['kind'] ?? '');
            $pagePath = $this->normalizePagePath((string)($job['page_path'] ?? '/'));
            if ($siteId <= 0 || $label === '') {
                throw new RuntimeException('Некорректная AI-задача очереди');
            }

            $site = $this->loadSiteOrFail($siteId);
            $row = $this->loadRow();
            $client = $this->aiClient();
            $resultJob = ['site_id' => $siteId, 'label' => $label, 'kind' => $kind, 'page_path' => $pagePath];

            if ($kind === 'sub_home_text' || $kind === 'root_home_text') {
                $res = $this->generateHomeTextForLabel($siteId, $site, $row, $client, $label);
                $resultJob['file'] = (string)($res['file'] ?? '');
                hub_log('AI_QUEUE_JOB_DONE', ['site_id' => $siteId, 'label' => $label, 'kind' => $kind, 'file' => $resultJob['file'], 'path' => (string)($res['path'] ?? '')]);
                (new PublishDirtyService())->markDirty($siteId, 'Изменены тексты сайта. Выгрузите актуальные данные на VPS.');
            } elseif ($kind === 'page_bundle') {
                $res = $this->generateSingleInternalPageBundle($siteId, $site, $row, $label, $pagePath);
                $resultJob['file'] = (string)($res['text_file'] ?? '');
                hub_log('AI_QUEUE_JOB_DONE', ['site_id' => $siteId, 'label' => $label, 'kind' => $kind, 'page_path' => $pagePath, 'file' => $resultJob['file']]);
                (new PublishDirtyService())->markDirty($siteId, 'Изменены страницы и тексты сайта. Выгрузите актуальные данные на VPS.');
            } else {
                throw new RuntimeException('Неподдерживаемый вид AI-задачи: ' . $kind);
            }

            $this->finishAiQueueJob((int)$job['id'], true, '');
            $this->updateAiCronState(true, $resultJob, '');

            return [
                'processed' => true,
                'job' => $resultJob,
                'message' => 'done',
            ];
        } catch (Throwable $e) {
            $this->finishAiQueueJob((int)$job['id'], false, $e->getMessage());
            $this->updateAiCronState(false, $job, $e->getMessage());
            hub_log('AI_QUEUE_JOB_ERROR', [
                'site_id' => (int)($job['site_id'] ?? 0),
                'label' => (string)($job['label'] ?? ''),
                'kind' => (string)($job['kind'] ?? ''),
                'page_path' => (string)($job['page_path'] ?? ''),
                'err' => $e->getMessage(),
            ]);
            return [
                'processed' => true,
                'job' => [
                    'site_id' => (int)($job['site_id'] ?? 0),
                    'label' => (string)($job['label'] ?? ''),
                    'kind' => (string)($job['kind'] ?? ''),
                    'page_path' => (string)($job['page_path'] ?? ''),
                ],
                'message' => 'error: ' . $e->getMessage(),
            ];
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

    private function normalizeRootPageInheritance(array &$cfg): void
    {
        $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
        $root = is_array($pages['/'] ?? null) ? $pages['/'] : [];
        if (empty($root['text_file'])) {
            $root['text_file'] = 'home.php';
        }
        if (!isset($root['priority'])) {
            $root['priority'] = '1.0';
        }
        if (!array_key_exists('sitemap', $root)) {
            $root['sitemap'] = true;
        }
        $root['title'] = '$inherit';
        $root['h1'] = '$inherit';
        $root['description'] = '$inherit';
        $root['keywords'] = '$inherit';
        $pages['/'] = $root;
        $cfg['pages'] = $pages;
    }

    private function applyGeneratedRootMeta(array &$cfg, array $json): void
    {
        $cfg['title'] = trim((string)($json['title'] ?? ''));
        $cfg['h1'] = trim((string)($json['h1'] ?? ''));
        $cfg['description'] = trim((string)($json['description'] ?? ''));
        $cfg['keywords'] = trim((string)($json['keywords'] ?? ''));
        $this->normalizeRootPageInheritance($cfg);
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

            if ($path === '/') {
                $this->applyGeneratedRootMeta($cfg, $json);
                $this->saveSubCfgSafe($siteId, $label, $cfg);
            } else {
                $page['title'] = (string)($json['title'] ?? '$inherit');
                $page['h1'] = (string)($json['h1'] ?? '$inherit');
                $page['description'] = (string)($json['description'] ?? '$inherit');
                $page['keywords'] = (string)($json['keywords'] ?? '$inherit');

                if (empty($page['text_file'])) {
                    $page['text_file'] = $this->makePageSlug($path) . '.php';
                }

                $pages[$path] = $page;
                $cfg['pages'] = $pages;
                $this->normalizeRootPageInheritance($cfg);
                $this->saveSubCfgSafe($siteId, $label, $cfg);
            }

            $this->flash('success', 'AI-мета для страницы сгенерированы.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены SEO-данные сайта. Выгрузите актуальные данные на VPS.');
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

            $this->logAiPageTextStage('start', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
            ]);

            $this->aiPageTextLog('start', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
            ]);

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

            $this->aiPageTextLog('before_generate', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
                'text_file' => basename((string)$page['text_file']),
                'had_text_file_before' => !empty($page['text_file']) ? 1 : 0,
            ]);

            $this->logAiPageTextStage('before_generate', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
                'text_file' => (string)$page['text_file'],
                'had_text_file_before' => !empty($page['text_file']) ? 1 : 0,
            ]);

            $res = $this->generatePageTextData($siteId, $site, $row, $label, $path, $cfg, $page);

            $this->aiPageTextLog('after_generate', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
                'text_file' => basename((string)$res['text_file']),
                'html_len' => mb_strlen((string)$res['html']),
            ]);

            $dir = $this->textsDirForLabel($siteId, $label);
            Paths::ensureDir($dir);
            $fullPath = rtrim($dir, '/\\') . '/' . basename($res['text_file']);
            file_put_contents($fullPath, $res['html']);
            $this->aiPageTextLog('file_written', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
                'full_path' => $fullPath,
            ]);

            $page['text_file'] = $res['text_file'];
            $pages[$path] = $page;
            $cfg['pages'] = $pages;
            $this->normalizeRootPageInheritance($cfg);
            $this->saveSubCfgSafe($siteId, $label, $cfg);
            $this->aiPageTextLog('config_saved', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
                'text_file' => basename((string)$res['text_file']),
            ]);

            $this->flash('success', 'AI-текст страницы сгенерирован.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены тексты сайта. Выгрузите актуальные данные на VPS.');
            $_SESSION['wm_log'][] = "AI текст страницы сгенерирован: {$label} {$path}";
            $this->aiPageTextLog('done', [
                'site_id' => $siteId,
                'label' => $label,
                'path' => $path,
            ]);
            $this->redirect('/sites/texts/edit?id=' . $siteId . '&label=' . urlencode($label) . '&file=' . rawurlencode(basename($res['text_file'])));
        } catch (Throwable $e) {
            $this->aiPageTextLog('error', [
                'site_id' => (int)($_GET['id'] ?? 0),
                'label' => $this->normalizeAiLabel((string)($_GET['label'] ?? '_default')),
                'path' => $this->normalizePagePath((string)($_GET['path'] ?? '/')),
                'message' => $e->getMessage(),
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
            if ($label === '_default') {
                $rootCfg = $cfg;
                unset($rootCfg['label']);
                $rootJson = json_encode($rootCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $pdo = DB::pdo();
                $pdo->prepare("INSERT INTO site_default_configs (site_id, config_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), updated_at = CURRENT_TIMESTAMP")->execute([$siteId, $rootJson]);
                $pdo->prepare("INSERT INTO site_configs (site_id, json) VALUES (?, ?) ON DUPLICATE KEY UPDATE json = VALUES(json), updated_at = CURRENT_TIMESTAMP")->execute([$siteId, $rootJson]);
            }
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

        $this->flash('success', 'Batch-настройка сохранена.');
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
        $this->flash('success', 'Batch-настройка сброшена.');
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

    private function loadCatalogBrandNameRu(string $label): string
    {
        $label = $this->normalizeAiLabel($label);

        if ($label === '_default') {
            return '';
        }

        try {
            DB::pdo()->exec("ALTER TABLE subdomain_catalog ADD COLUMN brand_name_ru VARCHAR(255) NOT NULL DEFAULT '' AFTER brand_name");
        } catch (Throwable $e) {
        }

        $st = DB::pdo()->prepare("SELECT brand_name_ru FROM subdomain_catalog WHERE label = ? LIMIT 1");
        $st->execute([$label]);
        return trim((string)($st->fetchColumn() ?: ''));
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

    private function fallbackBrandNameRu(array $site, string $label): string
    {
        $label = $this->normalizeAiLabel($label);
        if ($label === '_default') {
            return '';
        }
        $catalogBrandRu = $this->loadCatalogBrandNameRu($label);
        return $catalogBrandRu !== '' ? $catalogBrandRu : '';
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
            'brand_name' => '{BRAND}',
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

        $copyAllLabels = !empty($_POST['copy_all_labels']);

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

        if ($copyAllLabels) {
            $labelsStmt = DB::pdo()->prepare("SELECT label FROM site_subdomains WHERE site_id = ? AND enabled = 1 ORDER BY label ASC");
            $labelsStmt->execute([$siteId]);
            $labelRows = $labelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($labelRows as $labelRow) {
                $targetLabel = $this->normalizeAiLabel((string)($labelRow['label'] ?? ''));
                DB::pdo()->prepare("INSERT INTO site_ai_label_settings (site_id,label,brand_name,brand_count,text_symbols,link_registration_path,link_slots_path,link_bonuses_path,required_phrases,forbidden_phrases,extra_instruction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name), brand_count=VALUES(brand_count), text_symbols=VALUES(text_symbols), link_registration_path=VALUES(link_registration_path), link_slots_path=VALUES(link_slots_path), link_bonuses_path=VALUES(link_bonuses_path), required_phrases=VALUES(required_phrases), forbidden_phrases=VALUES(forbidden_phrases), extra_instruction=VALUES(extra_instruction), updated_at=CURRENT_TIMESTAMP")
                    ->execute([$siteId,$targetLabel,$brandName,$brandCount,$textSymbols,$linkRegistrationPath,$linkSlotsPath,$linkBonusesPath,$requiredPhrases,$forbiddenPhrases,$extraInstruction]);
            }
            $this->flash('success', 'Шаблоны и переменные сохранены для текущего label и скопированы на все label сайта.');
        } else {
            $this->flash('success', 'Шаблоны и переменные для текущего label сохранены.');
        }

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
            $parts = parse_url($path);
            $normalized = (string)($parts['path'] ?? '/');
            if ($normalized === '') {
                $normalized = '/';
            }
            if (!empty($parts['query'])) {
                $normalized .= '?' . $parts['query'];
            }
            if (!empty($parts['fragment'])) {
                $normalized .= '#' . $parts['fragment'];
            }
            return $normalized;
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

    private function buildPreferredBrandForText(array $vars): string
    {
        $brandRu = trim((string)($vars['{BRAND_RU}'] ?? ''));
        if ($brandRu !== '') {
            return $brandRu;
        }

        return trim((string)($vars['{BRAND}'] ?? ''));
    }

    private function sanitizeTextPromptVars(array $vars): array
    {
        $vars['{HOST}'] = '';
        $vars['{DOMAIN}'] = '';
        $vars['{LABEL}'] = '';

        if (isset($vars['{PAGE_PATH}'])) {
            $vars['{PAGE_URL}'] = (string)$vars['{PAGE_PATH}'];
        }

        return $vars;
    }

    private function stripHostMentionsFromText(string $text, array $site, string $label, array $vars): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        $replacement = $this->buildPreferredBrandForText($vars);
        $host = trim($this->buildEntityHost($site, $label));
        $domain = trim((string)($site['domain'] ?? ''));

        $needles = array_values(array_filter(array_unique([
            $host,
            $domain,
            'https://' . $host,
            'http://' . $host,
            'https://www.' . $host,
            'http://www.' . $host,
            'www.' . $host,
            'https://' . $domain,
            'http://' . $domain,
            'https://www.' . $domain,
            'http://www.' . $domain,
            'www.' . $domain,
        ])));

        foreach ($needles as $needle) {
            $quoted = preg_quote($needle, '~');
            $text = preg_replace('~' . $quoted . '~iu', $replacement, $text);
        }

        if ($replacement === '') {
            $text = preg_replace('~\(\s*\)~u', '', $text);
            $text = preg_replace('~\s{2,}~u', ' ', $text);
        }

        return trim($text);
    }



    private function aiPageTextLog(string $stage, array $data = []): void
    {
        $payload = array_merge(['stage' => $stage], $data);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"stage":"' . addslashes($stage) . '","log_error":"json_encode_failed"}';
        }
        error_log('[AI_PAGE_TEXT_STAGE] ' . $json);
    }

    private function countBrandMentionsInHtml(string $html, string $brand): int
    {
        $brand = trim($brand);
        if ($brand === '') {
            return 0;
        }

        $plain = trim((string)preg_replace('~<[^>]+>~u', ' ', $html));
        if ($plain === '') {
            return 0;
        }

        $quoted = preg_quote($brand, '~');
        if (!preg_match_all('~' . $quoted . '~iu', $plain, $m)) {
            return 0;
        }

        return count($m[0]);
    }

    private function analyzeGeneratedTextTargets(string $html, array $vars): array
    {
        $plain = trim((string)preg_replace('~<[^>]+>~u', ' ', $html));
        $plain = preg_replace('~\s+~u', ' ', $plain);
        $brand = $this->buildPreferredBrandForText($vars);
        $targetSymbols = max(500, (int)($vars['{SYMBOLS}'] ?? 4000));
        $targetBrandCount = max(0, (int)($vars['{BRAND_COUNT}'] ?? 0));
        $actualSymbols = mb_strlen($plain);
        $actualBrandCount = $this->countBrandMentionsInHtml($html, $brand);
        $minSymbols = max(400, (int)floor($targetSymbols * 0.85));
        $maxSymbols = max(600, (int)ceil($targetSymbols * 1.15));
        $brandMin = max(0, $targetBrandCount - 1);
        $brandMax = $targetBrandCount > 0 ? ($targetBrandCount + 1) : 999999;

        return [
            'brand' => $brand,
            'target_symbols' => $targetSymbols,
            'actual_symbols' => $actualSymbols,
            'min_symbols' => $minSymbols,
            'max_symbols' => $maxSymbols,
            'target_brand_count' => $targetBrandCount,
            'actual_brand_count' => $actualBrandCount,
            'brand_min' => $brandMin,
            'brand_max' => $brandMax,
            'symbols_ok' => $actualSymbols >= $minSymbols && $actualSymbols <= $maxSymbols,
            'brand_ok' => $targetBrandCount <= 0 ? true : ($actualBrandCount >= $brandMin && $actualBrandCount <= $brandMax),
        ];
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
        $brandFallback = $this->fallbackBrandName($site, $label);
        $brandRu = $this->fallbackBrandNameRu($site, $label);
        if ($brandRu === '') {
            $brandRu = $brandFallback;
        }

        if ($brand === '' || preg_match('~\{[A-Z0-9_]+\}~', $brand)) {
            $brand = $brandFallback !== '' ? $brandFallback : $brandRu;
        }

        $brandCount = (string)((int)($entityAi['brand_count'] ?? 5));
        $symbols = (string)((int)($entityAi['text_symbols'] ?? 4000));

        $domain = trim((string)($site['domain'] ?? ''));
        $host = $this->buildEntityHost($site, $label);

        $linkRegistration = $this->makeEntityUrl($site, $label, (string)($entityAi['link_registration_path'] ?? ''));
        $linkSlots = $this->makeEntityUrl($site, $label, (string)($entityAi['link_slots_path'] ?? ''));
        $linkBonuses = $this->makeEntityUrl($site, $label, (string)($entityAi['link_bonuses_path'] ?? ''));

        $promolink = trim((string)($cfg['promolink'] ?? ''));
        $linkMirror = $this->normalizeInnerPath($promolink);

        return [
            '{BRAND}' => $brand,
            '{BRAND_RU}' => $brandRu,
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

    private function buildTextGenerationRequirementsBlock(array $vars): string
    {
        $parts = [];

        $brand = trim((string)($vars['{BRAND}'] ?? ''));
        $brandCount = max(0, (int)($vars['{BRAND_COUNT}'] ?? 0));
        $symbols = max(500, (int)($vars['{SYMBOLS}'] ?? 0));

        $parts[] = "Технические требования к тексту:";
        $parts[] = "- итоговый объём текста: около {$symbols} символов без искусственного раздувания";
        $parts[] = "- минимально допустимый объём: " . max(400, (int)floor($symbols * 0.85)) . " символов";
        $parts[] = "- максимально допустимый объём: " . max(600, (int)ceil($symbols * 1.15)) . " символов";

        if ($brand !== '') {
            if ($brandCount > 0) {
                $brandRu = trim((string)($vars['{BRAND_RU}'] ?? ''));
                $parts[] = "- в тексте должны использоваться только точные формы бренда {$brand}" . ($brandRu !== '' && $brandRu !== $brand ? " и {$brandRu}" : '') . ", без склонений и без разбиения названия";
                $parts[] = "- суммарное количество точных упоминаний всех допустимых вариантов бренда должно быть ровно {$brandCount}";
                if ($brandRu !== '' && $brandRu !== $brand) {
                    $parts[] = "- считай общую сумму упоминаний {$brand} + {$brandRu}; эта совокупная сумма не должна превышать {$brandCount} и должна быть ровно {$brandCount}";
                }
                $parts[] = "- перед ответом самостоятельно перепроверь итоговое количество упоминаний бренда";
            } else {
                $parts[] = "- не злоупотребляй повторением бренда {$brand}";
            }
        }

        $parts[] = "- не используй хост, домен, URL или label как имя бренда в тексте";
        $parts[] = "- если русский вариант бренда известен, используй только допустимые формы бренда и контролируй их суммарное количество";
        $parts[] = "- не подставляй в текст технические переменные, домены и служебные значения";

        return "\n\n" . implode("\n", $parts);
    }

    private function resolveTextMaxTokens(array $row, array $vars): int
    {
        $base = max(200, (int)($row['max_tokens'] ?? 1200));
        $symbols = max(500, (int)($vars['{SYMBOLS}'] ?? 4000));
        $estimated = (int)ceil($symbols / 1.8) + 700;

        return min(8000, max($base, $estimated));
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
        $textVars = $this->sanitizeTextPromptVars($vars);

        $prompt = trim((string)(
            $label === '_default'
                ? ($row['text_prompt_root'] ?? '')
                : ($row['text_prompt_sub'] ?? '')
        ));

        if ($prompt === '') {
            $prompt = 'Ты профессиональный SEO-копирайтер для iGaming. Верни только HTML-фрагмент без markdown и без пояснений.';
        }

        $prompt = $this->replacePromptVars($prompt, $textVars);

        $userPrompt = "Сгенерируй HTML-текст для главной страницы бренда.\n";
        $userPrompt .= "Бренд: " . (string)($textVars['{BRAND}'] ?? '') . "\n";
        $userPrompt .= "Объём: " . (string)($textVars['{SYMBOLS}'] ?? '') . "\n";
        $userPrompt .= "Ссылки для подстановки:\n";
        $userPrompt .= "- registration: " . (string)($textVars['{LINK_REGISTRATION}'] ?? '') . "\n";
        $userPrompt .= "- slots: " . (string)($textVars['{LINK_SLOTS}'] ?? '') . "\n";
        $userPrompt .= "- bonuses: " . (string)($textVars['{LINK_BONUSES}'] ?? '') . "\n";
        $userPrompt .= "- mirror: " . (string)($textVars['{LINK_MIRROR}'] ?? '') . "\n\n";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildTextGenerationRequirementsBlock($textVars);
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $result = $client->simpleText(
            'Ты профессиональный SEO-копирайтер для iGaming. Верни только HTML-фрагмент без markdown и без пояснений.',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            $this->resolveTextMaxTokens($row, $textVars)
        );

        $html = $this->cleanupAiText($result);
        $html = $this->stripHostMentionsFromText($html, $site, $label, $textVars);
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

        $this->logAiPageTextStage('before_entity_settings', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
        ]);

        $entityAi = DB::withReconnect(function () use ($siteId, $label, $site) {
            return $this->loadEntityAiSettings($siteId, $label, $site);
        });

        $textFile = (string)($page['text_file'] ?? (($pagePath === '/') ? 'home.php' : ($this->makePageSlug($pagePath) . '.php')));
        $vars = $this->buildPagePromptVars($site, $label, $cfg, $entityAi, $pagePath, $textFile, $page);
        $textVars = $this->sanitizeTextPromptVars($vars);

        $this->logAiPageTextStage('entity_settings_loaded', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
            'brand' => (string)($textVars['{BRAND}'] ?? ''),
            'brand_ru' => (string)($textVars['{BRAND_RU}'] ?? ''),
            'brand_count' => (int)($textVars['{BRAND_COUNT}'] ?? 0),
            'symbols' => (int)($textVars['{SYMBOLS}'] ?? 0),
        ]);

        $prompt = trim((string)($row['page_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = 'Ты веб-копирайтер. Верни только HTML-фрагмент без markdown и без пояснений.';
        }

        $prompt = $this->replacePromptVars($prompt, $textVars);

        $userPrompt = "Сгенерируй HTML-текст для страницы {$pagePath}.\n";
        $userPrompt .= "Бренд: " . (string)($textVars['{BRAND}'] ?? '') . "\n\n";
        $userPrompt .= $prompt;
        $userPrompt .= $this->buildTextGenerationRequirementsBlock($textVars);
        $userPrompt .= $this->buildEntityExtraBlock($entityAi);

        $maxTokens = $this->resolveTextMaxTokens($row, $textVars);
        $this->logAiPageTextStage('ai_request_prepared', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
            'text_file' => (string)$textFile,
            'user_prompt_len' => mb_strlen($userPrompt),
            'model' => (string)($row['model'] ?? 'deepseek-chat'),
            'temperature' => (float)($row['temperature'] ?? 0.7),
            'max_tokens' => $maxTokens,
            'brand_count' => (int)($textVars['{BRAND_COUNT}'] ?? 0),
            'symbols' => (int)($textVars['{SYMBOLS}'] ?? 0),
        ]);

        DB::reset();
        $this->logAiPageTextStage('db_reset_before_ai', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
        ]);

        $html = $this->aiClient()->simpleText(
            'Ты веб-копирайтер. Верни только HTML-фрагмент без markdown и без пояснений.',
            $userPrompt,
            (string)($row['model'] ?? 'deepseek-chat'),
            (float)($row['temperature'] ?? 0.7),
            $maxTokens
        );

        $this->logAiPageTextStage('ai_response_received', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
            'raw_len' => mb_strlen((string)$html),
        ]);

        DB::reconnect();
        $this->logAiPageTextStage('db_reconnected_after_ai', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
        ]);

        $html = $this->cleanupAiText($html);
        $html = $this->stripHostMentionsFromText($html, $site, $label, $textVars);
        if ($html === '') {
            throw new RuntimeException('AI вернул пустой текст страницы');
        }

        $analysis = $this->analyzeGeneratedTextTargets($html, $textVars);
        $this->logAiPageTextStage('generated_text_analyzed', [
            'site_id' => $siteId,
            'label' => $label,
            'path' => $pagePath,
            'actual_symbols' => (int)($analysis['actual_symbols'] ?? 0),
            'target_symbols' => (int)($analysis['target_symbols'] ?? 0),
            'actual_brand_count' => (int)($analysis['actual_brand_count'] ?? 0),
            'target_brand_count' => (int)($analysis['target_brand_count'] ?? 0),
            'symbols_ok' => !empty($analysis['symbols_ok']) ? 1 : 0,
            'brand_ok' => !empty($analysis['brand_ok']) ? 1 : 0,
        ]);

        return [
            'text_file' => basename($textFile),
            'html' => $html,
        ];
    }

    private function logAiPageTextStage(string $stage, array $ctx = []): void
    {
        if (!function_exists('hub_log')) {
            return;
        }
        $ctx['stage'] = $stage;
        hub_log('AI_PAGE_TEXT_STAGE', $ctx);
    }


    private function generateSingleInternalPageBundle(int $siteId, array $site, array $row, string $label, string $path): array
    {
        $label = $this->normalizeAiLabel($label);
        $path = $this->normalizePagePath($path);
        if ($path === '/' || $path === '/404') {
            throw new RuntimeException('Для очереди внутренних страниц доступны только внутренние URL кроме 404');
        }

        $domain = (string)($site['domain'] ?? '');
        $cfg = $this->loadSubCfgOrCreate($siteId, $label, $domain);
        $pages = is_array($cfg['pages'] ?? null) ? $cfg['pages'] : [];
        $page = is_array($pages[$path] ?? null) ? $pages[$path] : [];
        if (!$page && !array_key_exists($path, $pages)) {
            throw new RuntimeException('Страница не найдена в config pages: ' . $path);
        }
        if (empty($page['text_file'])) {
            $page['text_file'] = $this->makePageSlug($path) . '.php';
        }

        $metaJson = $this->generatePageMetaData($siteId, $site, $row, $label, $path, $cfg, $page);
        $page['title'] = (string)($metaJson['title'] ?? '$inherit');
        $page['h1'] = (string)($metaJson['h1'] ?? '$inherit');
        $page['description'] = (string)($metaJson['description'] ?? '$inherit');
        $page['keywords'] = (string)($metaJson['keywords'] ?? '$inherit');

        $textRes = $this->generatePageTextData($siteId, $site, $row, $label, $path, $cfg, $page);
        $page['text_file'] = $textRes['text_file'];

        $dir = $this->textsDirForLabel($siteId, $label);
        Paths::ensureDir($dir);
        file_put_contents(rtrim($dir, '/\\') . '/' . basename((string)$textRes['text_file']), (string)$textRes['html']);

        $pages[$path] = $page;
        $cfg['pages'] = $pages;
        $this->normalizeRootPageInheritance($cfg);
        $this->saveSubCfgSafe($siteId, $label, $cfg);

        return [
            'path' => $path,
            'text_file' => (string)$textRes['text_file'],
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

            if ($path === '/') {
                $this->applyGeneratedRootMeta($cfg, $metaJson);
                $page = is_array($cfg['pages']['/'] ?? null) ? $cfg['pages']['/'] : $page;
            } else {
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
        $this->normalizeRootPageInheritance($cfg);
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

            $this->flash('success', 'AI-генерация всех страниц для текущего label завершена.');
            (new PublishDirtyService())->markDirty($siteId, 'Изменены страницы и тексты сайта. Выгрузите актуальные данные на VPS.');
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