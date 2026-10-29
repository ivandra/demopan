<?php
/**
 * CRM Integration Module - Retry Queue
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_queue {

    /**
     * Add item to queue
     *
     * @param int $log_id
     * @param string $scheduled_at DateTime string
     * @return int|null Queue ID
     */
    public static function add($log_id, $scheduled_at) {
        $db = nc_core::get_object()->db;

        // Check if already in queue
        $exists = $db->get_var(
            "SELECT Queue_ID FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`
             WHERE `Log_ID` = " . (int)$log_id
        );

        if ($exists) {
            // Update scheduled time
            $db->query("
                UPDATE `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`
                SET `Scheduled_At` = '" . $db->escape($scheduled_at) . "'
                WHERE `Log_ID` = " . (int)$log_id
            );
            return (int)$exists;
        }

        $db->query("
            INSERT INTO `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`
                (`Log_ID`, `Scheduled_At`)
            VALUES
                (" . (int)$log_id . ", '" . $db->escape($scheduled_at) . "')
        ");

        return $db->insert_id ?: null;
    }

    /**
     * Remove item from queue
     *
     * @param int $queue_id
     */
    public static function remove($queue_id) {
        $db = nc_core::get_object()->db;

        $db->query("DELETE FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "` WHERE `Queue_ID` = " . (int)$queue_id);
    }

    /**
     * Remove item by log ID
     *
     * @param int $log_id
     */
    public static function remove_by_log_id($log_id) {
        $db = nc_core::get_object()->db;

        $db->query("DELETE FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "` WHERE `Log_ID` = " . (int)$log_id);
    }

    /**
     * Get pending items ready for processing
     *
     * @param int $limit
     * @return array
     */
    public static function get_pending($limit = 100) {
        $db = nc_core::get_object()->db;
        $now = date('Y-m-d H:i:s');

        return $db->get_results("
            SELECT q.*, l.*
            FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "` q
            JOIN `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "` l ON q.Log_ID = l.Log_ID
            WHERE q.Scheduled_At <= '$now'
            ORDER BY q.Scheduled_At ASC
            LIMIT " . (int)$limit,
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get queue count
     *
     * @return int
     */
    public static function get_count() {
        $db = nc_core::get_object()->db;

        return (int)$db->get_var("SELECT COUNT(*) FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`");
    }

    /**
     * Get pending count (ready for processing)
     *
     * @return int
     */
    public static function get_pending_count() {
        $db = nc_core::get_object()->db;
        $now = date('Y-m-d H:i:s');

        return (int)$db->get_var(
            "SELECT COUNT(*) FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`
             WHERE `Scheduled_At` <= '$now'"
        );
    }

    /**
     * Reschedule item
     *
     * @param int $queue_id
     * @param string $scheduled_at
     */
    public static function reschedule($queue_id, $scheduled_at) {
        $db = nc_core::get_object()->db;

        $db->query("
            UPDATE `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "`
            SET `Scheduled_At` = '" . $db->escape($scheduled_at) . "'
            WHERE `Queue_ID` = " . (int)$queue_id
        );
    }

    /**
     * Process queue
     *
     * @param int $limit Maximum items to process
     * @return array Processing results
     */
    public static function process($limit = 100) {
        $items = self::get_pending($limit);
        $results = [];

        foreach ($items as $item) {
            $log_id = $item['Log_ID'];
            $queue_id = $item['Queue_ID'];
            $provider_id = $item['Provider_ID'];
            $site_id = $item['Catalogue_ID'];
            $retry_count = (int)$item['Retry_Count'];

            try {
                // Create provider
                $provider = nc_crm_integration_factory::create($provider_id, $site_id);

                if (!$provider) {
                    nc_crm_integration_logger::mark_failed($log_id, 'Provider not found');
                    self::remove($queue_id);
                    $results[$log_id] = ['success' => false, 'error' => 'Provider not found'];
                    continue;
                }

                // Recreate lead from log data
                $request_data = json_decode($item['Request_Data'], true);
                $lead = nc_crm_integration_lead::from_array($request_data);

                // Send lead
                $result = $provider->send_lead($lead);

                if ($result['success']) {
                    nc_crm_integration_logger::mark_success($log_id, $result['external_id'] ?? null);
                    self::remove($queue_id);
                    $results[$log_id] = ['success' => true, 'external_id' => $result['external_id'] ?? null];
                } else {
                    nc_crm_integration_logger::increment_retry($log_id);

                    if ($retry_count + 1 >= $provider->get_max_retries()) {
                        // Max retries reached
                        nc_crm_integration_logger::mark_failed($log_id, $result['error'] ?? 'Max retries exceeded');
                        self::remove($queue_id);
                        $results[$log_id] = ['success' => false, 'error' => 'Max retries exceeded'];
                    } else {
                        // Schedule next retry
                        $crm = nc_crm_integration::get_instance($site_id);
                        $next_time = $crm->calculate_next_retry_time($retry_count + 1);
                        self::reschedule($queue_id, $next_time);
                        $results[$log_id] = ['success' => false, 'error' => $result['error'], 'next_retry' => $next_time];
                    }
                }

            } catch (Exception $e) {
                nc_crm_integration_logger::increment_retry($log_id);
                nc_crm_integration_logger::update_log($log_id, ['Error_Message' => $e->getMessage()]);

                if ($retry_count + 1 >= 3) {
                    nc_crm_integration_logger::mark_failed($log_id, $e->getMessage());
                    self::remove($queue_id);
                }

                $results[$log_id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Clean orphaned queue items (logs that don't exist)
     *
     * @return int Number of cleaned items
     */
    public static function clean_orphaned() {
        $db = nc_core::get_object()->db;

        $db->query("
            DELETE q FROM `" . NC_CRM_INTEGRATION_SEND_QUEUE_TABLE . "` q
            LEFT JOIN `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "` l ON q.Log_ID = l.Log_ID
            WHERE l.Log_ID IS NULL
        ");

        return $db->rows_affected;
    }
}
