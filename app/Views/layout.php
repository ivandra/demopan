<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hub панель</title>
    <link rel="stylesheet" href="/assets/admin.css?v=20260326-miniline">
    <script defer src="/assets/admin.js?v=20260326-miniline"></script>
</head>
<body>
<?php
if (!function_exists('layout_h')) {
    function layout_h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('layout_path_is')) {
    function layout_path_is(string $current, array $patterns): bool {
        foreach ($patterns as $p) {
            if ($p === '') continue;

            $exact = false;
            if (substr($p, -1) === '$') {
                $exact = true;
                $p = substr($p, 0, -1);
            }

            if ($exact) {
                if ($current === $p) {
                    return true;
                }
            } else {
                if (strpos($current, $p) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}

$currentUri  = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = (string)(parse_url($currentUri, PHP_URL_PATH) ?: '/');

$layoutSiteId = 0;
if (isset($site['id'])) {
    $layoutSiteId = (int)$site['id'];
} elseif (isset($siteId)) {
    $layoutSiteId = (int)$siteId;
} elseif (isset($deploy['site_id'])) {
    $layoutSiteId = (int)$deploy['site_id'];
} elseif (isset($_GET['site_id'])) {
    $layoutSiteId = (int)$_GET['site_id'];
} elseif (isset($_GET['id'])) {
    $layoutSiteId = (int)$_GET['id'];
}

$layoutDomain = '';
if (isset($site['domain'])) {
    $layoutDomain = (string)$site['domain'];
} elseif ($layoutSiteId > 0 && class_exists('DB')) {
    try {
        $st = DB::pdo()->prepare("SELECT domain FROM sites WHERE id=? LIMIT 1");
        $st->execute([$layoutSiteId]);
        $layoutDomain = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $layoutDomain = '';
    }
}

$hideNav = ($currentPath === '/login');

$flashBag = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$publishDirtySites = [];
$currentSiteDirty = null;
$currentSiteStatus = null;
if ($layoutSiteId > 0 && class_exists('DB')) {
    try {
        $st = DB::pdo()->prepare("SELECT id, domain, publish_dirty, publish_dirty_at, publish_dirty_message, fp_site_created, fp_ftp_ready, fp_files_ready, fp_files_last_ok, ssl_ready, ssl_checked_at, ssl_last_ok, fp_ftp_last_ok, domain_purchase_status, dns_status FROM sites WHERE id=? LIMIT 1");
        $st->execute([$layoutSiteId]);
        $rowCurrentSiteDirty = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $currentSiteStatus = $rowCurrentSiteDirty;
        if (!empty($rowCurrentSiteDirty['publish_dirty'])) {
            $currentSiteDirty = $rowCurrentSiteDirty;
        }
    } catch (Throwable $e) {
        $currentSiteDirty = null;
        $currentSiteStatus = null;
    }
}
if (class_exists('PublishDirtyService')) {
    try {
        $publishDirtySites = (new PublishDirtyService())->getDirtySites(10);
    } catch (Throwable $e) {
        $publishDirtySites = [];
    }
}

$globalNav = [

    ['title' => 'Сайты',                 'href' => '/sites',              'match' => ['/sites']],
    ['title' => 'Серверы',               'href' => '/servers',            'match' => ['/servers']],
    ['title' => 'Аккаунты Namecheap',    'href' => '/registrar/accounts', 'match' => ['/registrar/accounts']],
    ['title' => 'Контакты регистратора', 'href' => '/registrar/contacts', 'match' => ['/registrar/contacts']],
    ['title' => 'Каталог сабов',         'href' => '/subdomains',         'match' => ['/subdomains']],
    ['title' => 'SSL',                   'href' => '/ssl',                'match' => ['/ssl']],
    ['title' => 'Вебмастер',             'href' => '/webmaster',          'match' => ['/webmaster']],
    ['title' => 'AI-настройки',          'href' => '/ai/settings',        'match' => ['/ai/settings']],
];

$siteNav = [];
if ($layoutSiteId > 0) {
    $siteNav = [
        ['title' => 'Обзор',            'href' => '/sites/overview?id=' . $layoutSiteId,   'match' => ['/sites/overview', '/sites/clone/done']],
        ['title' => 'Настройки сайта',  'href' => '/sites/edit?id=' . $layoutSiteId,       'match' => ['/sites/edit$']],
        ['title' => 'Домен и DNS',      'href' => '/domains?id=' . $layoutSiteId,          'match' => ['/domains']],
        ['title' => 'Поддомены',        'href' => '/sites/subdomains?id=' . $layoutSiteId, 'match' => ['/sites/subdomains']],
        ['title' => 'Контент и SEO',    'href' => '/sites/subcfg?id=' . $layoutSiteId,     'match' => ['/sites/subcfg', '/sites/pages', '/sites/texts', '/sites/files']],
        ['title' => 'AI',               'href' => '/sites/ai?id=' . $layoutSiteId,         'match' => ['/sites/ai']],
        ['title' => 'Публикация',       'href' => '/deploy?id=' . $layoutSiteId,           'match' => ['/deploy']],
        ['title' => 'SSL',              'href' => '/ssl/site?id=' . $layoutSiteId,         'match' => ['/ssl/site']],
        ['title' => 'Вебмастер',        'href' => '/webmaster/site?id=' . $layoutSiteId,   'match' => ['/webmaster/site']],
        ['title' => 'Клонировать',      'href' => '/sites/clone?id=' . $layoutSiteId,      'match' => ['/sites/clone$']],
    ];
}
?>

<?php
if (!function_exists('layout_status_meta')) {
    function layout_status_meta(?array $siteRow, int $siteId = 0): array {
        $siteRow = is_array($siteRow) ? $siteRow : [];
        $publishDirty = !empty($siteRow['publish_dirty']);
        $filesReady = !empty($siteRow['fp_files_ready']);

        $subTotal = 0;
        $subEnabled = 0;
        $subDnsOk = 0;

        $sslTotal = 0;
        $sslOk = 0;

        $wmTotal = 0;
        $wmVerified = 0;

        if ($siteId > 0 && class_exists('DB')) {
            try {
                $st = DB::pdo()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END),0) AS enabled_cnt, COALESCE(SUM(CASE WHEN enabled=1 AND COALESCE(dns_status,'')='ok' THEN 1 ELSE 0 END),0) AS dns_ok_cnt FROM site_subdomains WHERE site_id=?");
                $st->execute([$siteId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $subTotal = (int)($row['total'] ?? 0);
                $subEnabled = (int)($row['enabled_cnt'] ?? 0);
                $subDnsOk = (int)($row['dns_ok_cnt'] ?? 0);
            } catch (Throwable $e) {}

            try {
                $st = DB::pdo()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN https_ok=1 THEN 1 ELSE 0 END),0) AS ok_cnt FROM ssl_checks WHERE site_id=? AND enabled=1");
                $st->execute([$siteId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $sslTotal = (int)($row['total'] ?? 0);
                $sslOk = (int)($row['ok_cnt'] ?? 0);
            } catch (Throwable $e) {}

            try {
                $st = DB::pdo()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END),0) AS verified_cnt FROM webmaster_hosts WHERE site_id=?");
                $st->execute([$siteId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $wmTotal = (int)($row['total'] ?? 0);
                $wmVerified = (int)($row['verified_cnt'] ?? 0);
            } catch (Throwable $e) {}
        }

        $dnsOk = ((string)($siteRow['dns_status'] ?? '') === 'configured');
        $dnsWarn = (!$dnsOk && !empty($siteRow['domain_purchase_status']) && (string)$siteRow['domain_purchase_status'] !== 'none');

        $subsOk = ($subEnabled > 0 && $subDnsOk >= $subEnabled);
        $subsWarn = (!$subsOk && $subDnsOk > 0);

        $vpsOk = (!empty($siteRow['fp_site_created']) && !empty($siteRow['fp_ftp_ready']));
        $vpsWarn = (!$vpsOk && (!empty($siteRow['fp_site_created']) || !empty($siteRow['fp_ftp_ready'])));

        $sslPillOk = ($sslTotal > 0 && $sslOk >= $sslTotal);
        $sslPillWarn = (!$sslPillOk && $sslOk > 0);

        $wmPillOk = ($wmTotal > 0 && $wmVerified >= $wmTotal);
        $wmPillWarn = (!$wmPillOk && $wmVerified > 0);

        return [
            [
                'label' => 'DNS',
                'ok' => $dnsOk,
                'warn' => $dnsWarn,
                'text' => $dnsOk ? 'ok' : ((string)($siteRow['dns_status'] ?? 'none')),
            ],
            [
                'label' => 'Сабы',
                'ok' => $subsOk,
                'warn' => $subsWarn,
                'text' => $subEnabled . '/' . $subTotal,
            ],
            [
                'label' => 'VPS',
                'ok' => $vpsOk,
                'warn' => $vpsWarn,
                'text' => $vpsOk ? 'ok' : ((!empty($siteRow['fp_site_created']) || !empty($siteRow['fp_ftp_ready'])) ? 'part' : 'off'),
            ],
            [
                'label' => 'Файлы',
                'ok' => ($filesReady && !$publishDirty),
                'warn' => ($filesReady && $publishDirty),
                'text' => !$filesReady ? 'нет' : ($publishDirty ? 'нужна выгрузка' : 'ok'),
            ],
            [
                'label' => 'SSL',
                'ok' => $sslPillOk,
                'warn' => $sslPillWarn,
                'text' => $sslTotal > 0 ? ($sslOk . '/' . $sslTotal) : '0/0',
            ],
            [
                'label' => 'WM',
                'ok' => $wmPillOk,
                'warn' => $wmPillWarn,
                'text' => $wmTotal > 0 ? ($wmVerified . '/' . $wmTotal) : '0/0',
            ],
        ];
    }
}
?>


<?php if (!$hideNav): ?>
<header class="hub-header">
    <div class="hub-brand">
        <div>
            <div class="hub-brand__title">Hub.seotop-one.ru</div>
            <div class="hub-brand__sub">Внутренняя панель управления сайтами, поддоменами, публикацией, AI и Вебмастером</div>
        </div>
        <div class="hub-chip">внутренняя панель</div>
    </div>

    <nav class="hub-nav">
        <?php foreach ($globalNav as $item): ?>
            <a
                href="<?= layout_h($item['href']) ?>"
                class="<?= layout_path_is($currentPath, $item['match']) ? 'is-active' : '' ?>"
            ><?= layout_h($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php if ($layoutSiteId > 0): ?>
    <div class="hub-sitebar">
        <div class="hub-sitebar__head">
            <div>
                <div class="hub-sitebar__title">
                    Сайт #<?= (int)$layoutSiteId ?>
                    <?php if ($layoutDomain !== ''): ?>
                        — <?= layout_h($layoutDomain) ?>
                    <?php endif; ?>
                </div>
                <div class="hub-sitebar__meta">Быстрая навигация по текущему сайту</div>
            </div>
        </div>

        <nav class="hub-subnav">
            <?php foreach ($siteNav as $item): ?>
                <a
                    href="<?= layout_h($item['href']) ?>"
                    class="<?= layout_path_is($currentPath, $item['match']) ? 'is-active' : '' ?>"
                ><?= layout_h($item['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
<?php endif; ?>
<?php if ($layoutSiteId > 0 && !empty($currentSiteStatus)): ?>
    <?php
        $layoutStatuses = layout_status_meta($currentSiteStatus, $layoutSiteId);
        $needPublishAction = (!empty($currentSiteStatus['publish_dirty']) || empty($currentSiteStatus['fp_files_ready']));
    ?>
    <div class="hub-statusline<?= $needPublishAction ? ' is-warning' : '' ?>">
        <div class="hub-statusline__left">
            <?php foreach ($layoutStatuses as $status): ?>
                <?php $cls = !empty($status['warn']) ? 'is-warn' : (!empty($status['ok']) ? 'is-ok' : 'is-bad'); ?>
                <span class="hub-mini-pill <?= $cls ?>">
                    <span class="hub-mini-pill__dot" aria-hidden="true"></span>
                    <b><?= layout_h((string)$status['label']) ?></b>
                    <span><?= layout_h((string)$status['text']) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if ($needPublishAction): ?>
            <div class="hub-statusline__right">
                <a class="btn btn-primary btn-sm" href="/deploy?id=<?= (int)$layoutSiteId ?>">Нужна публикация</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>


<main class="hub-main">
    <?php if (!empty($publishDirtySites)): ?>
        <?php foreach ($publishDirtySites as $dirtySite): ?>
            <div class="alert alert-warning mt-16">
                <b>Требуется выгрузка на VPS:</b> сайт <b><?= layout_h((string)($dirtySite['domain'] ?? '')) ?></b>.<br>
                <?= layout_h((string)($dirtySite['publish_dirty_message'] ?? 'Есть локальные изменения.')) ?><br>
                <span class="small">Смотреть это можно здесь, в верхней желтой плашке, и в разделе сайта <b>AI</b> или <b>Обзор</b>.</span>
                <div class="page-actions mt-8"><a class="btn btn-primary" href="/deploy?id=<?= (int)($dirtySite['id'] ?? 0) ?>">Открыть публикацию</a></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($flashBag['success'])): ?>
        <?php foreach ($flashBag['success'] as $msg): ?>
            <div class="alert alert-success mt-16"><?= layout_h($msg) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($flashBag['error'])): ?>
        <?php foreach ($flashBag['error'] as $msg): ?>
            <div class="alert alert-danger mt-16"><?= layout_h($msg) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php require __DIR__ . '/' . $path . '.php'; ?>
</main>
</body>
</html>