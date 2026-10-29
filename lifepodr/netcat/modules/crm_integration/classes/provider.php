<?php
/**
 * CRM Integration Module - Base Provider Class
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

abstract class nc_crm_integration_provider {

    // Status constants
    const STATUS_SUCCESS = 1;
    const STATUS_ERROR = 2;
    const STATUS_PENDING = 3;
    const STATUS_RETRY = 4;

    /** @var int Provider ID */
    protected $id;

    /** @var int Site ID */
    protected $site_id;

    /** @var array Provider settings */
    protected $settings = [];

    /** @var int Maximum retry attempts */
    protected $max_retries = 3;

    /** @var int Request timeout in milliseconds */
    protected $timeout = 10000;

    /** @var array Errors */
    protected $errors = [];

    /** @var nc_db */
    protected $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = nc_core::get_object()->db;
    }

    /**
     * Send lead to CRM
     *
     * @param nc_crm_integration_lead $lead
     * @return array ['success' => bool, 'external_id' => string|null, 'error' => string|null]
     */
    abstract public function send_lead(nc_crm_integration_lead $lead);

    /**
     * Validate provider settings
     *
     * @return bool
     */
    abstract public function validate_settings();

    /**
     * Test connection to CRM
     *
     * @return bool
     */
    abstract public function test_connection();

    /**
     * Get provider name for display
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Get settings list with descriptions
     *
     * @return array ['setting_name' => ['label' => string, 'type' => string, 'hint' => string|null]]
     */
    public function get_settings_list() {
        return [];
    }

    /**
     * Get available CRM fields for mapping
     *
     * @return array ['field_name' => 'Field Label']
     */
    public function get_available_fields() {
        return [];
    }

    /**
     * Set provider ID
     *
     * @param int $id
     * @return $this
     */
    public function set_id($id) {
        $this->id = (int)$id;
        return $this;
    }

    /**
     * Get provider ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Set site ID
     *
     * @param int $site_id
     * @return $this
     */
    public function set_site_id($site_id) {
        $this->site_id = (int)$site_id;
        return $this;
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
     * Set settings
     *
     * @param array $settings
     * @return $this
     */
    public function set_settings(array $settings) {
        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    /**
     * Get setting value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($name, $default = null) {
        return $this->settings[$name] ?? $default;
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get maximum retries
     *
     * @return int
     */
    public function get_max_retries() {
        return $this->max_retries;
    }

    /**
     * Get timeout
     *
     * @return int
     */
    public function get_timeout() {
        return $this->timeout;
    }

    /**
     * Add error
     *
     * @param string $error
     */
    protected function add_error($error) {
        $this->errors[] = $error;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Reset errors
     */
    public function reset_errors() {
        $this->errors = [];
    }

    /**
     * Has errors
     *
     * @return bool
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Make HTTP request
     *
     * @param string $url
     * @param string $method
     * @param array|string $data
     * @param array $headers
     * @return array ['success' => bool, 'response' => array|string, 'error' => string|null, 'http_code' => int]
     */
    protected function http_request($url, $method = 'POST', $data = [], $headers = []) {
        $ch = curl_init();

        // Check if headers already contain Content-Type and Accept
        $has_content_type = false;
        $has_accept = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $has_content_type = true;
            }
            if (stripos($header, 'Accept:') === 0) {
                $has_accept = true;
            }
        }

        // Add default headers only if not already present
        if (!$has_content_type) {
            array_unshift($headers, 'Content-Type: application/json');
        }
        if (!$has_accept) {
            array_unshift($headers, 'Accept: application/json');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
        } elseif ($method === 'GET' && !empty($data)) {
            $options[CURLOPT_URL] = $url . '?' . http_build_query($data);
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            if (!empty($data)) {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'response' => null,
                'error' => $error,
                'http_code' => $http_code,
            ];
        }

        $decoded = json_decode($response, true);

        return [
            'success' => $http_code >= 200 && $http_code < 300,
            'response' => $decoded !== null ? $decoded : $response,
            'error' => null,
            'http_code' => $http_code,
        ];
    }

    /**
     * Get mapped lead data
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function get_mapped_data(nc_crm_integration_lead $lead) {
        $crm = nc_crm_integration::get_instance($this->site_id);
        $mapping = $crm->get_field_mapping(
            $this->id,
            $lead->get_source_type(),
            $lead->get_component_id()
        );

        $data = [];
        foreach ($lead->get_fields() as $field => $value) {
            if (isset($mapping[$field])) {
                $crm_field = $mapping[$field]['crm_field'];
                $transform = $mapping[$field]['transform'];
                $data[$crm_field] = $this->transform_value($value, $transform);
            } else {
                // Use field name as-is if no mapping
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Transform value
     *
     * @param mixed $value
     * @param string|null $transform
     * @return mixed
     */
    protected function transform_value($value, $transform = null) {
        if ($transform === null || $value === null) {
            return $value;
        }

        switch ($transform) {
            case 'trim':
                return trim($value);

            case 'phone_format':
                // Remove everything except digits and +
                return preg_replace('/[^\d+]/', '', $value);

            case 'email_format':
                return strtolower(trim($value));

            case 'uppercase':
                return mb_strtoupper($value, 'UTF-8');

            case 'lowercase':
                return mb_strtolower($value, 'UTF-8');

            case 'strip_tags':
                return strip_tags($value);

            case 'int':
                return (int)$value;

            case 'float':
                return (float)$value;

            case 'bool':
                return (bool)$value;

            default:
                return $value;
        }
    }

    /**
     * Save setting to database
     *
     * @param string $name
     * @param mixed $value
     */
    public function save_setting($name, $value) {
        $this->settings[$name] = $value;

        $this->db->query("
            INSERT INTO `" . NC_CRM_INTEGRATION_PROVIDER_SETTING_TABLE . "`
                (`Catalogue_ID`, `Provider_ID`, `Param_Name`, `Param_Value`)
            VALUES
                ({$this->site_id}, {$this->id}, '" . $this->db->escape($name) . "', '" . $this->db->escape($value) . "')
            ON DUPLICATE KEY UPDATE
                `Param_Value` = '" . $this->db->escape($value) . "'
        ");
    }

    /**
     * Load settings from database
     */
    public function load_settings() {
        $settings = $this->db->get_results(
            "SELECT `Param_Name`, `Param_Value`
             FROM `" . NC_CRM_INTEGRATION_PROVIDER_SETTING_TABLE . "`
             WHERE `Catalogue_ID` = {$this->site_id}
               AND `Provider_ID` = {$this->id}",
            ARRAY_A
        );

        if ($settings) {
            foreach ($settings as $row) {
                $this->settings[$row['Param_Name']] = $row['Param_Value'];
            }
        }
    }
}
