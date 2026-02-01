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

// В Files у тебя редактируются только корневые файлы build.
// Для template-multy корневой файл — config.default.php, а не config.php.
$configFileForFiles = (($site['template'] ?? '') === 'template-multy') ? 'config.default.php' : 'config.php';
?>

<h2>Pages: <?= htmlspecialchars($site['domain']) ?></h2>
<p style="font-size:13px;opacity:.85;">
    label: <code><?= $labelEsc ?></code>
    | config.php генерируется в: <code><?= htmlspecialchars($configTargetPath) ?></code>
    | <a href="/sites/files/edit?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>&file=<?= rawurlencode($configFileForFiles) ?>">открыть в Files</a>
</p>

<p>
    <a href="/sites/edit?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>">← назад к SEO</a> |
    <a href="/sites/texts?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>">Texts</a>
</p>

<hr>

<form method="post" action="/sites/pages/text-new?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>" style="margin-bottom:12px;">
    <input type="hidden" name="label" value="<?= $labelEsc ?>">
    <label>Быстро создать файл в texts</label>
    <input name="new_file" placeholder="new.php">
    <button type="submit">Создать и открыть</button>
</form>

<form method="post" action="/sites/pages?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>">
    <input type="hidden" name="label" value="<?= $labelEsc ?>">

    <table>
        <tr>
            <th>URL</th>
            <th>Title</th>
            <th>H1</th>
            <th>Description</th>
            <th>Keywords</th>
            <th>Text file</th>
            <th>Priority</th>
            <th>In sitemap</th>
        </tr>

        <?php $i=0; foreach ($pages as $url => $p): ?>
        <?php
            $currentFile = basename((string)($p['text_file'] ?? 'home.php'));
            $isUsed = isset($used[$currentFile]);
        ?>
        <tr>
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
                        <a href="/sites/texts/edit?id=<?= (int)$site['id'] ?>&label=<?= $labelEnc ?>&file=<?= rawurlencode($currentFile) ?>">редактировать</a>
                    </div>
                <?php endif; ?>
            </td>

            <td><input name="priority[<?= $i ?>]" value="<?= htmlspecialchars($p['priority'] ?? '') ?>" style="width:60px"></td>

            <td style="text-align:center">
                <input type="checkbox" name="sitemap[<?= $i ?>]" <?= (isset($p['sitemap']) && $p['sitemap'] === false) ? '' : 'checked' ?>>
            </td>
        </tr>
        <?php $i++; endforeach; ?>

        <!-- новая строка -->
        <tr>
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
