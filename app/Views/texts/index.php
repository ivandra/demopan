<h2>Texts: <?= htmlspecialchars($site['domain'], ENT_QUOTES, 'UTF-8') ?></h2>

<?php
$isMulty = (($site['template'] ?? '') === 'template-multy');
$configFileForLink = $isMulty ? 'config.default.php' : 'config.php';
$configLabelSuffix = $isMulty ? (' (label: ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ')') : '';
?>

<p style="font-size:13px;opacity:.85;">
    Конфиг генерируется в: <code><?= htmlspecialchars($configTargetPath, ENT_QUOTES, 'UTF-8') ?></code><?= $configLabelSuffix ?>
    | <a href="/sites/files/edit?id=<?= (int)$site['id'] ?>&file=<?= rawurlencode($configFileForLink) ?>">открыть в Files</a>
</p>

<p>
    <a href="/">← назад</a>
</p>

<form method="post" action="/sites/texts/new?id=<?= (int)$site['id'] ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>">
    <?php if ($isMulty): ?>
        <input type="hidden" name="label" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <label>Новый файл</label>
    <input name="new_file" placeholder="new.php">
    <button type="submit">Создать</button>
</form>

<hr>

<?php if (!$files): ?>
    <p>Файлов в texts пока нет.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Файл</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><code><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></code></td>
                <td>
                    <a href="/sites/texts/edit?id=<?= (int)$site['id'] ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>&file=<?= rawurlencode($f) ?>">Открыть</a>
                    |
                    <form method="post"
                          action="/sites/texts/delete?id=<?= (int)$site['id'] ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>"
                          style="display:inline"
                          onsubmit="return confirm('Удалить файл <?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>?');">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($isMulty): ?>
                            <input type="hidden" name="label" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <button type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
