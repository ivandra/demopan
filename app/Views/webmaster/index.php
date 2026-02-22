<h2>Webmaster</h2>

<p>
    <a href="/webmaster/connect">Настройки / токен</a>
</p>

<h3>Сайты</h3>

<?php if (empty($sites)): ?>
    <p>Сайтов нет</p>
<?php else: ?>
    <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
        <tr>
            <th>ID</th>
            <th>Домен</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($sites as $s): ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= htmlspecialchars((string)$s['domain'], ENT_QUOTES) ?></td>
                <td>
                    <a href="/webmaster/site?id=<?= (int)$s['id'] ?>">Открыть</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
