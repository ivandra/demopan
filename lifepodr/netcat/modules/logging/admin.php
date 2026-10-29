<?php

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)).( strstr(__FILE__, "/") ? "/" : "\\" );
require_once $NETCAT_FOLDER . 'vars.inc.php';

$module_keyword = "logging";
require_once $ADMIN_FOLDER . 'function.inc.php';
require_once nc_module_folder('logging') . 'nc_logging_admin.class.php';

// load modules env
if (!isset($MODULE_VARS)) $MODULE_VARS = $nc_core->modules->get_module_vars();

// UI config
require_once ($ADMIN_FOLDER."modules/ui.php");
// UI functional
$UI_CONFIG = new ui_config_module($module_keyword);

$Title1 = NETCAT_MODULES;
$Title2 = NETCAT_MODULE_LOGGING;

// default phase
if (!isset($phase)) $phase = 1;

// второй ряд вкладок (на сером фоне, как у разделов): журналы активности и согласий.
// Базовый ui_config_module даёт вкладку 'admin' (журнал активности) + 'settings';
// добавляем 'consent' (журнал согласий) и 'cookie' (выбор в окне cookie-согласия)
// перед настройками и переименовываем 'admin'.
$is_consent_phase = in_array((int)$phase, array(10, 11), true);
$is_cookie_phase = in_array((int)$phase, array(12, 13, 14), true);
$logging_tabs = array();
foreach ($UI_CONFIG->tabs as $logging_tab) {
    if ($logging_tab['id'] === 'admin') {
        $logging_tab['caption'] = NETCAT_MODULE_LOGGING_ACTIVITY_JOURNAL;
    }
    if ($logging_tab['id'] === 'settings') {
        $logging_tabs[] = array(
            'id'       => 'consent',
            'caption'  => NETCAT_MODULE_LOGGING_CONSENT_JOURNAL,
            'location' => 'module.logging.consent',
        );
        $logging_tabs[] = array(
            'id'       => 'cookie',
            'caption'  => NETCAT_MODULE_LOGGING_COOKIE_JOURNAL,
            'location' => 'module.logging.cookie',
        );
    }
    $logging_tabs[] = $logging_tab;
}
$UI_CONFIG->tabs = $logging_tabs;
if ($is_cookie_phase) {
    $UI_CONFIG->activeTab = 'cookie';
    $UI_CONFIG->locationHash = 'module.logging.cookie';
} else {
    $UI_CONFIG->activeTab = $is_consent_phase ? 'consent' : 'admin';
    $UI_CONFIG->locationHash = $is_consent_phase ? 'module.logging.consent' : 'module.logging';
}

// admin object
try {
    $nc_logging_admin = new nc_logging_admin();
} catch (Exception $e) {
    // header
    BeginHtml($Title2, $Title2, "http://".$DOC_DOMAIN."/settings/modules/logging/");
    // got error
    nc_print_status($e->getMessage(), "error");
    // footer
    EndHtml();
    // stop
    exit;
}

switch ($phase) {
    // step 1: show logging data
    case 1:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        // check permission
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        // show logging data
        $nc_logging_admin->show_logging_data(isset($curPos) ? $curPos : 0);
        break;

    // step 2: show clear dialog
    case 2:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        // check permission
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        // show dialog
        $nc_logging_admin->clear_logging_dialog();
        break;

    // step 3: clear logging data
    case 3:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        // check permission
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        // clear logging data
        $nc_logging_admin->clear_logging_data();
        // show logging data
        $nc_logging_admin->show_logging_data();
        break;

    // step 10: consent journal (152-FZ) list
    case 10:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        $nc_logging_admin->show_consent_data(isset($curPos) ? $curPos : 0);
        break;

    // step 11: consent journal record
    case 11:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        $nc_logging_admin->show_consent_record();
        break;

    // step 12: cookie consent choices list
    case 12:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        $nc_logging_admin->show_cookie_consent_data();
        break;

    // step 13: cookie consent choice record
    case 13:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        $nc_logging_admin->show_cookie_consent_record();
        break;

    // step 14: delete cookie consent choices older than N days (POST + CSRF token)
    case 14:
        BeginHtml($Title2, $Title1, "http://".$DOC_DOMAIN."/settings/modules/".$module_keyword."/");
        $perm->ExitIfNotAccess(array(NC_PERM_MODULE, 'logging'), 0, 0, 0, 0);
        if (nc_array_value($_SERVER, 'REQUEST_METHOD') !== 'POST' || !$nc_core->token->verify()) {
            nc_print_status(NETCAT_TOKEN_INVALID, 'error');
        } else {
            $nc_logging_admin->purge_cookie_consent_data();
        }
        $nc_logging_admin->show_cookie_consent_data();
        break;
}

EndHtml();
