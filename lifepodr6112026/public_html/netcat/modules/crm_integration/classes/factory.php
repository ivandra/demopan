<?php
/**
 * CRM Integration Module - Provider Factory
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_factory {

    /** @var array Cached provider instances */
    protected static $instances = [];

    /** @var array Cached ID to class mapping */
    protected static $id_to_class = [];

    /** @var array Cached class to ID mapping */
    protected static $class_to_id = [];

    /**
     * Create provider instance
     *
     * @param string|int $provider Provider ID or class name
     * @param int|null $site_id Site ID
     * @return nc_crm_integration_provider|null
     */
    public static function create($provider, $site_id = null) {
        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        if ($site_id === null) {
            $site_id = (int)$nc_core->catalogue->get_current('Catalogue_ID');
        }

        // Resolve provider ID and class name
        if (is_numeric($provider)) {
            $provider_id = (int)$provider;
            $class_name = self::get_class_by_id($provider_id);
        } else {
            $class_name = $provider;
            $provider_id = self::get_id_by_class($class_name);
        }

        if (!$class_name || !$provider_id) {
            return null;
        }

        // Check cache
        $cache_key = "{$provider_id}_{$site_id}";
        if (isset(self::$instances[$cache_key])) {
            return self::$instances[$cache_key];
        }

        // Check if class exists
        if (!class_exists($class_name)) {
            // Try to load from provider directory
            $file = NC_CRM_INTEGRATION_FOLDER . 'classes/provider/' . self::get_filename_from_class($class_name) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (!class_exists($class_name)) {
            return null;
        }

        // Create instance
        /** @var nc_crm_integration_provider $instance */
        $instance = new $class_name();
        $instance->set_id($provider_id);
        $instance->set_site_id($site_id);
        $instance->load_settings();

        self::$instances[$cache_key] = $instance;

        return $instance;
    }

    /**
     * Get class name by provider ID
     *
     * @param int $id
     * @return string|null
     */
    public static function get_class_by_id($id) {
        $id = (int)$id;

        if (isset(self::$id_to_class[$id])) {
            return self::$id_to_class[$id];
        }

        $db = nc_core::get_object()->db;
        $class = $db->get_var(
            "SELECT `Provider_Class`
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "`
             WHERE `Provider_ID` = $id
               AND `Enabled` = 1"
        );

        if ($class) {
            self::$id_to_class[$id] = $class;
            self::$class_to_id[$class] = $id;
        }

        return $class;
    }

    /**
     * Get provider ID by class name
     *
     * @param string $class
     * @return int|null
     */
    public static function get_id_by_class($class) {
        if (isset(self::$class_to_id[$class])) {
            return self::$class_to_id[$class];
        }

        $db = nc_core::get_object()->db;
        $id = $db->get_var(
            "SELECT `Provider_ID`
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "`
             WHERE `Provider_Class` = '" . $db->escape($class) . "'
               AND `Enabled` = 1"
        );

        if ($id) {
            $id = (int)$id;
            self::$id_to_class[$id] = $class;
            self::$class_to_id[$class] = $id;
        }

        return $id ? (int)$id : null;
    }

    /**
     * Get all available providers
     *
     * @return array
     */
    public static function get_all_providers() {
        $db = nc_core::get_object()->db;

        return $db->get_results(
            "SELECT *
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "`
             WHERE `Enabled` = 1
             ORDER BY `Priority` ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get enabled providers for site
     *
     * @param int $site_id
     * @return array
     */
    public static function get_enabled_providers($site_id) {
        $db = nc_core::get_object()->db;

        return $db->get_results(
            "SELECT p.*, pc.Priority as Site_Priority, pc.Enabled as Site_Enabled
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "` p
             LEFT JOIN `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "` pc
               ON p.Provider_ID = pc.Provider_ID AND pc.Catalogue_ID = " . (int)$site_id . "
             WHERE p.Enabled = 1
             ORDER BY pc.Priority ASC, p.Priority ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Check if provider is enabled for site
     *
     * @param int $provider_id
     * @param int $site_id
     * @return bool
     */
    public static function is_provider_enabled($provider_id, $site_id) {
        $db = nc_core::get_object()->db;

        return (bool)$db->get_var(
            "SELECT 1
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "`
             WHERE `Provider_ID` = " . (int)$provider_id . "
               AND `Catalogue_ID` = " . (int)$site_id . "
               AND `Enabled` = 1"
        );
    }

    /**
     * Enable provider for site
     *
     * @param int $provider_id
     * @param int $site_id
     * @param int $priority
     */
    public static function enable_provider($provider_id, $site_id, $priority = 100) {
        $db = nc_core::get_object()->db;

        $db->query("
            INSERT INTO `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "`
                (`Catalogue_ID`, `Provider_ID`, `Enabled`, `Priority`)
            VALUES
                (" . (int)$site_id . ", " . (int)$provider_id . ", 1, " . (int)$priority . ")
            ON DUPLICATE KEY UPDATE
                `Enabled` = 1, `Priority` = " . (int)$priority
        );

        self::clear_cache();
    }

    /**
     * Disable provider for site
     *
     * @param int $provider_id
     * @param int $site_id
     */
    public static function disable_provider($provider_id, $site_id) {
        $db = nc_core::get_object()->db;

        $db->query("
            UPDATE `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "`
            SET `Enabled` = 0
            WHERE `Provider_ID` = " . (int)$provider_id . "
              AND `Catalogue_ID` = " . (int)$site_id
        );

        self::clear_cache();
    }

    /**
     * Get filename from class name
     *
     * @param string $class
     * @return string
     */
    protected static function get_filename_from_class($class) {
        // nc_crm_integration_provider_bitrix24 -> bitrix24
        if (strpos($class, 'nc_crm_provider_') === 0) {
            return substr($class, strlen('nc_crm_provider_'));
        }
        return $class;
    }

    /**
     * Clear factory cache
     */
    public static function clear_cache() {
        self::$instances = [];
        self::$id_to_class = [];
        self::$class_to_id = [];
    }
}
