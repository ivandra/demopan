<?php
declare(strict_types=1);

// ---------- 0) Roots ----------
$publicRoot = realpath(__DIR__);
if ($publicRoot === false) {
    http_response_code(500);
    echo "PUBLIC_ROOT resolve failed";
    exit;
}

$appRoot = realpath($publicRoot . '/..');
if ($appRoot === false) {
    http_response_code(500);
    echo "APP_ROOT resolve failed";
    exit;
}

if (!defined('PUBLIC_ROOT')) define('PUBLIC_ROOT', $publicRoot);
if (!defined('APP_ROOT')) define('APP_ROOT', $appRoot);

// ---------- 1) Paths + storage ----------
require_once APP_ROOT . '/app/Core/Paths.php';
Paths::ensureStorageTree();
Paths::ensureDir(Paths::storage('logs'));

// На всякий случай фиксируем рабочую директорию на APP_ROOT,
// чтобы случайные mkdir('storage/...') создавались внутри public_html
@chdir(APP_ROOT);

// ---------- 2) PROD-safe error logging ----------
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Все php ошибки в /public_html/storage/php_error.log
ini_set('error_log', Paths::storage('php_error.log'));

// ---------- 3) Simple logger ----------
function hub_log(string $msg, array $ctx = []): void
{
    $file = Paths::storage('logs/hub.log');
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx) {
        $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// Ловим фаталы и пишем в hub.log
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        hub_log('FATAL', [
            'type' => $e['type'],
            'msg'  => $e['message'],
            'file' => $e['file'],
            'line' => $e['line'],
        ]);
    }
});

// Логируем каждый запрос (чтобы видеть POST/GET)
hub_log('REQUEST', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri'    => $_SERVER['REQUEST_URI'] ?? '',
    'cwd'    => getcwd(),
    'APP_ROOT' => APP_ROOT,
    'STORAGE'  => Paths::storage(''),
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? '',
]);

// ---------- 4) Session ----------
session_start();

// ---------- 5) Config ----------
$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config/app.php';

function config(string $key, $default = null) {
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    $parts = explode('.', $key);
    foreach ($parts as $p) {
        if (!is_array($cfg) || !array_key_exists($p, $cfg)) return $default;
        $cfg = $cfg[$p];
    }
    return $cfg;
}

// ---------- 6) Core ----------
require APP_ROOT . '/app/Core/DB.php';
require APP_ROOT . '/app/Core/Router.php';
require APP_ROOT . '/app/Core/Controller.php';

// ---------- 7) Services (ВАЖНО: без автозагрузчика надо require) ----------
require APP_ROOT . '/app/Services/Crypto.php';
require APP_ROOT . '/app/Services/NamecheapClient.php';
require APP_ROOT . '/app/Services/FastpanelClient.php';

require APP_ROOT . '/app/Services/MultiSiteConfigWriter.php';
require APP_ROOT . '/app/Services/SubdomainProvisioner.php';
require APP_ROOT . '/app/Services/ZipBuilder.php';
require APP_ROOT . '/app/Services/ZipService.php';

// ---------- 8) Controllers ----------
require APP_ROOT . '/app/Controllers/AuthController.php';
require APP_ROOT . '/app/Controllers/SiteController.php';
require APP_ROOT . '/app/Controllers/FastpanelServerController.php';
require APP_ROOT . '/app/Controllers/DeployController.php';
require APP_ROOT . '/app/Controllers/DomainsController.php';
require APP_ROOT . '/app/Controllers/RegistrarAccountsController.php';
require APP_ROOT . '/app/Controllers/RegistrarContactsController.php';
require APP_ROOT . '/app/Controllers/SubdomainsController.php';
require APP_ROOT . '/app/Controllers/SiteSubdomainsController.php';
require APP_ROOT . '/app/Controllers/SiteSubCfgController.php';

// ---------- 9) Route wrapper ----------
function action($obj, string $method): callable {
    return function() use ($obj, $method) {
        return $obj->$method();
    };
}

// ---------- 10) Routing ----------
$router = new Router();

$fp     = new FastpanelServerController();
$deploy = new DeployController();

$auth = new AuthController();
$site = new SiteController();

$domains           = new DomainsController();
$registrarAccounts = new RegistrarAccountsController();
$registrarContacts = new RegistrarContactsController();

$subdomains = new SubdomainsController();
$siteSubs   = new SiteSubdomainsController();
$siteSubCfg = new SiteSubCfgController();

// Pages
$router->get('/', action($site, 'index'));
$router->get('/login', action($auth, 'loginForm'));
$router->post('/login', action($auth, 'login'));

$router->get('/sites/create', action($site, 'createForm'));
$router->post('/sites/create', action($site, 'store'));
$router->post('/sites/check-domain', action($site, 'checkDomain'));

$router->get('/sites/pages', action($site, 'pagesForm'));
$router->post('/sites/pages', action($site, 'pagesUpdate'));
$router->post('/sites/pages/text-new', action($site, 'pagesTextNew'));

$router->get('/sites/edit', action($site, 'editForm'));
$router->post('/sites/edit', action($site, 'update'));
$router->post('/sites/delete', action($site, 'delete'));

$router->get('/sites/export', action($site, 'exportZip'));

$router->get('/sites/texts', action($site, 'textsIndex'));
$router->get('/sites/texts/edit', action($site, 'textsEdit'));
$router->post('/sites/texts/save', action($site, 'textsSave'));
$router->post('/sites/texts/new', action($site, 'textsNew'));
$router->post('/sites/texts/delete', action($site, 'textsDelete'));

$router->get('/sites/files', action($site, 'filesIndex'));
$router->get('/sites/files/edit', action($site, 'filesEdit'));
$router->post('/sites/files/save', action($site, 'filesSave'));
$router->post('/sites/files/restore', action($site, 'filesRestore'));

$router->post('/sites/build', action($site, 'build'));

// Servers
$router->get('/servers', action($fp, 'index'));
$router->get('/servers/create', action($fp, 'createForm'));
$router->post('/servers/create', action($fp, 'store'));
$router->get('/servers/edit', action($fp, 'editForm'));
$router->post('/servers/edit', action($fp, 'update'));
$router->post('/servers/delete', action($fp, 'delete'));
$router->get('/servers/test', action($fp, 'test'));

// Deploy
$router->get('/deploy', action($deploy, 'form'));
$router->get('/deploy/create-site', action($deploy, 'createSite'));
$router->post('/deploy/update-files', action($deploy, 'updateFiles'));
$router->post('/deploy/reset', action($deploy, 'reset'));
$router->get('/deploy/report', action($deploy, 'report'));
$router->post('/deploy/issue-ssl', action($deploy, 'issueSslSelfSigned'));

// Domains
$router->get('/domains', action($domains, 'form'));
$router->post('/domains/check', action($domains, 'check'));
$router->post('/domains/purchase-dns', action($domains, 'purchaseAndDns'));

// Registrar accounts
$router->get('/registrar/accounts', action($registrarAccounts, 'index'));
$router->get('/registrar/accounts/create', action($registrarAccounts, 'createForm'));
$router->post('/registrar/accounts/create', action($registrarAccounts, 'store'));
$router->get('/registrar/accounts/edit', action($registrarAccounts, 'editForm'));
$router->post('/registrar/accounts/edit', action($registrarAccounts, 'update'));
$router->post('/registrar/accounts/delete', action($registrarAccounts, 'delete'));

// Registrar contacts
$router->get('/registrar/contacts', action($registrarContacts, 'index'));
$router->get('/registrar/contacts/create', action($registrarContacts, 'createForm'));
$router->post('/registrar/contacts/create', action($registrarContacts, 'store'));
$router->get('/registrar/contacts/edit', action($registrarContacts, 'editForm'));
$router->post('/registrar/contacts/edit', action($registrarContacts, 'update'));
$router->post('/registrar/contacts/delete', action($registrarContacts, 'delete'));

// Global subdomains catalog
$router->get('/subdomains', action($subdomains, 'index'));
$router->post('/subdomains/bulk-add', action($subdomains, 'bulkAdd'));
$router->post('/subdomains/delete', action($subdomains, 'delete'));
$router->post('/subdomains/toggle', action($subdomains, 'toggle'));

// Per-site subdomains
$router->get('/sites/subdomains', action($siteSubs, 'form'));
$router->post('/sites/subdomains/apply', action($siteSubs, 'applyBatch'));
$router->post('/sites/subdomains/toggle', action($siteSubs, 'toggleOne'));
$router->post('/sites/subdomains/delete', action($siteSubs, 'deleteOne'));
$router->post('/sites/subdomains/delete-catalog', action($siteSubs, 'deleteCatalog'));
$router->post('/sites/subdomains/detect-registrar', action($siteSubs, 'detectRegistrar'));
$router->post('/sites/subdomains/set-registrar', action($siteSubs, 'setRegistrar'));
$router->post('/sites/subdomains/update-ip', action($siteSubs, 'updateIp'));
$router->post('/sites/subdomains/delete-catalog-dns', action($siteSubs, 'deleteCatalogDns'));

// Subcfg
$router->get('/sites/subcfg', action($siteSubCfg, 'form'));
$router->post('/sites/subcfg/save', action($siteSubCfg, 'save'));
$router->post('/sites/subcfg/create', action($siteSubCfg, 'create'));
$router->post('/sites/subcfg/delete', action($siteSubCfg, 'delete'));
$router->post('/sites/subcfg/regenAll', action($siteSubCfg, 'regenAll'));

// Debug
$router->get('/debug/log', function () {
    header('Content-Type: text/plain; charset=utf-8');
    $file = Paths::storage('logs/hub.log');
    echo "LOG FILE: $file\n\n";
    if (!is_file($file)) {
        echo "log not found\n";
        return;
    }
    readfile($file);
});

$router->get('/debug/paths', function () {
    header('Content-Type: text/plain; charset=utf-8');

    echo "APP_ROOT=" . (defined('APP_ROOT') ? APP_ROOT : '') . "\n";
    echo "PUBLIC_ROOT=" . (defined('PUBLIC_ROOT') ? PUBLIC_ROOT : '') . "\n";
    echo "STORAGE_ROOT=" . (defined('STORAGE_ROOT') ? STORAGE_ROOT : '') . "\n";
    echo "cwd=" . getcwd() . "\n";
    echo "error_log=" . ini_get('error_log') . "\n";
});


$router->dispatch();
