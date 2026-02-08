<?php
require_once __DIR__ . '/bootstrap.php';

header("Content-Type: application/xml; charset=utf-8");

$currentDate = date('Y-m-d');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('~:\d+$~', '', $host);
$currentDomain = $scheme . '://' . $host;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pages as $path => $p) {
    if (!is_array($p)) continue;
    if (($p['sitemap'] ?? true) === false) continue;

    $loc = rtrim($currentDomain, '/') . ($path === '/' ? '/' : $path . '/');
    $priority = $p['priority'] ?? '0.8';

    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>{$currentDate}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>{$priority}</priority>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
