<?php

/**
 * Сниппет произвольного кода
 */
class nc_external_scripts_snippet_custom extends nc_external_scripts_snippet {

    protected $type = 'custom';

    protected static $default_settings = [
        'code_head_start' => '',
        'code_head_end' => '',
        'code_body_start' => '',
        'code_body_end' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_start(): string {
        return $this->get_setting('code_head_start') ?: '';
    }

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        return $this->get_setting('code_head_end') ?: '';
    }

    /**
     * @inheritdoc
     */
    public function get_code_body_start(): string {
        return $this->get_setting('code_body_start') ?: '';
    }

    /**
     * @inheritdoc
     */
    public function get_code_body_end(): string {
        return $this->get_setting('code_body_end') ?: '';
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'code_head_start' => [
                'type' => 'textarea',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START,
                'rows' => 5,
                'description' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START_DESC,
            ],
            'code_head_end' => [
                'type' => 'textarea',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END,
                'rows' => 5,
                'description' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END_DESC,
            ],
            'code_body_start' => [
                'type' => 'textarea',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START,
                'rows' => 5,
                'description' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START_DESC,
            ],
            'code_body_end' => [
                'type' => 'textarea',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END,
                'rows' => 5,
                'description' => NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        // Хотя бы одно поле должно быть заполнено
        return !empty($this->get_setting('code_head_start')) ||
               !empty($this->get_setting('code_head_end')) ||
               !empty($this->get_setting('code_body_start')) ||
               !empty($this->get_setting('code_body_end'));
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        if (!$this->validate_settings()) {
            return ['general' => NETCAT_MODULE_EXTSCRIPTS_ERROR_CUSTOM_EMPTY];
        }
        return [];
    }
}
