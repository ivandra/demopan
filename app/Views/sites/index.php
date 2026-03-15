<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function status_badge(bool $ok, string $okText = 'Да', string $failText = 'Нет'): string {
    return $ok
        ? '<span class="badge badge-success">' . h($okText) . '</span>'
        : '<span class="badge badge-muted">' . h($failText) . '</span>';
}
?>

<div class="page-head">
    <h1 class="page-title">Сайты</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="/sites/create">Создать сайт</a>
    </div>
    <div class="page-subtitle">
        Список всех сайтов панели с быстрым доступом к обзору, контенту, публикации, SSL и Webmaster.
    </div>
</div>

<div class="panel-grid panel-grid--3">
    <div class="panel-card">
        <div class="small muted">Всего сайтов</div>
        <div class="kpi"><?= count($sites ?? []) ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Быстрые разделы</div>
        <div class="sites-top-actions mt-12">
            <a class="btn btn-secondary" href="/servers">Серверы</a>
            <a class="btn btn-secondary" href="/subdomains">Каталог сабов</a>
            <a class="btn btn-secondary" href="/ssl">SSL monitor</a>
            <a class="btn btn-secondary" href="/ssl/settings">TG settings</a>
        </div>
    </div>

    <div class="panel-card">
        <div class="small muted">Маршрут работы</div>
        <div class="small mt-12">
            Обзор → Домен/DNS → Поддомены → Контент и SEO → AI → Build → Публикация → SSL → Webmaster
        </div>
    </div>
</div>

<?php if (empty($sites)): ?>
    <div class="panel-card empty-state mt-16">
        <h2 class="section-title">Сайтов пока нет</h2>
        <div class="small muted">Создайте первый сайт, чтобы начать сборку и публикацию.</div>
        <div class="page-actions mt-16">
            <a class="btn btn-primary" href="/sites/create">Создать сайт</a>
        </div>
    </div>
<?php else: ?>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Список сайтов</h2>
        <div class="small muted">Всего: <b><?= count($sites) ?></b></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Сайт</th>
                <th>Шаблон</th>
                <th>Статус</th>
                <th>VPS</th>
                <th>FTP</th>
                <th>Файлы</th>
                <th>SSL / Monitor</th>
                <th style="min-width:420px;">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sites as $site): ?>
                <?php
                $siteId = (int)($site['id'] ?? 0);

                $domain   = (string)($site['domain'] ?? '');
                $template = (string)($site['template'] ?? '');
                $status   = (string)($site['status'] ?? '');

                $vpsOk      = ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0);
                $ftpOk      = ((int)($site['fp_ftp_ready'] ?? 0) === 1);
                $filesOk    = ((int)($site['fp_files_ready'] ?? 0) === 1);
                $sslReady   = ((int)($site['ssl_ready'] ?? 0) === 1);
                $sslHasCert = ((int)($site['ssl_has_cert'] ?? 0) === 1);
                $sslCertId  = (int)($site['ssl_cert_id'] ?? 0);
                $sslErr     = (string)($site['ssl_error'] ?? '');

                $fpStateText = 'Нет';
                $fpStateClass = 'badge-muted';

                if (!$vpsOk) {
                    $fpStateText = 'FP: —';
                    $fpStateClass = 'badge-muted';
                } elseif ($sslErr !== '') {
                    $fpStateText = 'FP: Ошибка';
                    $fpStateClass = 'badge-danger';
                } elseif ($sslReady) {
                    $fpStateText = 'FP: Готов';
                    $fpStateClass = 'badge-success';
                } elseif ($sslHasCert) {
                    $fpStateText = 'FP: Не применён';
                    $fpStateClass = 'badge-warning';
                } else {
                    $fpStateText = 'FP: Нет SSL';
                    $fpStateClass = 'badge-muted';
                }

                $monCount = 0;
                $monOkCount = 0;
                $monLast = '';
                $monAllOk = null;

                try {
                    $pdo = DB::pdo();
                    $st = $pdo->prepare("
                        SELECT
                          SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS cnt,
                          SUM(CASE WHEN enabled=1 AND https_ok=1 THEN 1 ELSE 0 END) AS ok_cnt,
                          MAX(updated_at) AS last_dt
                        FROM ssl_checks
                        WHERE site_id=?
                    ");
                    $st->execute([$siteId]);
                    $agg = $st->fetch(PDO::FETCH_ASSOC) ?: [];

                    $monCount = (int)($agg['cnt'] ?? 0);
                    $monOkCount = (int)($agg['ok_cnt'] ?? 0);
                    $monLast = (string)($agg['last_dt'] ?? '');

                    if ($monCount > 0) {
                        $monAllOk = ($monOkCount === $monCount);
                    }
                } catch (Throwable $e) {
                    $monAllOk = null;
                }

                $needWarn = ($sslHasCert && !$sslReady && $monAllOk === true);
                ?>
                <tr>
                    <td class="nowrap"><?= $siteId ?></td>

                    <td>
                        <div class="sites-domain"><?= h($domain) ?></div>
                        <span class="sites-meta">ID: #<?= $siteId ?></span>
                    </td>

                    <td><?= h($template) ?></td>

                    <td>
                        <?php if ($status === 'active'): ?>
                            <span class="badge badge-success">Активен</span>
                        <?php elseif ($status === 'disabled'): ?>
                            <span class="badge badge-warning">Выключен</span>
                        <?php else: ?>
                            <span class="badge badge-muted"><?= h($status !== '' ? $status : '—') ?></span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= status_badge($vpsOk, 'Да', 'Нет') ?>
                        <?php if ($vpsOk && !empty($site['fp_site_id'])): ?>
                            <div class="small muted mt-8">FP ID: #<?= (int)$site['fp_site_id'] ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= status_badge($ftpOk, 'Готов', 'Нет') ?>
                        <?php if ($ftpOk && !empty($site['fp_ftp_last_ok'])): ?>
                            <div class="small muted mt-8"><?= h(date('d.m H:i', strtotime((string)$site['fp_ftp_last_ok']))) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= status_badge($filesOk, 'Загружены', 'Нет') ?>
                        <?php if ($filesOk && !empty($site['fp_files_last_ok'])): ?>
                            <div class="small muted mt-8"><?= h(date('d.m H:i', strtotime((string)$site['fp_files_last_ok']))) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="sites-ssl-stack">
                            <div class="sites-ssl-line">
                                <span class="badge <?= $fpStateClass ?>"><?= h($fpStateText) ?></span>
                                <?php if ($sslCertId > 0 && $vpsOk): ?>
                                    <span class="badge badge-muted">cert #<?= $sslCertId ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="sites-ssl-line">
                                <?php if ($monAllOk === true): ?>
                                    <span class="badge badge-success">Monitor: OK</span>
                                <?php elseif ($monAllOk === false): ?>
                                    <span class="badge badge-danger">Monitor: NOT OK</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Monitor: —</span>
                                <?php endif; ?>

                                <?php if ($monCount > 0): ?>
                                    <span class="badge badge-muted"><?= $monOkCount ?>/<?= $monCount ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($monLast !== ''): ?>
                                <div class="small muted">last: <?= h(date('Y-m-d H:i', strtotime($monLast))) ?></div>
                            <?php endif; ?>

                            <?php if ($needWarn): ?>
                                <div class="sites-ssl-note">
                                    Сертификат уже работает по мониторингу, но в FastPanel ещё не отмечен как применённый.
                                </div>
                            <?php endif; ?>

                            <div class="small">
                                <a href="/ssl/site?id=<?= $siteId ?>">Открыть SSL</a>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="sites-actions">
                            <a class="btn btn-secondary btn-sm" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>

                            <form method="post"
                                  action="/sites/build?id=<?= $siteId ?>"
                                  data-confirm="Запустить сборку для сайта <?= h($domain) ?>?">
                                <button class="btn btn-primary btn-sm" type="submit">Build</button>
                            </form>

                            <a class="btn btn-secondary btn-sm" href="/deploy?id=<?= $siteId ?>">Публикация</a>
                            <a class="btn btn-secondary btn-sm" href="/domains?id=<?= $siteId ?>">Домен/DNS</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/subdomains?id=<?= $siteId ?>">Поддомены</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Контент и SEO</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/clone?id=<?= $siteId ?>">Клонировать</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/edit?id=<?= $siteId ?>">Настройки</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/pages?id=<?= $siteId ?>&label=_default">Страницы</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/texts?id=<?= $siteId ?>&label=_default">Тексты</a>
                            <a class="btn btn-secondary btn-sm" href="/sites/files?id=<?= $siteId ?>">Файлы</a>
                            <a class="btn btn-secondary btn-sm" href="/webmaster/site?id=<?= $siteId ?>">Webmaster</a>

                            <form method="post"
                                  action="/ssl/check-now?id=<?= $siteId ?>"
                                  data-confirm="Принудительно проверить SSL сейчас для корня и enabled=1 поддоменов?">
                                <button class="btn btn-secondary btn-sm" type="submit">Проверить SSL</button>
                            </form>

                            <?php if (!empty($site['build_path'])): ?>
                                <a class="btn btn-secondary btn-sm" href="/sites/export?id=<?= $siteId ?>">ZIP</a>
                            <?php endif; ?>

                            <form method="post"
                                  action="/sites/delete?id=<?= $siteId ?>"
                                  data-confirm="Удалить сайт #<?= $siteId ?> (<?= h($domain) ?>)?">
                                <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
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