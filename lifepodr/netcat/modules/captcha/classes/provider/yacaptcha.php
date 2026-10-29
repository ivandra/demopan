<?php

/**
 * Яндекс SmartCaptcha
 *
 * Настройки в Settings:
 *   YaCaptcha_ClientKey — ключ клиента (client key)
 *   YaCaptcha_ServerKey — ключ сервера (server key)
 *   RemovedLegacyText — текст, который убирается из формы
 *   RemovedLegacyBlocks — блоки, которые убираются из формы
 *
 */
class nc_captcha_provider_yacaptcha extends nc_captcha_provider {

    const VERIFY_URL = 'https://smartcaptcha.yandexcloud.net/validate';

    /** @var int таймаут ожидания ответа от yandex в секундах */
    protected $request_timeout = 10;

    /** @var int время жизни кэша ответов в секундах */
    protected $cache_lifetime = 300;

    /** @var array локальный кэш результатов */
    protected $result_cache = array();

    /** @var bool */
    protected $script_emitted = false;

    /**
     * @return null
     */
    public function generate_challenge_id() {
        return null;
    }

    /**
     * @param null $challenge_id
     * @return null
     */
    public function generate_challenge($challenge_id = null) {
        return null;
    }

    /**
     * @return null
     */
    public function get_new_challenge_data() {
        return null;
    }

    /**
     * Возвращает код для вставки в форму.
     *
     * @param string $challenge_id
     * @param array $parameters
     *     — context => контекст вызова (js_template — для nc_comments::wall())
     *     – hl => код языка
     * @return string
     */
    public function get_challenge_html($challenge_id = null, array $parameters = array()) {
        $attributes = array(
            'class' => 'smart-captcha',
            'data-sitekey' => $this->get_setting('YaCaptcha_ClientKey'),
            'data-callback' => 'nc_yacaptcha_save',
        );

        foreach ($parameters as $key => $value) {
            if (substr($key, 0, 5) === 'data-') {
                $attributes[$key] = $value;
            }
        }

        $data_attributes_string = nc_make_attribute_string_from_array($attributes);

        return
            "<div " . $data_attributes_string['result'] . "></div>" .
            $this->get_challenge_js($parameters);
    }

    /**
     * @param array $parameters
     * @return string
     */
    protected function get_challenge_js(array $parameters) {
        if (nc_array_value($parameters, 'context') !== 'js_template') {
            if ($this->script_emitted) {
                return '';
            }
            $this->script_emitted = true;
        }

        $js = '<script>' .
               $this->get_remove_legacy_markup_js() .
               $this->get_js_file('nc_yacaptcha.min.js') .
               '</script>';

        $js = str_replace('"', "'", $js);

        $hl = nc_array_value($parameters, 'hl') ?: nc_core::get_object()->subdivision->get_current('Language') ?: 'ru';

        $js .= "<script src='https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=nc_yacaptcha_render&hl=$hl' async></script>";

        return $js;
    }

    /**
     * Проверяет ответ пользователя.
     *
     * @param string $user_response
     * @param string $challenge_id
     * @param bool $invalidate_challenge
     * @return bool
     */
    public function verify_user_response($user_response, $challenge_id = null, $invalidate_challenge = true) {
        $result = $this->get_verification_result($user_response, !$invalidate_challenge);
        if ($invalidate_challenge) {
            $this->delete_cached_verification_result($user_response);
        }
        return !empty($result['status']) && $result['status'] === 'ok';
    }

    /**
     * @param string $user_response
     * @param bool $should_cache_result
     * @return array
     */
    protected function get_verification_result($user_response, $should_cache_result) {
        $cached_verification_result = $this->get_cached_verification_result($user_response);
        $verification_result = $cached_verification_result ?: $this->make_verification_request($user_response);

        if (!$verification_result) {
            return array('status' => 'failed', 'message' => 'netcat-no-response');
        }

        $result = json_decode($verification_result, true);
        if (!$result) {
            return array('status' => 'failed', 'message' => 'netcat-json-error');
        }

        if (!$cached_verification_result && $should_cache_result && !empty($result['status']) && $result['status'] === 'ok') {
            $this->cache_verification_result($user_response, $verification_result);
        }

        return $result;
    }

    /**
     * Выполняет запрос к Яндекс SmartCaptcha для проверки ответа.
     *
     * @param $user_response
     * @return string|false
     */
    protected function make_verification_request($user_response) {
        $data = array(
            'secret' => $this->get_setting('YaCaptcha_ServerKey'),
            'token' => $user_response,
            'ip' => $_SERVER['REMOTE_ADDR'],
        );

        $url = self::VERIFY_URL . '?' . http_build_query($data, '', '&');

        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            // Проверяем TLS-сертификат сервера капчи: иначе MITM может подделать
            // ответ {"status":"ok"} и обойти капчу. Эндпоинт Яндекса имеет валидный
            // сертификат, проверка по системному CA-бандлу работает штатно.
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Netcat CMS',
            CURLOPT_TIMEOUT => $this->request_timeout,
        ));

        $response = curl_exec($curl);

        return $response;
    }

    /**
     * @param string $user_response
     * @param string $result
     */
    protected function cache_verification_result($user_response, $result) {
        if (!$user_response) {
            return;
        }

        $this->result_cache[$user_response] = $result;

        $db = nc_core::get_object()->db;
        $db->query(
            "INSERT INTO `Captchas_Recaptcha`
                SET `Response` = '" . $db->escape($user_response) . "',
                    `Result` = '" . $db->escape($result) . "',
                    `Expires` = DATE_ADD(NOW(), INTERVAL $this->cache_lifetime SECOND)"
        );
    }

    /**
     * @param string $user_response
     * @return string|null
     */
    protected function get_cached_verification_result($user_response) {
        if (!$user_response) {
            return null;
        }

        if (isset($this->result_cache[$user_response])) {
            return $this->result_cache[$user_response];
        }

        $db = nc_core::get_object()->db;
        $db->query("DELETE FROM `Captchas_Recaptcha` WHERE `Expires` < NOW()");

        return $db->get_var(
            "SELECT `Result`
               FROM `Captchas_Recaptcha`
              WHERE `Response` = '" . $db->escape($user_response) . "'"
        );
    }

    /**
     * @param string $user_response
     */
    protected function delete_cached_verification_result($user_response) {
        unset($this->result_cache[$user_response]);
        $db = nc_core::get_object()->db;
        $db->query("DELETE LOW_PRIORITY FROM `Captchas_Recaptcha` WHERE `Response` = '" . $db->escape($user_response) . "'");
    }

    /**
     * @return array
     */
    public function get_configuration_errors() {
        if (!function_exists('curl_init')) {
            return array(NETCAT_MODULE_CAPTCHA_SETTINGS_YACAPTCHA_NO_CURL);
        }

        if (!$this->get_setting('YaCaptcha_ServerKey')) {
            return array(NETCAT_MODULE_CAPTCHA_SETTINGS_YACAPTCHA_ADD_KEYS);
        }

        $response = $this->get_verification_result('', false);
        if (!empty($response['message']) && in_array($response['message'], array('netcat-no-response', 'netcat-json-error'))) {
            return array(NETCAT_MODULE_CAPTCHA_SETTINGS_YACAPTCHA_NO_CONNECTION);
        }

        return array();
    }
}
