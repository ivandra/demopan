<?php

/**
 * Сниппет Google Analytics 4
 */
class nc_external_scripts_snippet_google_analytics4 extends nc_external_scripts_snippet {

    protected $type = 'google_analytics';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_ANALYTICS;
    }

    protected static $default_settings = [
        'measurement_id' => '',
        'enable_events' => false,
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        $id = $this->get_setting('measurement_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$id}');
</script>
<!-- End Google Analytics 4 -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'measurement_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID,
                'required' => true,
                'placeholder' => 'G-XXXXXXXXXX',
                'description' => NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID_DESC,
            ],
            'enable_events' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS,
                'description' => NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function needs_analytics_event_script(): bool {
        return (bool)$this->get_setting('enable_events');
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('measurement_id');
        return !empty($id) && preg_match('/^G-[A-Z0-9]+$/i', $id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('measurement_id');

        if (empty($id)) {
            $errors['measurement_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        } elseif (!preg_match('/^G-[A-Z0-9]+$/i', $id)) {
            $errors['measurement_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_FORMAT;
        }

        return $errors;
    }
}
