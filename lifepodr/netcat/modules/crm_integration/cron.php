<?php
/**
 * CRM Integration Module - Cron Script
 *
 * Processes retry queue for failed CRM sends.
 * Called via Netcat cron system (Settings -> Cron Tasks).
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

require_once "../../require/cron_api.inc.php";

try {
    $results = nc_crm_integration_queue::process(100);

    $success = 0;
    $failed = 0;

    foreach ($results as $log_id => $result) {
        if ($result['success']) {
            $success++;
        } else {
            $failed++;
        }
    }

    if ($success > 0 || $failed > 0) {
        echo "Processed: $success success, $failed failed\n";
    }

    // Clean orphaned queue items periodically
    if (rand(0, 10) === 0) {
        $cleaned = nc_crm_integration_queue::clean_orphaned();
        if ($cleaned > 0) {
            echo "Cleaned $cleaned orphaned queue items\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
