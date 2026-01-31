<h2>FASTPANEL серверы</h2>

<p>
    <a href="/">← к сайтам</a> |
    <a href="/servers/create">Добавить сервер</a>
</p>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
    <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Host</th>
        <th>User</th>
        <th>TLS verify</th>
        <th>Действия</th>
    </tr>

    <?php foreach (($servers ?? []) as $s): ?>
        <tr>
            <td><?= (int)$s['id'] ?></td>
            <td><?= htmlspecialchars($s['title']) ?></td>
            <td><?= htmlspecialchars($s['host']) ?></td>
            <td><?= htmlspecialchars($s['username']) ?></td>
            <td><?= (int)$s['verify_tls'] ?></td>
            <td>
                <a href="/servers/edit?id=<?= (int)$s['id'] ?>">Редактировать</a>
                |
                <a href="/servers/test?id=<?= (int)$s['id'] ?>" target="_blank">Test</a>
                |
                <form method="post" action="/servers/delete?id=<?= (int)$s['id'] ?>" style="display:inline"
                      onsubmit="return confirm('Удалить сервер?');">
                    <button type="submit">Удалить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>

    <?php if (empty($servers)): ?>
        <tr><td colspan="6">Пока нет серверов</td></tr>
    <?php endif; ?>
</table>
