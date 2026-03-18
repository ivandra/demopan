<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$row = is_array($row ?? null) ? $row : [];
$apiKey = (string)($apiKey ?? '');

$provider = (string)($row['provider'] ?? 'deepseek');
$model = (string)($row['model'] ?? 'deepseek-chat');
$temperature = (string)($row['temperature'] ?? '0.7');
$maxTokens = (string)($row['max_tokens'] ?? '1200');
$metaPromptRoot = (string)($row['meta_prompt_root'] ?? '');
$metaPromptSub  = (string)($row['meta_prompt_sub'] ?? '');
$textPromptRoot = (string)($row['text_prompt_root'] ?? '');
$textPromptSub  = (string)($row['text_prompt_sub'] ?? '');
$pagePrompt     = (string)($row['page_prompt'] ?? '');
$pageMetaPrompt = (string)($row['page_meta_prompt'] ?? '');
$globalMetaTitleTemplate = (string)($row['global_meta_title_template'] ?? '');
$globalMetaH1Template = (string)($row['global_meta_h1_template'] ?? '');
$globalMetaDescriptionTemplate = (string)($row['global_meta_description_template'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">AI-настройки</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
        <a class="btn btn-secondary" href="/ai/test">Проверить API</a>
    </div>
    <div class="page-subtitle">
        Глобальные настройки подключения к AI, единые prompts и общие шаблоны метатегов для всей панели.
    </div>
</div>

<div class="panel-card stack-gap-md">
    <h2 class="section-title">Что хранится здесь</h2>
    <div class="small">
        На этой странице хранятся только:
        <br>— API-ключ, модель, temperature, max tokens;
        <br>— глобальные prompts для root / sub / page;
        <br>— общие шаблоны метатегов с переменными.
    </div>
    <div class="note">
        Итоговые <b>Title / H1 / Description / Keywords</b> конкретного сайта или label редактируются не здесь, а в разделе <b>Контент и SEO</b>.
        Параметры генерации для конкретного label задаются в разделе <b>AI для сайта</b>.
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
            <h2 class="section-title">Переменные, которые можно использовать</h2>

            <ul class="status-list small">
                <li><b>{BRAND}</b> — название бренда текущего label;</li>
                <li><b>{HOST}</b> — текущий хост (например, banda.site.ru);</li>
                <li><b>{LABEL}</b> — label текущей сущности;</li>
                <li><b>{LINK_REGISTRATION}</b>, <b>{LINK_SLOTS}</b>, <b>{LINK_BONUSES}</b>, <b>{LINK_MIRROR}</b> — ссылки из настроек label;</li>
                <li><b>{PAGE_PATH}</b>, <b>{PAGE_URL}</b>, <b>{PAGE_TITLE}</b>, <b>{PAGE_H1}</b> — переменные страниц.</li>
            </ul>

            <div class="note">
                Переменные подставляются автоматически. На уровне сайта и label меняются только их значения, а не сами глобальные prompts.
            </div>
        </div>
    </div>


    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Глобальные шаблоны метатегов</h2>
        <div class="small muted">Если поле заполнено, шаблон применяется поверх ответа AI для всех сайтов и label.</div>

        <div class="field-row">
            <label>Глобальный шаблон Title</label>
            <input name="global_meta_title_template" value="<?= h($globalMetaTitleTemplate) ?>" placeholder="{BRAND} - официальный сайт зеркало с игровыми автоматами">
        </div>

        <div class="field-row">
            <label>Глобальный шаблон H1</label>
            <input name="global_meta_h1_template" value="<?= h($globalMetaH1Template) ?>" placeholder="{BRAND} — официальный сайт казино">
        </div>

        <div class="field-row">
            <label>Глобальный шаблон Description</label>
            <textarea name="global_meta_description_template" class="big-textarea"><?= h($globalMetaDescriptionTemplate) ?></textarea>
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