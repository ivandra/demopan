<?php
// bootstrap.php

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

if (!function_exists('normalizePath')) {
    function normalizePath($u): string {
        $path = parse_url($u, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') $path = '/';
        if ($path !== '/') $path = rtrim($path, '/');
        return $path;
    }
}

$host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('~:\d+$~', '', $host);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$domain = $scheme . '://' . $host;
$currentUrl = $domain . ($_SERVER['REQUEST_URI'] ?? '/');

$title = $title ?? '';
$description = $description ?? '';
$keywords = $keywords ?? '';
$h1 = $h1 ?? '';

$pages = $pages ?? [];
$promolink = $promolink ?? '/reg';
$internal_reg_url = $internal_reg_url ?? '';
$partner_override_url = $partner_override_url ?? '';
$redirect_enabled = $redirect_enabled ?? 0;
$base_new_url = $base_new_url ?? '';
$base_second_url = $base_second_url ?? '';

$yandex_verification = $yandex_verification ?? '';
$yandex_metrika = $yandex_metrika ?? '';

$logo = $logo ?? '';
$favicon = $favicon ?? '';

$logoUrlDefault = $logoUrlDefault ?? '/img/logo.png';
$faviconUrlDefault = $faviconUrlDefault ?? '/favicon.ico';

$assetsPath = ''; // шаблонные img/styles лежат в корне build

$textsDir = $textsDir ?? (__DIR__ . '/subs/_default/texts');
$base_domain = $base_domain ?? $host;

$applyConfigArray = function(array $ret, string $currentHost) use (&$title, &$description, &$keywords, &$h1, &$pages, &$promolink, &$internal_reg_url, &$partner_override_url, &$redirect_enabled, &$base_new_url, &$base_second_url, &$logo, &$favicon, &$textsDir, &$yandex_verification, &$yandex_metrika, &$base_domain) {
    $siteCfg = is_array($ret['site'] ?? null) ? $ret['site'] : [];
    $pagesCfg = is_array($ret['pages'] ?? null) ? $ret['pages'] : [];

    if (array_key_exists('title', $siteCfg)) $title = (string)$siteCfg['title'];
    if (array_key_exists('description', $siteCfg)) $description = (string)$siteCfg['description'];
    if (array_key_exists('keywords', $siteCfg)) $keywords = (string)$siteCfg['keywords'];
    if (array_key_exists('h1', $siteCfg)) $h1 = (string)$siteCfg['h1'];

    if (array_key_exists('promolink', $siteCfg)) $promolink = (string)$siteCfg['promolink'];
    if (array_key_exists('internal_reg_url', $siteCfg)) $internal_reg_url = (string)$siteCfg['internal_reg_url'];
    if (array_key_exists('partner_override_url', $siteCfg)) $partner_override_url = (string)$siteCfg['partner_override_url'];
    if (array_key_exists('redirect_enabled', $siteCfg)) $redirect_enabled = (int)$siteCfg['redirect_enabled'];
    if (array_key_exists('base_new_url', $siteCfg)) $base_new_url = (string)$siteCfg['base_new_url'];
    if (array_key_exists('base_second_url', $siteCfg)) $base_second_url = (string)$siteCfg['base_second_url'];

    if (array_key_exists('logo', $siteCfg)) $logo = (string)$siteCfg['logo'];
    if (array_key_exists('favicon', $siteCfg)) $favicon = (string)$siteCfg['favicon'];

    if (array_key_exists('yandex_verification', $siteCfg)) $yandex_verification = (string)$siteCfg['yandex_verification'];
    if (array_key_exists('yandex_metrika', $siteCfg)) $yandex_metrika = (string)$siteCfg['yandex_metrika'];

    if (!empty($ret['texts_dir'])) {
        $textsDir = (string)$ret['texts_dir'];
    }

    if (!empty($pagesCfg)) {
        $pages = $pagesCfg;
    }

    $base_domain = $currentHost;
};

// 1) загружаем default config
$default404 = null;
$defaultRet = require __DIR__ . '/config.default.php';

if (is_array($defaultRet)) {
    $applyConfigArray($defaultRet, $host);
    $default404 = $pages['/404'] ?? null;
} else {
    $default404 = $pages['/404'] ?? null;
}

// 2) определяем ключ саба
$subKey = '_default';
$parts = explode('.', $host);
if (count($parts) >= 3) {
    $subKey = $parts[0];
}

$subDir = __DIR__ . '/subs/' . $subKey;

// 3) оверлей саба
if (is_dir($subDir) && file_exists($subDir . '/config.php')) {
    $subRet = require $subDir . '/config.php';
    if (is_array($subRet)) {
        $applyConfigArray($subRet, $host);
    }
}

// 4) гарантируем 404
if (empty($pages['/404']) && is_array($default404)) {
    $pages['/404'] = $default404;
}

// ------------------------------
// assets overlay (logo/favicon)
// ------------------------------
$subAssetsWeb = '/subs/' . $subKey . '/assets';
$subAssetsDir = $subDir . '/assets';

$makeSubAssetUrl = function($val) use ($subAssetsWeb) {
    $v = trim((string)$val);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v) || strpos($v, '/') === 0) return $v;
    $v = preg_replace('~^assets/~i', '', $v);
    return $subAssetsWeb . '/' . $v;
};

$makeSubAssetFile = function($val) use ($subAssetsDir) {
    $v = trim((string)$val);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v) || strpos($v, '/') === 0) return '';
    $v = preg_replace('~^assets/~i', '', $v);
    return $subAssetsDir . '/' . $v;
};

if (empty($logoUrl) && !empty($logo)) {
    $f = $makeSubAssetFile($logo);
    if ($f && is_file($f)) $logoUrl = $makeSubAssetUrl($logo);
}
if (empty($faviconUrl) && !empty($favicon)) {
    $f = $makeSubAssetFile($favicon);
    if ($f && is_file($f)) $faviconUrl = $makeSubAssetUrl($favicon);
}

if (empty($logoUrl)) {
    foreach (['logo.svg','logo.webp','logo.png','logo.jpg','logo.jpeg'] as $f) {
        if (is_file($subAssetsDir . '/' . $f)) {
            $logoUrl = $subAssetsWeb . '/' . $f;
            break;
        }
    }
    if (empty($logoUrl)) $logoUrl = $logoUrlDefault;
}

if (empty($faviconUrl)) {
    foreach (['favicon.ico','favicon.png','favicon.svg','favicon.webp'] as $f) {
        if (is_file($subAssetsDir . '/' . $f)) {
            $faviconUrl = $subAssetsWeb . '/' . $f;
            break;
        }
    }
    if (empty($faviconUrl)) $faviconUrl = $faviconUrlDefault;
}