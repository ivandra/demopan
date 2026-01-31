<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/guard.php';

// ------------------------------
// Роутинг страниц по URL
// ------------------------------
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($reqPath !== '/') {
    $reqPath = rtrim($reqPath, '/');
}

if (isset($pages[$reqPath]) && is_array($pages[$reqPath])) {
    $page = $pages[$reqPath];
} else {
    http_response_code(404);
    $page = $pages['/404'] ?? ($pages['/'] ?? []);
}

// ------------------------------
// Применяем мета (с фоллбеками на дефолт из config.php)
// ------------------------------
$title = $page['title'] ?? $title;
$description = $page['description'] ?? $description;
$keywords = $page['keywords'] ?? $keywords;

// H1: сначала из страницы, иначе дефолтный из config.php, иначе title
$h1 = $page['h1'] ?? $h1 ?? $title;

// Файл текста страницы
$pageTextFile = $page['text_file'] ?? (__DIR__ . '/text.php');

// OG/canonical URL
$pathForUrl = ($reqPath === '/') ? '/' : ($reqPath . '/');
$currentUrl = rtrim($domain, '/') . $pathForUrl;

// ------------------------------
// Рендер
// ------------------------------
require_once __DIR__ . '/header.php';
?>

<main class="container">
    <h1><?php echo htmlspecialchars($h1, ENT_QUOTES, 'UTF-8'); ?></h1>

    <?php
    if (is_string($pageTextFile) && $pageTextFile !== '' && file_exists($pageTextFile)) {
        require $pageTextFile;
    } else {
        echo "<p>Контент не найден.</p>";
    }
    ?>
</main>

<?php
require_once __DIR__ . '/footer.php';
