<h2>Редактирование файла: <code><?= htmlspecialchars($safeFile) ?></code></h2>
<p style="font-size:13px;opacity:.85;">
    config.php генерируется в: <code><?= htmlspecialchars($configTargetPath) ?></code>
    | <a href="/sites/files/edit?id=<?= (int)$site['id'] ?>&file=config.php">открыть в Files</a>
</p>

<p>
    <a href="/sites/texts?id=<?= (int)$site['id'] ?>">← к списку</a>
</p>

<form method="post" action="/sites/texts/save?id=<?= (int)$site['id'] ?>">
    <input type="hidden" name="file" value="<?= htmlspecialchars($safeFile) ?>">

    <textarea name="content" style="width:100%;height:70vh;font-family:monospace;"><?= htmlspecialchars($content) ?></textarea>

    <p>
        <button type="submit">Сохранить</button>
    </p>
</form>
