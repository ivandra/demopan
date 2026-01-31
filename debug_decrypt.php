<?php
session_start();

define('BASE_PATH', __DIR__);

// чтобы видеть ошибки в логах
ini_set('display_errors', '1');
error_reporting(E_ALL);

$GLOBALS['APP_CONFIG'] = require BASE_PATH . '/config/app.php';

function config(string $key, $default = null) {
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    $parts = explode('.', $key);
    foreach ($parts as $p) {
        if (!is_array($cfg) || !array_key_exists($p, $cfg)) return $default;
        $cfg = $cfg[$p];
    }
    return $cfg;
}

// ВАЖНО: подключаем Crypto после config()
require BASE_PATH . '/app/Services/Crypto.php';

// если нужно — DB
require BASE_PATH . '/app/Core/DB.php';

// Пример теста: вытащить api_key_enc и расшифровать
$rows = DB::pdo()->query("SELECT id, username, api_user, api_key_enc FROM registrar_accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/plain; charset=utf-8');

foreach ($rows as $r) {
    $enc = (string)$r['api_key_enc'];
    echo "id={$r['id']} user={$r['username']} enc_len=" . strlen($enc) . " enc_head=" . substr($enc, 0, 30) . "\n";

    try {
        $dec = Crypto::decrypt($enc);
        echo "DECRYPT_OK: " . substr($dec, 0, 6) . "...\n";
    } catch (Throwable $e) {
        echo "DECRYPT_FAIL: " . $e->getMessage() . "\n";
    }

    echo "----\n";
}
