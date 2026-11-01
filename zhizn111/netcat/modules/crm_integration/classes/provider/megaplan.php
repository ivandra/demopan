<?php
/**
 * CRM Integration Module - Megaplan Provider
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 * @see https://dev.megaplan.ru/
 */

class nc_crm_integration_provider_megaplan extends nc_crm_integration_provider {

    /** @var string API base URL */
    protected $api_base_url;

    /** @var array Default settings */
    protected $settings = [
        'domain' => null,
        'access_token' => null,
        'program_id' => null,
        'manager_id' => null,
        'responsible_id' => null,
    ];

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return defined('NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_MEGAPLAN')
            ? NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_MEGAPLAN
            : 'Megaplan';
    }

    /**
     * Get settings list
     *
     * @return array
     */
    public function get_settings_list() {
        return [
            'domain' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_DOMAIN')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_DOMAIN
                    : 'Домен',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_DOMAIN_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_DOMAIN_HINT
                    : 'Например: mycompany.megaplan.ru',
                'required' => true,
            ],
            'access_token' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_TOKEN')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_TOKEN
                    : 'Access Token',
                'type' => 'password',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_TOKEN_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_TOKEN_HINT
                    : 'API-токен из настроек приложения в Мегаплане',
                'required' => true,
            ],
            'program_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_PROGRAM')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_PROGRAM
                    : 'ID схемы сделки',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_PROGRAM_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_PROGRAM_HINT
                    : 'Обязательно для создания сделки. Можно получить через API: GET /api/v3/program',
                'required' => true,
            ],
            'manager_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_MANAGER')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_MANAGER
                    : 'ID менеджера',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_MANAGER_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_MANAGER_HINT
                    : 'ID сотрудника, ответственного за сделку',
                'required' => false,
            ],
            'responsible_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_RESPONSIBLE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_RESPONSIBLE
                    : 'ID ответственного',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_RESPONSIBLE_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_MEGAPLAN_RESPONSIBLE_HINT
                    : 'ID сотрудника-исполнителя',
                'required' => false,
            ],
        ];
    }

    /**
     * Get available CRM fields
     *
     * @return array
     */
    public function get_available_fields() {
        return [
            'name' => 'Название сделки',
            'description' => 'Описание',
            'contractor.name' => 'Имя контакта',
            'contractor.lastName' => 'Фамилия контакта',
            'contractor.middleName' => 'Отчество контакта',
            'contractor.phone' => 'Телефон',
            'contractor.email' => 'E-mail',
            'contractor.company' => 'Компания',
        ];
    }

    /**
     * Get API base URL
     *
     * @return string
     */
    protected function get_api_base_url() {
        if (!$this->api_base_url) {
            $domain = $this->get_setting('domain');
            // Remove protocol if present
            $domain = preg_replace('#^https?://#', '', $domain);
            // Remove trailing slash
            $domain = rtrim($domain, '/');
            $this->api_base_url = "https://{$domain}";
        }
        return $this->api_base_url;
    }

    /**
     * Send lead to Megaplan
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    public function send_lead(nc_crm_integration_lead $lead) {
        $this->reset_errors();

        if (!$this->validate_settings()) {
            return [
                'success' => false,
                'external_id' => null,
                'error' => implode('; ', $this->get_errors()),
            ];
        }

        // First, create or find contractor (contact)
        $contractor_id = $this->create_contractor($lead);

        // Prepare deal data
        $deal_data = $this->prepare_deal_data($lead, $contractor_id);

        // Create deal
        $response = $this->api_call('POST', '/api/v3/deal', $deal_data);

        if ($response['success'] && isset($response['response']['data']['id'])) {
            return [
                'success' => true,
                'external_id' => (string)$response['response']['data']['id'],
                'error' => null,
                'response' => $response['response'],
            ];
        }

        // Build detailed error message
        $error = 'Unknown error';
        if (!empty($response['error'])) {
            $error = $response['error'];
        } elseif (isset($response['response']['meta']['errors'][0]['message'])) {
            $error = $response['response']['meta']['errors'][0]['message'];
        } elseif (isset($response['response']['error']['message'])) {
            $error = $response['response']['error']['message'];
        } elseif (isset($response['response']['message'])) {
            $error = $response['response']['message'];
        } elseif (is_array($response['response'])) {
            $error = 'API error (HTTP ' . ($response['http_code'] ?? '?') . '): ' . json_encode($response['response'], JSON_UNESCAPED_UNICODE);
        }

        return [
            'success' => false,
            'external_id' => null,
            'error' => $error,
            'response' => $response['response'] ?? null,
        ];
    }

    /**
     * Validate settings
     *
     * @param bool $check_program_id Whether to require program_id
     * @return bool
     */
    public function validate_settings($check_program_id = true) {
        $required = ['domain', 'access_token'];

        // program_id is required for sending leads, but not for connection test
        if ($check_program_id) {
            $required[] = 'program_id';
        }

        foreach ($required as $field) {
            if (empty($this->get_setting($field))) {
                $this->add_error("$field is required");
                return false;
            }
        }

        return true;
    }

    /**
     * Test connection
     *
     * @return bool
     */
    public function test_connection() {
        $this->reset_errors();

        // Don't require program_id for connection test
        if (!$this->validate_settings(false)) {
            return false;
        }

        // Try to get list of deal programs (this verifies token is valid)
        $response = $this->api_call('GET', '/api/v3/program');

        if ($response['success']) {
            // Debug: show raw response structure
            $response_data = $response['response'];

            // Try different possible data locations
            $programs_data = null;
            if (isset($response_data['data']) && is_array($response_data['data'])) {
                $programs_data = $response_data['data'];
            } elseif (isset($response_data['items']) && is_array($response_data['items'])) {
                $programs_data = $response_data['items'];
            } elseif (is_array($response_data) && !isset($response_data['meta'])) {
                // Maybe it's a direct array
                $programs_data = $response_data;
            }

            // Build programs list
            $programs = [];
            if ($programs_data) {
                foreach ($programs_data as $program) {
                    if (isset($program['id'])) {
                        $name = $program['name'] ?? $program['title'] ?? 'Program #' . $program['id'];
                        $programs[$program['id']] = $name;
                    }
                }
            }

            // Check result
            $current_program = $this->get_setting('program_id');

            if (!empty($programs)) {
                // If program_id is set and valid - success!
                if ($current_program && isset($programs[$current_program])) {
                    return true;
                }

                // Show available programs
                $programs_list = [];
                foreach ($programs as $id => $name) {
                    $programs_list[] = "$id: $name";
                }

                if ($current_program && !isset($programs[$current_program])) {
                    $this->add_error("Invalid program_id: $current_program. Available programs:\n" . implode("\n", $programs_list));
                } else {
                    $this->add_error("Connection OK! Enter program ID in 'ID схемы сделки' field. Available programs:\n" . implode("\n", $programs_list));
                }
                return false;
            } else {
                // No programs found
                $this->add_error("Connection OK, but no deal programs found. Create a deal scheme in Megaplan first (Процессы/Сделки).");
                return false;
            }
        }

        // Build detailed error message
        $error_msg = 'Connection failed';
        if (!empty($response['error'])) {
            $error_msg .= ': ' . $response['error'];
        } elseif (!empty($response['response']['error']['message'])) {
            $error_msg .= ': ' . $response['response']['error']['message'];
        } elseif (!empty($response['response']['message'])) {
            $error_msg .= ': ' . $response['response']['message'];
        } elseif (is_array($response['response'])) {
            $error_msg .= ' (HTTP ' . $response['http_code'] . '): ' . json_encode($response['response'], JSON_UNESCAPED_UNICODE);
        } else {
            $error_msg .= ' (HTTP ' . $response['http_code'] . ')';
        }

        $this->add_error($error_msg);
        return false;
    }

    /**
     * Create or find contractor in Megaplan
     *
     * @param nc_crm_integration_lead $lead
     * @return int|null Contractor ID
     */
    protected function create_contractor(nc_crm_integration_lead $lead) {
        $contractor_data = [
            'contentType' => 'ContractorHuman', // Physical person
        ];

        // Name
        if ($name = $lead->get('Name')) {
            $contractor_data['firstName'] = $name;
        }
        if ($surname = $lead->get('Surname')) {
            $contractor_data['lastName'] = $surname;
        }
        if ($patronymic = $lead->get('Patronymic')) {
            $contractor_data['middleName'] = $patronymic;
        }

        // Contacts via contactInfo (API v3 format)
        $contact_info = [];
        if ($phone = $lead->get('Phone')) {
            $contact_info[] = [
                'contentType' => 'ContactInfo',
                'type' => 'phone',
                'value' => $this->transform_value($phone, 'phone_format'),
            ];
        }
        if ($email = $lead->get('Email')) {
            $contact_info[] = [
                'contentType' => 'ContactInfo',
                'type' => 'email',
                'value' => $this->transform_value($email, 'email_format'),
            ];
        }
        if (!empty($contact_info)) {
            $contractor_data['contactInfo'] = $contact_info;
        }

        // Company name (for human contractor, stored in company field)
        if ($company = $lead->get('Company')) {
            $contractor_data['company'] = $company;
        }

        // Create contractor (API v3 uses /contractorHuman for physical persons)
        $response = $this->api_call('POST', '/api/v3/contractorHuman', $contractor_data);

        if ($response['success'] && isset($response['response']['data']['id'])) {
            return (int)$response['response']['data']['id'];
        }

        return null;
    }

    /**
     * Prepare deal data for Megaplan API
     *
     * @param nc_crm_integration_lead $lead
     * @param int|null $contractor_id
     * @return array
     */
    protected function prepare_deal_data(nc_crm_integration_lead $lead, $contractor_id = null) {
        $data = [
            'program' => [
                'id' => (int)$this->get_setting('program_id'),
                'contentType' => 'Program',
            ],
            'name' => $lead->get_title() ?: 'Заявка с сайта',
        ];

        // Contractor
        if ($contractor_id) {
            $data['contractor'] = [
                'id' => (string)$contractor_id,
                'contentType' => 'ContractorHuman',
            ];
        }

        // Note: manager and responsible fields require specific API format
        // that may vary between Megaplan accounts. Skipping for now.

        // Description with comment and UTM
        $description_parts = [];

        if ($comment = $lead->get('Comment')) {
            $description_parts[] = $comment;
        }

        // Add UTM info
        $utm_info = $this->build_utm_description($lead);
        if ($utm_info) {
            $description_parts[] = "\n--- UTM ---\n" . $utm_info;
        }

        // Add source info
        if ($referer = $lead->get_metadata('Referer')) {
            $description_parts[] = "\nИсточник: " . $referer;
        }

        if (!empty($description_parts)) {
            $data['description'] = implode("\n", $description_parts);
        }

        // Apply custom mapping - only for explicitly mapped fields
        // (Megaplan API rejects unknown fields)
        $allowed_deal_fields = ['name', 'description', 'price'];
        $allowed_contractor_fields = ['name', 'lastName', 'middleName', 'phone', 'email', 'company'];

        $mapped_data = $this->get_mapped_data($lead);
        foreach ($mapped_data as $field => $value) {
            if (empty($value)) {
                continue;
            }

            // Handle nested fields like contractor.name
            if (strpos($field, '.') !== false) {
                $parts = explode('.', $field, 2);
                $parent = $parts[0];
                $child = $parts[1];

                // Skip contractor fields - contractor is created separately via API
                // and linked by ID, not by inline data
                // (keeping this comment for future reference)
            } else {
                // Only allow known deal fields
                if (in_array($field, $allowed_deal_fields)) {
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Build UTM description text
     *
     * @param nc_crm_integration_lead $lead
     * @return string
     */
    protected function build_utm_description(nc_crm_integration_lead $lead) {
        $utm_parts = [];

        if ($utm_source = $lead->get_utm('source')) {
            $utm_parts[] = "Source: $utm_source";
        }
        if ($utm_medium = $lead->get_utm('medium')) {
            $utm_parts[] = "Medium: $utm_medium";
        }
        if ($utm_campaign = $lead->get_utm('campaign')) {
            $utm_parts[] = "Campaign: $utm_campaign";
        }
        if ($utm_content = $lead->get_utm('content')) {
            $utm_parts[] = "Content: $utm_content";
        }
        if ($utm_term = $lead->get_utm('term')) {
            $utm_parts[] = "Term: $utm_term";
        }

        return implode("\n", $utm_parts);
    }

    /**
     * Make API call to Megaplan
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array
     */
    protected function api_call($method, $endpoint, array $data = []) {
        $url = $this->get_api_base_url() . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->get_setting('access_token'),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->http_request($url, $method, $data, $headers);
    }

    /**
     * Get available deal programs (schemes)
     *
     * @return array
     */
    public function get_programs() {
        $response = $this->api_call('GET', '/api/v3/program');

        if ($response['success'] && isset($response['response']['data'])) {
            $programs = [];
            foreach ($response['response']['data'] as $program) {
                $programs[$program['id']] = $program['name'];
            }
            return $programs;
        }

        return [];
    }

    /**
     * Get employees for manager/responsible selection
     *
     * @return array
     */
    public function get_employees() {
        $response = $this->api_call('GET', '/api/v3/employee');

        if ($response['success'] && isset($response['response']['data'])) {
            $employees = [];
            foreach ($response['response']['data'] as $employee) {
                $name = trim($employee['lastName'] . ' ' . $employee['firstName']);
                $employees[$employee['id']] = $name;
            }
            return $employees;
        }

        return [];
    }
}
