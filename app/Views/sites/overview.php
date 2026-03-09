<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteId = (int)($siteId ?? 0);
$site = $site ?? [];
$subStats = $subStats ?? [];
$contentStats = $contentStats ?? [];
$buildStats = $buildStats ?? [];
$deployStats = $deployStats ?? [];
$sslStats = $sslStats ?? [];
$wmStats = $wmStats ?? [];
$freshClone = !empty($freshClone);

$domain = (string)($site['domain'] ?? '');
$template = (string)($site['template'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Обзор сайта</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
        <a class="btn btn-secondary" href="/sites/edit?id=<?= $siteId ?>">Настройки сайта</a>
        <a class="btn btn-secondary" href="/sites/clone?id=<?= $siteId ?>">Клонировать</a>
    </div>
    <div class="page-subtitle">
        Сайт #<?= $siteId ?> — <code><?= h($domain) ?></code>
    </div>
</div>

<?php if ($freshClone): ?>
    <div class="alert alert-success">
        <b>Клон создан успешно.</b> Ниже — постоянная рабочая карта по подготовке сайта к выпуску.
    </div>
<?php endif; ?>

<div class="panel-grid panel-grid--3">
    <div class="panel-card">
        <div class="small muted">Домен</div>
        <div style="font-size:22px;font-weight:700;margin-top:4px;"><?= h($domain) ?></div>
        <div class="small muted mt-8">Шаблон: <b><?= h($template) ?></b></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Поддомены</div>
        <div style="font-size:22px;font-weight:700;margin-top:4px;">
            <?= (int)($subStats['enabled_subs'] ?? 0) ?> / <?= (int)($subStats['total_subs'] ?? 0) ?>
        </div>
        <div class="small muted mt-8">включено / всего</div>
    </div>

    <div class="panel-card">
        <div class="small muted">SSL monitor</div>
        <div style="font-size:22px;font-weight:700;margin-top:4px;">
            <?= (int)($sslStats['ok'] ?? 0) ?> / <?= (int)($sslStats['total'] ?? 0) ?>
        </div>
        <div class="small muted mt-8">OK / проверяется</div>
    </div>
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">1) Домен и DNS</h2>

        <ul class="status-list small">
            <li>Аккаунт регистратора:
                <?= !empty($site['registrar_account_id']) ? '<span class="badge badge-success">выбран</span>' : '<span class="badge badge-warning">не выбран</span>' ?>
            </li>
            <li>IP для VPS:
                <?= !empty($site['vps_ip']) ? '<span class="badge badge-success">' . h($site['vps_ip']) . '</span>' : '<span class="badge badge-warning">не задан</span>' ?>
            </li>
            <li>Статус DNS в карточке сайта:
                <?= !empty($site['dns_status']) && $site['dns_status'] !== 'none'
                    ? '<span class="badge badge-success">' . h($site['dns_status']) . '</span>'
                    : '<span class="badge badge-muted">нет данных</span>' ?>
            </li>
        </ul>

        <div class="page-actions">
            <a class="btn btn-primary" href="/domains?id=<?= $siteId ?>">Открыть Домен и DNS</a>
            <a class="btn btn-secondary" href="/sites/edit?id=<?= $siteId ?>">Настройки сайта</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">2) Поддомены</h2>

        <ul class="status-list small">
            <li>Всего сабов: <b><?= (int)($subStats['total_subs'] ?? 0) ?></b></li>
            <li>Включено: <b><?= (int)($subStats['enabled_subs'] ?? 0) ?></b></li>
            <li>DNS ok по всем сущностям: <b><?= (int)($subStats['dns_ok_all'] ?? 0) ?></b></li>
        </ul>

        <div class="page-actions">
            <a class="btn btn-primary" href="/sites/subdomains?id=<?= $siteId ?>">Открыть Поддомены</a>
            <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Открыть root / _default</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">3) Контент и SEO</h2>

        <ul class="status-list small">
            <li>Root-конфиг:
                <?= !empty($contentStats['default_exists'])
                    ? '<span class="badge badge-success">есть</span>'
                    : '<span class="badge badge-danger">нет</span>' ?>
            </li>
            <li>Саб-конфигов: <b><?= (int)($contentStats['sub_cfg_count'] ?? 0) ?></b></li>
            <li>Label с pages: <b><?= (int)($contentStats['labels_with_pages'] ?? 0) ?></b></li>
            <li>Всего страниц в конфигах: <b><?= (int)($contentStats['pages_total'] ?? 0) ?></b></li>
        </ul>

        <div class="page-actions">
            <a class="btn btn-primary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Контент и SEO</a>
            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=_default">Страницы root</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=_default">Тексты root</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">4) AI</h2>

        <div class="small muted">
            Быстрый доступ к runtime-опциям, генерации root / sub / pages и массовым операциям.
        </div>

        <div class="page-actions">
            <a class="btn btn-ai" href="/sites/ai?id=<?= $siteId ?>">Открыть AI для сайта</a>
            <a class="btn btn-secondary" href="/ai/settings">Глобальные AI-настройки</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">5) Build</h2>

        <ul class="status-list small">
            <li>Build path:
                <?= !empty($buildStats['build_rel'])
                    ? '<code>' . h($buildStats['build_rel']) . '</code>'
                    : '<span class="badge badge-muted">не задан</span>' ?>
            </li>
            <li>Папка build:
                <?= !empty($buildStats['build_exists'])
                    ? '<span class="badge badge-success">есть</span>'
                    : '<span class="badge badge-warning">нет</span>' ?>
            </li>
            <li>ZIP:
                <?= !empty($buildStats['zip_exists'])
                    ? '<span class="badge badge-success">есть</span>'
                    : '<span class="badge badge-muted">нет</span>' ?>
            </li>
        </ul>

        <div class="page-actions">
            <form method="post" action="/sites/build?id=<?= $siteId ?>" data-confirm="Запустить build для <?= h($domain) ?>?">
                <button type="submit" class="btn btn-primary">Сделать build</button>
            </form>

            <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">Файлы build</a>

            <?php if (!empty($buildStats['zip_exists'])): ?>
                <a class="btn btn-secondary" href="/sites/export?id=<?= $siteId ?>">Скачать ZIP</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">6) Публикация на VPS</h2>

        <ul class="status-list small">
            <li>FastPanel site:
                <?= !empty($deployStats['fp_site_created'])
                    ? '<span class="badge badge-success">создан</span>'
                    : '<span class="badge badge-warning">не создан</span>' ?>
            </li>
            <li>FTP:
                <?= !empty($deployStats['ftp_ready'])
                    ? '<span class="badge badge-success">готов</span>'
                    : '<span class="badge badge-warning">не готов</span>' ?>
            </li>
            <li>Файлы:
                <?= !empty($deployStats['files_ready'])
                    ? '<span class="badge badge-success">загружались</span>'
                    : '<span class="badge badge-warning">нет данных</span>' ?>
            </li>
            <li>Последний deploy status:
                <?= !empty($deployStats['last_status'])
                    ? '<span class="badge badge-muted">' . h($deployStats['last_status']) . '</span>'
                    : '<span class="badge badge-muted">—</span>' ?>
            </li>
        </ul>

        <div class="page-actions">
            <a class="btn btn-primary" href="/deploy?id=<?= $siteId ?>">Открыть Deploy</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">7) SSL</h2>

        <ul class="status-list small">
            <li>Проверок OK: <b><?= (int)($sslStats['ok'] ?? 0) ?></b> / <?= (int)($sslStats['total'] ?? 0) ?></li>
            <li>Последняя проверка:
                <?= !empty($sslStats['last']) ? '<code>' . h($sslStats['last']) . '</code>' : '—' ?>
            </li>
            <li>FP ssl_ready:
                <?= !empty($site['ssl_ready'])
                    ? '<span class="badge badge-success">да</span>'
                    : '<span class="badge badge-muted">нет</span>' ?>
            </li>
        </ul>

        <div class="page-actions">
            <form method="post" action="/ssl/check-now?id=<?= $siteId ?>" data-confirm="Принудительно проверить SSL сейчас?">
                <button type="submit" class="btn btn-primary">Проверить SSL сейчас</button>
            </form>

            <a class="btn btn-secondary" href="/ssl/site?id=<?= $siteId ?>">Открыть SSL</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">8) Webmaster</h2>

        <ul class="status-list small">
            <li>Hosts: <b><?= (int)($wmStats['verified'] ?? 0) ?></b> / <?= (int)($wmStats['total'] ?? 0) ?> verified</li>
            <li>Sitemap добавлен: <b><?= (int)($wmStats['sitemaps'] ?? 0) ?></b></li>
            <li>Robots подтверждён: <b><?= (int)($wmStats['robots'] ?? 0) ?></b></li>
            <li>Последнее обновление:
                <?= !empty($wmStats['last_sync']) ? '<code>' . h($wmStats['last_sync']) . '</code>' : '—' ?>
            </li>
        </ul>

        <div class="page-actions">
            <a class="btn btn-primary" href="/webmaster/site?id=<?= $siteId ?>">Открыть Webmaster</a>
        </div>
    </div>
</div>