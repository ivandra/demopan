<?php
// ===== BOOT GUARD: detect wrong storage creation =====
$APP_ROOT = realpath(__DIR__ . '/..');          // /public_html
$PROJECT_ROOT = realpath($APP_ROOT . '/..');    // /home/.../hub.seotop-one.ru
$STORAGE_ROOT = $APP_ROOT . '/storage';         // правильный
$WRONG_STORAGE = $PROJECT_ROOT . '/storage';    // неправильный

if (!defined('APP_ROOT')) define('APP_ROOT', $APP_ROOT);
if (!defined('STORAGE_ROOT')) define('STORAGE_ROOT', $STORAGE_ROOT);

@mkdir(STORAGE_ROOT . '/logs', 0775, true);

$wrongBefore = file_exists($WRONG_STORAGE);

register_shutdown_function(function () use ($wrongBefore, $WRONG_STORAGE) {
    // если в ходе запроса появился /home/.../storage
    if (!$wrongBefore && file_exists($WRONG_STORAGE)) {

        $out = "=== WRONG STORAGE APPEARED ===\n";
        $out .= "time=" . date('Y-m-d H:i:s') . "\n";
        $out .= "WRONG_STORAGE={$WRONG_STORAGE}\n";
        $out .= "cwd=" . getcwd() . "\n";
        $out .= "SCRIPT_FILENAME=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
        $out .= "DOCUMENT_ROOT=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";

        // что именно там появилось
        if (is_dir($WRONG_STORAGE)) {
            $items = @scandir($WRONG_STORAGE) ?: [];
            $out .= "WRONG_STORAGE CONTENT:\n";
            foreach ($items as $it) {
                if ($it === '.' || $it === '..') continue;
                $out .= " - {$it}\n";
            }
        } else {
            $out .= "WRONG_STORAGE is FILE (not dir)\n";
        }

        // какие файлы были реально подключены этим запросом
        $files = get_included_files();
        $out .= "\nINCLUDED FILES (" . count($files) . "):\n" . implode("\n", $files) . "\n";

        @file_put_contents(STORAGE_ROOT . '/logs/wrong_storage_' . date('Ymd_His') . '.log', $out);
    }
});

session_start();

/**
 * public/index.php:
 * __DIR__ = /home/s/.../public_html/public
 */

// 1) Жестко фиксируем корни
define('PUBLIC_ROOT', realpath(__DIR__));           // .../public_html/public
define('APP_ROOT',    realpath(PUBLIC_ROOT . '/..')); // .../public_html

// storage ДОЛЖЕН быть внутри public_html
define('STORAGE_ROOT', APP_ROOT . '/storage');

// 2) На всякий случай фиксируем рабочую директорию
// (чтобы любые mkdir('storage/...') тоже создавались в public_html)
@chdir(APP_ROOT);

// PROD-safe
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// 3) Гарантируем storage + logs
if (!is_dir(STORAGE_ROOT)) {
    @mkdir(STORAGE_ROOT, 0775, true);
}
if (!is_dir(STORAGE_ROOT . '/logs')) {
    @mkdir(STORAGE_ROOT . '/logs', 0775, true);
}

// общий php_error.log
ini_set('error_log', STORAGE_ROOT . '/php_error.log');

// 4) Простой логгер (всегда пишет, чтобы лог не был пустой)
function hub_log(string $msg, array $ctx = []): void
{
    $file = STORAGE_ROOT . '/logs/hub.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx) {
        $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// 5) Ловим фаталы и всегда логируем, если все падает
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

// 6) Логируем каждый запрос (теперь лог не будет пустым)
hub_log('REQUEST', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri'    => $_SERVER['REQUEST_URI'] ?? '',
    'cwd'    => getcwd(),
    'APP_ROOT' => APP_ROOT,
    'STORAGE_ROOT' => STORAGE_ROOT,
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? '',
]);

// 7) Загружаем конфиг
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

// 8) Подключаем ядро
require APP_ROOT . '/app/Core/DB.php';
require APP_ROOT . '/app/Core/Router.php';
require APP_ROOT . '/app/Core/Controller.php';

// Контроллеры
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

// Сервисы (если используются где-то в контроллерах)
require APP_ROOT . '/app/Services/Crypto.php';
require APP_ROOT . '/app/Services/NamecheapClient.php';
require APP_ROOT . '/app/Services/FastpanelClient.php';

// 9) Обертка для роутов, чтобы не ловить TypeError "callable"
// (у тебя это было из-за non-callable array handler)
function action($obj, string $method): callable {
    return function() use ($obj, $method) {
        return $obj->$method();
    };
}

// 10) Роутинг
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
$siteSubCfg = new SiteSubCfgController();

// Pages
$router->get('/', action($site, 'index'));
$router->get('/login', action($auth, 'loginForm'));
$router->post('/login', action($auth, 'login'));

$router->get('/sites/create', action($site, 'createForm'));
$router->post('/sites/create', action($site, 'store'));
$router->post('/sites/check-domain', action($site, 'checkDomain'));

$router->get('/sites/pages', action($site, 'pagesForm'));     // ?id=1
$router->post('/sites/pages', action($site, 'pagesUpdate'));  // ?id=1
$router->post('/sites/pages/text-new', action($site, 'pagesTextNew')); // ?id=1

$router->get('/sites/edit', action($site, 'editForm'));       // ?id=1
$router->post('/sites/edit', action($site, 'update'));        // ?id=1
$router->post('/sites/delete', action($site, 'delete'));      // ?id=1

$router->get('/sites/export', action($site, 'exportZip'));    // ?id=1

$router->get('/sites/texts', action($site, 'textsIndex'));    // ?id=1
$router->get('/sites/texts/edit', action($site, 'textsEdit')); // ?id=1&file=home.php
$router->post('/sites/texts/save', action($site, 'textsSave')); // ?id=1
$router->post('/sites/texts/new', action($site, 'textsNew'));   // ?id=1
$router->post('/sites/texts/delete', action($site, 'textsDelete')); // ?id=1

$router->get('/sites/files', action($site, 'filesIndex'));           // ?id=1
$router->get('/sites/files/edit', action($site, 'filesEdit'));       // ?id=1&file=header.php
$router->post('/sites/files/save', action($site, 'filesSave'));      // ?id=1
$router->post('/sites/files/restore', action($site, 'filesRestore')); // ?id=1

$router->post('/sites/build', action($site, 'build')); // ?id=1

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

// ===== DEBUG =====
$router->get('/debug/paths', function () {
    header('Content-Type: text/plain; charset=utf-8');

    echo "=== DEBUG PATHS ===\n";
    echo "APP_ROOT=" . APP_ROOT . "\n";
    echo "PUBLIC_ROOT=" . PUBLIC_ROOT . "\n";
    echo "STORAGE_ROOT=" . STORAGE_ROOT . "\n";
    echo "getcwd()=" . getcwd() . "\n";
    echo "DOCUMENT_ROOT=" . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
    echo "SCRIPT_FILENAME=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
    echo "error_log=" . ini_get('error_log') . "\n";
    echo "\n";

    $parent = realpath(APP_ROOT . '/..');
    $sibling = $parent ? ($parent . '/storage') : '';
    echo "PARENT=" . ($parent ?: '') . "\n";
    echo "SIBLING_STORAGE=" . $sibling . "\n";
    echo "Sibling exists? " . (is_dir($sibling) ? 'YES' : 'NO') . "\n";
    echo "Sibling writable? " . (is_writable($sibling) ? 'YES' : 'NO') . "\n";
});

$router->get('/debug/log', function () {
    header('Content-Type: text/plain; charset=utf-8');
    $file = STORAGE_ROOT . '/logs/hub.log';

    echo "LOG FILE: $file\n";
    if (!is_file($file)) {
        echo "log not found\n";
        return;
    }
    echo "\n=== CONTENT ===\n";
    readfile($file);
});

$router->dispatch();
