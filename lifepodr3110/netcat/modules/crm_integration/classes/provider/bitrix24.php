<?php
/**
 * CRM Integration Module - Bitrix24 Provider
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class nc_crm_integration_provider_bitrix24 extends nc_crm_integration_provider {

    /** @var array Default settings */
    protected $settings = [
        'webhook_url' => null,
        'default_source_id' => 'WEB',
        'default_status_id' => 'NEW',
        'assigned_by_id' => null,
        'create_contact' => '0',
    ];

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return defined('NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_BITRIX24')
            ? NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_BITRIX24
            : 'Bitrix24';
    }

    /**
     * Get settings list
     *
     * @return array
     */
    public function get_settings_list() {
        return [
            'webhook_url' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_WEBHOOK_URL')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_WEBHOOK_URL
                    : 'Webhook URL',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_WEBHOOK_URL_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_WEBHOOK_URL_HINT
                    : 'Example: https://xxx.bitrix24.ru/rest/1/xxx/',
                'required' => true,
            ],
            'default_source_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_SOURCE')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_SOURCE
                    : 'Источник по умолчанию',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_SOURCE_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_SOURCE_HINT
                    : 'WEB, CALL, EMAIL и т.д.',
                'required' => false,
            ],
            'default_status_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_STATUS')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_STATUS
                    : 'Статус по умолчанию',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_STATUS_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_DEFAULT_STATUS_HINT
                    : 'NEW, IN_PROCESS и т.д.',
                'required' => false,
            ],
            'assigned_by_id' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_ASSIGNED_BY')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_ASSIGNED_BY
                    : 'Ответственный (ID)',
                'type' => 'text',
                'hint' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_ASSIGNED_BY_HINT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_ASSIGNED_BY_HINT
                    : 'ID пользователя в Битрикс24',
                'required' => false,
            ],
            'create_contact' => [
                'label' => defined('NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_CREATE_CONTACT')
                    ? NETCAT_MODULE_CRM_INTEGRATION_BITRIX24_CREATE_CONTACT
                    : 'Создавать контакт вместе с лидом',
                'type' => 'checkbox',
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
            'TITLE' => 'Название лида',
            'NAME' => 'Имя',
            'SECOND_NAME' => 'Отчество',
            'LAST_NAME' => 'Фамилия',
            'COMPANY_TITLE' => 'Компания',
            'PHONE' => 'Телефон',
            'EMAIL' => 'E-mail',
            'WEB' => 'Сайт',
            'COMMENTS' => 'Комментарий',
            'SOURCE_DESCRIPTION' => 'Описание источника',
            'POST' => 'Должность',
            'ADDRESS' => 'Адрес',
            'ADDRESS_CITY' => 'Город',
            'ADDRESS_POSTAL_CODE' => 'Индекс',
            'UTM_SOURCE' => 'UTM Source',
            'UTM_MEDIUM' => 'UTM Medium',
            'UTM_CAMPAIGN' => 'UTM Campaign',
            'UTM_CONTENT' => 'UTM Content',
            'UTM_TERM' => 'UTM Term',
        ];
    }

    /**
     * Send lead to Bitrix24
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

        // Prepare lead fields
        $fields = $this->prepare_lead_fields($lead);

        // API call
        $response = $this->api_call('crm.lead.add', [
            'fields' => $fields,
            'params' => ['REGISTER_SONET_EVENT' => 'Y'],
        ]);

        if ($response['success'] && isset($response['response']['result'])) {
            return [
                'success' => true,
                'external_id' => (string)$response['response']['result'],
                'error' => null,
                'response' => $response['response'],
            ];
        }

        $error = $response['error']
            ?? $response['response']['error_description']
            ?? $response['response']['error']
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
        $webhook_url = $this->get_setting('webhook_url');

        if (empty($webhook_url)) {
            $this->add_error('Webhook URL is required');
            return false;
        }

        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            $this->add_error('Invalid Webhook URL format');
            return false;
        }

        // Check URL format (should contain /rest/)
        if (strpos($webhook_url, '/rest/') === false) {
            $this->add_error('Webhook URL should contain /rest/ path');
            return false;
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

        $response = $this->api_call('crm.lead.fields');

        if ($response['success'] && isset($response['response']['result'])) {
            return true;
        }

        $this->add_error($response['error'] ?? 'Connection failed');
        return false;
    }

    /**
     * Prepare lead fields for Bitrix24 API
     *
     * @param nc_crm_integration_lead $lead
     * @return array
     */
    protected function prepare_lead_fields(nc_crm_integration_lead $lead) {
        $fields = [];

        // Title
        $fields['TITLE'] = $lead->get_title() ?: 'Заявка с сайта';

        // Name fields
        if ($name = $lead->get('Name')) {
            $fields['NAME'] = $name;
        }
        if ($surname = $lead->get('Surname')) {
            $fields['LAST_NAME'] = $surname;
        }
        if ($patronymic = $lead->get('Patronymic')) {
            $fields['SECOND_NAME'] = $patronymic;
        }

        // Phone (Bitrix24 expects array of phone objects)
        if ($phone = $lead->get('Phone')) {
            $fields['PHONE'] = [
                ['VALUE' => $this->transform_value($phone, 'phone_format'), 'VALUE_TYPE' => 'WORK']
            ];
        }

        // Email (Bitrix24 expects array of email objects)
        if ($email = $lead->get('Email')) {
            $fields['EMAIL'] = [
                ['VALUE' => $this->transform_value($email, 'email_format'), 'VALUE_TYPE' => 'WORK']
            ];
        }

        // Company
        if ($company = $lead->get('Company')) {
            $fields['COMPANY_TITLE'] = $company;
        }

        // Comment
        if ($comment = $lead->get('Comment')) {
            $fields['COMMENTS'] = $comment;
        }

        // Source info
        $fields['SOURCE_ID'] = $this->get_setting('default_source_id', 'WEB');
        $fields['STATUS_ID'] = $this->get_setting('default_status_id', 'NEW');

        // Assigned user
        if ($assigned = $this->get_setting('assigned_by_id')) {
            $fields['ASSIGNED_BY_ID'] = (int)$assigned;
        }

        // UTM fields
        if ($utm_source = $lead->get_utm('source')) {
            $fields['UTM_SOURCE'] = $utm_source;
        }
        if ($utm_medium = $lead->get_utm('medium')) {
            $fields['UTM_MEDIUM'] = $utm_medium;
        }
        if ($utm_campaign = $lead->get_utm('campaign')) {
            $fields['UTM_CAMPAIGN'] = $utm_campaign;
        }
        if ($utm_content = $lead->get_utm('content')) {
            $fields['UTM_CONTENT'] = $utm_content;
        }
        if ($utm_term = $lead->get_utm('term')) {
            $fields['UTM_TERM'] = $utm_term;
        }

        // Source description (page URL)
        $referer = $lead->get_metadata('Referer');
        if ($referer) {
            $fields['SOURCE_DESCRIPTION'] = $referer;
        }

        // Apply custom mapping
        $mapped_data = $this->get_mapped_data($lead);
        foreach ($mapped_data as $field => $value) {
            // Don't override standard fields with empty values
            if (!empty($value) || !isset($fields[$field])) {
                $fields[$field] = $value;
            }
        }

        // Remove empty values but keep arrays
        return array_filter($fields, function($value) {
            return $value !== null && $value !== '' && $value !== [];
        });
    }

    /**
     * Make API call to Bitrix24
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    protected function api_call($method, array $params = []) {
        $webhook_url = rtrim($this->get_setting('webhook_url'), '/');
        $url = $webhook_url . '/' . $method;

        return $this->http_request($url, 'POST', $params);
    }

    /**
     * Get lead statuses from Bitrix24
     *
     * @return array
     */
    public function get_lead_statuses() {
        $response = $this->api_call('crm.status.list', [
            'filter' => ['ENTITY_ID' => 'STATUS'],
        ]);

        if ($response['success'] && isset($response['response']['result'])) {
            $statuses = [];
            foreach ($response['response']['result'] as $status) {
                $statuses[$status['STATUS_ID']] = $status['NAME'];
            }
            return $statuses;
        }

        return [];
    }

    /**
     * Get lead sources from Bitrix24
     *
     * @return array
     */
    public function get_lead_sources() {
        $response = $this->api_call('crm.status.list', [
            'filter' => ['ENTITY_ID' => 'SOURCE'],
        ]);

        if ($response['success'] && isset($response['response']['result'])) {
            $sources = [];
            foreach ($response['response']['result'] as $source) {
                $sources[$source['STATUS_ID']] = $source['NAME'];
            }
            return $sources;
        }

        return [];
    }
}
