<h2>Files: <?= htmlspecialchars($site['domain']) ?></h2>

<p>
    <a href="/">← назад</a> |
    <a href="/sites/edit?id=<?= (int)$site['id'] ?>">SEO</a> |
    <a href="/sites/pages?id=<?= (int)$site['id'] ?>">Pages</a> |
    <a href="/sites/texts?id=<?= (int)$site['id'] ?>">Texts</a>
</p>

<table>
    <tr>
        <th>Файл</th>
        <th>Статус</th>
        <th>Размер</th>
        <th>Действия</th>
    </tr>

    <?php foreach ($files as $f): ?>
        <tr>
            <td><code><?= htmlspecialchars($f['name']) ?></code></td>
            <td><?= $f['exists'] ? 'есть' : '<span style="opacity:.7">нет</span>' ?></td>
            <td><?= (int)$f['size'] ?> bytes</td>
            <td>
                <a href="/sites/files/edit?id=<?= (int)$site['id'] ?>&file=<?= rawurlencode($f['name']) ?>">Открыть</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
