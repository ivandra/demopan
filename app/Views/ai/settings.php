<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$row = is_array($row ?? null) ? $row : [];
$apiKey = (string)($apiKey ?? '');

$provider = (string)($row['provider'] ?? 'deepseek');
$model = (string)($row['model'] ?? 'deepseek-chat');
$temperature = (string)($row['temperature'] ?? '0.7');
$maxTokens = (string)($row['max_tokens'] ?? '1200');
$promptV1 = (string)($row['prompt_v1'] ?? '');
$promptV2 = (string)($row['prompt_v2'] ?? '');

$metaPromptRoot = (string)($row['meta_prompt_root'] ?? '');
$metaPromptSub  = (string)($row['meta_prompt_sub'] ?? '');
$textPromptRoot = (string)($row['text_prompt_root'] ?? '');
$textPromptSub  = (string)($row['text_prompt_sub'] ?? '');
$pagePrompt     = (string)($row['page_prompt'] ?? '');
$pageMetaPrompt = (string)($row['page_meta_prompt'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">AI-настройки</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
        <a class="btn btn-secondary" href="/ai/test">Проверить API</a>
    </div>
    <div class="page-subtitle">
        Глобальные настройки подключения к AI и шаблоны промптов для root, поддоменов и внутренних страниц.
    </div>
</div>

<form method="post" action="/ai/settings" class="panel-card system-form stack-gap-lg">
    <div class="panel-grid panel-grid--2">
        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Подключение</h2>

            <div class="field-row">
                <label>Провайдер</label>
                <input name="provider" value="<?= h($provider) ?>">
            </div>

            <div class="field-row">
                <label>API key</label>
                <input name="api_key" value="<?= h($apiKey) ?>" class="system-code">
            </div>

            <div class="field-row">
                <label>Модель</label>
                <input name="model" value="<?= h($model) ?>">
            </div>

            <div class="panel-grid panel-grid--2">
                <div class="field-row">
                    <label>Temperature</label>
                    <input name="temperature" value="<?= h($temperature) ?>">
                </div>

                <div class="field-row">
                    <label>Max tokens</label>
                    <input name="max_tokens" value="<?= h($maxTokens) ?>">
                </div>
            </div>
        </div>

        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Назначение</h2>

            <ul class="status-list small">
                <li><b>prompt_v1</b> и <b>prompt_v2</b> — старые общие шаблоны;</li>
                <li><b>meta_prompt_root</b> — SEO-мета для основного домена;</li>
                <li><b>meta_prompt_sub</b> — SEO-мета для поддоменов;</li>
                <li><b>text_prompt_root</b> — текст главной root;</li>
                <li><b>text_prompt_sub</b> — текст главной поддомена;</li>
                <li><b>page_prompt</b> — текст внутренней страницы;</li>
                <li><b>page_meta_prompt</b> — meta внутренней страницы.</li>
            </ul>

            <div class="note">
                Runtime-параметры конкретного сайта задаются не здесь, а на экране
                <b>AI для сайта</b>.
            </div>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Старые общие промпты</h2>

        <div class="field-row">
            <label>Промпт вариант 1</label>
            <textarea name="prompt_v1" class="big-textarea"><?= h($promptV1) ?></textarea>
        </div>

        <div class="field-row">
            <label>Промпт вариант 2</label>
            <textarea name="prompt_v2" class="big-textarea"><?= h($promptV2) ?></textarea>
        </div>
    </div>

    <div class="panel-grid panel-grid--2">
        <div class="panel-card stack-gap-md">
            <h2 class="section-title">SEO-мета</h2>

            <div class="field-row">
                <label>Промпт: meta root</label>
                <textarea name="meta_prompt_root" class="big-textarea"><?= h($metaPromptRoot) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: meta subdomains</label>
                <textarea name="meta_prompt_sub" class="big-textarea"><?= h($metaPromptSub) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: page meta</label>
                <textarea name="page_meta_prompt" class="big-textarea"><?= h($pageMetaPrompt) ?></textarea>
            </div>
        </div>

        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Тексты</h2>

            <div class="field-row">
                <label>Промпт: text root</label>
                <textarea name="text_prompt_root" class="big-textarea"><?= h($textPromptRoot) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: text subdomains</label>
                <textarea name="text_prompt_sub" class="big-textarea"><?= h($textPromptSub) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: page text</label>
                <textarea name="page_prompt" class="big-textarea"><?= h($pagePrompt) ?></textarea>
            </div>
        </div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить настройки</button>
        <a class="btn btn-secondary" href="/ai/test">Проверить API</a>
    </div>
</form>