<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('~:\d+$~', '', $host);

// Разрешаем robots только для base_domain и его поддоменов
$allowed = false;
if (!empty($base_domain)) {
    $bd = strtolower($base_domain);
    $allowed = ($host === $bd) || str_ends_with($host, '.' . $bd);
}

if (!$allowed) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$currentDomain = $scheme . '://' . $host;

echo "User-agent: *\n";
echo "Allow: /\n";

// закрываем служебное
echo "Disallow: /subs/\n";
echo "Disallow: /*.php$\n";

// закрываем promolink (/reg)
if (!empty($promolink)) {
    $p = '/' . trim($promolink, '/');
    echo "Disallow: {$p}\n";
}

echo "Host: " . rtrim($currentDomain, '/') . "/\n";
echo "Sitemap: " . rtrim($currentDomain, '/') . "/sitemap.xml\n";
