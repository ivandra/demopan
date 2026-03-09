<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="page-head">
    <h1 class="page-title">Аккаунты регистратора</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="/registrar/accounts/create">Добавить аккаунт</a>
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
    <div class="page-subtitle">
        Сейчас в панели используются аккаунты Namecheap для проверки, покупки доменов и DNS.
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="panel-card empty-state mt-16">
        <h2 class="section-title">Аккаунтов пока нет</h2>
        <div class="small muted">Добавьте первый аккаунт регистратора.</div>
    </div>
<?php else: ?>
    <div class="panel-card mt-16">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Провайдер</th>
                    <th>Окружение</th>
                    <th>Client IP</th>
                    <th>ApiUser</th>
                    <th>Логин</th>
                    <th>Created</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= h((string)$r['provider']) ?></td>
                        <td>
                            <?php if ((int)($r['is_sandbox'] ?? 0) === 1): ?>
                                <span class="badge badge-warning">sandbox</span>
                            <?php else: ?>
                                <span class="badge badge-success">prod</span>
                            <?php endif; ?>

                            <?php if ((int)($r['is_default'] ?? 0) === 1): ?>
                                <div class="small muted mt-8">default</div>
                            <?php endif; ?>
                        </td>
                        <td><code><?= h((string)$r['client_ip']) ?></code></td>
                        <td><?= h((string)$r['api_user']) ?></td>
                        <td><?= h((string)$r['username']) ?></td>
                        <td><?= h((string)$r['created_at']) ?></td>
                        <td>
                            <div class="system-actions">
                                <a class="btn btn-sm btn-secondary" href="/registrar/accounts/edit?id=<?= (int)$r['id'] ?>">Редактировать</a>

                                <form method="post"
                                      action="/registrar/accounts/delete?id=<?= (int)$r['id'] ?>"
                                      data-confirm="Удалить аккаунт регистратора #<?= (int)$r['id'] ?>?">
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