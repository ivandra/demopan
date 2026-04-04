<?php
require_once __DIR__ . '/guard.php';

$reqPath = normalizePath($_SERVER['REQUEST_URI'] ?? '/');

$page = $pages[$reqPath] ?? null;

if (!$page || !is_array($page)) {
    http_response_code(404);
    $page = $pages['/404'] ?? null;
}

if (!$page || !is_array($page)) {
    http_response_code(404);
    exit;
}

$resolveInherited = function(array $page, string $key, string $fallback): string {
    if (!array_key_exists($key, $page)) {
        return $fallback;
    }

    $v = $page[$key];

    if ($v === '$inherit' || $v === null || $v === '') {
        return $fallback;
    }

    return (string)$v;
};

$title = $resolveInherited($page, 'title', (string)$title);
$description = $resolveInherited($page, 'description', (string)$description);
$keywords = $resolveInherited($page, 'keywords', (string)$keywords);
$h1 = $resolveInherited($page, 'h1', (string)$h1);

$pathForUrl = ($reqPath === '/') ? '/' : ($reqPath . '/');
$currentUrl = rtrim($domain, '/') . $pathForUrl;

$textFile = (string)($page['text_file'] ?? '');

if ($textFile !== '' && !preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $textFile)) {
    $textsBase = rtrim((string)($textsDir ?? ''), '/\\');
    if ($textsBase !== '') {
        $textFile = $textsBase . '/' . ltrim($textFile, '/\\');
    }
}

if ($textFile === '' || !is_file($textFile)) {
    http_response_code(404);

    $fallbackFile = (string)($pages['/404']['text_file'] ?? '');
    if ($fallbackFile !== '' && !preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $fallbackFile)) {
        $textsBase = rtrim((string)($textsDir ?? ''), '/\\');
        if ($textsBase !== '') {
            $fallbackFile = $textsBase . '/' . ltrim($fallbackFile, '/\\');
        }
    }

    $textFile = $fallbackFile;
}

require_once __DIR__ . '/header.php';

if ($textFile && is_file($textFile)) {
    require $textFile;
}

require_once __DIR__ . '/footer.php';