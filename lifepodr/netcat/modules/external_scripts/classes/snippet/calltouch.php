<?php

/**
 * Сниппет Calltouch (онлайн-чат и коллтрекинг)
 */
class nc_external_scripts_snippet_calltouch extends nc_external_scripts_snippet {

    protected $type = 'calltouch';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_ANALYTICS;
    }

    protected static $default_settings = [
        'mod_id' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        $id = $this->get_setting('mod_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- Calltouch -->
<script type="text/javascript">
(function(w,d,n,c){w.CalltouchDataObject=n;w[n]=function(){w[n]["callbacks"].push(arguments)};
if(!w[n]["callbacks"]){w[n]["callbacks"]=[]}w[n]["loaded"]=false;if(typeof c!=="object"){c=[c]}
w[n]["counters"]=c;for(var i=0;i<c.length;i+=1){p(c[i])}function p(cId){var a=d.getElementsByTagName("script")[0],
s=d.createElement("script"),i=function(){a.parentNode.insertBefore(s,a)};s.type="text/javascript";
s.async=true;s.src="https://mod.calltouch.ru/init.js?id="+cId;if(w.opera=="[object Opera]"){d.addEventListener("DOMContentLoaded",i,false)}else{i()}}})(window,document,"ct","{$id}");
</script>
<!-- End Calltouch -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'mod_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID,
                'required' => true,
                'placeholder' => 'xxxxxxxxxx',
                'description' => NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('mod_id');
        return !empty($id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('mod_id');

        if (empty($id)) {
            $errors['mod_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        }

        return $errors;
    }
}
