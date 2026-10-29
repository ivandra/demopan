<?php

/**
 * Сниппет Google Tag Manager
 */
class nc_external_scripts_snippet_google_gtm extends nc_external_scripts_snippet {

    protected $type = 'google_tag_manager';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_MARKETING;
    }

    protected static $default_settings = [
        'container_id' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        $id = $this->get_setting('container_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$id}');</script>
<!-- End Google Tag Manager -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_code_body_start(): string {
        $id = $this->get_setting('container_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'container_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID,
                'required' => true,
                'placeholder' => 'GTM-XXXXXXX',
                'description' => NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('container_id');
        return !empty($id) && preg_match('/^GTM-[A-Z0-9]+$/i', $id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('container_id');

        if (empty($id)) {
            $errors['container_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_REQUIRED;
        } elseif (!preg_match('/^GTM-[A-Z0-9]+$/i', $id)) {
            $errors['container_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_FORMAT;
        }

        return $errors;
    }
}
