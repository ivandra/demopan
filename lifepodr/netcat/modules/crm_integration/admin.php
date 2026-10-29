<?php
/**
 * CRM Integration Module - Admin Interface
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)).( strstr(__FILE__, "/") ? "/" : "\\" );
require_once $NETCAT_FOLDER . 'vars.inc.php';
require $ADMIN_FOLDER . 'function.inc.php';
require_once $ADMIN_FOLDER . 'modules/ui.php';

global $perm, $UI_CONFIG;

$nc_core = nc_core::get_object();
$db = $nc_core->db;
$input = $nc_core->input;

// Check permissions
$perm->ExitIfNotAccess([NC_PERM_MODULE, 'crm_integration'], 0, 0, 0, 1);

// Get parameters
$phase = $input->fetch_get_post('phase') ?: 'index';
$site_id = (int)$input->fetch_get_post('site_id');
$provider_id = (int)$input->fetch_get_post('provider_id');

// Determine site_id (standard mechanism)
$cookie_name = 'nc_admin_site_id';
$cookie_id = $nc_core->input->fetch_cookie($cookie_name);
$new_id = $nc_core->input->fetch_post_get('site_id');

if (strlen($new_id)) {
    $site_id = (int)$new_id;
    $nc_core->cookie->set($cookie_name, $site_id, 0);
} elseif (strlen($cookie_id)) {
    $site_id = (int)$cookie_id;
} else {
    $site_id = (int)$nc_core->catalogue->get_current('Catalogue_ID');
}

// Fallback to first available site
if (!$site_id) {
    $all_sites = $nc_core->catalogue->get_all();
    if ($all_sites) {
        $site_id = (int)key($all_sites);
    }
}

// UI Config
require_once nc_module_folder('crm_integration') . 'ui_config.php';
$UI_CONFIG = new ui_config_module_crm_integration($phase, $site_id, $provider_id);

// Process actions
$action = $input->fetch_post('action');
$message = '';
$error = '';

if ($action && $nc_core->token->verify()) {
    switch ($action) {
        case 'save_provider_settings':
            $provider = nc_crm_integration_factory::create($provider_id, $site_id);
            if ($provider) {
                $settings = $input->fetch_post('settings');
                $settings_list = $provider->get_settings_list();
                foreach ($settings_list as $name => $config) {
                    $value = isset($settings[$name]) ? $settings[$name] : '';
                    $provider->save_setting($name, $value);
                }

                // Enable/disable provider
                $enabled = (int)$input->fetch_post('provider_enabled');
                if ($enabled) {
                    nc_crm_integration_factory::enable_provider($provider_id, $site_id);
                } else {
                    nc_crm_integration_factory::disable_provider($provider_id, $site_id);
                }

                // Save field mappings
                $mappings = $input->fetch_post('mapping');
                if (is_array($mappings)) {
                    // Delete existing mappings
                    $db->query("DELETE FROM `" . NC_CRM_INTEGRATION_FIELD_MAPPING_TABLE . "` WHERE `Catalogue_ID` = $site_id AND `Provider_ID` = $provider_id");

                    // Insert new mappings
                    foreach ($mappings as $mapping) {
                        $netcat_field = $db->escape(trim($mapping['netcat_field'] ?? ''));
                        $crm_field = $db->escape(trim($mapping['crm_field'] ?? ''));
                        $transform = $db->escape(trim($mapping['transform'] ?? ''));

                        if ($netcat_field && $crm_field) {
                            $db->query("INSERT INTO `" . NC_CRM_INTEGRATION_FIELD_MAPPING_TABLE . "`
                                (`Catalogue_ID`, `Provider_ID`, `Netcat_Field`, `Crm_Field`, `Transform`)
                                VALUES ($site_id, $provider_id, '$netcat_field', '$crm_field', '$transform')");
                        }
                    }
                }

                $message = NETCAT_MODULE_CRM_INTEGRATION_SAVED;
            }
            break;

        case 'toggle_provider':
            $enable = (int)$input->fetch_post('enable');
            if ($enable) {
                nc_crm_integration_factory::enable_provider($provider_id, $site_id);
            } else {
                nc_crm_integration_factory::disable_provider($provider_id, $site_id);
            }
            break;

        case 'test_connection':
            $provider = nc_crm_integration_factory::create($provider_id, $site_id);
            if ($provider) {
                if ($provider->test_connection()) {
                    $message = NETCAT_MODULE_CRM_INTEGRATION_CONNECTION_SUCCESS;
                } else {
                    $error = NETCAT_MODULE_CRM_INTEGRATION_CONNECTION_FAILED . ': ' . implode(', ', $provider->get_errors());
                }
            }
            break;

        case 'save_source':
            $source_type = $input->fetch_post('source_type');
            $source_object_id = (int)$input->fetch_post('source_object_id');
            $source_provider_id = (int)$input->fetch_post('source_provider_id');

            if ($source_type && $source_provider_id) {
                $db->query("
                    INSERT INTO `" . NC_CRM_INTEGRATION_SOURCE_TABLE . "`
                        (`Catalogue_ID`, `Provider_ID`, `Source_Type`, `Source_Object_ID`, `Enabled`)
                    VALUES
                        ($site_id, $source_provider_id, '" . $db->escape($source_type) . "',
                         " . ($source_object_id ?: 'NULL') . ", 1)
                ");
                $message = NETCAT_MODULE_CRM_INTEGRATION_SAVED;
            }
            break;

        case 'delete_source':
            $source_id = (int)$input->fetch_post('source_id');
            if ($source_id) {
                $db->query("DELETE FROM `" . NC_CRM_INTEGRATION_SOURCE_TABLE . "` WHERE `Source_ID` = $source_id AND `Catalogue_ID` = $site_id");
                $message = NETCAT_MODULE_CRM_INTEGRATION_DELETED;
            }
            break;

        case 'toggle_source':
            $source_id = (int)$input->fetch_post('source_id');
            $enable = (int)$input->fetch_post('enable');
            if ($source_id) {
                $db->query("UPDATE `" . NC_CRM_INTEGRATION_SOURCE_TABLE . "` SET `Enabled` = $enable WHERE `Source_ID` = $source_id AND `Catalogue_ID` = $site_id");
            }
            break;

        case 'retry_send':
            $log_id = (int)$input->fetch_post('log_id');
            if ($log_id) {
                $log = nc_crm_integration_logger::get_log($log_id);
                if ($log && $log['Catalogue_ID'] == $site_id) {
                    // Reset status and add to queue
                    nc_crm_integration_logger::update_log($log_id, [
                        'Status' => nc_crm_integration_provider::STATUS_RETRY,
                        'Retry_Count' => 0,
                    ]);
                    nc_crm_integration_queue::add($log_id, date('Y-m-d H:i:s'));
                    $message = 'Добавлено в очередь отправки';
                }
            }
            break;
    }
}

// Output interface
BeginHtml(NETCAT_MODULE_CRM_INTEGRATION, '', '');

// Show messages
if ($message) {
    echo nc_core::get_object()->ui->alert->success($message);
}
if ($error) {
    echo nc_core::get_object()->ui->alert->error($error);
}

// Load view
switch ($phase) {
    case 'provider_settings':
        include nc_module_folder('crm_integration') . 'admin/views/provider_settings.view.php';
        break;

    case 'sources':
        include nc_module_folder('crm_integration') . 'admin/views/sources.view.php';
        break;

    case 'log':
        include nc_module_folder('crm_integration') . 'admin/views/log.view.php';
        break;

    default:
        include nc_module_folder('crm_integration') . 'admin/views/index.view.php';
        break;
}

EndHtml();
