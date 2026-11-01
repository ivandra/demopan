<?php

/**
 * Класс для безопасного хеширования паролей с использованием Argon2id
 * и прозрачной миграции с устаревших алгоритмов
 *
 * @package nc_auth
 */
class nc_auth_password {

    /** @var nc_Db */
    protected $db;

    /** @var nc_Core */
    protected $core;

    /** @var string Предпочтительный алгоритм хеширования */
    protected $preferred_algo;

    /**
     * @var array Опции для Argon2id; пустой массив, если Argon2 не поддерживается
     *            текущей сборкой PHP (тогда используется bcrypt с опциями по умолчанию).
     *            Заполняется в конструкторе: константы PASSWORD_ARGON2_DEFAULT_*
     *            существуют только при наличии поддержки Argon2, поэтому обращаться
     *            к ним в инициализаторе свойства нельзя.
     */
    protected $argon2_options = [];

    /** @var int|null Кешированный размер колонки Password */
    protected $password_column_size = null;

    /** @var int Минимальный размер колонки для современных хешей */
    const MIN_COLUMN_SIZE = 97;

    /**
     * Construct
     */
    public function __construct() {
        $this->core = nc_Core::get_object();
        $this->db = $this->core->db;

        // Используем Argon2id если доступен, иначе bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            $this->preferred_algo = PASSWORD_ARGON2ID;
            $this->argon2_options = [
                'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
            ];
        } else {
            $this->preferred_algo = PASSWORD_BCRYPT;
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
     * Создает хеш пароля с использованием современного алгоритма
     *
     * @param string $password Пароль в открытом виде
     * @return string Хеш пароля
     */
    public function hash(string $password): string {
        return password_hash($password, $this->preferred_algo, $this->argon2_options);
    }

    /**
     * Проверяет пароль против хеша
     * Поддерживает legacy хеши (MD5, SHA1) и современные алгоритмы
     *
     * @param string $password Пароль в открытом виде
     * @param string $hash Хеш пароля из базы данных
     * @return bool True если пароль верный
     */
    public function verify(string $password, string $hash): bool {
        // Пустой хеш - пароль не установлен
        if (empty($hash)) {
            return false;
        }

        // Проверка современных хешей (bcrypt, argon2)
        if ($this->is_modern_hash($hash)) {
            return password_verify($password, $hash);
        }

        // Проверка legacy хешей
        return $this->verify_legacy($password, $hash);
    }

    /**
     * Проверяет, нужна ли перехешировка пароля
     *
     * @param string $hash Текущий хеш пароля
     * @return bool True если нужна перехешировка
     */
    public function needs_rehash(string $hash): bool {
        // Legacy хеши всегда нужно перехешировать
        if (!$this->is_modern_hash($hash)) {
            return true;
        }

        // Проверяем, соответствует ли хеш текущим настройкам алгоритма
        return password_needs_rehash($hash, $this->preferred_algo, $this->argon2_options);
    }

    /**
     * Обновляет хеш пароля пользователя в базе данных
     *
     * @param int $user_id ID пользователя
     * @param string $new_hash Новый хеш пароля
     * @return bool True если обновление успешно
     */
    public function update_user_hash(int $user_id, string $new_hash): bool {
        $user_id = (int)$user_id;
        $escaped_hash = $this->db->escape($new_hash);

        $this->db->query(
            "UPDATE `User` SET `Password` = '{$escaped_hash}' WHERE `User_ID` = '{$user_id}'"
        );

        return $this->db->rows_affected > 0;
    }

    /**
     * Получает хеш пароля пользователя из базы данных
     *
     * @param int $user_id ID пользователя
     * @return string|null Хеш пароля или null
     */
    public function get_user_hash(int $user_id): ?string {
        $user_id = (int)$user_id;
        return $this->db->get_var(
            "SELECT `Password` FROM `User` WHERE `User_ID` = '{$user_id}'"
        );
    }

    /**
     * Мигрирует пароль пользователя на современный алгоритм
     * Вызывается после успешной авторизации
     *
     * @param int $user_id ID пользователя
     * @param string $password Пароль в открытом виде
     * @return bool True если миграция выполнена
     */
    public function migrate_if_needed(int $user_id, string $password): bool {
        // Проверяем размер колонки Password - если слишком мал, миграция невозможна
        if (!$this->can_store_modern_hash()) {
            // Логируем предупреждение один раз
            static $warned = false;
            if (!$warned) {
                error_log('nc_auth_password: Password column is too small for modern hashes. Run: ALTER TABLE User MODIFY COLUMN Password varchar(255) NOT NULL DEFAULT ""');
                $warned = true;
            }
            return false;
        }

        $current_hash = $this->get_user_hash($user_id);

        if ($current_hash && $this->needs_rehash($current_hash)) {
            $new_hash = $this->hash($password);
            return $this->update_user_hash($user_id, $new_hash);
        }

        return false;
    }

    /**
     * Проверяет, достаточен ли размер колонки Password для современных хешей
     *
     * @return bool True если размер достаточен
     */
    public function can_store_modern_hash(): bool {
        if ($this->password_column_size === null) {
            $this->password_column_size = $this->get_password_column_size();
        }
        return $this->password_column_size >= self::MIN_COLUMN_SIZE;
    }

    /**
     * Получает размер колонки Password из схемы БД
     *
     * @return int Размер колонки в символах
     */
    protected function get_password_column_size(): int {
        $result = $this->db->get_row(
            "SELECT CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'User'
             AND COLUMN_NAME = 'Password'",
            ARRAY_A
        );
        return (int)($result['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
    }

    /**
     * Проверяет, является ли хеш современным (bcrypt, argon2)
     *
     * @param string $hash Хеш пароля
     * @return bool True если это современный хеш
     */
    protected function is_modern_hash(string $hash): bool {
        // Современные хеши начинаются с $2y$ (bcrypt), $argon2i$, $argon2id$
        return (
            strpos($hash, '$2y$') === 0 ||
            strpos($hash, '$2a$') === 0 ||
            strpos($hash, '$2b$') === 0 ||
            strpos($hash, '$argon2i$') === 0 ||
            strpos($hash, '$argon2id$') === 0
        );
    }

    /**
     * Проверяет legacy хеши (MD5, SHA1, MySQL PASSWORD())
     *
     * @param string $password Пароль в открытом виде
     * @param string $hash Хеш пароля из базы
     * @return bool True если пароль верный
     */
    protected function verify_legacy(string $password, string $hash): bool {
        // MySQL PASSWORD() function - 41 символ, начинается с *
        if (strlen($hash) === 41 && $hash[0] === '*') {
            // MySQL 4.1+ PASSWORD() = CONCAT('*', UPPER(SHA1(UNHEX(SHA1(password)))))
            $mysql_hash = '*' . strtoupper(sha1(sha1($password, true)));
            return hash_equals($hash, $mysql_hash);
        }

        // Простой MD5 (32 символа)
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), md5($password));
        }

        // SHA1 (40 символов)
        if (strlen($hash) === 40 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), sha1($password));
        }

        // SHA256 (64 символа)
        if (strlen($hash) === 64 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), hash('sha256', $password));
        }

        // Старый MySQL PASSWORD() (16 символов) - MySQL pre-4.1
        if (strlen($hash) === 16 && ctype_xdigit($hash)) {
            $computed = $this->db->get_var(
                "SELECT OLD_PASSWORD('" . $this->db->escape($password) . "')"
            );
            if ($computed !== null) {
                return hash_equals(strtolower($hash), strtolower($computed));
            }
            return false;
        }

        return false;
    }

    /**
     * Проверяет, установлен ли пароль для пользователя
     *
     * @param int $user_id ID пользователя
     * @return bool True если пароль установлен
     */
    public function user_has_password(int $user_id): bool {
        $hash = $this->get_user_hash($user_id);
        return !empty($hash);
    }

    /**
     * Устанавливает новый пароль для пользователя
     *
     * @param int $user_id ID пользователя
     * @param string $password Новый пароль в открытом виде
     * @return bool True если пароль успешно установлен
     * @throws RuntimeException Если колонка Password слишком мала
     */
    public function set_user_password(int $user_id, string $password): bool {
        // Проверяем размер колонки перед установкой современного хеша
        if (!$this->can_store_modern_hash()) {
            throw new RuntimeException(
                'Password column is too small for modern hashes. ' .
                'Run: ALTER TABLE User MODIFY COLUMN Password varchar(255) NOT NULL DEFAULT ""'
            );
        }

        $hash = $this->hash($password);
        return $this->update_user_hash($user_id, $hash);
    }

    /**
     * Возвращает информацию об алгоритме хеширования
     *
     * @param string $hash Хеш пароля
     * @return array Информация о хеше
     */
    public function get_hash_info(string $hash): array {
        if ($this->is_modern_hash($hash)) {
            return password_get_info($hash);
        }

        // Legacy hash info
        $length = strlen($hash);

        if ($length === 41 && $hash[0] === '*') {
            return ['algo' => 'mysql_password', 'algoName' => 'MySQL PASSWORD()', 'options' => []];
        }
        if ($length === 32) {
            return ['algo' => 'md5', 'algoName' => 'MD5', 'options' => []];
        }
        if ($length === 40) {
            return ['algo' => 'sha1', 'algoName' => 'SHA1', 'options' => []];
        }
        if ($length === 64) {
            return ['algo' => 'sha256', 'algoName' => 'SHA256', 'options' => []];
        }

        return ['algo' => 'unknown', 'algoName' => 'Unknown', 'options' => []];
    }
}
