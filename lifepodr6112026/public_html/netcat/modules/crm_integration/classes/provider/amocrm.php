<?php
/**
 * CRM Integration Module - AmoCRM Provider
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_provider_amocrm extends nc_crm_integration_provider {

    /** @var string API base URL */
    protected $api_base_url;

    /** @var array Default settings */
    protected $settings = [
        'subdomain' => null,
        'client_id' => null,
        'client_secret' => null,
        'redirect_uri' => null,
        'access_token' => null,
        'refresh_token' => null,
        'token_expires' => null,
        'pipeline_id' => null,
        'status_id' => null,
    ];

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return defined('NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_AMOCRM')
            ? NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_AMOCRM
            : 'AmoCRM';
    }

    /**
     * Get settings list
     *
     * @return array
     */
    public function get_settings_list() {
        return [
            'subdomain' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_SUBDOMAIN')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_SUBDOMAIN
                    : 'Subdomain',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_SUBDOMAIN_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_SUBDOMAIN_HINT
                    : 'Example: mycompany (without .amocrm.ru)',
                'required' => true,
            ],
            'client_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_CLIENT_ID')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_CLIENT_ID
                    : 'Client ID',
                'type' => 'text',
                'required' => true,
            ],
            'client_secret' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_CLIENT_SECRET')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_CLIENT_SECRET
                    : 'Client Secret',
                'type' => 'password',
                'required' => true,
            ],
            'redirect_uri' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_REDIRECT_URI')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_REDIRECT_URI
                    : 'Redirect URI',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_REDIRECT_URI_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_REDIRECT_URI_HINT
                    : 'URL для OAuth callback',
                'required' => true,
            ],
            'pipeline_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_PIPELINE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_PIPELINE
                    : 'Воронка',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_PIPELINE_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_PIPELINE_HINT
                    : 'ID воронки для новых сделок',
                'required' => false,
            ],
            'status_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_STATUS')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_STATUS
                    : 'Статус сделки',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_STATUS_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_STATUS_HINT
                    : 'ID начального статуса',
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
            'price' => 'Бюджет',
            'first_name' => 'Имя контакта',
            'last_name' => 'Фамилия контакта',
            'PHONE' => 'Телефон',
            'EMAIL' => 'E-mail',
        ];
    }

    /**
     * Get API base URL
     *
     * @return string
     */
    protected function get_api_base_url() {
        if (!$this->api_base_url) {
            $subdomain = $this->get_setting('subdomain');
            $this->api_base_url = "https://{$subdomain}.amocrm.ru";
        }
        return $this->api_base_url;
    }

    /**
     * Send lead to AmoCRM
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

        // Ensure token is valid
        try {
            $this->ensure_token_valid();
        } catch (Exception $e) {
            return [
                'success' => false,
                'external_id' => null,
                'error' => 'Token error: ' . $e->getMessage(),
            ];
        }

        // Prepare lead data
        $lead_data = $this->prepare_lead_data($lead);

        // Create lead with contact
        $response = $this->api_call('POST', '/api/v4/leads/complex', [$lead_data]);

        if ($response['success'] && isset($response['response'][0]['id'])) {
            $lead_id = (int)$response['response'][0]['id'];

            // Add comment as note if present
            $this->add_lead_note($lead_id, $lead);

            return [
                'success' => true,
                'external_id' => (string)$lead_id,
                'error' => null,
                'response' => $response['response'],
            ];
        }

        $error = $response['error']
            ?? $response['response']['detail']
            ?? $response['response']['title']
            ?? 'Unknown error';

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
     * @return bool
     */
    public function validate_settings() {
        $required = ['subdomain', 'client_id', 'client_secret', 'redirect_uri'];

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

        if (!$this->validate_settings()) {
            return false;
        }

        // Check if we have valid tokens
        if (!$this->get_setting('access_token')) {
            $this->add_error('Not authorized. Please authorize first.');
            return false;
        }

        try {
            $this->ensure_token_valid();

            $response = $this->api_call('GET', '/api/v4/account');

            if ($response['success'] && isset($response['response']['id'])) {
                return true;
            }

            $this->add_error($response['error'] ?? 'Connection failed');
            return false;

        } catch (Exception $e) {
            $this->add_error($e->getMessage());
            return false;
        }
    }

    /**
     * Check if authorized
     *
     * @return bool
     */
    public function is_authorized() {
        return !empty($this->get_setting('access_token'));
    }

    /**
     * Get authorization URL
     *
     * @param string $state Optional state parameter
     * @return string
     */
    public function get_authorization_url($state = '') {
        $params = [
            'client_id' => $this->get_setting('client_id'),
            'redirect_uri' => $this->get_setting('redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
        ];

        // OAuth авторизация происходит на основном домене amocrm.ru
        return 'https://www.amocrm.ru/oauth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     *
     * @param string $code
     * @return bool
     */
    public function exchange_code($code) {
        $response = $this->http_request(
            $this->get_api_base_url() . '/oauth2/access_token',
            'POST',
            [
                'client_id' => $this->get_setting('client_id'),
                'client_secret' => $this->get_setting('client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->get_setting('redirect_uri'),
            ]
        );

        if ($response['success'] && isset($response['response']['access_token'])) {
            $this->save_setting('access_token', $response['response']['access_token']);
            $this->save_setting('refresh_token', $response['response']['refresh_token']);
            $this->save_setting('token_expires', time() + $response['response']['expires_in']);
            return true;
        }

        return false;
    }

    /**
     * Ensure access token is valid
     *
     * @throws Exception
     */
    protected function ensure_token_valid() {
        $token_expires = (int)$this->get_setting('token_expires');

        // Refresh if token expires in less than 60 seconds
        if (time() >= $token_expires - 60) {
            $this->refresh_access_token();
        }
    }

    /**
     * Refresh access token
     *
     * @throws Exception
     */
    protected function refresh_access_token() {
        $refresh_token = $this->get_setting('refresh_token');

        if (!$refresh_token) {
            throw new Exception('No refresh token available');
        }

        $response = $this->http_request(
            $this->get_api_base_url() . '/oauth2/access_token',
            'POST',
            [
                'client_id' => $this->get_setting('client_id'),
                'client_secret' => $this->get_setting('client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'redirect_uri' => $this->get_setting('redirect_uri'),
            ]
        );

        if ($response['success'] && isset($response['response']['access_token'])) {
            $this->save_setting('access_token', $response['response']['access_token']);
            $this->save_setting('refresh_token', $response['response']['refresh_token']);
            $this->save_setting('token_expires', time() + $response['response']['expires_in']);
        } else {
            throw new Exception('Failed to refresh token: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Prepare lead data for AmoCRM API
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function prepare_lead_data(nc_crm_integration_lead $lead) {
        $data = [
            'name' => $lead->get_title() ?: 'Заявка с сайта',
        ];

        // Pipeline and status
        if ($pipeline_id = $this->get_setting('pipeline_id')) {
            $data['pipeline_id'] = (int)$pipeline_id;
        }
        if ($status_id = $this->get_setting('status_id')) {
            $data['status_id'] = (int)$status_id;
        }

        // Contact data
        $contact_data = [];

        if ($name = $lead->get('Name')) {
            $contact_data['first_name'] = $name;
        }
        if ($surname = $lead->get('Surname')) {
            $contact_data['last_name'] = $surname;
        }

        // Custom fields for contact
        $custom_fields = [];

        if ($phone = $lead->get('Phone')) {
            $custom_fields[] = $this->make_phone_field($phone);
        }
        if ($email = $lead->get('Email')) {
            $custom_fields[] = $this->make_email_field($email);
        }

        if (!empty($custom_fields)) {
            $contact_data['custom_fields_values'] = $custom_fields;
        }

        if (!empty($contact_data)) {
            $data['_embedded'] = [
                'contacts' => [$contact_data],
            ];
        }

        // Apply custom field mapping from DB
        $mapped_data = $this->get_mapped_data($lead);
        foreach ($mapped_data as $field => $value) {
            if (empty($value)) {
                continue;
            }
            // Handle special fields that go to contact
            if (in_array($field, ['first_name', 'last_name'])) {
                if (!isset($data['_embedded']['contacts'][0])) {
                    $data['_embedded']['contacts'] = [[]];
                }
                $data['_embedded']['contacts'][0][$field] = $value;
            } elseif (in_array($field, ['PHONE', 'EMAIL'])) {
                // Phone/Email are custom fields in contact
                if (!isset($data['_embedded']['contacts'][0])) {
                    $data['_embedded']['contacts'] = [[]];
                }
                if (!isset($data['_embedded']['contacts'][0]['custom_fields_values'])) {
                    $data['_embedded']['contacts'][0]['custom_fields_values'] = [];
                }
                if ($field === 'PHONE') {
                    $data['_embedded']['contacts'][0]['custom_fields_values'][] = $this->make_phone_field($value);
                } else {
                    $data['_embedded']['contacts'][0]['custom_fields_values'][] = $this->make_email_field($value);
                }
            } else {
                // Standard lead field
                $data[$field] = $value;
            }
        }

        // Build UTM tags for lead
        $tags = $this->build_utm_tags($lead);
        if (!empty($tags)) {
            $data['_embedded']['tags'] = $tags;
        }

        return $data;
    }

    /**
     * Build UTM tags for AmoCRM lead
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function build_utm_tags(nc_crm_integration_lead $lead) {
        $tags = [];

        if ($utm_source = $lead->get_utm('source')) {
            $tags[] = ['name' => 'utm_source:' . $utm_source];
        }
        if ($utm_medium = $lead->get_utm('medium')) {
            $tags[] = ['name' => 'utm_medium:' . $utm_medium];
        }
        if ($utm_campaign = $lead->get_utm('campaign')) {
            $tags[] = ['name' => 'utm_campaign:' . $utm_campaign];
        }
        if ($utm_content = $lead->get_utm('content')) {
            $tags[] = ['name' => 'utm_content:' . $utm_content];
        }
        if ($utm_term = $lead->get_utm('term')) {
            $tags[] = ['name' => 'utm_term:' . $utm_term];
        }

        return $tags;
    }

    /**
     * Make phone field for AmoCRM
     *
     * @param string $phone
     * @return array
     */
    protected function make_phone_field($phone) {
        return [
            'field_code' => 'PHONE',
            'values' => [
                ['value' => $this->transform_value($phone, 'phone_format'), 'enum_code' => 'WORK'],
            ],
        ];
    }

    /**
     * Make email field for AmoCRM
     *
     * @param string $email
     * @return array
     */
    protected function make_email_field($email) {
        return [
            'field_code' => 'EMAIL',
            'values' => [
                ['value' => $this->transform_value($email, 'email_format'), 'enum_code' => 'WORK'],
            ],
        ];
    }

    /**
     * Add note to lead with comment and additional info
     *
     * @param int $lead_id
     * @param nc_crm_integration_lead $lead
     */
    protected function add_lead_note($lead_id, nc_crm_integration_lead $lead) {
        $note_parts = [];

        // Add comment/question
        if ($comment = $lead->get('Comment')) {
            $note_parts[] = $comment;
        }

        // Add source info
        if ($referer = $lead->get_metadata('Referer')) {
            $note_parts[] = "Источник: " . $referer;
        }

        // Add UTM info
        $utm_info = [];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $utm_param) {
            if ($value = $lead->get_utm($utm_param)) {
                $utm_info[] = "utm_{$utm_param}: {$value}";
            }
        }
        if (!empty($utm_info)) {
            $note_parts[] = "UTM метки:\n" . implode("\n", $utm_info);
        }

        if (empty($note_parts)) {
            return;
        }

        $note_text = implode("\n\n", $note_parts);

        // Create note via API
        $this->api_call('POST', "/api/v4/leads/{$lead_id}/notes", [
            [
                'note_type' => 'common',
                'params' => [
                    'text' => $note_text,
                ],
            ],
        ]);
    }

    /**
     * Make API call to AmoCRM
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
        ];

        return $this->http_request($url, $method, $data, $headers);
    }

    /**
     * Get pipelines from AmoCRM
     *
     * @return array
     */
    public function get_pipelines() {
        try {
            $this->ensure_token_valid();
            $response = $this->api_call('GET', '/api/v4/leads/pipelines');

            if ($response['success'] && isset($response['response']['_embedded']['pipelines'])) {
                $pipelines = [];
                foreach ($response['response']['_embedded']['pipelines'] as $pipeline) {
                    $pipelines[$pipeline['id']] = [
                        'name' => $pipeline['name'],
                        'statuses' => [],
                    ];
                    foreach ($pipeline['_embedded']['statuses'] as $status) {
                        $pipelines[$pipeline['id']]['statuses'][$status['id']] = $status['name'];
                    }
                }
                return $pipelines;
            }
        } catch (Exception $e) {
            // Silent fail
        }

        return [];
    }
}
