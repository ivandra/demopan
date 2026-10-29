<?php
/**
 * CRM Integration Module - Universal Webhook Provider
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_provider_webhook extends nc_crm_integration_provider {

    /** @var array Default settings */
    protected $settings = [
        'webhook_url' => null,
        'http_method' => 'POST',
        'content_type' => 'application/json',
        'custom_headers' => null,
        'body_template' => null,
        'success_code' => '200',
        'external_id_path' => null,
        'auth_type' => 'none',
        'auth_header' => null,
        'auth_value' => null,
    ];

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return defined('NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_WEBHOOK')
            ? NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_WEBHOOK
            : 'Webhook';
    }

    /**
     * Get settings list
     *
     * @return array
     */
    public function get_settings_list() {
        return [
            'webhook_url' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_URL')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_URL
                    : 'URL',
                'type' => 'text',
                'required' => true,
            ],
            'http_method' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_METHOD')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_METHOD
                    : 'HTTP метод',
                'type' => 'select',
                'options' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH'],
                'required' => true,
            ],
            'content_type' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_CONTENT_TYPE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_CONTENT_TYPE
                    : 'Content-Type',
                'type' => 'select',
                'options' => [
                    'application/json' => 'JSON',
                    'application/x-www-form-urlencoded' => 'Form URL-encoded',
                ],
                'required' => true,
            ],
            'custom_headers' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_HEADERS')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_HEADERS
                    : 'Заголовки (JSON)',
                'type' => 'textarea',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_HEADERS_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_HEADERS_HINT
                    : 'JSON-объект с заголовками, например: {"X-Api-Key": "abc123"}',
                'required' => false,
            ],
            'body_template' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_BODY')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_BODY
                    : 'Шаблон тела запроса',
                'type' => 'textarea',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_BODY_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_BODY_HINT
                    : 'JSON-шаблон с плейсхолдерами {{Name}}, {{Phone}}. Оставьте пустым для формата по умолчанию.',
                'required' => false,
            ],
            'success_code' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_SUCCESS_CODES')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_SUCCESS_CODES
                    : 'Успешные HTTP-коды',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_SUCCESS_CODES_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_SUCCESS_CODES_HINT
                    : 'HTTP-коды через запятую (например: 200,201,204)',
                'required' => false,
            ],
            'external_id_path' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_EXTERNAL_ID')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_EXTERNAL_ID
                    : 'JSON-путь к ID',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_EXTERNAL_ID_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_EXTERNAL_ID_HINT
                    : 'Путь к внешнему ID в ответе (например: data.id)',
                'required' => false,
            ],
            'auth_type' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_TYPE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_TYPE
                    : 'Тип авторизации',
                'type' => 'select',
                'options' => [
                    'none' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_NONE') ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_NONE : 'Без авторизации',
                    'bearer' => 'Bearer Token',
                    'basic' => 'Basic Auth',
                    'custom' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_CUSTOM') ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_CUSTOM : 'Свой заголовок',
                ],
                'required' => false,
            ],
            'auth_header' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_HEADER')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_HEADER
                    : 'Имя заголовка авторизации',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_HEADER_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_HEADER_HINT
                    : 'Имя заголовка (например: X-Api-Key)',
                'required' => false,
            ],
            'auth_value' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_VALUE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_VALUE
                    : 'Значение авторизации',
                'type' => 'password',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_VALUE_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_WEBHOOK_AUTH_VALUE_HINT
                    : 'Токен или учётные данные',
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
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'message' => 'Message',
            'company' => 'Company',
            'source' => 'Source URL',
        ];
    }

    /**
     * Send lead via webhook
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

        // Prepare request
        $url = $this->get_setting('webhook_url');
        $method = $this->get_setting('http_method', 'POST');
        $content_type = $this->get_setting('content_type', 'application/json');

        // Build headers
        $headers = $this->build_headers($content_type);

        // Build body
        $body = $this->build_body($lead, $content_type);

        // Make request
        $response = $this->http_request($url, $method, $body, $headers);

        // Check success
        $success_codes = array_map('trim', explode(',', $this->get_setting('success_code', '200,201,204')));
        $is_success = in_array((string)$response['http_code'], $success_codes);

        if ($is_success) {
            $external_id = $this->extract_external_id($response['response']);

            return [
                'success' => true,
                'external_id' => $external_id,
                'error' => null,
                'response' => $response['response'],
            ];
        }

        $error = $response['error'] ?? "HTTP {$response['http_code']}";

        // Try to extract error message from response
        if (is_array($response['response'])) {
            if (isset($response['response']['error'])) {
                $error = $response['response']['error'];
            } elseif (isset($response['response']['message'])) {
                $error = $response['response']['message'];
            }
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
     * @return bool
     */
    public function validate_settings() {
        $webhook_url = $this->get_setting('webhook_url');

        if (empty($webhook_url)) {
            $this->add_error('Webhook URL is required');
            return false;
        }

        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            $this->add_error('Invalid Webhook URL format');
            return false;
        }

        // Validate custom headers JSON if provided
        $custom_headers = $this->get_setting('custom_headers');
        if (!empty($custom_headers)) {
            $decoded = json_decode($custom_headers, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->add_error('Custom headers must be valid JSON');
                return false;
            }
        }

        // Validate body template JSON if provided
        $body_template = $this->get_setting('body_template');
        if (!empty($body_template)) {
            $decoded = json_decode($body_template, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->add_error('Body template must be valid JSON');
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

        // Create test lead
        $test_lead = nc_crm_integration_lead::from_array([
            'id' => 0,
            'site_id' => $this->site_id,
            'component_id' => 0,
            'source_type' => 'test',
            'fields' => [
                'Name' => 'Test',
                'Email' => 'test@example.com',
                'Phone' => '+70000000000',
                'Comment' => 'Test connection',
            ],
        ]);

        $result = $this->send_lead($test_lead);

        if (!$result['success']) {
            $this->add_error($result['error'] ?? 'Connection test failed');
            return false;
        }

        return true;
    }

    /**
     * Build request headers
     *
     * @param string $content_type
     * @return array
     */
    protected function build_headers($content_type) {
        $headers = [
            "Content-Type: $content_type",
            'Accept: application/json',
        ];

        // Add authentication
        $auth_type = $this->get_setting('auth_type', 'none');
        $auth_value = $this->get_setting('auth_value');

        switch ($auth_type) {
            case 'bearer':
                if ($auth_value) {
                    $headers[] = "Authorization: Bearer $auth_value";
                }
                break;

            case 'basic':
                if ($auth_value) {
                    $headers[] = "Authorization: Basic " . base64_encode($auth_value);
                }
                break;

            case 'custom':
                $auth_header = $this->get_setting('auth_header');
                if ($auth_header && $auth_value) {
                    $headers[] = "$auth_header: $auth_value";
                }
                break;
        }

        // Add custom headers
        $custom_headers = $this->get_setting('custom_headers');
        if (!empty($custom_headers)) {
            $parsed = json_decode($custom_headers, true);
            if (is_array($parsed)) {
                foreach ($parsed as $name => $value) {
                    $headers[] = "$name: $value";
                }
            }
        }

        return $headers;
    }

    /**
     * Build request body
     *
     * @param nc_crm_integration_lead $lead
     * @param string $content_type
     * @return array|string
     */
    protected function build_body(nc_crm_integration_lead $lead, $content_type) {
        $body_template = $this->get_setting('body_template');

        if (!empty($body_template)) {
            // Use custom template
            $body = $this->process_template($body_template, $lead);
        } else {
            // Default body structure
            $body = $this->build_default_body($lead);
        }

        if ($content_type === 'application/x-www-form-urlencoded') {
            return http_build_query($body);
        }

        return $body;
    }

    /**
     * Build default body structure
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function build_default_body(nc_crm_integration_lead $lead) {
        $body = [
            'id' => $lead->get_id(),
            'source' => 'netcat',
            'source_type' => $lead->get_source_type(),
            'created_at' => date('c'),
        ];

        // Apply custom field mapping from DB
        $mapped_data = $this->get_mapped_data($lead);

        // Add mapped fields (with transformations applied)
        $body['fields'] = $mapped_data;

        // Also include raw fields for backward compatibility
        $body['raw_fields'] = $lead->get_fields();

        // Add UTM
        $utm = $lead->get_all_utm();
        if (!empty($utm)) {
            $body['utm'] = $utm;
        }

        // Add metadata
        $body['metadata'] = [
            'site_id' => $lead->get_site_id(),
            'component_id' => $lead->get_component_id(),
            'ip' => $lead->get_metadata('IP'),
            'referer' => $lead->get_metadata('Referer'),
        ];

        return $body;
    }

    /**
     * Process template with placeholders
     *
     * @param string $template
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function process_template($template, nc_crm_integration_lead $lead) {
        // Replace placeholders
        $replacements = [];

        // Apply mapped fields (with transformations)
        $mapped_data = $this->get_mapped_data($lead);
        foreach ($mapped_data as $field => $value) {
            $replacements["{{" . $field . "}}"] = $value;
        }

        // Raw fields (for backward compatibility, without transformations)
        foreach ($lead->get_fields() as $field => $value) {
            $replacements["{{raw_" . $field . "}}"] = $value;
            // Also set original field name if not already set by mapping
            if (!isset($replacements["{{" . $field . "}}"])) {
                $replacements["{{" . $field . "}}"] = $value;
            }
        }

        // UTM
        foreach ($lead->get_all_utm() as $key => $value) {
            $replacements["{{utm_" . $key . "}}"] = $value;
        }

        // Metadata
        $replacements['{{id}}'] = $lead->get_id();
        $replacements['{{site_id}}'] = $lead->get_site_id();
        $replacements['{{component_id}}'] = $lead->get_component_id();
        $replacements['{{source_type}}'] = $lead->get_source_type();
        $replacements['{{title}}'] = $lead->get_title();
        $replacements['{{ip}}'] = $lead->get_metadata('IP') ?? '';
        $replacements['{{referer}}'] = $lead->get_metadata('Referer') ?? '';
        $replacements['{{created_at}}'] = date('c');

        $processed = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        // Clean up remaining placeholders
        $processed = preg_replace('/\{\{[^}]+\}\}/', '', $processed);

        return json_decode($processed, true) ?: [];
    }

    /**
     * Extract external ID from response
     *
     * @param mixed $response
     * @return string|null
     */
    protected function extract_external_id($response) {
        $path = $this->get_setting('external_id_path');

        if (empty($path) || !is_array($response)) {
            return null;
        }

        // Navigate JSON path (e.g. "data.id")
        $parts = explode('.', $path);
        $current = $response;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return is_scalar($current) ? (string)$current : null;
    }

    /**
     * Override HTTP request to handle form-urlencoded
     *
     * @param string $url
     * @param string $method
     * @param array|string $data
     * @param array $headers
     * @return array
     */
    protected function http_request($url, $method = 'POST', $data = [], $headers = []) {
        $ch = curl_init();

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
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (!empty($data)) {
            // Check content type from headers
            $is_json = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Type: application/json') !== false) {
                    $is_json = true;
                    break;
                }
            }

            if ($is_json && is_array($data)) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
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
}
