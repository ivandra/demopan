<?php
header('Content-Type: text/plain; charset=utf-8');

function rp($p) {
    $r = @realpath($p);
    return $r !== false ? $r : '(realpath:false) ' . $p;
}

echo "=== HUB PATH DIAGNOSTICS ===\n";
echo "PHP: " . PHP_VERSION . "\n\n";

echo "__FILE__: " . __FILE__ . "\n";
echo "__DIR__:  " . __DIR__ . "\n";
echo "getcwd(): " . getcwd() . "\n";
echo "rp('.') : " . rp('.') . "\n\n";

$keys = [
    'DOCUMENT_ROOT',
    'CONTEXT_DOCUMENT_ROOT',
    'SCRIPT_FILENAME',
    'SCRIPT_NAME',
    'PHP_SELF',
    'REQUEST_URI',
    'PWD',
    'HOME',
];
echo "=== \$_SERVER ===\n";
foreach ($keys as $k) {
    $v = $_SERVER[$k] ?? '';
    echo str_pad($k, 22) . ": " . ($v === '' ? '(empty)' : $v) . "\n";
}
echo "\n";

echo "=== realpath(\$_SERVER[...]) ===\n";
foreach (['DOCUMENT_ROOT','CONTEXT_DOCUMENT_ROOT','SCRIPT_FILENAME'] as $k) {
    $v = $_SERVER[$k] ?? '';
    if ($v !== '') {
        echo str_pad($k, 22) . ": " . rp($v) . "\n";
    }
}
echo "\n";

$env = getenv('PUBLIC_ROOT') ?: '';
echo "PUBLIC_ROOT env: " . ($env !== '' ? $env : '(empty)') . "\n";
if ($env !== '') echo "PUBLIC_ROOT realpath: " . rp($env) . "\n";
echo "\n";

/**
 * Имитация резолвера publicRoot()
 */
function resolve_public_root(): string {
    // 0) ENV
    $env = trim((string)getenv('PUBLIC_ROOT'));
    if ($env !== '' && is_dir($env)) return rtrim(rp($env), '/');

    // 1) DOCUMENT_ROOT
    $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($doc !== '' && is_dir($doc)) return rtrim(rp($doc), '/');

    // 2) SCRIPT_FILENAME -> dir
    $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== '' && is_file($script)) return rtrim(rp(dirname($script)), '/');

    // 3) fallback
    return rtrim(rp(__DIR__), '/');
}

$root = resolve_public_root();
echo "=== resolved public root ===\n";
echo "public_root = " . $root . "\n";
echo "public_root/public_html exists? " . (is_dir($root . '/public_html') ? 'YES' : 'NO') . "\n";
echo "public_root/storage exists?     " . (is_dir($root . '/storage') ? 'YES' : 'NO') . "\n";
echo "\n";

echo "=== typical targets ===\n";
echo "target storage: " . $root . "/storage/builds/site_10/subs/_default\n";
echo "target in public_html: " . $root . "/public_html/storage/builds/site_10/subs/_default\n";
echo "\n";

echo "=== END ===\n";
