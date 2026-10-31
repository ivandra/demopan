<?php

/**
 * Сниппет Jivo
 */
class nc_external_scripts_snippet_jivo extends nc_external_scripts_snippet {

    protected $type = 'jivo';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_FUNCTIONAL;
    }

    protected static $default_settings = [
        'widget_id' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_body_end(): string {
        $id = $this->get_setting('widget_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Jivo -->
<script src="//code.jivo.ru/widget/{$id}" async></script>
<!-- End Jivo -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'widget_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID,
                'required' => true,
                'description' => NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('widget_id');
        return !empty($id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('widget_id');

        if (empty($id)) {
            $errors['widget_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        }

        return $errors;
    }
}
