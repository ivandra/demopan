<?php

$source = file_get_contents($argv[1]);
$start = strpos($source, 'function li_payload_file_check(');
$end = strpos($source, 'function li_payload_check(', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Production verifier not found');
}
function li_fail($message)
{
    throw new RuntimeException($message);
}
function li_diagnostic($kind, array $error, array $trace = [])
{
    $GLOBALS['recorded'][] = $error;
}
eval(substr($source, $start, $end - $start));
$design = sys_get_temp_dir() . '/demopan-upload-' . bin2hex(random_bytes(8));
mkdir($design, 0700);
$checks = [];
function check($name, $condition)
{
    global $checks;
    $checks[] = ['name' => $name, 'passed' => (bool) $condition];
    if (!$condition) {
        throw new RuntimeException($name);
    }
}
function rejected($name, $hash, $message)
{
    try {
        li_payload_file_check($name, $hash);
    } catch (RuntimeException $error) {
        return strpos($error->getMessage(), $message) !== false;
    }
    return false;
}
try {
    $text = "Require all denied\nDeny from all\n";
    $hash = hash('sha256', $text);
    file_put_contents($design . '/.htaccess', $text);
    check('exact LF file accepted', li_payload_file_check('.htaccess', $hash) === false);
    $crlf = str_replace("\n", "\r\n", $text);
    file_put_contents($design . '/.htaccess', $crlf);
    check('CRLF text accepted against original SHA', li_payload_file_check('.htaccess', $hash) === true);
    check('preflight does not rewrite uploaded bytes', file_get_contents($design . '/.htaccess') === $crlf);
    file_put_contents($design . '/.htaccess', "Require all granted\r\nDeny from all\r\n");
    check('changed access directive rejected despite CRLF', rejected('.htaccess', $hash, 'не совпадает'));
    check('mismatch diagnostic has actual SHA and size', isset($recorded[0]['actual_sha256'], $recorded[0]['bytes']) && $recorded[0]['actual_sha256'] !== $hash);
    unlink($design . '/.htaccess');
    check('missing hidden file reported precisely', rejected('.htaccess', $hash, 'PHP не видит файл'));
    $binary = "\x89PNG\r\n\x1a\nDATA\x00\xff\n";
    file_put_contents($design . '/image.png', $binary);
    check('unchanged binary accepted', li_payload_file_check('image.png', hash('sha256', $binary)) === false);
    file_put_contents($design . '/image.png', str_replace("\n", "\r\n", $binary));
    check('changed binary never normalized', rejected('image.png', hash('sha256', $binary), 'не совпадает'));
    file_put_contents($design . '/unknown.dat', $crlf);
    check('unknown extension kept strict', rejected('unknown.dat', $hash, 'не совпадает'));
    file_put_contents($design . '/.htaccess', $text);
    chmod($design . '/.htaccess', 0000);
    clearstatcache(true, $design . '/.htaccess');
    check('unreadable file reported precisely', rejected('.htaccess', $hash, 'недоступен для чтения'));
    echo json_encode(['php' => PHP_VERSION, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
} finally {
    foreach (glob($design . '/*') as $file) {
        unlink($file);
    }
    if (is_file($design . '/.htaccess')) {
        chmod($design . '/.htaccess', 0600);
        unlink($design . '/.htaccess');
    }
    rmdir($design);
}
