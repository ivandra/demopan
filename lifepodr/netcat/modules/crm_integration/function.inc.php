<?php
/**
 * CRM Integration Module - Entry Point
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

if (!class_exists('nc_System')) {
    die('Unable to load file.');
}

$nc_core = nc_core::get_object();

// Module folder path
define('NC_CRM_INTEGRATION_FOLDER', nc_module_folder('crm_integration'));

// Database table names
const NC_CRM_INTEGRATION_PROVIDER_TABLE = 'CrmIntegration_Provider';
const NC_CRM_INTEGRATION_PROVIDER_SETTING_TABLE = 'CrmIntegration_Provider_Setting';
const NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE = 'CrmIntegration_ProviderCatalogue';
const NC_CRM_INTEGRATION_SOURCE_TABLE = 'CrmIntegration_Source';
const NC_CRM_INTEGRATION_FIELD_MAPPING_TABLE = 'CrmIntegration_FieldMapping';
const NC_CRM_INTEGRATION_SEND_LOG_TABLE = 'CrmIntegration_SendLog';
const NC_CRM_INTEGRATION_SEND_QUEUE_TABLE = 'CrmIntegration_SendQueue';

// Autoload classes with nc_crm_ prefix
$nc_core->register_class_autoload_path(
    'nc_crm_integration_',
    NC_CRM_INTEGRATION_FOLDER . 'classes'
);

// Load main class
require_once NC_CRM_INTEGRATION_FOLDER . 'nc_crm_integration.class.php';

// Initialize module
nc_crm_integration::init();

// Register event listener for object creation
$nc_core->event->add_listener(
    nc_event::AFTER_OBJECT_CREATED,
    function ($site_id, $subdivision_id, $infoblock_id, $component_id, $object_id) {
        try {
            $crm = nc_crm_integration::get_instance($site_id);

            // Get providers configured for this source
            $provider_ids = $crm->get_providers_for_source($component_id, $infoblock_id);

            if (empty($provider_ids)) {
                return;
            }

            // Create lead from object
            $lead = nc_crm_integration_lead::from_object($object_id, $component_id, $site_id);
            $lead->set_subdivision_id($subdivision_id);
            $lead->set_infoblock_id($infoblock_id);

            // Send only to providers from sources configuration
            $crm->send_to_providers($lead, $provider_ids);
        } catch (Exception $e) {
            // Log error but don't break the request
            nc_crm_integration_logger::log_error($site_id, 0, 'event_handler', $e->getMessage());
        }
    }
);
