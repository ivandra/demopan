<?php
// bootstrap.php

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

function normalizePath($u): string {
    $path = parse_url($u, PHP_URL_PATH);
    if ($path === null || $path === false || $path === '') $path = '/';
    if ($path !== '/') $path = rtrim($path, '/');
    return $path;
}

$host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('~:\d+$~', '', $host);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$domain = $scheme . '://' . $host;

$currentUrl = $domain . ($_SERVER['REQUEST_URI'] ?? '/');

require_once __DIR__ . '/config.default.php';

// определяем ключ поддомена
$subKey = '_default';
$parts = explode('.', $host);
if (count($parts) >= 3) $subKey = $parts[0];

$subDir = __DIR__ . '/subs/' . $subKey;

// сохраняем дефолтный 404 на случай, если оверлей затрет $pages целиком
$default404 = $pages['/404'] ?? null;

// оверлей
if (is_dir($subDir) && file_exists($subDir . '/config.php')) {
    require $subDir . '/config.php';
}

// гарантируем /404
if (empty($pages['/404']) && is_array($default404)) {
    $pages['/404'] = $default404;
}

// ------------------------------
// assets overlay (logo/favicon)
// ------------------------------

$subAssetsWeb = '/subs/' . $subKey . '/assets';
$subAssetsDir = $subDir . '/assets';

$logoUrlDefault = $logoUrlDefault ?? '/img/logo.png';
$faviconUrlDefault = $faviconUrlDefault ?? '/favicon.ico';

// helper: нормализует "logo.webp" или "assets/logo.webp" -> "/subs/<sub>/assets/logo.webp"
$makeSubAssetUrl = function($val) use ($subAssetsWeb) {
    $v = trim((string)$val);
    if ($v === '') return '';
    // если уже абсолютный URL или абсолютный путь сайта — оставляем как есть
    if (preg_match('~^https?://~i', $v) || strpos($v, '/') === 0) return $v;
    // если написали assets/xxx — отрежем "assets/"
    $v = preg_replace('~^assets/~i', '', $v);
    return $subAssetsWeb . '/' . $v;
};

$makeSubAssetFile = function($val) use ($subAssetsDir) {
    $v = trim((string)$val);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v) || strpos($v, '/') === 0) return ''; // не файл в subs
    $v = preg_replace('~^assets/~i', '', $v);
    return $subAssetsDir . '/' . $v;
};

// 1) Явные настройки из конфига (рекомендуемый короткий формат: $logo/$favicon)
if (empty($logoUrl) && !empty($logo)) {
    $f = $makeSubAssetFile($logo);
    if ($f && is_file($f)) $logoUrl = $makeSubAssetUrl($logo);
}
if (empty($faviconUrl) && !empty($favicon)) {
    $f = $makeSubAssetFile($favicon);
    if ($f && is_file($f)) $faviconUrl = $makeSubAssetUrl($favicon);
}

// 2) Если явно не задано — авто-поиск в subs/<sub>/assets/
if (empty($logoUrl)) {
    foreach (['logo.svg','logo.webp','logo.png','logo.jpg','logo.jpeg'] as $f) {
        if (is_file($subAssetsDir . '/' . $f)) { $logoUrl = $subAssetsWeb . '/' . $f; break; }
    }
    if (empty($logoUrl)) $logoUrl = $logoUrlDefault;
}

if (empty($faviconUrl)) {
    foreach (['favicon.ico','favicon.png','favicon.svg','favicon.webp'] as $f) {
        if (is_file($subAssetsDir . '/' . $f)) { $faviconUrl = $subAssetsWeb . '/' . $f; break; }
    }
    if (empty($faviconUrl)) $faviconUrl = $faviconUrlDefault;
}
