<?php
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function sameHost(string $a, string $b): bool {
    $hostA = parse_url(rtrim($a, '/'), PHP_URL_HOST);
    $hostB = parse_url(rtrim($b, '/'), PHP_URL_HOST);
    return mb_strtolower($hostA ?? '') === mb_strtolower($hostB ?? '');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$currentBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');

// Если домен не совпадает с тем, что в конфиге — закрываем
if (!sameHost($currentBase, $domain)) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

// Нормальный robots для правильного домена
echo "User-agent: *\n";
echo "Allow: /\n";

// Закрываем служебное
echo "Disallow: /reg\n";
echo "Disallow: /*.php$\n";

// (опционально) косметические Allow по страницам из конфига
if (isset($pages) && is_array($pages)) {
    foreach ($pages as $path => $cfg) {
        $path = (string)$path;
        if ($path === '' || $path === '/' || $path === '/404') continue;

        if (is_array($cfg) && !empty($cfg['robots_disallow'])) {
            echo "Disallow: " . rtrim($path, '/') . "\n";
            continue;
        }

        echo "Allow: " . rtrim($path, '/') . "\n";
    }
}

echo "Host: " . rtrim($domain, '/') . "/\n";
echo "Sitemap: " . rtrim($domain, '/') . "/sitemap.xml\n";
