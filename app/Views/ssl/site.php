<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$domain = (string)($site['domain'] ?? '');
$siteId = (int)($siteId ?? 0);
?>

<div class="page-head">
    <h1 class="page-title">SSL мониторинг</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/ssl">Общий список</a>
        <a class="btn btn-secondary" href="/deploy?id=<?= $siteId ?>">Публикация</a>
    </div>
    <div class="page-subtitle">
        Сайт #<?= $siteId ?> — <code><?= h($domain) ?></code>
    </div>
</div>

<div class="panel-grid panel-grid--3">
    <div class="panel-card">
        <div class="small muted">Итоговый статус</div>
        <div class="mt-12">
            <?php if (!empty($allOk)): ?>
                <span class="badge badge-success">ALL OK</span>
            <?php else: ?>
                <span class="badge badge-danger">NOT OK</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-card">
        <div class="small muted">Последняя проверка</div>
        <div class="kpi"><?= h($lastUpdated ?: '—') ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Состояние cron</div>
        <div class="mt-12">
            <?php if (!empty($cronAlive)): ?>
                <span class="badge badge-success">alive</span>
            <?php else: ?>
                <span class="badge badge-danger">stale</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($cronLast)): ?>
            <div class="small muted mt-8">last: <?= h($cronLast) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-actions">
        <form method="post" action="/ssl/site/check-now?id=<?= (int)$siteId ?>" data-confirm="Принудительно проверить SSL/HTTP сейчас?">
            <button type="submit" class="btn btn-primary">Проверить сейчас</button>
        </form>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Хосты и сертификаты</h2>
        <div class="small muted">root и поддомены текущего сайта</div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Label</th>
                <th>Domain</th>
                <th>Enabled</th>
                <th>HTTP</th>
                <th>SSL</th>
                <th>Details</th>
                <th>Updated</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($rows ?? []) as $x): ?>
                <?php
                $label = (string)($x['label'] ?? '');
                $fqdn  = (string)($x['fqdn'] ?? '');
                $r     = $x['row'] ?? null;

                $enabled = !empty($x['enabled']);
                $httpsOk = !empty($x['https_ok']);
                $http    = (int)($x['http_code'] ?? 0);
                $upd     = (string)($x['updated_at'] ?? '');

                $expires = $r ? (string)($r['ssl_expires_at'] ?? '') : '';
                $issuer  = $r ? (string)($r['ssl_issuer'] ?? '') : '';
                $subject = $r ? (string)($r['ssl_subject'] ?? '') : '';
                $err     = $r ? (string)($r['ssl_error'] ?? '') : '';
                ?>
                <tr>
                    <td><?= h($label === '' ? '(root)' : $label) ?></td>
                    <td><b><?= h($fqdn) ?></b></td>

                    <td>
                        <?php if ($enabled): ?>
                            <span class="badge badge-success">ON</span>
                        <?php else: ?>
                            <span class="badge badge-muted">OFF</span>
                        <?php endif; ?>
                    </td>

                    <td><?= $http > 0 ? (int)$http : '—' ?></td>

                    <td>
                        <?php if ($httpsOk): ?>
                            <span class="badge badge-success">OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger">FAIL</span>
                        <?php endif; ?>
                    </td>

                    <td class="small">
                        <?php if ($httpsOk): ?>
                            <?php if ($expires !== ''): ?><div>expires: <b><?= h($expires) ?></b></div><?php endif; ?>
                            <?php if ($issuer !== ''): ?><div>issuer: <?= h($issuer) ?></div><?php endif; ?>
                            <?php if ($subject !== ''): ?><div>subject: <?= h($subject) ?></div><?php endif; ?>
                        <?php else: ?>
                            <?= $err !== '' ? '<span class="badge badge-danger">Ошибка</span><div class="mt-8">' . h($err) . '</div>' : '—' ?>
                        <?php endif; ?>
                    </td>

                    <td class="small muted"><?= h($upd ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7" class="muted">Записей пока нет.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>