<h2>Редактирование: <code><?= htmlspecialchars($safeFile) ?></code></h2>

<p>
    <a href="/sites/files?id=<?= (int)$site['id'] ?>">← к списку файлов</a>
</p>

<form method="post" action="/sites/files/save?id=<?= (int)$site['id'] ?>">
    <input type="hidden" name="file" value="<?= htmlspecialchars($safeFile) ?>">

    <textarea name="content" style="width:100%;height:70vh;font-family:monospace;"><?= htmlspecialchars($content) ?></textarea>

    <p>
        <button type="submit">Сохранить (с бэкапом)</button>
    </p>
</form>

<?php if (!empty($backups)): ?>
    <hr>
    <h3>Бэкапы</h3>

    <form method="post" action="/sites/files/restore?id=<?= (int)$site['id'] ?>" onsubmit="return confirm('Восстановить выбранный бэкап? Текущий файл будет сохранен как бэкап.');">
        <input type="hidden" name="file" value="<?= htmlspecialchars($safeFile) ?>">
        <select name="backup">
            <?php foreach ($backups as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Восстановить</button>
    </form>
<?php endif; ?>
