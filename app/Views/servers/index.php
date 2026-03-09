<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$servers = is_array($servers ?? null) ? $servers : [];
?>

<div class="page-head">
    <h1 class="page-title">Серверы FastPanel</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="/servers/create">Добавить сервер</a>
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
    <div class="page-subtitle">
        Подключения к FastPanel, которые используются для создания сайтов, FTP и деплоя.
    </div>
</div>

<?php if (empty($servers)): ?>
    <div class="panel-card empty-state mt-16">
        <h2 class="section-title">Серверов пока нет</h2>
        <div class="small muted">Добавьте первое подключение к FastPanel.</div>
        <div class="page-actions mt-16">
            <a class="btn btn-primary" href="/servers/create">Добавить сервер</a>
        </div>
    </div>
<?php else: ?>
    <div class="panel-card mt-16">
        <div class="page-head page-head--compact">
            <h2 class="section-title">Список серверов</h2>
            <div class="small muted">Всего: <b><?= count($servers) ?></b></div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Host</th>
                    <th>User</th>
                    <th>TLS verify</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($servers as $s): ?>
                    <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td class="system-table-domain"><?= h($s['title']) ?></td>
                        <td><code><?= h($s['host']) ?></code></td>
                        <td><?= h($s['username']) ?></td>
                        <td>
                            <?php if ((int)($s['verify_tls'] ?? 0) === 1): ?>
                                <span class="badge badge-success">Проверять</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Не проверять</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="system-actions">
                                <a class="btn btn-sm btn-secondary" href="/servers/edit?id=<?= (int)$s['id'] ?>">Редактировать</a>
                                <a class="btn btn-sm btn-secondary" href="/servers/test?id=<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Проверить</a>

                                <form method="post"
                                      action="/servers/delete?id=<?= (int)$s['id'] ?>"
                                      data-confirm="Удалить сервер <?= h($s['title']) ?>?">
                                    <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>