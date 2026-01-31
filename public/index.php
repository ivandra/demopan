<?php
session_start();

define('BASE_PATH', dirname(__DIR__));

// PROD-safe: не выводим ошибки в HTML
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Важно: storage должен существовать и быть writable
if (!is_dir(BASE_PATH . '/storage')) {
    @mkdir(BASE_PATH . '/storage', 0775, true);
}
ini_set('error_log', BASE_PATH . '/storage/php_error.log');

error_reporting(E_ALL);

// Не обязательно, но можно
ob_start();

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

require __DIR__ . '/../app/Core/DB.php';
require __DIR__ . '/../app/Core/Router.php';
require __DIR__ . '/../app/Core/Controller.php';

require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/SiteController.php';
require __DIR__ . '/../app/Controllers/FastpanelServerController.php';
require __DIR__ . '/../app/Controllers/DeployController.php';
require_once __DIR__ . '/../app/Controllers/DomainsController.php';
require __DIR__ . '/../app/Controllers/RegistrarAccountsController.php';
require __DIR__ . '/../app/Controllers/RegistrarContactsController.php';
require __DIR__ . '/../app/Controllers/SubdomainsController.php';
require __DIR__ . '/../app/Controllers/SiteSubdomainsController.php';

require __DIR__ . '/../app/Services/Crypto.php';
require __DIR__ . '/../app/Services/NamecheapClient.php';
require __DIR__ . '/../app/Services/FastpanelClient.php';


$router = new Router();
$fp = new FastpanelServerController();
$deploy = new DeployController();

$auth = new AuthController();
$site = new SiteController();
$domains = new DomainsController();
$registrarAccounts = new RegistrarAccountsController();
$registrarContacts = new RegistrarContactsController();

$subdomains = new SubdomainsController();
$siteSubs   = new SiteSubdomainsController();




$router->get('/', [$site, 'index']);
$router->get('/login', [$auth, 'loginForm']);
$router->get('/sites/create', [$site, 'createForm']);
$router->post('/sites/check-domain', [$site, 'checkDomain']);
$router->get('/sites/pages', [$site, 'pagesForm']);   // ?id=1
$router->get('/sites/edit', [$site, 'editForm']);     // ?id=1
$router->get('/sites/export', [$site, 'exportZip']); // ?id=1
$router->get('/sites/texts', [$site, 'textsIndex']);      // ?id=1
$router->get('/sites/texts/edit', [$site, 'textsEdit']);  // ?id=1&file=home.php
$router->post('/login', [$auth, 'login']);
$router->post('/sites/create', [$site, 'store']);
$router->post('/sites/edit', [$site, 'update']);      // ?id=1
$router->post('/sites/pages', [$site, 'pagesUpdate']); // ?id=1
$router->post('/sites/delete', [$site, 'delete']); // ?id=1
$router->post('/sites/pages/text-new', [$site, 'pagesTextNew']); // ?id=1
$router->post('/sites/texts/save', [$site, 'textsSave']); // ?id=1
$router->post('/sites/texts/new', [$site, 'textsNew']);   // ?id=1
$router->post('/sites/texts/delete', [$site, 'textsDelete']); // ?id=1

$router->get('/sites/files', [$site, 'filesIndex']);          // ?id=1
$router->get('/sites/files/edit', [$site, 'filesEdit']);      // ?id=1&file=header.php
$router->post('/sites/files/save', [$site, 'filesSave']);     // ?id=1
$router->post('/sites/files/restore', [$site, 'filesRestore']); // ?id=1
$router->post('/sites/build', [$site, 'build']); // ?id=1

$router->get('/servers',        [$fp, 'index']);
$router->get('/servers/create', [$fp, 'createForm']);
$router->post('/servers/create',[$fp, 'store']);
$router->get('/servers/edit',   [$fp, 'editForm']);
$router->post('/servers/edit',  [$fp, 'update']);
$router->post('/servers/delete',[$fp, 'delete']);
$router->get('/servers/test',   [$fp, 'test']);


$router->get('/deploy', [$deploy, 'form']);
$router->get('/deploy/create-site', [$deploy, 'createSite']);
$router->post('/deploy/update-files', [$deploy, 'updateFiles']);
$router->post('/deploy/reset', [$deploy, 'reset']);
$router->get('/deploy/report', [$deploy, 'report']);


$router->post('/deploy/issue-ssl', [$deploy, 'issueSslSelfSigned']); // ?id=1

// Domains
// Domains
$router->get('/domains', [$domains, 'form']);
$router->post('/domains/check', [$domains, 'check']);
$router->post('/domains/purchase-dns', [$domains, 'purchaseAndDns']);

// Registrar accounts
$router->get('/registrar/accounts',        [$registrarAccounts, 'index']);
$router->get('/registrar/accounts/create', [$registrarAccounts, 'createForm']);
$router->post('/registrar/accounts/create',[$registrarAccounts, 'store']);
$router->get('/registrar/accounts/edit',   [$registrarAccounts, 'editForm']);
$router->post('/registrar/accounts/edit',  [$registrarAccounts, 'update']);
$router->post('/registrar/accounts/delete',[$registrarAccounts, 'delete']);

// Registrar contacts
$router->get('/registrar/contacts',        [$registrarContacts, 'index']);
$router->get('/registrar/contacts/create', [$registrarContacts, 'createForm']);
$router->post('/registrar/contacts/create',[$registrarContacts, 'store']);
$router->get('/registrar/contacts/edit',   [$registrarContacts, 'editForm']);
$router->post('/registrar/contacts/edit',  [$registrarContacts, 'update']);
$router->post('/registrar/contacts/delete',[$registrarContacts, 'delete']);


// Global subdomains catalog
$router->get('/subdomains', [$subdomains, 'index']);
$router->post('/subdomains/bulk-add', [$subdomains, 'bulkAdd']);
$router->post('/subdomains/delete', [$subdomains, 'delete']);   // ?id=
$router->post('/subdomains/toggle', [$subdomains, 'toggle']);   // ?id=

// Per-site subdomains
$router->get('/sites/subdomains', [$siteSubs, 'form']);                 // ?id=SITE_ID
$router->post('/sites/subdomains/apply', [$siteSubs, 'applyBatch']);    // ?id=SITE_ID
$router->post('/sites/subdomains/toggle', [$siteSubs, 'toggleOne']);    // ?sub_id=
$router->post('/sites/subdomains/delete', [$siteSubs, 'deleteOne']);    // ?sub_id=
$router->post('/sites/subdomains/delete-catalog', [$siteSubs, 'deleteCatalog']);
$router->post('/sites/subdomains/detect-registrar', [$siteSubs, 'detectRegistrar']); // ?id=SITE_ID
$router->post('/sites/subdomains/set-registrar', [$siteSubs, 'setRegistrar']); // ?id=SITE_ID
$router->post('/sites/subdomains/update-ip', [$siteSubs, 'updateIp']);          // ?id=SITE_ID
$router->post('/sites/subdomains/delete-catalog-dns', [$siteSubs, 'deleteCatalogDns']); // ?id=SITE_ID (опционально)





$router->get('/debug/ip', function () {
    header('Content-Type: text/plain; charset=utf-8');

    echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? '') . PHP_EOL;
    echo "SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? '') . PHP_EOL;

    // Публичный исходящий IP (часто именно его нужно whitelist-ить)
    $ip = @file_get_contents('https://api.ipify.org');
    echo "OUTBOUND_PUBLIC_IP: " . trim((string)$ip) . PHP_EOL;
});

$router->get('/debug/nc-accounts', function () {
    header('Content-Type: text/plain; charset=utf-8');

    $db = DB::pdo()->query("SELECT DATABASE()")->fetchColumn();
    echo "DB=" . $db . PHP_EOL;

    $rows = DB::pdo()->query("
        SELECT id, is_sandbox, username, api_user, LENGTH(api_key_enc) AS len, LEFT(api_key_enc, 30) AS head
        FROM registrar_accounts
        WHERE provider='namecheap'
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    print_r($rows);
});



$router->dispatch();
