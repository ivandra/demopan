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
$presets = is_array($presets ?? null) ? $presets : [];
$selectedPresetId = (int)($selectedPresetId ?? 0);
$selectedPreset = is_array($selectedPreset ?? null) ? $selectedPreset : [];
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
                <li><b>{BRAND_RU}</b> — русская версия бренда из каталога поддоменов;</li>
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
        <div class="small muted">Если поле заполнено, шаблон применяется поверх ответа AI для всех сайтов и label. Если поле пустое, значение генерируется нейронкой.</div>

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
                <label>Промпт: SEO-мета для основного домена</label>
                <textarea name="meta_prompt_root" class="big-textarea"><?= h($metaPromptRoot) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: SEO-мета для поддоменов</label>
                <textarea name="meta_prompt_sub" class="big-textarea"><?= h($metaPromptSub) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: SEO-мета для внутренних страниц</label>
                <textarea name="page_meta_prompt" class="big-textarea"><?= h($pageMetaPrompt) ?></textarea>
            </div>
        </div>

        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Тексты</h2>

            <div class="field-row">
                <label>Промпт: тексты для основного домена</label>
                <textarea name="text_prompt_root" class="big-textarea"><?= h($textPromptRoot) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: тексты для поддоменов</label>
                <textarea name="text_prompt_sub" class="big-textarea"><?= h($textPromptSub) ?></textarea>
            </div>

            <div class="field-row">
                <label>Промпт: тексты для внутренних страниц</label>
                <textarea name="page_prompt" class="big-textarea"><?= h($pagePrompt) ?></textarea>
            </div>
        </div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить настройки</button>
        <a class="btn btn-secondary" href="/ai/test">Проверить API</a>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Наборы шаблонов SEO-мета</h2>
        <div class="small muted">Можно сохранить текущий набор глобальных промптов и шаблонов под своим названием, а потом открывать его для редактирования и сохранять изменения без путаницы.</div>

        <div class="note">
            <b>Как работать с наборами:</b><br>
            1. Выберите набор в списке ниже.<br>
            2. Нажмите <b>Открыть выбранный набор</b> — форма выше загрузит его значения.<br>
            3. Измените поля и нажмите <b>Сохранить настройки</b> — если набор открыт, обновится и глобальная запись, и сам этот набор.<br>
            4. Чтобы создать новый набор, заполните имя и описание ниже и нажмите <b>Сохранить как новый набор</b>.
        </div>

        <input type="hidden" name="preset_id" value="<?= $selectedPresetId ?>">

        <div class="panel-grid panel-grid--2">
            <div class="field-row">
                <label>Выбрать сохраненный набор</label>
                <select name="preset_id">
                    <option value="0">— не выбран —</option>
                    <?php foreach ($presets as $preset): ?>
                        <option value="<?= (int)($preset['id'] ?? 0) ?>" <?= $selectedPresetId === (int)($preset['id'] ?? 0) ? 'selected' : '' ?>>#<?= (int)($preset['id'] ?? 0) ?> — <?= h((string)($preset['title'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="inline-form mt-8">
                    <button type="submit" name="preset_action" value="open_preset" class="btn btn-secondary btn-sm">Открыть выбранный набор</button>
                    <?php if ($selectedPresetId > 0): ?>
                        <button type="submit" name="preset_action" value="delete_preset" class="btn btn-danger btn-sm" onclick="return confirm('Удалить выбранный набор?')">Удалить</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="field-row">
                <label>Открыт сейчас</label>
                <input value="<?= !empty($selectedPreset) ? ('#' . (int)($selectedPreset['id'] ?? 0) . ' — ' . (string)($selectedPreset['title'] ?? '')) : 'Глобальные настройки без выбранного набора' ?>" readonly>
            </div>
        </div>

        <div class="panel-grid panel-grid--2">
            <div class="field-row">
                <label>Название нового набора</label>
                <input name="new_preset_title" value="" placeholder="например: Meta soft RU 2026">
            </div>
            <div class="field-row">
                <label>Описание для нового набора</label>
                <input name="new_preset_description" value="" placeholder="например: мягкие коммерческие меты для казино">
            </div>
        </div>

        <div class="page-actions">
            <button type="submit" name="preset_action" value="save_settings" class="btn btn-primary">Сохранить настройки</button>
            <button type="submit" name="preset_action" value="save_preset" class="btn btn-secondary">Сохранить как новый набор</button>
            <a class="btn btn-secondary" href="/ai/test">Проверить API</a>
        </div>

        <?php if (!empty($selectedPreset)): ?>
            <div class="panel-card stack-gap-md">
                <h3 class="section-title">Текущий выбранный набор</h3>
                <div class="small muted">Здесь можно поменять только имя и описание набора. Содержимое полей набора сохраняется кнопкой «Сохранить настройки» выше.</div>

                <div class="note">
                    <b>#<?= (int)($selectedPreset['id'] ?? 0) ?> — <?= h((string)($selectedPreset['title'] ?? '')) ?></b><br>
                    <?php $presetDescriptionText = trim((string)($selectedPreset['description'] ?? '')); ?>
                    <?= $presetDescriptionText !== '' ? nl2br(h($presetDescriptionText)) : '<span class="muted">Описание пока не заполнено.</span>' ?>
                </div>

                <div class="panel-grid panel-grid--2">
                    <div class="field-row">
                        <label>Название выбранного набора</label>
                        <input name="edit_preset_title" value="<?= h((string)($selectedPreset['title'] ?? '')) ?>">
                    </div>
                    <div class="field-row">
                        <label>ID набора</label>
                        <input value="#<?= (int)($selectedPreset['id'] ?? 0) ?>" disabled>
                    </div>
                </div>

                <div class="field-row">
                    <label>Описание выбранного набора</label>
                    <textarea name="edit_preset_description" class="big-textarea"><?= h((string)($selectedPreset['description'] ?? '')) ?></textarea>
                </div>

                <div class="page-actions">
                    <button type="submit" name="preset_action" value="update_preset" class="btn btn-secondary">Сохранить название и описание</button>
                    <button type="submit" name="preset_action" value="delete_preset" class="btn btn-danger" onclick="return confirm('Удалить выбранный набор?')">Удалить набор</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($presets)): ?>
            <div class="panel-card stack-gap-md">
                <h3 class="section-title">Все сохраненные наборы</h3>
                <div class="stack-gap-sm">
                    <?php foreach ($presets as $preset): ?>
                        <div class="note">
                            <div class="page-head page-head--compact" style="margin-bottom:8px;align-items:center;">
                                <div>
                                    <b>#<?= (int)($preset['id'] ?? 0) ?> — <?= h((string)($preset['title'] ?? '')) ?></b>
                                </div>
                                <div class="inline-form">
                                    <a class="btn btn-secondary btn-sm" href="/ai/settings?preset_id=<?= (int)($preset['id'] ?? 0) ?>">Открыть</a>
                                </div>
                            </div>
                            <?php $listDescription = trim((string)($preset['description'] ?? '')); ?>
                            <?= $listDescription !== '' ? nl2br(h($listDescription)) : '<span class="muted">Описание не заполнено.</span>' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    </div>

</form>
