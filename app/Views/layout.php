<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hub панель</title>
    <link rel="stylesheet" href="/assets/admin.css?v=20260317a">
    <script defer src="/assets/admin.js?v=20260317a"></script>
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
}

$hideNav = ($currentPath === '/login');

$flashBag = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

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
<?php endif; ?>

<main class="hub-main">
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