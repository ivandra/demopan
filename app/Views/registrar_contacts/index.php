<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="page-head">
    <h1 class="page-title">Контакты регистратора</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="/registrar/contacts/create">Добавить контакт</a>
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
    <div class="page-subtitle">
        Контактные профили, которые используются при покупке доменов.
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="panel-card empty-state mt-16">
        <h2 class="section-title">Контактов пока нет</h2>
        <div class="small muted">Добавьте первый контактный профиль.</div>
    </div>
<?php else: ?>
    <div class="panel-card mt-16">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Country</th>
                    <th>City</th>
                    <th>Postal</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= h((string)$r['label']) ?></td>
                        <td><?= h((string)$r['first_name'] . ' ' . (string)$r['last_name']) ?></td>
                        <td><?= h((string)$r['email']) ?></td>
                        <td><?= h((string)$r['phone']) ?></td>
                        <td><?= h((string)$r['country']) ?></td>
                        <td><?= h((string)$r['city']) ?></td>
                        <td><?= h((string)$r['postal_code']) ?></td>
                        <td>
                            <div class="system-actions">
                                <a class="btn btn-sm btn-secondary" href="/registrar/contacts/edit?id=<?= (int)$r['id'] ?>">Редактировать</a>

                                <form method="post"
                                      action="/registrar/contacts/delete?id=<?= (int)$r['id'] ?>"
                                      data-confirm="Удалить контакт #<?= (int)$r['id'] ?>?">
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