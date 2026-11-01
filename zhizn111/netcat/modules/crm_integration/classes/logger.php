<?php
/**
 * CRM Integration Module - Logger
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_logger {

    /**
     * Log send attempt
     *
     * @param int $site_id
     * @param int $provider_id
     * @param nc_crm_integration_lead $lead
     * @param array $result
     * @return int|null Log ID
     */
    public static function log_send($site_id, $provider_id, nc_crm_integration_lead $lead, array $result) {
        $db = nc_core::get_object()->db;

        $status = $result['success'] ? nc_crm_integration_provider::STATUS_SUCCESS : nc_crm_integration_provider::STATUS_ERROR;
        $external_id = $result['external_id'] ?? null;
        $error_message = $result['error'] ?? null;

        // Prepare request data (mask sensitive info)
        $request_data = $lead->to_array();
        unset($request_data['metadata']); // Don't store metadata in log

        // Prepare response data
        $response_data = $result['response'] ?? null;

        $db->query("
            INSERT INTO `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
                (`Catalogue_ID`, `Provider_ID`, `Source_Type`, `Source_Object_ID`,
                 `Component_ID`, `Status`, `External_ID`, `Retry_Count`,
                 `Request_Data`, `Response_Data`, `Error_Message`)
            VALUES
                (" . (int)$site_id . ",
                 " . (int)$provider_id . ",
                 '" . $db->escape($lead->get_source_type()) . "',
                 " . (int)$lead->get_id() . ",
                 " . (int)$lead->get_component_id() . ",
                 " . (int)$status . ",
                 " . ($external_id ? "'" . $db->escape($external_id) . "'" : "NULL") . ",
                 0,
                 '" . $db->escape(json_encode($request_data, JSON_UNESCAPED_UNICODE)) . "',
                 " . ($response_data ? "'" . $db->escape(json_encode($response_data, JSON_UNESCAPED_UNICODE)) . "'" : "NULL") . ",
                 " . ($error_message ? "'" . $db->escape($error_message) . "'" : "NULL") . ")
        ");

        return $db->insert_id ?: null;
    }

    /**
     * Log error
     *
     * @param int $site_id
     * @param int $provider_id
     * @param string $action
     * @param string $message
     */
    public static function log_error($site_id, $provider_id, $action, $message) {
        $db = nc_core::get_object()->db;

        $db->query("
            INSERT INTO `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
                (`Catalogue_ID`, `Provider_ID`, `Source_Type`, `Source_Object_ID`,
                 `Component_ID`, `Status`, `Error_Message`)
            VALUES
                (" . (int)$site_id . ",
                 " . (int)$provider_id . ",
                 '" . $db->escape($action) . "',
                 0,
                 0,
                 " . nc_crm_integration_provider::STATUS_ERROR . ",
                 '" . $db->escape($message) . "')
        ");
    }

    /**
     * Update log entry
     *
     * @param int $log_id
     * @param array $data
     */
    public static function update_log($log_id, array $data) {
        $db = nc_core::get_object()->db;

        $updates = [];
        foreach ($data as $field => $value) {
            if ($value === null) {
                $updates[] = "`$field` = NULL";
            } elseif (is_numeric($value)) {
                $updates[] = "`$field` = " . (int)$value;
            } else {
                $updates[] = "`$field` = '" . $db->escape($value) . "'";
            }
        }

        if (!empty($updates)) {
            $db->query("
                UPDATE `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
                SET " . implode(', ', $updates) . "
                WHERE `Log_ID` = " . (int)$log_id
            );
        }
    }

    /**
     * Get log entry
     *
     * @param int $log_id
     * @return array|null
     */
    public static function get_log($log_id) {
        $db = nc_core::get_object()->db;

        return $db->get_row(
            "SELECT * FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "` WHERE `Log_ID` = " . (int)$log_id,
            ARRAY_A
        );
    }

    /**
     * Get logs for site
     *
     * @param int $site_id
     * @param array $filters ['provider_id', 'status', 'date_from', 'date_to']
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_logs($site_id, array $filters = [], $limit = 50, $offset = 0) {
        $db = nc_core::get_object()->db;

        $where = ["`Catalogue_ID` = " . (int)$site_id];

        if (!empty($filters['provider_id'])) {
            $where[] = "`Provider_ID` = " . (int)$filters['provider_id'];
        }

        if (isset($filters['status'])) {
            $where[] = "`Status` = " . (int)$filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`Created` >= '" . $db->escape($filters['date_from']) . "'";
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`Created` <= '" . $db->escape($filters['date_to']) . "'";
        }

        if (!empty($filters['source_type'])) {
            $where[] = "`Source_Type` = '" . $db->escape($filters['source_type']) . "'";
        }

        $sql = "SELECT l.*, p.Provider_Name
                FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "` l
                LEFT JOIN `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "` p
                  ON l.Provider_ID = p.Provider_ID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY l.Created DESC
                LIMIT " . (int)$offset . ", " . (int)$limit;

        return $db->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get log count for site
     *
     * @param int $site_id
     * @param array $filters
     * @return int
     */
    public static function get_logs_count($site_id, array $filters = []) {
        $db = nc_core::get_object()->db;

        $where = ["`Catalogue_ID` = " . (int)$site_id];

        if (!empty($filters['provider_id'])) {
            $where[] = "`Provider_ID` = " . (int)$filters['provider_id'];
        }

        if (isset($filters['status'])) {
            $where[] = "`Status` = " . (int)$filters['status'];
        }

        return (int)$db->get_var(
            "SELECT COUNT(*) FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "` WHERE " . implode(' AND ', $where)
        );
    }

    /**
     * Get statistics for site
     *
     * @param int $site_id
     * @param int|null $provider_id
     * @return array
     */
    public static function get_stats($site_id, $provider_id = null) {
        $db = nc_core::get_object()->db;

        $where = "`Catalogue_ID` = " . (int)$site_id;
        if ($provider_id) {
            $where .= " AND `Provider_ID` = " . (int)$provider_id;
        }

        $stats = $db->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN `Status` = " . nc_crm_integration_provider::STATUS_SUCCESS . " THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN `Status` = " . nc_crm_integration_provider::STATUS_ERROR . " THEN 1 ELSE 0 END) as error,
                SUM(CASE WHEN `Status` = " . nc_crm_integration_provider::STATUS_PENDING . " THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN `Status` = " . nc_crm_integration_provider::STATUS_RETRY . " THEN 1 ELSE 0 END) as retry
            FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
            WHERE $where
        ", ARRAY_A);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'success' => (int)($stats['success'] ?? 0),
            'error' => (int)($stats['error'] ?? 0),
            'pending' => (int)($stats['pending'] ?? 0),
            'retry' => (int)($stats['retry'] ?? 0),
        ];
    }

    /**
     * Clean old logs
     *
     * @param int $days Days to keep
     * @return int Number of deleted rows
     */
    public static function clean_old_logs($days = 90) {
        $db = nc_core::get_object()->db;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $db->query("
            DELETE FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
            WHERE `Created` < '$date'
        ");

        return $db->rows_affected;
    }

    /**
     * Mark log as success
     *
     * @param int $log_id
     * @param string|null $external_id
     */
    public static function mark_success($log_id, $external_id = null) {
        self::update_log($log_id, [
            'Status' => nc_crm_integration_provider::STATUS_SUCCESS,
            'External_ID' => $external_id,
            'Error_Message' => null,
        ]);
    }

    /**
     * Mark log as failed
     *
     * @param int $log_id
     * @param string $error
     */
    public static function mark_failed($log_id, $error) {
        self::update_log($log_id, [
            'Status' => nc_crm_integration_provider::STATUS_ERROR,
            'Error_Message' => $error,
        ]);
    }

    /**
     * Increment retry count
     *
     * @param int $log_id
     */
    public static function increment_retry($log_id) {
        $db = nc_core::get_object()->db;

        $db->query("
            UPDATE `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
            SET `Retry_Count` = `Retry_Count` + 1,
                `Status` = " . nc_crm_integration_provider::STATUS_RETRY . "
            WHERE `Log_ID` = " . (int)$log_id
        );
    }
}
