<?php

/**
 * Сниппет VK Pixel
 */
class nc_external_scripts_snippet_vk_pixel extends nc_external_scripts_snippet {

    protected $type = 'vk_pixel';

    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_MARKETING;
    }

    protected static $default_settings = [
        'pixel_id' => '',
    ];

    /**
     * @inheritdoc
     */
    public function get_code_head_end(): string {
        $id = $this->get_setting('pixel_id');
        if (!$id) {
            return '';
        }

        $id = htmlspecialchars($id, ENT_QUOTES);

        return <<<HTML
<!-- VK Pixel (Top.Mail.Ru counter) -->
<script type="text/javascript">
var _tmr = window._tmr || (window._tmr = []);
_tmr.push({id: "{$id}", type: "pageView", start: (new Date()).getTime()});
(function (d, w, id) {
  if (d.getElementById(id)) return;
  var ts = d.createElement("script"); ts.type = "text/javascript"; ts.async = true; ts.id = id;
  ts.src = "https://top-fwz1.mail.ru/js/code.js";
  var f = function () {var s = d.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ts, s);};
  if (w.opera == "[object Opera]") { d.addEventListener("DOMContentLoaded", f, false); } else { f(); }
})(document, window, "tmr-code");
</script>
<noscript><div><img src="https://top-fwz1.mail.ru/counter?id={$id};js=na" style="position:absolute;left:-9999px;" alt="Top.Mail.Ru" /></div></noscript>
<!-- /VK Pixel -->
HTML;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_fields(): array {
        return [
            'pixel_id' => [
                'type' => 'text',
                'label' => NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID,
                'required' => true,
                'description' => NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID_DESC,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function validate_settings(): bool {
        $id = $this->get_setting('pixel_id');
        return !empty($id);
    }

    /**
     * @inheritdoc
     */
    public function get_validation_errors(): array {
        $errors = [];
        $id = $this->get_setting('pixel_id');

        if (empty($id)) {
            $errors['pixel_id'] = NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED;
        }

        return $errors;
    }
}
