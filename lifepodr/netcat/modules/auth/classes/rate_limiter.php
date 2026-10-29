<?php

/**
 * Класс для ограничения частоты запросов (rate limiting)
 * Защита от brute-force атак и злоупотреблений
 *
 * @package nc_auth
 */
class nc_auth_rate_limiter {

    /** @var int Максимум попыток с одного IP за период (по умолчанию) */
    const DEFAULT_MAX_ATTEMPTS_PER_IP = 10;

    /** @var int Максимум попыток для одного логина за период (по умолчанию) */
    const DEFAULT_MAX_ATTEMPTS_PER_LOGIN = 5;

    /** @var int Период отслеживания попыток в секундах (по умолчанию) */
    const DEFAULT_TRACKING_PERIOD = 900; // 15 минут

    /** @var int Базовая длительность блокировки (секунды) */
    const LOCKOUT_DURATION = 900; // 15 минут

    /** @var int Максимальная задержка между попытками (секунды) */
    const MAX_DELAY = 30;

    /** @var nc_Db */
    protected $db;

    /** @var nc_Core */
    protected $core;

    /** @var string Таблица для хранения попыток */
    protected $table = 'Auth_LoginAttempts';

    /** @var int Конфигурируемые лимиты */
    protected $max_attempts_ip;
    protected $max_attempts_login;
    protected $tracking_period;

    /**
     * Construct
     */
    public function __construct() {
        $this->core = nc_Core::get_object();
        $this->db = $this->core->db;

        // Загружаем настройки из БД (с fallback на константы)
        $this->max_attempts_ip = (int)$this->core->get_settings('rate_limit_max_ip', 'auth') ?: self::DEFAULT_MAX_ATTEMPTS_PER_IP;
        $this->max_attempts_login = (int)$this->core->get_settings('rate_limit_max_login', 'auth') ?: self::DEFAULT_MAX_ATTEMPTS_PER_LOGIN;
        $this->tracking_period = (int)$this->core->get_settings('rate_limit_period', 'auth') ?: self::DEFAULT_TRACKING_PERIOD;

        $this->ensure_table();
    }

    /**
     * Гарантирует наличие таблицы попыток (создаёт при отсутствии).
     * Выполняется не чаще одного раза за запрос. Делает таблицу
     * самовосстанавливающейся для сайтов, обновлённых без auth-setup.
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
     * Проверяет, заблокирован ли IP-адрес или логин
     *
     * @param string $ip IP-адрес
     * @param string|null $login Логин пользователя (опционально)
     * @return bool True если заблокирован
     */
    public function is_blocked(string $ip, ?string $login = null): bool {
        $ip_blocked = $this->is_ip_blocked($ip);
        $login_blocked = $login ? $this->is_login_blocked($login) : false;

        return $ip_blocked || $login_blocked;
    }

    /**
     * Проверяет, заблокирован ли IP-адрес
     *
     * @param string $ip IP-адрес
     * @return bool
     */
    protected function is_ip_blocked(string $ip): bool {
        $ip = $this->db->escape($ip);
        $period = $this->tracking_period;

        $count = $this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `IP_Address` = '{$ip}'
             AND `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
        );

        return $count >= $this->max_attempts_ip;
    }

    /**
     * Проверяет, заблокирован ли логин
     *
     * @param string $login Логин
     * @return bool
     */
    protected function is_login_blocked(string $login): bool {
        $login = $this->db->escape($login);
        $period = $this->tracking_period;

        $count = $this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `Login` = '{$login}'
             AND `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
        );

        return $count >= $this->max_attempts_login;
    }

    /**
     * Записывает попытку авторизации
     *
     * @param string $ip IP-адрес
     * @param string|null $login Логин пользователя
     * @param bool $success Успешна ли попытка
     * @return bool
     */
    public function record_attempt(string $ip, ?string $login = null, bool $success = false): bool {
        $ip = $this->db->escape($ip);
        $login = $login ? "'" . $this->db->escape($login) . "'" : "NULL";
        $success_int = $success ? 1 : 0;

        $result = $this->db->query(
            "INSERT INTO `{$this->table}`
             (`IP_Address`, `Login`, `Success`, `Attempted`)
             VALUES ('{$ip}', {$login}, {$success_int}, NOW())"
        );

        // При успешной авторизации очищаем историю неудачных попыток
        if ($success && $login !== "NULL") {
            $this->clear_login_attempts($this->db->escape(trim($login, "'")));
        }

        return $result !== false;
    }

    /**
     * Очищает историю неудачных попыток для логина
     *
     * @param string $login Логин
     * @return bool
     */
    public function clear_login_attempts(string $login): bool {
        $login = $this->db->escape($login);

        $result = $this->db->query(
            "DELETE FROM `{$this->table}`
             WHERE `Login` = '{$login}' AND `Success` = 0"
        );

        return $result !== false;
    }

    /**
     * Очищает историю попыток для IP
     *
     * @param string $ip IP-адрес
     * @return bool
     */
    public function clear_ip_attempts(string $ip): bool {
        $ip = $this->db->escape($ip);

        $result = $this->db->query(
            "DELETE FROM `{$this->table}`
             WHERE `IP_Address` = '{$ip}' AND `Success` = 0"
        );

        return $result !== false;
    }

    /**
     * Возвращает число неудачных попыток в текущем окне отслеживания
     * (максимум из подсчёта по IP и по логину). Используется политикой капчи.
     *
     * @param string $ip IP-адрес
     * @param string|null $login Логин пользователя
     * @return int
     */
    public function get_failed_count(string $ip, ?string $login = null): int {
        $period = $this->tracking_period;
        $ip_esc = $this->db->escape($ip);

        $ip_count = (int)$this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `IP_Address` = '{$ip_esc}'
             AND `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
        );

        $login_count = 0;
        if ($login !== null && $login !== '') {
            $login_esc = $this->db->escape($login);
            $login_count = (int)$this->db->get_var(
                "SELECT COUNT(*) FROM `{$this->table}`
                 WHERE `Login` = '{$login_esc}'
                 AND `Success` = 0
                 AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
            );
        }

        return max($ip_count, $login_count);
    }

    /**
     * Рассчитывает прогрессивную задержку между попытками
     *
     * @param string $ip IP-адрес
     * @param string|null $login Логин пользователя
     * @return int Задержка в секундах
     */
    public function get_delay(string $ip, ?string $login = null): int {
        $ip = $this->db->escape($ip);
        $period = $this->tracking_period;

        // Получаем количество неудачных попыток
        $ip_attempts = $this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `IP_Address` = '{$ip}'
             AND `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
        );

        $login_attempts = 0;
        if ($login) {
            $login = $this->db->escape($login);
            $login_attempts = $this->db->get_var(
                "SELECT COUNT(*) FROM `{$this->table}`
                 WHERE `Login` = '{$login}'
                 AND `Success` = 0
                 AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
            );
        }

        $max_attempts = max($ip_attempts, $login_attempts);

        // Прогрессивная задержка: 0, 1, 2, 4, 8, 16, 30, 30, ...
        if ($max_attempts <= 1) {
            return 0;
        }

        $delay = pow(2, min($max_attempts - 2, 5));
        return min($delay, self::MAX_DELAY);
    }

    /**
     * Возвращает время до разблокировки
     *
     * @param string $ip IP-адрес
     * @param string|null $login Логин
     * @return int Секунды до разблокировки (0 если не заблокирован)
     */
    public function get_unlock_time(string $ip, ?string $login = null): int {
        if (!$this->is_blocked($ip, $login)) {
            return 0;
        }

        $ip = $this->db->escape($ip);
        $period = $this->tracking_period;

        // Находим время первой неудачной попытки в текущем периоде
        $first_attempt = $this->db->get_var(
            "SELECT MIN(`Attempted`) FROM `{$this->table}`
             WHERE `IP_Address` = '{$ip}'
             AND `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$period} SECOND)"
        );

        if (!$first_attempt) {
            return 0;
        }

        $unlock_time = strtotime($first_attempt) + self::LOCKOUT_DURATION;
        $remaining = $unlock_time - time();

        return max(0, $remaining);
    }

    /**
     * Очищает устаревшие записи (для cron)
     *
     * @return int Количество удаленных записей
     */
    public function cleanup(): int {
        // Удаляем записи старше 24 часов
        $result = $this->db->query(
            "DELETE FROM `{$this->table}`
             WHERE `Attempted` < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return $this->db->rows_affected;
    }

    /**
     * Получает статистику попыток авторизации
     *
     * @param int $hours Период в часах
     * @return array Статистика
     */
    public function get_stats(int $hours = 24): array {
        $hours = (int)$hours;

        $total = $this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `Attempted` > DATE_SUB(NOW(), INTERVAL {$hours} HOUR)"
        );

        $failed = $this->db->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$hours} HOUR)"
        );

        $successful = $total - $failed;

        $unique_ips = $this->db->get_var(
            "SELECT COUNT(DISTINCT `IP_Address`) FROM `{$this->table}`
             WHERE `Success` = 0
             AND `Attempted` > DATE_SUB(NOW(), INTERVAL {$hours} HOUR)"
        );

        $blocked_ips = $this->db->get_var(
            "SELECT COUNT(*) FROM (
                SELECT `IP_Address` FROM `{$this->table}`
                WHERE `Success` = 0
                AND `Attempted` > DATE_SUB(NOW(), INTERVAL " . $this->tracking_period . " SECOND)
                GROUP BY `IP_Address`
                HAVING COUNT(*) >= " . $this->max_attempts_ip . "
            ) AS blocked"
        );

        return [
            'total_attempts' => (int)$total,
            'failed_attempts' => (int)$failed,
            'successful_attempts' => (int)$successful,
            'unique_ips_with_failures' => (int)$unique_ips,
            'currently_blocked_ips' => (int)$blocked_ips,
            'period_hours' => $hours,
        ];
    }

    /**
     * Создает таблицу для хранения попыток (для setup)
     *
     * @return bool
     */
    public function create_table(): bool {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `Attempt_ID` INT AUTO_INCREMENT PRIMARY KEY,
            `IP_Address` VARCHAR(45) NOT NULL,
            `Login` VARCHAR(255) DEFAULT NULL,
            `Attempted` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `Success` TINYINT(1) DEFAULT 0,
            KEY `idx_ip_time` (`IP_Address`, `Attempted`),
            KEY `idx_login_time` (`Login`, `Attempted`),
            KEY `idx_attempted` (`Attempted`)
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
