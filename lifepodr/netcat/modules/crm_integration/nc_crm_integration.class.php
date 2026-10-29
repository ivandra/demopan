<?php
/**
 * CRM Integration Module - Main Class
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration {

    /** @var nc_crm_integration[] Instances by site */
    protected static $instances = [];

    /** @var bool Initialization flag */
    protected static $initialized = false;

    /** @var int Site ID */
    protected $site_id;

    /** @var nc_core */
    protected $nc_core;

    /** @var nc_db */
    protected $db;

    /** @var array Cached enabled providers */
    protected $enabled_providers_cache = null;

    /** @var array Cached sources */
    protected $sources_cache = null;

    /**
     * Initialize module
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
    }

    /**
     * Get instance for site
     *
     * @param int|null $site_id Site ID (null = current site)
     * @return nc_crm_integration
     */
    public static function get_instance($site_id = null) {
        $nc_core = nc_core::get_object();

        if ($site_id === null) {
            $site_id = (int)$nc_core->catalogue->get_current('Catalogue_ID');
        }

        $site_id = (int)$site_id;

        if (!isset(self::$instances[$site_id])) {
            self::$instances[$site_id] = new self($site_id);
        }

        return self::$instances[$site_id];
    }

    /**
     * Constructor
     *
     * @param int $site_id
     */
    protected function __construct($site_id) {
        $this->site_id = (int)$site_id;
        $this->nc_core = nc_core::get_object();
        $this->db = $this->nc_core->db;
    }

    /**
     * Get site ID
     *
     * @return int
     */
    public function get_site_id() {
        return $this->site_id;
    }

    /**
     * Check if source is enabled for CRM integration
     *
     * @param int $component_id
     * @param int $infoblock_id
     * @return bool
     */
    public function is_source_enabled($component_id, $infoblock_id) {
        $providers = $this->get_providers_for_source($component_id, $infoblock_id);
        return !empty($providers);
    }

    /**
     * Get provider IDs for a specific source (component/infoblock)
     *
     * @param int $component_id
     * @param int $infoblock_id
     * @return array Provider IDs
     */
    public function get_providers_for_source($component_id, $infoblock_id) {
        $sources = $this->get_enabled_sources();
        $provider_ids = [];

        foreach ($sources as $source) {
            $matches = false;

            // Check requests module
            if ($source['Source_Type'] === 'requests') {
                $requests_component_id = $this->get_requests_component_id();
                if ($requests_component_id && $component_id == $requests_component_id) {
                    $matches = true;
                }
            }

            // Check specific component
            if ($source['Source_Type'] === 'component' && $source['Source_Object_ID'] == $component_id) {
                $matches = true;
            }

            // Check specific infoblock
            if ($source['Source_Type'] === 'infoblock' && $source['Source_Object_ID'] == $infoblock_id) {
                $matches = true;
            }

            if ($matches && !empty($source['Provider_ID'])) {
                $provider_ids[] = (int)$source['Provider_ID'];
            }
        }

        return array_unique($provider_ids);
    }

    /**
     * Get enabled sources for site
     *
     * @return array
     */
    public function get_enabled_sources() {
        if ($this->sources_cache !== null) {
            return $this->sources_cache;
        }

        $this->sources_cache = $this->db->get_results(
            "SELECT * FROM `" . NC_CRM_INTEGRATION_SOURCE_TABLE . "`
             WHERE `Catalogue_ID` = {$this->site_id}
               AND `Enabled` = 1",
            ARRAY_A
        ) ?: [];

        return $this->sources_cache;
    }

    /**
     * Get requests module component ID
     *
     * @return int|null
     */
    protected function get_requests_component_id() {
        static $component_id = null;

        if ($component_id === null) {
            if (class_exists('nc_requests')) {
                $requests = nc_requests::get_instance($this->site_id);
                $component_id = $requests->get_request_component_id();
            } else {
                $component_id = 0;
            }
        }

        return $component_id ?: null;
    }

    /**
     * Send lead to specific providers
     *
     * @param nc_crm_integration_lead $lead
     * @param array $provider_ids List of provider IDs to send to
     * @return array Results by provider ID
     */
    public function send_to_providers(nc_crm_integration_lead $lead, array $provider_ids) {
        $results = [];

        foreach ($provider_ids as $provider_id) {
            $provider_id = (int)$provider_id;

            // Check for duplicates
            if ($this->is_already_sent($lead, $provider_id)) {
                $results[$provider_id] = [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Already sent'
                ];
                continue;
            }

            try {
                $provider = nc_crm_integration_factory::create($provider_id, $this->site_id);

                if (!$provider) {
                    $results[$provider_id] = [
                        'success' => false,
                        'error' => 'Provider not found'
                    ];
                    continue;
                }

                // Send lead
                $result = $provider->send_lead($lead);
                $results[$provider_id] = $result;

                // Log result
                $log_id = nc_crm_integration_logger::log_send(
                    $this->site_id,
                    $provider_id,
                    $lead,
                    $result
                );

                // If error, add to retry queue
                if (!$result['success'] && $log_id) {
                    nc_crm_integration_queue::add($log_id, $this->calculate_next_retry_time(0));
                }

            } catch (Exception $e) {
                $results[$provider_id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                nc_crm_integration_logger::log_error(
                    $this->site_id,
                    $provider_id,
                    'send_lead',
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Send lead to all enabled providers (for backward compatibility)
     *
     * @param nc_crm_integration_lead $lead
     * @return array Results by provider ID
     */
    public function send_to_enabled_providers(nc_crm_integration_lead $lead) {
        $providers = $this->get_enabled_providers();
        $provider_ids = array_column($providers, 'Provider_ID');
        return $this->send_to_providers($lead, $provider_ids);
    }

    /**
     * Get enabled providers for site
     *
     * @return array
     */
    public function get_enabled_providers() {
        if ($this->enabled_providers_cache !== null) {
            return $this->enabled_providers_cache;
        }

        $this->enabled_providers_cache = $this->db->get_results(
            "SELECT p.*, pc.Priority as Site_Priority
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "` p
             JOIN `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "` pc
               ON p.Provider_ID = pc.Provider_ID
             WHERE pc.Catalogue_ID = {$this->site_id}
               AND pc.Enabled = 1
               AND p.Enabled = 1
             ORDER BY pc.Priority ASC",
            ARRAY_A
        ) ?: [];

        return $this->enabled_providers_cache;
    }

    /**
     * Check if lead was already sent to provider
     *
     * @param nc_crm_integration_lead $lead
     * @param int $provider_id
     * @return bool
     */
    protected function is_already_sent(nc_crm_integration_lead $lead, $provider_id) {
        $result = $this->db->get_var(
            "SELECT Log_ID FROM `" . NC_CRM_INTEGRATION_SEND_LOG_TABLE . "`
             WHERE `Catalogue_ID` = {$this->site_id}
               AND `Provider_ID` = " . (int)$provider_id . "
               AND `Source_Object_ID` = " . (int)$lead->get_id() . "
               AND `Component_ID` = " . (int)$lead->get_component_id() . "
               AND `Status` = " . nc_crm_integration_provider::STATUS_SUCCESS . "
             LIMIT 1"
        );

        return (bool)$result;
    }

    /**
     * Calculate next retry time
     *
     * @param int $retry_count
     * @return string DateTime string
     */
    public function calculate_next_retry_time($retry_count) {
        // Exponential backoff: 1 min, 5 min, 15 min
        $delays = [60, 300, 900];
        $delay = $delays[$retry_count] ?? 900;

        return date('Y-m-d H:i:s', time() + $delay);
    }

    /**
     * Get field mapping for provider and source
     *
     * @param int $provider_id
     * @param string $source_type
     * @param int|null $source_id
     * @return array
     */
    public function get_field_mapping($provider_id, $source_type, $source_id = null) {
        $where = "WHERE `Catalogue_ID` = {$this->site_id}
                    AND `Provider_ID` = " . (int)$provider_id . "
                    AND `Source_Type` = '" . $this->db->escape($source_type) . "'";

        if ($source_id !== null) {
            $where .= " AND (`Source_ID` = " . (int)$source_id . " OR `Source_ID` IS NULL)";
        }

        $mappings = $this->db->get_results(
            "SELECT * FROM `" . NC_CRM_INTEGRATION_FIELD_MAPPING_TABLE . "` $where",
            ARRAY_A
        ) ?: [];

        $result = [];
        foreach ($mappings as $mapping) {
            $result[$mapping['Netcat_Field']] = [
                'crm_field' => $mapping['Crm_Field'],
                'transform' => $mapping['Transform']
            ];
        }

        return $result;
    }

    /**
     * Clear caches
     */
    public function clear_cache() {
        $this->enabled_providers_cache = null;
        $this->sources_cache = null;
    }
}
