<?php
/** @var array $site */
/** @var array $pages */
/** @var array $textFiles */
/** @var array $used */
/** @var string $configTargetPath */
/** @var string $label */

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$label = isset($label) ? (string)$label : '_default';
$labelEnc = urlencode($label);
$siteId   = (int)($site['id'] ?? 0);

$entityTitle = ($label === '_default') ? 'Основной домен (_default)' : ('Поддомен: ' . $label);
$entityHost  = ($label === '_default')
    ? (string)($site['domain'] ?? '')
    : ($label . '.' . (string)($site['domain'] ?? ''));

// В Files редактируются только корневые файлы build.
// Для multy-root это config.default.php.
$configFileForFiles = (($site['template'] ?? '') === 'template-multy') ? 'config.default.php' : 'config.php';

$baseTextFiles = is_array($textFiles ?? null) ? $textFiles : [];
if (!$baseTextFiles) {
    $baseTextFiles = ['home.php'];
}
?>

<div class="page-head">
    <h1 class="page-title">Страницы: <?= h($site['domain'] ?? '') ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= $labelEnc ?>">Контент и SEO</a>
        <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= $labelEnc ?>">Тексты</a>
        <a class="btn btn-ai" href="/sites/ai?id=<?= $siteId ?>">AI для сайта</a>
    </div>
    <div class="page-subtitle">
        Здесь редактируются внутренние страницы выбранной сущности.
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Сейчас редактируется</div>
    <div class="site-context__title"><?= h($entityTitle) ?></div>
    <div class="site-context__meta">
        Хост: <code><?= h($entityHost) ?></code>
        <br>
        Конфиг генерируется в: <code><?= h($configTargetPath) ?></code>
        |
        <a href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($configFileForFiles) ?>">Открыть config в Files</a>
    </div>
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Быстрые действия</h2>

        <div class="page-actions">
            <a class="btn btn-ai"
               href="/ai/generate-all-pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>"
               data-confirm="Сгенерировать мета и тексты для всех страниц этой метки?">
                AI: сгенерировать всё для этой метки
            </a>

            <a class="btn btn-secondary"
               href="/sites/subcfg?id=<?= $siteId ?>&label=<?= $labelEnc ?>">
                Открыть Контент и SEO
            </a>
        </div>

        <div class="note">
            Пустые поля в мета-полях сохраняются как <code>$inherit</code>.
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Быстро создать файл в texts</h2>

        <form method="post" action="/sites/pages/text-new?id=<?= $siteId ?>&label=<?= $labelEnc ?>" class="inline-form">
            <input type="hidden" name="label" value="<?= h($label) ?>">
            <input type="text" name="new_file" placeholder="new.php">
            <button type="submit" class="btn btn-primary">Создать и открыть</button>
        </form>

        <div class="small muted">
            Удобно, если для новой страницы нужен отдельный текстовый файл.
        </div>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Пакетная AI-генерация по выбранным страницам</h2>

    <form method="post" action="/ai/generate-selected-pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>" id="aiBatchForm" class="stack-gap-md">
        <div class="page-actions">
            <button type="button" class="btn btn-secondary" data-check-all=".page-batch-check">Выбрать все</button>
            <button type="button" class="btn btn-secondary" data-check-none=".page-batch-check">Снять все</button>
        </div>

        <div class="page-actions">
            <button type="submit"
                    form="aiBatchForm"
                    name="mode"
                    value="meta"
                    class="btn btn-primary"
                    data-require-checked=".page-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы одну страницу."
                    data-confirm="Сгенерировать AI-мета для выбранных страниц?">
                AI-мета для выбранных
            </button>

            <button type="submit"
                    form="aiBatchForm"
                    name="mode"
                    value="text"
                    class="btn btn-ai"
                    data-require-checked=".page-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы одну страницу."
                    data-confirm="Сгенерировать AI текст для выбранных страниц?">
                AI-текст для выбранных
            </button>

            <button type="submit"
                    form="aiBatchForm"
                    name="mode"
                    value="all"
                    class="btn btn-secondary"
                    data-require-checked=".page-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы одну страницу."
                    data-confirm="Сгенерировать AI-мета и тексты для выбранных страниц?">
                AI всё для выбранных
            </button>
        </div>

        <div class="small muted">
            Отметьте страницы в первом столбце таблицы ниже.
        </div>
    </form>
</div>

<form method="post" action="/sites/pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>" class="mt-16">
    <input type="hidden" name="label" value="<?= h($label) ?>">

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th style="width:52px;text-align:center;">
                    <input type="checkbox" data-toggle-check-group=".page-batch-check">
                </th>
                <th>URL</th>
                <th>Title</th>
                <th>H1</th>
                <th>Description</th>
                <th>Keywords</th>
                <th>Text file</th>
                <th>AI-действия</th>
                <th>Priority</th>
                <th>В sitemap</th>
            </tr>
            </thead>
            <tbody>
            <?php $i = 0; foreach ($pages as $url => $p): ?>
                <?php
                $currentFile = basename((string)($p['text_file'] ?? 'home.php'));
                $rowTextFiles = $baseTextFiles;
                if ($currentFile !== '' && !in_array($currentFile, $rowTextFiles, true)) {
                    $rowTextFiles[] = $currentFile;
                }
                ?>
                <tr>
                    <td style="text-align:center;">
                        <input type="checkbox"
                               class="page-batch-check"
                               form="aiBatchForm"
                               name="selected_urls[]"
                               value="<?= h($url) ?>">
                    </td>

                    <td><input type="text" name="url[<?= $i ?>]" value="<?= h($url) ?>"></td>
                    <td><input type="text" name="title[<?= $i ?>]" value="<?= h((($p['title'] ?? '') === '$inherit') ? '' : ($p['title'] ?? '')) ?>"></td>
                    <td><input type="text" name="h1[<?= $i ?>]" value="<?= h((($p['h1'] ?? '') === '$inherit') ? '' : ($p['h1'] ?? '')) ?>"></td>
                    <td><input type="text" name="description[<?= $i ?>]" value="<?= h((($p['description'] ?? '') === '$inherit') ? '' : ($p['description'] ?? '')) ?>"></td>
                    <td><input type="text" name="keywords[<?= $i ?>]" value="<?= h((($p['keywords'] ?? '') === '$inherit') ? '' : ($p['keywords'] ?? '')) ?>"></td>

                    <td>
                        <select name="text_file[<?= $i ?>]">
                            <?php foreach ($rowTextFiles as $tf): ?>
                                <option value="<?= h($tf) ?>" <?= $tf === $currentFile ? 'selected' : '' ?>>
                                    <?= h($tf) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($currentFile): ?>
                            <div class="small mt-8">
                                <a href="/sites/texts/edit?id=<?= $siteId ?>&label=<?= $labelEnc ?>&file=<?= rawurlencode($currentFile) ?>">Редактировать файл</a>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="inline-actions">
                            <a class="btn btn-sm btn-primary"
                               href="/ai/generate-page-meta?id=<?= $siteId ?>&label=<?= $labelEnc ?>&path=<?= urlencode($url) ?>"
                               data-confirm="Сгенерировать AI-мета для страницы <?= h($url) ?>?">
                                AI meta
                            </a>

                            <a class="btn btn-sm btn-ai"
                               href="/ai/generate-page-text?id=<?= $siteId ?>&label=<?= $labelEnc ?>&path=<?= urlencode($url) ?>"
                               data-confirm="Сгенерировать AI-текст для страницы <?= h($url) ?>?">
                                AI text
                            </a>
                        </div>
                    </td>

                    <td><input type="text" name="priority[<?= $i ?>]" value="<?= h($p['priority'] ?? '') ?>"></td>

                    <td style="text-align:center;">
                        <input type="checkbox" name="sitemap[<?= $i ?>]" <?= (isset($p['sitemap']) && $p['sitemap'] === false) ? '' : 'checked' ?>>
                    </td>
                </tr>
            <?php $i++; endforeach; ?>

            <tr>
                <td class="muted" style="text-align:center;">—</td>
                <td><input type="text" name="url[<?= $i ?>]" placeholder="/new-page"></td>
                <td><input type="text" name="title[<?= $i ?>]" placeholder="пусто = наследовать"></td>
                <td><input type="text" name="h1[<?= $i ?>]" placeholder="пусто = наследовать"></td>
                <td><input type="text" name="description[<?= $i ?>]" placeholder="пусто = наследовать"></td>
                <td><input type="text" name="keywords[<?= $i ?>]" placeholder="пусто = наследовать"></td>
                <td>
                    <select name="text_file[<?= $i ?>]">
                        <?php foreach ($baseTextFiles as $tf): ?>
                            <option value="<?= h($tf) ?>"><?= h($tf) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="small muted">После сохранения</td>
                <td><input type="text" name="priority[<?= $i ?>]" placeholder="0.5"></td>
                <td style="text-align:center;"><input type="checkbox" name="sitemap[<?= $i ?>]" checked></td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="note mt-12">
        Если поля Title / H1 / Description / Keywords пустые, на фронте будет работать логика наследования.
    </div>

    <div class="page-actions mt-12">
        <button type="submit" class="btn btn-primary">Сохранить страницы и пересобрать config.php</button>
    </div>
</form>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Файлы texts</h2>
        <div class="small muted">
            Используются страницами: <b><?= count($used) ?></b> |
            Всего файлов: <b><?= count($textFiles) ?></b>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Файл</th>
                <th>Статус</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($textFiles as $tf): ?>
                <tr>
                    <td><code><?= h($tf) ?></code></td>
                    <td>
                        <?php if (isset($used[$tf])): ?>
                            <span class="badge badge-success">Используется</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Не используется</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$textFiles): ?>
                <tr>
                    <td colspan="2" class="muted">Файлов пока нет.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>