<?php
/** @var array $site */
/** @var array $pages */
/** @var array $textFiles */
/** @var array $used */
/** @var string $configTargetPath */
/** @var string $label */

$label = isset($label) ? (string)$label : '_default';
$labelEnc = urlencode($label);
$labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
$siteId   = (int)($site['id'] ?? 0);

// В Files у тебя редактируются только корневые файлы build.
// Для template-multy корневой файл — config.default.php, а не config.php.
$configFileForFiles = (($site['template'] ?? '') === 'template-multy') ? 'config.default.php' : 'config.php';
?>

<style>
.pages-top-actions {
    margin: 14px 0 18px 0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.pages-btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #d7dbe2;
    background: #f3f4f6;
    color: #222;
    text-decoration: none;
    cursor: pointer;
}
.pages-btn-green {
    background: #27ae60;
    border-color: #27ae60;
    color: #fff;
}
.pages-btn-blue {
    background: #2f80ed;
    border-color: #2f80ed;
    color: #fff;
}
.pages-btn-purple {
    background: #6f42c1;
    border-color: #6f42c1;
    color: #fff;
}
.pages-btn-dark {
    background: #222;
    border-color: #222;
    color: #fff;
}
.pages-batch-box {
    margin: 16px 0;
    padding: 14px;
    border: 1px solid #ddd;
    border-radius: 10px;
    background: #fafafa;
}
.pages-batch-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.pages-ai-cell {
    white-space: nowrap;
}
.pages-ai-cell-inner {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
}
.pages-ai-mini {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 7px;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
}
.pages-ai-mini-blue {
    background: #2f80ed;
}
.pages-ai-mini-purple {
    background: #6f42c1;
}
.pages-check-col {
    width: 44px;
    text-align: center;
}
</style>

<h2>Pages: <?= htmlspecialchars($site['domain']) ?></h2>
<p style="font-size:13px;opacity:.85;">
    label: <code><?= $labelEsc ?></code>
    | config.php генерируется в: <code><?= htmlspecialchars($configTargetPath) ?></code>
    | <a href="/sites/files/edit?id=<?= $siteId ?>&label=<?= $labelEnc ?>&file=<?= rawurlencode($configFileForFiles) ?>">открыть в Files</a>
</p>

<p>
    <a href="/sites/edit?id=<?= $siteId ?>&label=<?= $labelEnc ?>">← назад к SEO</a> |
    <a href="/sites/texts?id=<?= $siteId ?>&label=<?= $labelEnc ?>">Texts</a> |
    <a href="/sites/ai?id=<?= $siteId ?>">AI-фабрика</a>
</p>

<div class="pages-top-actions">
    <a href="/ai/generate-all-pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>"
       onclick="return confirm('Сгенерировать meta и тексты для всех страниц label=<?= $labelEsc ?> ?');"
       class="pages-btn pages-btn-green">
        AI: сгенерировать всё для этого label
    </a>

    <a href="/sites/subcfg?id=<?= $siteId ?>&label=<?= $labelEnc ?>"
       class="pages-btn">
        Открыть SubCfg
    </a>
</div>

<hr>

<form method="post" action="/sites/pages/text-new?id=<?= $siteId ?>&label=<?= $labelEnc ?>" style="margin-bottom:12px;">
    <input type="hidden" name="label" value="<?= $labelEsc ?>">
    <label>Быстро создать файл в texts</label>
    <input name="new_file" placeholder="new.php">
    <button type="submit">Создать и открыть</button>
</form>

<div class="pages-batch-box">
    <form method="post" action="/ai/generate-selected-pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>" id="aiBatchForm">
        <div style="font-weight:700;margin-bottom:10px;">Пакетная AI-генерация по выбранным страницам</div>

        <div class="pages-batch-row" style="margin-bottom:10px;">
            <button type="button" class="pages-btn" onclick="pagesSelectAll(true)">Выбрать все</button>
            <button type="button" class="pages-btn" onclick="pagesSelectAll(false)">Снять все</button>
        </div>

        <div class="pages-batch-row">
            <button type="submit"
                    name="mode"
                    value="meta"
                    class="pages-btn pages-btn-blue"
                    onclick="return pagesBatchConfirm('Сгенерировать AI meta для выбранных страниц?');">
                AI meta для выбранных
            </button>

            <button type="submit"
                    name="mode"
                    value="text"
                    class="pages-btn pages-btn-purple"
                    onclick="return pagesBatchConfirm('Сгенерировать AI тексты для выбранных страниц?');">
                AI text для выбранных
            </button>

            <button type="submit"
                    name="mode"
                    value="all"
                    class="pages-btn pages-btn-dark"
                    onclick="return pagesBatchConfirm('Сгенерировать AI meta и тексты для выбранных страниц?');">
                AI всё для выбранных
            </button>
        </div>

        <div style="margin-top:10px;font-size:12px;opacity:.75;">
            Отметь страницы в первом столбце таблицы ниже, затем нажми нужную кнопку.
        </div>
    </form>
</div>

<form method="post" action="/sites/pages?id=<?= $siteId ?>&label=<?= $labelEnc ?>">
    <input type="hidden" name="label" value="<?= $labelEsc ?>">

    <table>
        <tr>
            <th class="pages-check-col">
                <input type="checkbox" id="checkAllTop" onclick="pagesToggleHeader(this.checked)">
            </th>
            <th>URL</th>
            <th>Title</th>
            <th>H1</th>
            <th>Description</th>
            <th>Keywords</th>
            <th>Text file</th>
            <th>AI</th>
            <th>Priority</th>
            <th>In sitemap</th>
        </tr>

        <?php $i=0; foreach ($pages as $url => $p): ?>
        <?php
            $currentFile = basename((string)($p['text_file'] ?? 'home.php'));
            $isUsed = isset($used[$currentFile]);
        ?>
        <tr>
            <td class="pages-check-col">
                <input type="checkbox"
                       class="page-batch-check"
                       form="aiBatchForm"
                       name="selected_urls[]"
                       value="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
            </td>

            <td><input name="url[<?= $i ?>]" value="<?= htmlspecialchars($url) ?>" style="width:140px"></td>

            <td><input name="title[<?= $i ?>]" value="<?= htmlspecialchars(($p['title'] ?? '') === '$inherit' ? '' : ($p['title'] ?? '')) ?>"></td>
            <td><input name="h1[<?= $i ?>]" value="<?= htmlspecialchars(($p['h1'] ?? '') === '$inherit' ? '' : ($p['h1'] ?? '')) ?>"></td>
            <td><input name="description[<?= $i ?>]" value="<?= htmlspecialchars(($p['description'] ?? '') === '$inherit' ? '' : ($p['description'] ?? '')) ?>"></td>
            <td><input name="keywords[<?= $i ?>]" value="<?= htmlspecialchars(($p['keywords'] ?? '') === '$inherit' ? '' : ($p['keywords'] ?? '')) ?>"></td>

            <td>
                <select name="text_file[<?= $i ?>]">
                    <?php foreach ($textFiles as $tf): ?>
                        <option value="<?= htmlspecialchars($tf) ?>" <?= $tf === $currentFile ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tf) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($currentFile): ?>
                    <div style="font-size:12px;margin-top:4px;">
                        <a href="/sites/texts/edit?id=<?= $siteId ?>&label=<?= $labelEnc ?>&file=<?= rawurlencode($currentFile) ?>">редактировать</a>
                    </div>
                <?php endif; ?>
            </td>

            <td class="pages-ai-cell">
                <div class="pages-ai-cell-inner">
                    <a href="/ai/generate-page-meta?id=<?= $siteId ?>&label=<?= $labelEnc ?>&path=<?= urlencode($url) ?>"
                       onclick="return confirm('Сгенерировать AI meta для страницы <?= htmlspecialchars($url, ENT_QUOTES) ?> ?');"
                       class="pages-ai-mini pages-ai-mini-blue">
                        AI meta
                    </a>

                    <a href="/ai/generate-page-text?id=<?= $siteId ?>&label=<?= $labelEnc ?>&path=<?= urlencode($url) ?>"
                       onclick="return confirm('Сгенерировать AI текст для страницы <?= htmlspecialchars($url, ENT_QUOTES) ?> ?');"
                       class="pages-ai-mini pages-ai-mini-purple">
                        AI text
                    </a>
                </div>
            </td>

            <td><input name="priority[<?= $i ?>]" value="<?= htmlspecialchars($p['priority'] ?? '') ?>" style="width:60px"></td>

            <td style="text-align:center">
                <input type="checkbox" name="sitemap[<?= $i ?>]" <?= (isset($p['sitemap']) && $p['sitemap'] === false) ? '' : 'checked' ?>>
            </td>
        </tr>
        <?php $i++; endforeach; ?>

        <tr>
            <td class="pages-check-col" style="opacity:.4;">—</td>
            <td><input name="url[<?= $i ?>]" placeholder="/new"></td>
            <td><input name="title[<?= $i ?>]" placeholder="(пусто=inherit)"></td>
            <td><input name="h1[<?= $i ?>]" placeholder="(пусто=inherit)"></td>
            <td><input name="description[<?= $i ?>]" placeholder="(пусто=inherit)"></td>
            <td><input name="keywords[<?= $i ?>]" placeholder="(пусто=inherit)"></td>
            <td>
                <select name="text_file[<?= $i ?>]">
                    <?php foreach ($textFiles as $tf): ?>
                        <option value="<?= htmlspecialchars($tf) ?>"><?= htmlspecialchars($tf) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="white-space:nowrap;">
                <span style="font-size:12px;opacity:.7;">после сохранения</span>
            </td>
            <td><input name="priority[<?= $i ?>]" placeholder="0.5" style="width:60px"></td>
            <td style="text-align:center"><input type="checkbox" name="sitemap[<?= $i ?>]" checked></td>
        </tr>
    </table>

    <p>Если Title/H1/Description/Keywords пустые — в config.php будет подстановка переменных (<code>$title</code>, <code>$h1</code> и т.д.).</p>

    <button type="submit">Сохранить Pages и перегенерировать config.php</button>
</form>

<hr>

<h3>Файлы texts</h3>
<p style="font-size:13px;">
    Используются страницами: <b><?= count($used) ?></b> |
    Всего файлов: <b><?= count($textFiles) ?></b>
</p>

<table>
    <tr>
        <th>Файл</th>
        <th>Статус</th>
    </tr>
    <?php foreach ($textFiles as $tf): ?>
        <tr>
            <td><code><?= htmlspecialchars($tf) ?></code></td>
            <td>
                <?= isset($used[$tf]) ? 'используется' : '<span style="opacity:.7">не используется</span>' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<script>
function pagesSelectAll(state) {
    document.querySelectorAll('.page-batch-check').forEach(function (el) {
        el.checked = !!state;
    });

    var top = document.getElementById('checkAllTop');
    if (top) {
        top.checked = !!state;
    }
}

function pagesToggleHeader(state) {
    pagesSelectAll(state);
}

function pagesBatchConfirm(message) {
    var checked = document.querySelectorAll('.page-batch-check:checked').length;
    if (!checked) {
        alert('Сначала выбери хотя бы одну страницу.');
        return false;
    }
    return confirm(message);
}
</script>