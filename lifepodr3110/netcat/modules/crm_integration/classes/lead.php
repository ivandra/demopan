<?php
/**
 * CRM Integration Module - Lead Model
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_lead {

    /** @var int Object ID in Netcat */
    protected $id;

    /** @var int Site ID */
    protected $site_id;

    /** @var int Component ID */
    protected $component_id;

    /** @var int|null Subdivision ID */
    protected $subdivision_id;

    /** @var int|null Infoblock ID */
    protected $infoblock_id;

    /** @var string Source type (requests, component, custom) */
    protected $source_type = 'component';

    /** @var array Field values */
    protected $fields = [];

    /** @var array UTM parameters */
    protected $utm = [];

    /** @var array Metadata (IP, UserAgent, etc.) */
    protected $metadata = [];

    /**
     * Create lead from Netcat object
     *
     * @param int $object_id
     * @param int $component_id
     * @param int|null $site_id
     * @return nc_crm_integration_lead
     */
    public static function from_object($object_id, $component_id, $site_id = null) {
        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        $lead = new self();
        $lead->id = (int)$object_id;
        $lead->component_id = (int)$component_id;
        $lead->site_id = $site_id ? (int)$site_id : (int)$nc_core->catalogue->get_current('Catalogue_ID');

        // Determine source type
        if (class_exists('nc_requests')) {
            $requests = nc_requests::get_instance($lead->site_id);
            if ($requests->get_request_component_id() == $component_id) {
                $lead->source_type = 'requests';
            }
        }

        // Load object data
        $data = $db->get_row(
            "SELECT * FROM `Message{$component_id}` WHERE `Message_ID` = " . (int)$object_id,
            ARRAY_A
        );

        if ($data) {
            // Extract UTM fields
            $utm_fields = ['UTM_Source', 'UTM_Medium', 'UTM_Campaign', 'UTM_Content', 'UTM_Term'];
            foreach ($utm_fields as $field) {
                if (isset($data[$field])) {
                    $key = strtolower(str_replace('UTM_', '', $field));
                    $lead->utm[$key] = $data[$field];
                    unset($data[$field]);
                }
            }

            // Extract metadata fields
            $meta_fields = ['User_ID', 'IP', 'UserAgent', 'Created', 'LastUpdated', 'Checked', 'Priority'];
            foreach ($meta_fields as $field) {
                if (isset($data[$field])) {
                    $lead->metadata[$field] = $data[$field];
                    unset($data[$field]);
                }
            }

            // System fields to exclude
            $system_fields = ['Message_ID', 'Subdivision_ID', 'Sub_Class_ID', 'User_ID', 'Checked',
                'Priority', 'IP', 'UserAgent', 'Created', 'LastUpdated', 'Parent_Message_ID', 'ncSMB_Subdivision_ID'];

            // Remaining fields are lead data
            foreach ($data as $field => $value) {
                if (!in_array($field, $system_fields) && $value !== null && $value !== '') {
                    $lead->fields[$field] = $value;
                }
            }

            // Store subdivision from object
            if (isset($data['Subdivision_ID'])) {
                $lead->subdivision_id = (int)$data['Subdivision_ID'];
            }
        }

        // Add request metadata
        $lead->metadata['IP'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $lead->metadata['UserAgent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $lead->metadata['Referer'] = $_SERVER['HTTP_REFERER'] ?? null;
        $lead->metadata['Created'] = $lead->metadata['Created'] ?? date('Y-m-d H:i:s');

        return $lead;
    }

    /**
     * Create lead from requests module
     *
     * @param int $request_id
     * @param int|null $site_id
     * @return nc_crm_integration_lead|null
     */
    public static function from_request($request_id, $site_id = null) {
        if (!class_exists('nc_requests')) {
            return null;
        }

        $nc_core = nc_core::get_object();

        if ($site_id === null) {
            $site_id = (int)$nc_core->catalogue->get_current('Catalogue_ID');
        }

        $requests = nc_requests::get_instance($site_id);
        $component_id = $requests->get_request_component_id();

        if (!$component_id) {
            return null;
        }

        $lead = self::from_object($request_id, $component_id, $site_id);
        $lead->source_type = 'requests';

        return $lead;
    }

    /**
     * Create lead from array
     *
     * @param array $data
     * @return nc_crm_integration_lead
     */
    public static function from_array(array $data) {
        $lead = new self();

        $lead->id = $data['id'] ?? 0;
        $lead->site_id = $data['site_id'] ?? 0;
        $lead->component_id = $data['component_id'] ?? 0;
        $lead->source_type = $data['source_type'] ?? 'custom';
        $lead->fields = $data['fields'] ?? [];
        $lead->utm = $data['utm'] ?? [];
        $lead->metadata = $data['metadata'] ?? [];

        return $lead;
    }

    /**
     * Get lead ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
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
     * Get component ID
     *
     * @return int
     */
    public function get_component_id() {
        return $this->component_id;
    }

    /**
     * Get subdivision ID
     *
     * @return int|null
     */
    public function get_subdivision_id() {
        return $this->subdivision_id;
    }

    /**
     * Set subdivision ID
     *
     * @param int $id
     * @return $this
     */
    public function set_subdivision_id($id) {
        $this->subdivision_id = (int)$id;
        return $this;
    }

    /**
     * Get infoblock ID
     *
     * @return int|null
     */
    public function get_infoblock_id() {
        return $this->infoblock_id;
    }

    /**
     * Set infoblock ID
     *
     * @param int $id
     * @return $this
     */
    public function set_infoblock_id($id) {
        $this->infoblock_id = (int)$id;
        return $this;
    }

    /**
     * Get source type
     *
     * @return string
     */
    public function get_source_type() {
        return $this->source_type;
    }

    /**
     * Get field value
     *
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    public function get($field, $default = null) {
        return $this->fields[$field] ?? $default;
    }

    /**
     * Set field value
     *
     * @param string $field
     * @param mixed $value
     * @return $this
     */
    public function set($field, $value) {
        $this->fields[$field] = $value;
        return $this;
    }

    /**
     * Get all fields
     *
     * @return array
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Get UTM parameter
     *
     * @param string $key
     * @return string|null
     */
    public function get_utm($key) {
        return $this->utm[strtolower($key)] ?? null;
    }

    /**
     * Get all UTM parameters
     *
     * @return array
     */
    public function get_all_utm() {
        return $this->utm;
    }

    /**
     * Get metadata value
     *
     * @param string $key
     * @return mixed
     */
    public function get_metadata($key) {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Get all metadata
     *
     * @return array
     */
    public function get_all_metadata() {
        return $this->metadata;
    }

    /**
     * Get lead title (for display in CRM)
     *
     * @return string
     */
    public function get_title() {
        // Try common title fields
        $title_fields = ['Title', 'Name', 'Subject', 'FormType'];
        foreach ($title_fields as $field) {
            if (!empty($this->fields[$field])) {
                return $this->fields[$field];
            }
        }

        // Generate from name fields
        $name_parts = [];
        if (!empty($this->fields['Surname'])) {
            $name_parts[] = $this->fields['Surname'];
        }
        if (!empty($this->fields['Name'])) {
            $name_parts[] = $this->fields['Name'];
        }

        if (!empty($name_parts)) {
            return implode(' ', $name_parts);
        }

        return 'Заявка с сайта #' . $this->id;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'component_id' => $this->component_id,
            'subdivision_id' => $this->subdivision_id,
            'infoblock_id' => $this->infoblock_id,
            'source_type' => $this->source_type,
            'fields' => $this->fields,
            'utm' => $this->utm,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON
     *
     * @return string
     */
    public function to_json() {
        return json_encode($this->to_array(), JSON_UNESCAPED_UNICODE);
    }
}
