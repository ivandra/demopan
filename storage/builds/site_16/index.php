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

$title = $page['title'] ?? $title;
$description = $page['description'] ?? $description;
$keywords = $page['keywords'] ?? $keywords;
$h1 = $page['h1'] ?? $h1;

$pathForUrl = ($reqPath === '/') ? '/' : ($reqPath . '/');
$currentUrl = rtrim($domain, '/') . $pathForUrl;

$textFile = $page['text_file'] ?? '';
if (!$textFile || !is_file($textFile)) {
    http_response_code(404);
    $textFile = $pages['/404']['text_file'] ?? '';
}

require_once __DIR__ . '/header.php';

if ($textFile && is_file($textFile)) {
    require $textFile;
}

require_once __DIR__ . '/footer.php';
