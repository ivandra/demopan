<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/xml; charset=utf-8");

$currentDate = date('Y-m-d');

function esc_xml(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

if (!isset($pages) || !is_array($pages) || empty($pages)) {
    // fallback: только главная
    $loc = rtrim($domain, '/') . '/';
    echo "  <url>\n";
    echo "    <loc>" . esc_xml($loc) . "</loc>\n";
    echo "    <lastmod>{$currentDate}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
    echo "</urlset>";
    exit;
}

foreach ($pages as $path => $cfg) {
    $path = (string)$path;

    // не включаем служебную
    if ($path === '/404') continue;

    // если страницу явно исключили: 'sitemap' => false
    if (is_array($cfg) && array_key_exists('sitemap', $cfg) && $cfg['sitemap'] === false) {
        continue;
    }

    // нормализуем путь
    if ($path === '') $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;
    if ($path !== '/') $path = rtrim($path, '/');

    // формируем url со слешем на конце
    $loc = rtrim($domain, '/') . (($path === '/') ? '/' : ($path . '/'));

    $changefreq = (is_array($cfg) && !empty($cfg['changefreq'])) ? (string)$cfg['changefreq'] : 'monthly';
    $priority   = (is_array($cfg) && !empty($cfg['priority'])) ? (string)$cfg['priority'] : (($path === '/') ? '1.0' : '0.8');

    echo "  <url>\n";
    echo "    <loc>" . esc_xml($loc) . "</loc>\n";
    echo "    <lastmod>" . esc_xml($currentDate) . "</lastmod>\n";
    echo "    <changefreq>" . esc_xml($changefreq) . "</changefreq>\n";
    echo "    <priority>" . esc_xml($priority) . "</priority>\n";
    echo "  </url>\n";
}

echo "</urlset>";
