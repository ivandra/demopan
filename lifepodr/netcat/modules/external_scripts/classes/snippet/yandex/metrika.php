<?php

/**
 * Сниппет Яндекс.Метрики
 */
class nc_external_scripts_snippet_yandex_metrika extends nc_external_scripts_snippet {

    protected $type = 'yandex_metrika';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_ANALYTICS;
    }

    protected static $default_settings = [
        'counter_id' => '',
        'clickmap' => true,
        'trackLinks' => true,
        'accurateTrackBounce' => true,
        'webvisor' => false,
        'trackHash' => false,
        'ecommerce' => false,
        'ecommerce_container' => 'dataLayer',
        'enable_events' => false,
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        $id = (int)$this->get_setting('counter_id');
        if (!$id) {
            return '';
        }

        $options = [
            'clickmap' => (bool)$this->get_setting('clickmap'),
            'trackLinks' => (bool)$this->get_setting('trackLinks'),
            'accurateTrackBounce' => (bool)$this->get_setting('accurateTrackBounce'),
            'webvisor' => (bool)$this->get_setting('webvisor'),
            'trackHash' => (bool)$this->get_setting('trackHash'),
        ];

        if ($this->get_setting('ecommerce')) {
            $container = $this->get_setting('ecommerce_container') ?: 'dataLayer';
            $options['ecommerce'] = $container;
        }

        $options_json = json_encode($options, JSON_UNESCAPED_UNICODE);

        return <<<HTML
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
   m[i].l=1*new Date();
   for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
   k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
   (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
   ym({$id}, "init", {$options_json});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/{$id}" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'counter_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID,
                'required' => true,
                'placeholder' => '12345678',
                'description' => NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID_DESC,
            ],
            'webvisor' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR,
                'description' => NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR_DESC,
            ],
            'clickmap' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_CLICKMAP,
                'default' => true,
            ],
            'trackLinks' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_TRACKLINKS,
                'default' => true,
            ],
            'accurateTrackBounce' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_ACCURATE_BOUNCE,
                'default' => true,
            ],
            'trackHash' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_TRACKHASH,
            ],
            'ecommerce' => [
                'type' => 'checkbox',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE,
                'description' => NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_DESC,
            ],
            'ecommerce_container' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_CONTAINER,
                'default' => 'dataLayer',
                'depends_on' => 'ecommerce',
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
        $id = $this->get_setting('counter_id');
        return !empty($id) && is_numeric($id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('counter_id');

        if (empty($id)) {
            $errors['counter_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        } elseif (!is_numeric($id)) {
            $errors['counter_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_NUMERIC;
        }

        return $errors;
    }
}
