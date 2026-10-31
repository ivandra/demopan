<?php

/**
 * Единая политика показа капчи в сценариях авторизации.
 *
 * Отвечает на единственный вопрос: нужно ли показывать капчу для действия
 * (вход / регистрация / восстановление пароля) с учётом настроек сайта
 * и числа предыдущих неудачных попыток.
 *
 * Источник счётчика попыток — таблица Auth_LoginAttempts (nc_auth_rate_limiter),
 * единая и для легаси-форм, и для современного popup.
 *
 * Настройки (scope 'system', с переопределением по сайту):
 *   - captcha_<action>_mode     : off | always | after_n
 *   - captcha_<action>_attempts  : число свободных попыток до показа капчи (для after_n)
 * При отсутствии новых ключей значения выводятся из легаси-настроек
 * AuthCaptchaEnabled / AuthCaptchaAttempts (обратная совместимость).
 *
 * @package nc_auth
 */
class nc_auth_captcha_policy {

    const ACTION_LOGIN = 'login';
    const ACTION_REGISTER = 'register';
    const ACTION_RECOVERY = 'recovery';

    const MODE_OFF = 'off';
    const MODE_ALWAYS = 'always';
    const MODE_AFTER_N = 'after_n';

    /** @var int Порог по умолчанию для режима after_n */
    const DEFAULT_THRESHOLD = 3;

    /** @var nc_Core */
    protected $core;

    public function __construct() {
        $this->core = nc_core::get_object();
    }

    /**
     * @return self
     */
    public static function get_object() {
        static $storage;
        if (!isset($storage)) {
            $storage = new self();
        }
        return $storage;
    }

    /**
     * Возвращает режим капчи для действия: off | always | after_n.
     *
     * @param string $action
     * @return string
     */
    public function get_mode($action, $catalogue_id = null) {
        $explicit = $this->core->get_settings("captcha_{$action}_mode", 'system', false, $catalogue_id);
        if ($explicit) {
            return $explicit;
        }

        // обратная совместимость / разумные значения по умолчанию
        switch ($action) {
            case self::ACTION_LOGIN:
                if (!$this->core->get_settings('AuthCaptchaEnabled', 'system', false, $catalogue_id)) {
                    return self::MODE_OFF;
                }
                $attempts = (int)$this->core->get_settings('AuthCaptchaAttempts', 'system', false, $catalogue_id);
                return $attempts > 0 ? self::MODE_AFTER_N : self::MODE_ALWAYS;

            case self::ACTION_REGISTER:
                return self::MODE_ALWAYS;

            case self::ACTION_RECOVERY:
            default:
                return self::MODE_OFF;
        }
    }

    /**
     * Число свободных попыток до показа капчи (для режима after_n).
     *
     * @param string $action
     * @return int
     */
    public function get_threshold($action, $catalogue_id = null) {
        $n = (int)$this->core->get_settings("captcha_{$action}_attempts", 'system', false, $catalogue_id);
        if ($n > 0) {
            return $n;
        }
        if ($action === self::ACTION_LOGIN) {
            $legacy = (int)$this->core->get_settings('AuthCaptchaAttempts', 'system', false, $catalogue_id);
            if ($legacy > 0) {
                return $legacy;
            }
        }
        return self::DEFAULT_THRESHOLD;
    }

    /**
     * Нужно ли показывать капчу для действия.
     *
     * @param string $action  одно из ACTION_*
     * @param string|null $email  логин/email пользователя (для подсчёта по логину)
     * @param string|null $ip  IP-адрес (по умолчанию REMOTE_ADDR)
     * @return bool
     */
    public function need_captcha($action, $email = null, $ip = null) {
        if (!nc_module_check_by_keyword('captcha')) {
            return false;
        }

        $mode = $this->get_mode($action);
        if ($mode === self::MODE_OFF) {
            return false;
        }
        if ($mode === self::MODE_ALWAYS) {
            return true;
        }

        // MODE_AFTER_N
        $threshold = $this->get_threshold($action);
        if ($threshold <= 0) {
            return true;
        }

        if ($ip === null) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }

        return $this->get_failed_count($ip, $email) >= $threshold;
    }

    /**
     * Число неудачных попыток (макс. из счёта по IP и по логину) в окне отслеживания.
     *
     * @param string $ip
     * @param string|null $email
     * @return int
     */
    protected function get_failed_count($ip, $email) {
        if (!class_exists('nc_auth_rate_limiter')) {
            return 0;
        }
        return nc_auth_rate_limiter::get_object()->get_failed_count($ip, $email !== '' ? $email : null);
    }

}
