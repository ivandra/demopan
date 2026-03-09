<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$sites = is_array($sites ?? null) ? $sites : [];
?>

<div class="page-head">
    <h1 class="page-title">Webmaster</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/webmaster/connect">Настройки / токен</a>
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
    <div class="page-subtitle">
        Быстрый вход в работу с Яндекс Webmaster по каждому сайту.
    </div>
</div>

<?php if (empty($sites)): ?>
    <div class="panel-card empty-state mt-16">
        <h2 class="section-title">Сайтов нет</h2>
        <div class="small muted">Сначала создайте хотя бы один сайт.</div>
    </div>
<?php else: ?>
    <div class="panel-card mt-16">
        <div class="page-head page-head--compact">
            <h2 class="section-title">Сайты</h2>
            <div class="small muted">Всего: <b><?= count($sites) ?></b></div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Домен</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sites as $s): ?>
                    <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td class="system-table-domain"><?= h((string)$s['domain']) ?></td>
                        <td>
                            <div class="system-actions">
                                <a class="btn btn-sm btn-primary" href="/webmaster/site?id=<?= (int)$s['id'] ?>">Открыть Webmaster</a>
                                <a class="btn btn-sm btn-secondary" href="/sites/overview?id=<?= (int)$s['id'] ?>">Обзор</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>