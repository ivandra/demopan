<h2>Редактирование файла: <code><?= htmlspecialchars($safeFile, ENT_QUOTES, 'UTF-8') ?></code></h2>

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
    <a href="/sites/texts?id=<?= (int)$site['id'] ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>">← к списку</a>
</p>

<form method="post" action="/sites/texts/save?id=<?= (int)$site['id'] ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>">
    <input type="hidden" name="file" value="<?= htmlspecialchars($safeFile, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($isMulty): ?>
        <input type="hidden" name="label" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <textarea name="content" style="width:100%;height:70vh;font-family:monospace;"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>

    <p>
        <button type="submit">Сохранить</button>
    </p>
</form>
