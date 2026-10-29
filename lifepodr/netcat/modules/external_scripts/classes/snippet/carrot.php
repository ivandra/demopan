<?php

/**
 * Сниппет Carrot Quest
 */
class nc_external_scripts_snippet_carrot extends nc_external_scripts_snippet {

    protected $type = 'carrot_quest';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_FUNCTIONAL;
    }

    protected static $default_settings = [
        'app_id' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_body_end(): string {
        $id = $this->get_setting('app_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Carrot Quest -->
<script type="text/javascript">
!function(){function t(t,e){return function(){window.carrotquestasync.push(t,arguments)}}if("undefined"==typeof carrotquest){var e=document.createElement("script");e.type="text/javascript",e.async=!0,e.src="//cdn.carrotquest.app/api.min.js",document.getElementsByTagName("head")[0].appendChild(e),window.carrotquest={},window.carrotquestasync=[],carrotquest.settings={};for(var n=["connect","track","identify","auth","498","onReady","addCallback","removeCallback","trackMessageInteraction"],a=0;a<n.length;a++)carrotquest[n[a]]=t(n[a])}}(),carrotquest.connect("{$id}");
</script>
<!-- End Carrot Quest -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'app_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID,
                'required' => true,
                'description' => NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('app_id');
        return !empty($id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('app_id');

        if (empty($id)) {
            $errors['app_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        }

        return $errors;
    }
}
