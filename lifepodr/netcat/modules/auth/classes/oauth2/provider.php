<?php
/**
 * Базовый абстрактный класс для OAuth2 провайдеров
 *
 * @package nc_auth
 */

abstract class nc_auth_oauth2_provider {

    /** @var nc_Core */
    protected $nc_core;

    /** @var string Идентификатор провайдера */
    protected $provider_id;

    /** @var array Настройки провайдера из БД */
    protected $settings = [];

    /** @var string URL для редиректа после авторизации */
    protected $redirect_to;

    /** @var int ID сайта */
    protected $catalogue_id;

    /**
     * URL для получения authorization code
     * @var string
     */
    protected $authorize_url;

    /**
     * URL для обмена code на access_token
     * @var string
     */
    protected $token_url;

    /**
     * URL для получения профиля пользователя
     * @var string
     */
    protected $userinfo_url;

    /**
     * Scope по умолчанию
     * @var string
     */
    protected $default_scope = '';

    /**
     * @param int|null $catalogue_id
     */
    public function __construct($catalogue_id = null) {
        $this->nc_core = nc_Core::get_object();
        $this->catalogue_id = $catalogue_id ?: $this->nc_core->catalogue->id();
        $this->load_settings();
    }

    /**
     * Загрузка настроек провайдера из БД
     */
    protected function load_settings(): void {
        $all_settings = $this->nc_core->get_settings('oauth2_providers', 'auth', false, $this->catalogue_id);
        $providers_settings = $all_settings ? @unserialize($all_settings) : [];
        $this->settings = nc_array_value($providers_settings, $this->provider_id, []);
    }

    /**
     * Включён ли провайдер
     */
    public function is_enabled(): bool {
        return !empty($this->settings['enabled']);
    }

    /**
     * Получить значение настройки
     */
    public function get_setting(string $key, $default = null) {
        return nc_array_value($this->settings, $key, $default);
    }

    /**
     * Получить callback URL (полный, с протоколом и доменом)
     * Без query-параметров — провайдер определяется через сессию
     */
    public function get_callback_url(): string {
        return $this->get_base_url() . 'modules/auth/api/callback.php';
    }

    /**
     * Получить базовый URL сайта (с протоколом и доменом)
     */
    protected function get_base_url(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? $this->nc_core->HTTP_HOST ?? 'localhost';
        $path = $this->nc_core->HTTP_ROOT_PATH ?: '/';

        return $protocol . $host . $path;
    }

    /**
     * Установить URL для редиректа после успешной авторизации
     */
    public function set_redirect_to(string $url): self {
        $this->redirect_to = $url;
        return $this;
    }

    /**
     * Получить URL для редиректа
     */
    public function get_redirect_to(): string {
        return $this->redirect_to ?: $this->nc_core->HTTP_ROOT_PATH;
    }

    /**
     * Генерация state-параметра для защиты от CSRF
     */
    public function generate_state(): string {
        $state = bin2hex(random_bytes(16));

        // Сохраняем state и redirect_to в сессии
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['nc_oauth2_state'] = $state;
        $_SESSION['nc_oauth2_redirect_to'] = $this->get_redirect_to();
        $_SESSION['nc_oauth2_provider'] = $this->provider_id;

        return $state;
    }

    /**
     * Проверка state-параметра
     */
    protected function verify_state(string $state): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $saved_state = nc_array_value($_SESSION, 'nc_oauth2_state', '');

        if (!$saved_state || !hash_equals($saved_state, $state)) {
            return false;
        }

        // Очищаем state после проверки
        unset($_SESSION['nc_oauth2_state']);

        return true;
    }

    /**
     * Получить сохранённый redirect_to из сессии
     */
    protected function get_saved_redirect_to(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $redirect_to = nc_array_value($_SESSION, 'nc_oauth2_redirect_to', '');
        unset($_SESSION['nc_oauth2_redirect_to']);

        return $redirect_to ?: $this->nc_core->HTTP_ROOT_PATH;
    }

    /**
     * Получить URL для начала авторизации
     */
    public function get_authorization_url(array $params = []): string {
        $default_params = [
            'response_type' => 'code',
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->get_callback_url(),
            'state' => $this->generate_state(),
        ];

        if ($this->default_scope) {
            $default_params['scope'] = $this->default_scope;
        }

        $params = array_merge($default_params, $params);

        return $this->authorize_url . '?' . http_build_query($params);
    }

    /**
     * Обменять authorization code на access token
     */
    public function exchange_code_for_token(string $code): array {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri' => $this->get_callback_url(),
        ];

        $response = $this->http_post($this->token_url, $params);

        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? $response['error']);
        }

        return $response;
    }

    /**
     * Получить профиль пользователя
     */
    public function get_user_profile(string $access_token): array {
        $response = $this->http_get($this->userinfo_url, [], [
            'Authorization: Bearer ' . $access_token,
        ]);

        if (isset($response['error'])) {
            throw new Exception($response['error_description'] ?? $response['error']);
        }

        return $this->normalize_user_profile($response);
    }

    /**
     * Нормализация профиля пользователя
     * Должен вернуть массив с ключами: id, email, name, first_name, last_name
     */
    abstract protected function normalize_user_profile(array $response): array;

    /**
     * Получить Client ID
     */
    abstract public function get_client_id(): string;

    /**
     * Получить Client Secret
     */
    abstract public function get_client_secret(): string;

    /**
     * HTTP GET запрос
     */
    protected function http_get(string $url, array $params = [], array $headers = []): array {
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP request failed: ' . $error);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Попробуем parse_str для form-urlencoded ответов
            parse_str($response, $data);
        }

        return $data ?: [];
    }

    /**
     * HTTP POST запрос
     */
    protected function http_post(string $url, array $params = [], array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ], $headers),
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP request failed: ' . $error);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            parse_str($response, $data);
        }

        return $data ?: [];
    }

    /**
     * Полный процесс авторизации (обработка callback)
     *
     * @param string $code Authorization code
     * @param string $state State параметр
     * @return array Данные пользователя
     * @throws Exception
     */
    public function process_callback(string $code, string $state): array {
        // Проверяем state
        if (!$this->verify_state($state)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_INVALID_STATE);
        }

        // Получаем токен
        $token_data = $this->exchange_code_for_token($code);
        $access_token = $token_data['access_token'] ?? '';

        if (!$access_token) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_ERROR);
        }

        // Получаем профиль
        $profile = $this->get_user_profile($access_token);

        // Добавляем токены к профилю
        $profile['_access_token'] = $access_token;
        $profile['_refresh_token'] = $token_data['refresh_token'] ?? null;
        $profile['_token_expires'] = isset($token_data['expires_in'])
            ? time() + (int)$token_data['expires_in']
            : null;

        return $profile;
    }

    /**
     * Найти или создать пользователя по данным профиля
     *
     * @param array $profile Нормализованный профиль
     * @return int User_ID
     */
    public function find_or_create_user(array $profile): int {
        $db = $this->nc_core->db;
        $external_id = $profile['id'];
        $email = $profile['email'] ?? '';

        // Ищем по external_id в таблице связей
        $user_id = (int)$db->get_var(
            "SELECT User_ID FROM Auth_ExternalAuth
             WHERE Service = '" . $db->escape($this->provider_id) . "'
             AND ExternalUser = '" . $db->escape($external_id) . "'"
        );

        if ($user_id) {
            // Обновляем токены
            $this->update_external_auth($user_id, $profile);
            return $user_id;
        }

        // Ищем по email
        if ($email) {
            $user_id = (int)$db->get_var(
                "SELECT User_ID FROM User WHERE Email = '" . $db->escape($email) . "' AND Checked = 1"
            );

            if ($user_id) {
                // Привязываем существующий аккаунт
                $this->create_external_auth($user_id, $profile);
                return $user_id;
            }
        }

        // Создаём нового пользователя
        return $this->create_user($profile);
    }

    /**
     * Создать нового пользователя
     */
    protected function create_user(array $profile): int {
        $db = $this->nc_core->db;

        // Генерируем email — для провайдеров без email создаём placeholder
        $email = $profile['email'] ?? '';
        if (empty($email)) {
            // Уникальный placeholder для провайдеров без email (например, Telegram)
            $email = $this->provider_id . '_' . $profile['id'] . '@oauth.local';
        }

        // Проверяем уникальность email
        $email = $this->generate_unique_email($email);

        // Генерируем случайный пароль
        $password = bin2hex(random_bytes(16));

        // Группы из настроек модуля auth
        $groups = $this->nc_core->get_settings('group', 'auth') ?: '';
        $group_ids = array_filter(array_map('intval', explode(',', $groups)));
        $main_group = $group_ids ? min($group_ids) : 4; // 4 = обычный пользователь

        // Вставляем пользователя
        $db->query(
            "INSERT INTO User (Email, Password, Name, Checked, Created, PermissionGroup_ID, UserType, Catalogue_ID)
             VALUES (
                '" . $db->escape($email) . "',
                " . $this->nc_core->MYSQL_ENCRYPT . "('" . $db->escape($password) . "'),
                '" . $db->escape($profile['name'] ?? '') . "',
                1,
                NOW(),
                " . (int)$main_group . ",
                'normal',
                " . (int)$this->catalogue_id . "
             )"
        );

        $user_id = (int)$db->insert_id;

        if (!$user_id) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_ERROR);
        }

        // Добавляем в группы
        foreach ($group_ids as $group_id) {
            $db->query(
                "INSERT IGNORE INTO User_Group (User_ID, PermissionGroup_ID)
                 VALUES (" . $user_id . ", " . $group_id . ")"
            );
        }

        // Создаём связь с провайдером
        $this->create_external_auth($user_id, $profile);

        return $user_id;
    }

    /**
     * Генерация уникального email
     */
    protected function generate_unique_email(string $email): string {
        $db = $this->nc_core->db;

        // Если email уже уникален, возвращаем его
        if (!$db->get_var("SELECT User_ID FROM User WHERE Email = '" . $db->escape($email) . "'")) {
            return $email;
        }

        // Иначе добавляем числовой суффикс
        $base = preg_replace('/@/', '_', $email, 1);
        $counter = 1;
        while ($db->get_var("SELECT User_ID FROM User WHERE Email = '" . $db->escape($base . $counter . '@oauth.local') . "'")) {
            $counter++;
        }

        return $base . $counter . '@oauth.local';
    }


    /**
     * Создать запись в Auth_ExternalAuth
     */
    protected function create_external_auth(int $user_id, array $profile): void {
        $db = $this->nc_core->db;

        $db->query(
            "INSERT INTO Auth_ExternalAuth (User_ID, Service, ExternalUser, Access_Token, Refresh_Token, Token_Expires, Profile_Data)
             VALUES (
                " . (int)$user_id . ",
                '" . $db->escape($this->provider_id) . "',
                '" . $db->escape($profile['id']) . "',
                " . ($profile['_access_token'] ? "'" . $db->escape($profile['_access_token']) . "'" : "NULL") . ",
                " . ($profile['_refresh_token'] ? "'" . $db->escape($profile['_refresh_token']) . "'" : "NULL") . ",
                " . ($profile['_token_expires'] ? "FROM_UNIXTIME(" . (int)$profile['_token_expires'] . ")" : "NULL") . ",
                '" . $db->escape(json_encode($profile, JSON_UNESCAPED_UNICODE)) . "'
             )"
        );
    }

    /**
     * Обновить запись в Auth_ExternalAuth
     */
    protected function update_external_auth(int $user_id, array $profile): void {
        $db = $this->nc_core->db;

        $db->query(
            "UPDATE Auth_ExternalAuth SET
                Access_Token = " . ($profile['_access_token'] ? "'" . $db->escape($profile['_access_token']) . "'" : "NULL") . ",
                Refresh_Token = " . ($profile['_refresh_token'] ? "'" . $db->escape($profile['_refresh_token']) . "'" : "NULL") . ",
                Token_Expires = " . ($profile['_token_expires'] ? "FROM_UNIXTIME(" . (int)$profile['_token_expires'] . ")" : "NULL") . ",
                Profile_Data = '" . $db->escape(json_encode($profile, JSON_UNESCAPED_UNICODE)) . "'
             WHERE User_ID = " . (int)$user_id . "
             AND Service = '" . $db->escape($this->provider_id) . "'"
        );
    }

    /**
     * Авторизовать пользователя
     */
    public function authorize_user(int $user_id): bool {
        $nc_user = new nc_user();
        return $nc_user->authorize_by_id($user_id);
    }

    /**
     * Получить идентификатор провайдера
     */
    public function get_provider_id(): string {
        return $this->provider_id;
    }

    /**
     * Получить название провайдера для отображения
     */
    abstract public function get_display_name(): string;
}
