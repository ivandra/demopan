<?php

/**
 * Класс для работы с кодами верификации email
 * Поддержка входа по коду, регистрации, checkout, восстановления пароля
 *
 * @package nc_auth
 */
class nc_auth_verify_code {

    /** @var string Цель: вход */
    const PURPOSE_LOGIN = 'login';

    /** @var string Цель: регистрация */
    const PURPOSE_REGISTRATION = 'registration';

    /** @var string Цель: checkout (оформление заказа) */
    const PURPOSE_CHECKOUT = 'checkout';

    /** @var string Цель: восстановление пароля */
    const PURPOSE_RECOVERY = 'recovery';

    /** @var int Максимум попыток ввода кода */
    const MAX_ATTEMPTS = 5;

    /** @var nc_Db */
    protected $db;

    /** @var nc_Core */
    protected $core;

    /** @var string Таблица для хранения кодов */
    protected $table = 'Auth_VerifyCode';

    /** @var nc_auth_rate_limiter */
    protected $rate_limiter;

    /**
     * Construct
     */
    public function __construct() {
        $this->core = nc_Core::get_object();
        $this->db = $this->core->db;
        $this->ensure_table();
    }

    /**
     * Гарантирует наличие таблицы кодов (создаёт при отсутствии).
     * Выполняется не чаще одного раза за запрос — самовосстановление для
     * сайтов, обновлённых без auth-setup.
     */
    protected function ensure_table() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        if (!$this->table_exists()) {
            $this->create_table();
        }
    }

    /**
     * Instance self object method
     *
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
     * Возвращает экземпляр rate limiter
     *
     * @return nc_auth_rate_limiter
     */
    protected function get_rate_limiter(): nc_auth_rate_limiter {
        if (!$this->rate_limiter) {
            require_once dirname(__FILE__) . '/rate_limiter.php';
            $this->rate_limiter = nc_auth_rate_limiter::get_object();
        }
        return $this->rate_limiter;
    }

    /**
     * Отправляет код верификации на email
     *
     * @param string $email Email адрес
     * @param string $purpose Цель (login, registration, checkout, recovery)
     * @param int|null $user_id ID пользователя (если известен)
     * @param bool $force Пропустить проверку интервала повторной отправки
     * @return array Результат: success, expires_in, resend_after, error
     */
    public function send(string $email, string $purpose, ?int $user_id = null, bool $force = false): array {
        $email = strtolower(trim($email));

        // Валидация email
        if (!nc_check_email($email)) {
            return [
                'success' => false,
                'error' => 'invalid_email',
                'message' => NETCAT_MODULE_AUTH_VERIFY_INVALID_EMAIL,
            ];
        }

        // Валидация purpose
        $valid_purposes = [self::PURPOSE_LOGIN, self::PURPOSE_REGISTRATION, self::PURPOSE_CHECKOUT, self::PURPOSE_RECOVERY];
        if (!in_array($purpose, $valid_purposes)) {
            return [
                'success' => false,
                'error' => 'invalid_purpose',
                'message' => NETCAT_MODULE_AUTH_VERIFY_INVALID_PURPOSE,
            ];
        }

        // Проверка rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limiter = $this->get_rate_limiter();

        if ($rate_limiter->is_blocked($ip, $email)) {
            $unlock_time = $rate_limiter->get_unlock_time($ip, $email);
            return [
                'success' => false,
                'error' => 'rate_limited',
                'message' => NETCAT_MODULE_AUTH_VERIFY_RATE_LIMITED,
                'retry_after' => $unlock_time,
            ];
        }

        // Проверка интервала повторной отправки (пропускается при force=true)
        $resend_interval = (int)$this->core->get_settings('verify_code_resend_interval', 'auth') ?: 60;

        if (!$force) {
            $last_code = $this->get_last_code($email, $purpose);

            if ($last_code) {
                $created = $last_code['Created_Unix'] ?? strtotime($last_code['Created']);
                $elapsed = time() - $created;

                if ($elapsed < $resend_interval) {
                    return [
                        'success' => false,
                        'error' => 'too_soon',
                        'message' => NETCAT_MODULE_AUTH_VERIFY_TOO_SOON,
                        'resend_after' => $resend_interval - $elapsed,
                    ];
                }
            }
        }

        // Генерация кода и хеша для ссылки
        $code_length = (int)$this->core->get_settings('verify_code_length', 'auth') ?: 6;
        $code = $this->generate_code($code_length);
        $hash = $this->generate_hash();
        $ttl = (int)$this->core->get_settings('verify_code_ttl', 'auth') ?: 10;

        // Инвалидируем предыдущие коды для этого email/purpose
        $this->invalidate_previous_codes($email, $purpose);

        // Сохраняем код в БД (используем DATE_ADD MySQL для корректного времени)
        $escaped_email = $this->db->escape($email);
        $escaped_purpose = $this->db->escape($purpose);
        $escaped_code = $this->db->escape($code);
        $escaped_hash = $this->db->escape($hash);
        $escaped_ip = $this->db->escape($ip);
        $user_id_value = $user_id ? (int)$user_id : 'NULL';

        $this->db->query(
            "INSERT INTO `{$this->table}`
             (`Email`, `Code`, `Hash`, `Purpose`, `User_ID`, `Expires`, `IP_Address`)
             VALUES ('{$escaped_email}', '{$escaped_code}', '{$escaped_hash}', '{$escaped_purpose}',
                     {$user_id_value}, DATE_ADD(NOW(), INTERVAL {$ttl} MINUTE), '{$escaped_ip}')"
        );

        if ($this->db->is_error) {
            return [
                'success' => false,
                'error' => 'database_error',
                'message' => NETCAT_MODULE_AUTH_VERIFY_ERROR,
            ];
        }

        // Отправляем email
        $sent = $this->send_email($email, $code, $hash, $purpose, $ttl);

        if (!$sent) {
            return [
                'success' => false,
                'error' => 'email_error',
                'message' => NETCAT_MODULE_AUTH_VERIFY_EMAIL_ERROR,
            ];
        }

        return [
            'success' => true,
            'expires_in' => $ttl * 60,
            'resend_after' => $resend_interval,
        ];
    }

    /**
     * Проверяет код верификации
     *
     * @param string $email Email адрес
     * @param string $code Код верификации
     * @return array Результат: valid, user_id, purpose, error
     */
    public function verify(string $email, string $code): array {
        $email = strtolower(trim($email));
        $code = trim($code);

        // Проверка rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_limiter = $this->get_rate_limiter();

        if ($rate_limiter->is_blocked($ip, $email)) {
            return [
                'valid' => false,
                'error' => 'rate_limited',
                'message' => NETCAT_MODULE_AUTH_VERIFY_RATE_LIMITED,
            ];
        }

        $escaped_email = $this->db->escape($email);

        // Получаем активный код
        $record = $this->db->get_row(
            "SELECT * FROM `{$this->table}`
             WHERE `Email` = '{$escaped_email}'
             AND `Verified` = 0
             AND `Expires` > NOW()
             AND `Attempts` < " . self::MAX_ATTEMPTS . "
             ORDER BY `Created` DESC
             LIMIT 1",
            ARRAY_A
        );

        if (!$record) {
            $rate_limiter->record_attempt($ip, $email, false);
            return [
                'valid' => false,
                'error' => 'code_not_found',
                'message' => NETCAT_MODULE_AUTH_VERIFY_CODE_NOT_FOUND,
            ];
        }

        // Увеличиваем счетчик попыток
        $this->db->query(
            "UPDATE `{$this->table}`
             SET `Attempts` = `Attempts` + 1
             WHERE `Code_ID` = {$record['Code_ID']}"
        );

        // Проверяем код (timing-safe сравнение)
        if (!hash_equals($record['Code'], $code)) {
            $rate_limiter->record_attempt($ip, $email, false);

            $attempts_left = self::MAX_ATTEMPTS - $record['Attempts'] - 1;
            return [
                'valid' => false,
                'error' => 'invalid_code',
                'message' => NETCAT_MODULE_AUTH_VERIFY_INVALID_CODE,
                'attempts_left' => max(0, $attempts_left),
            ];
        }

        // Код верный - помечаем как использованный
        $this->db->query(
            "UPDATE `{$this->table}`
             SET `Verified` = 1
             WHERE `Code_ID` = {$record['Code_ID']}"
        );

        $rate_limiter->record_attempt($ip, $email, true);

        return [
            'valid' => true,
            'user_id' => $record['User_ID'] ? (int)$record['User_ID'] : null,
            'purpose' => $record['Purpose'],
            'email' => $email,
        ];
    }

    /**
     * Проверяет уже верифицированный код (для двухэтапных операций, например сброс пароля)
     *
     * @param string $email Email пользователя
     * @param string $code Код верификации
     * @return array Результат: valid, user_id, purpose, email, error
     */
    public function check_verified_code(string $email, string $code): array {
        $email = strtolower(trim($email));
        $code = trim($code);

        $escaped_email = $this->db->escape($email);

        // Получаем верифицированный код (с небольшим допуском по времени после верификации)
        $record = $this->db->get_row(
            "SELECT * FROM `{$this->table}`
             WHERE `Email` = '{$escaped_email}'
             AND `Verified` = 1
             AND `Expires` > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             ORDER BY `Created` DESC
             LIMIT 1",
            ARRAY_A
        );

        if (!$record) {
            return [
                'valid' => false,
                'error' => 'code_not_found',
                'message' => NETCAT_MODULE_AUTH_VERIFY_CODE_NOT_FOUND,
            ];
        }

        // Проверяем код (timing-safe сравнение)
        if (!hash_equals($record['Code'], $code)) {
            return [
                'valid' => false,
                'error' => 'invalid_code',
                'message' => NETCAT_MODULE_AUTH_VERIFY_INVALID_CODE,
            ];
        }

        // Удаляем использованный код
        $this->db->query(
            "DELETE FROM `{$this->table}` WHERE `Code_ID` = {$record['Code_ID']}"
        );

        return [
            'valid' => true,
            'user_id' => $record['User_ID'] ? (int)$record['User_ID'] : null,
            'purpose' => $record['Purpose'],
            'email' => $email,
        ];
    }

    /**
     * Проверяет ссылку верификации (хеш)
     *
     * @param string $hash Хеш из ссылки
     * @return array Результат: valid, user_id, purpose, email, error
     */
    public function verify_link(string $hash): array {
        $hash = trim($hash);

        if (strlen($hash) !== 64) {
            return [
                'valid' => false,
                'error' => 'invalid_hash',
                'message' => NETCAT_MODULE_AUTH_VERIFY_INVALID_LINK,
            ];
        }

        $escaped_hash = $this->db->escape($hash);

        $record = $this->db->get_row(
            "SELECT * FROM `{$this->table}`
             WHERE `Hash` = '{$escaped_hash}'
             AND `Verified` = 0
             AND `Expires` > NOW()
             LIMIT 1",
            ARRAY_A
        );

        if (!$record) {
            return [
                'valid' => false,
                'error' => 'link_not_found',
                'message' => NETCAT_MODULE_AUTH_VERIFY_LINK_EXPIRED,
            ];
        }

        // Помечаем как использованный
        $this->db->query(
            "UPDATE `{$this->table}`
             SET `Verified` = 1
             WHERE `Code_ID` = {$record['Code_ID']}"
        );

        return [
            'valid' => true,
            'user_id' => $record['User_ID'] ? (int)$record['User_ID'] : null,
            'purpose' => $record['Purpose'],
            'email' => $record['Email'],
        ];
    }

    /**
     * Проверяет, верифицирован ли email для checkout
     *
     * @param string $email Email адрес
     * @return bool
     */
    public function is_checkout_verified(string $email): bool {
        $session_email = $_SESSION['nc_verified_checkout_email'] ?? null;
        return $session_email && strtolower($session_email) === strtolower(trim($email));
    }

    /**
     * Устанавливает email как верифицированный для checkout
     *
     * @param string $email Email адрес
     */
    public function set_checkout_verified(string $email): void {
        $_SESSION['nc_verified_checkout_email'] = strtolower(trim($email));
        $_SESSION['nc_verified_checkout_time'] = time();
    }

    /**
     * Очищает верификацию checkout
     */
    public function clear_checkout_verified(): void {
        unset($_SESSION['nc_verified_checkout_email']);
        unset($_SESSION['nc_verified_checkout_time']);
    }

    /**
     * Очищает устаревшие коды (для cron)
     *
     * @return int Количество удаленных записей
     */
    public function cleanup_expired(): int {
        // Удаляем истекшие коды старше 24 часов
        $this->db->query(
            "DELETE FROM `{$this->table}`
             WHERE `Expires` < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return $this->db->rows_affected;
    }

    /**
     * Генерирует числовой код
     *
     * @param int $length Длина кода (4 или 6)
     * @return string
     */
    protected function generate_code(int $length = 6): string {
        $length = in_array($length, [4, 6]) ? $length : 6;
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;

        return (string)random_int($min, $max);
    }

    /**
     * Генерирует хеш для ссылки
     *
     * @return string
     */
    protected function generate_hash(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Получает последний код для email/purpose
     *
     * @param string $email Email адрес
     * @param string $purpose Цель
     * @return array|null
     */
    protected function get_last_code(string $email, string $purpose): ?array {
        $escaped_email = $this->db->escape(strtolower(trim($email)));
        $escaped_purpose = $this->db->escape($purpose);

        $record = $this->db->get_row(
            "SELECT *, UNIX_TIMESTAMP(`Created`) as Created_Unix FROM `{$this->table}`
             WHERE `Email` = '{$escaped_email}'
             AND `Purpose` = '{$escaped_purpose}'
             ORDER BY `Created` DESC
             LIMIT 1",
            ARRAY_A
        );

        return $record ?: null;
    }

    /**
     * Инвалидирует предыдущие коды
     *
     * @param string $email Email адрес
     * @param string $purpose Цель
     */
    protected function invalidate_previous_codes(string $email, string $purpose): void {
        $escaped_email = $this->db->escape(strtolower(trim($email)));
        $escaped_purpose = $this->db->escape($purpose);

        $this->db->query(
            "UPDATE `{$this->table}`
             SET `Verified` = 1
             WHERE `Email` = '{$escaped_email}'
             AND `Purpose` = '{$escaped_purpose}'
             AND `Verified` = 0"
        );
    }

    /**
     * Отправляет email с кодом верификации
     *
     * @param string $email Email адрес
     * @param string $code Код верификации
     * @param string $hash Хеш для ссылки
     * @param string $purpose Цель
     * @param int $ttl Время жизни в минутах
     * @return bool
     */
    protected function send_email(string $email, string $code, string $hash, string $purpose, int $ttl): bool {
        $site_name = $this->core->catalogue->get_current('Catalogue_Name');
        $site_url = $this->core->catalogue->get_current('Domain');

        // Формируем ссылку для верификации
        $verify_url = 'https://' . $site_url . nc_module_path('auth') . 'confirm.php?verify_hash=' . $hash;

        // Получаем шаблон письма
        $subject = $this->get_email_subject($purpose, $site_name);
        $html_body = $this->get_email_body($purpose, $code, $verify_url, $ttl, $site_name, $site_url);
        $plain_body = strip_tags($html_body);

        // Отправка письма через nc_core->mail
        $from_email = $this->core->get_settings('SpamFromEmail') ?: 'noreply@' . $site_url;
        $from_name = $site_name;

        try {
            $this->core->mail->mailbody($plain_body, $html_body);
            $result = $this->core->mail->send($email, $from_email, $from_email, $subject, $from_name);
            return (bool)$result;
        } catch (Exception $e) {
            error_log('nc_auth_verify_code send_email error: ' . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log('nc_auth_verify_code send_email fatal: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Маппинг purpose -> ключ настройки в БД
     */
    protected function get_settings_key(string $purpose): string {
        $map = [
            self::PURPOSE_LOGIN => 'verify_login',
            self::PURPOSE_REGISTRATION => 'verify_registration',
            self::PURPOSE_CHECKOUT => 'verify_checkout',
            self::PURPOSE_RECOVERY => 'verify_recovery',
        ];
        return $map[$purpose] ?? 'verify_login';
    }

    /**
     * Возвращает тему письма
     *
     * @param string $purpose Цель
     * @param string $site_name Название сайта
     * @return string
     */
    protected function get_email_subject(string $purpose, string $site_name): string {
        $nc_core = nc_Core::get_object();
        $catalogue_id = $nc_core->catalogue->id();
        $settings_key = $this->get_settings_key($purpose);

        // Пробуем загрузить из настроек
        $subject = $nc_core->get_settings($settings_key . '_subject', 'auth', false, $catalogue_id);

        // Если в настройках пусто - используем дефолтные константы
        if (empty($subject)) {
            $default_subjects = [
                self::PURPOSE_LOGIN => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_SUBJECT')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_SUBJECT
                    : NETCAT_MODULE_AUTH_VERIFY_EMAIL_SUBJECT_LOGIN,
                self::PURPOSE_REGISTRATION => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_SUBJECT')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_SUBJECT
                    : NETCAT_MODULE_AUTH_VERIFY_EMAIL_SUBJECT_REGISTRATION,
                self::PURPOSE_CHECKOUT => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_SUBJECT')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_SUBJECT
                    : NETCAT_MODULE_AUTH_VERIFY_EMAIL_SUBJECT_CHECKOUT,
                self::PURPOSE_RECOVERY => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_SUBJECT')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_SUBJECT
                    : NETCAT_MODULE_AUTH_VERIFY_EMAIL_SUBJECT_RECOVERY,
            ];
            $subject = $default_subjects[$purpose] ?? NETCAT_MODULE_AUTH_VERIFY_EMAIL_SUBJECT_DEFAULT;
        }

        // Заменяем переменные (поддерживаем оба формата: %SITE_NAME и %SITE_NAME%)
        $subject = str_replace(['%SITE_NAME%', '%SITE_NAME'], $site_name, $subject);
        return $subject;
    }

    /**
     * Возвращает тело письма
     *
     * @param string $purpose Цель
     * @param string $code Код верификации
     * @param string $verify_url Ссылка для верификации
     * @param int $ttl Время жизни в минутах
     * @param string $site_name Название сайта
     * @param string $site_url URL сайта
     * @return string
     */
    protected function get_email_body(string $purpose, string $code, string $verify_url, int $ttl, string $site_name, string $site_url): string {
        $nc_core = nc_Core::get_object();
        $catalogue_id = $nc_core->catalogue->id();
        $settings_key = $this->get_settings_key($purpose);

        // Пробуем загрузить из настроек
        $template = $nc_core->get_settings($settings_key . '_body', 'auth', false, $catalogue_id);

        // Если в настройках пусто - используем дефолтные константы или файл
        if (empty($template)) {
            $default_bodies = [
                self::PURPOSE_LOGIN => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_BODY')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_BODY : null,
                self::PURPOSE_REGISTRATION => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_BODY')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_BODY : null,
                self::PURPOSE_CHECKOUT => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_BODY')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_BODY : null,
                self::PURPOSE_RECOVERY => defined('NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_BODY')
                    ? NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_BODY : null,
            ];

            $template = $default_bodies[$purpose] ?? null;

            // Если и дефолтной константы нет - fallback на файл
            if (empty($template)) {
                $template_path = dirname(dirname(__FILE__)) . '/templates/email/verify_code.html';
                if (file_exists($template_path)) {
                    $template = file_get_contents($template_path);
                } else {
                    $template = $this->get_default_email_template();
                }
            }
        }

        // Тексты для %PURPOSE% (для совместимости со старым файловым шаблоном)
        $purpose_texts = [
            self::PURPOSE_LOGIN => NETCAT_MODULE_AUTH_VERIFY_PURPOSE_LOGIN,
            self::PURPOSE_REGISTRATION => NETCAT_MODULE_AUTH_VERIFY_PURPOSE_REGISTRATION,
            self::PURPOSE_CHECKOUT => NETCAT_MODULE_AUTH_VERIFY_PURPOSE_CHECKOUT,
            self::PURPOSE_RECOVERY => NETCAT_MODULE_AUTH_VERIFY_PURPOSE_RECOVERY,
        ];

        $replacements = [
            '%CODE%' => $code,
            '%VERIFY_URL%' => $verify_url,
            '%TTL%' => $ttl,
            '%SITE_NAME%' => $site_name,
            '%SITE_URL%' => $site_url,
            '%PURPOSE%' => $purpose_texts[$purpose] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Возвращает шаблон письма по умолчанию
     *
     * @return string
     */
    protected function get_default_email_template(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center;
                padding: 20px; background: #f5f5f5; border-radius: 8px; margin: 20px 0; }
        .link { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff;
                text-decoration: none; border-radius: 6px; margin: 10px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;
                  font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h2>%PURPOSE%</h2>
    <p>Ваш код подтверждения:</p>
    <div class="code">%CODE%</div>
    <p>Или перейдите по ссылке:</p>
    <p><a href="%VERIFY_URL%" class="link">Подтвердить</a></p>
    <p>Код действителен %TTL% минут.</p>
    <div class="footer">
        <p>Если вы не запрашивали этот код, просто проигнорируйте это письмо.</p>
        <p>С уважением, %SITE_NAME%</p>
    </div>
</div>
</body>
</html>';
    }

    /**
     * Создает таблицу для хранения кодов (для setup)
     *
     * @return bool
     */
    public function create_table(): bool {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `Code_ID` INT AUTO_INCREMENT PRIMARY KEY,
            `Email` VARCHAR(255) NOT NULL,
            `Code` CHAR(6) NOT NULL,
            `Hash` VARCHAR(64) NOT NULL,
            `Purpose` ENUM('login', 'registration', 'checkout', 'recovery') NOT NULL,
            `User_ID` INT DEFAULT NULL,
            `Created` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `Expires` DATETIME NOT NULL,
            `Attempts` TINYINT DEFAULT 0,
            `Verified` TINYINT(1) DEFAULT 0,
            `IP_Address` VARCHAR(45),
            KEY `idx_email_purpose` (`Email`, `Purpose`),
            KEY `idx_hash` (`Hash`),
            KEY `idx_expires` (`Expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return $this->db->query($sql) !== false;
    }

    /**
     * Проверяет, существует ли таблица
     *
     * @return bool
     */
    public function table_exists(): bool {
        $result = $this->db->get_var("SHOW TABLES LIKE '{$this->table}'");
        return !empty($result);
    }
}
