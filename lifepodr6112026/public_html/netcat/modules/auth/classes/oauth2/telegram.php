<?php
/**
 * Провайдер авторизации через Telegram Login Widget
 *
 * Telegram не использует стандартный OAuth2 flow.
 * Вместо этого используется виджет, который передаёт подписанные данные.
 *
 * @see https://core.telegram.org/widgets/login
 * @package nc_auth
 */

class nc_auth_oauth2_telegram extends nc_auth_oauth2_provider {

    protected $provider_id = 'telegram';

    /**
     * @inheritDoc
     */
    public function get_client_id(): string {
        // Telegram не использует client_id, вместо него bot_username
        return $this->get_setting('bot_username', '');
    }

    /**
     * @inheritDoc
     */
    public function get_client_secret(): string {
        // Bot token используется для верификации
        return $this->get_setting('bot_token', '');
    }

    /**
     * @inheritDoc
     */
    public function get_display_name(): string {
        return defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_TELEGRAM')
            ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_TELEGRAM
            : 'Telegram';
    }

    /**
     * Telegram не использует стандартный authorization URL
     * Вместо этого данные передаются через виджет
     */
    public function get_authorization_url(array $params = []): string {
        // Возвращаем URL страницы с виджетом или callback URL
        return $this->get_callback_url();
    }

    /**
     * Верификация данных от Telegram
     *
     * @param array $auth_data Данные от Telegram виджета
     * @return bool
     */
    public function verify_telegram_data(array $auth_data): bool {
        $bot_token = $this->get_client_secret();

        if (empty($bot_token)) {
            return false;
        }

        $check_hash = $auth_data['hash'] ?? '';
        unset($auth_data['hash']);

        // Сортируем по ключам
        ksort($auth_data);

        // Формируем строку для проверки
        $data_check_arr = [];
        foreach ($auth_data as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }
        $data_check_string = implode("\n", $data_check_arr);

        // Вычисляем хеш
        $secret_key = hash('sha256', $bot_token, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        return hash_equals($hash, $check_hash);
    }

    /**
     * Проверка актуальности данных (не старше 24 часов)
     */
    public function is_data_fresh(array $auth_data): bool {
        $auth_date = (int)($auth_data['auth_date'] ?? 0);
        return (time() - $auth_date) < 86400; // 24 часа
    }

    /**
     * Обработка данных от Telegram виджета
     *
     * @param array $auth_data Данные от виджета
     * @return array Нормализованный профиль
     * @throws Exception
     */
    public function process_telegram_auth(array $auth_data): array {
        // Проверяем подпись
        if (!$this->verify_telegram_data($auth_data)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_INVALID_STATE);
        }

        // Проверяем свежесть
        if (!$this->is_data_fresh($auth_data)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_ERROR);
        }

        return $this->normalize_user_profile($auth_data);
    }

    /**
     * @inheritDoc
     */
    protected function normalize_user_profile(array $response): array {
        return [
            'id' => $response['id'],
            'email' => '', // Telegram не даёт email
            'name' => trim(($response['first_name'] ?? '') . ' ' . ($response['last_name'] ?? '')),
            'first_name' => $response['first_name'] ?? '',
            'last_name' => $response['last_name'] ?? '',
        ];
    }

    /**
     * Telegram не использует стандартный OAuth flow
     */
    public function exchange_code_for_token(string $code): array {
        throw new Exception('Telegram does not use OAuth2 code exchange');
    }

    /**
     * Telegram не использует access token для получения профиля
     */
    public function get_user_profile(string $access_token): array {
        throw new Exception('Telegram does not use access tokens');
    }

    /**
     * Генерация HTML-кода виджета Telegram Login
     *
     * @param array $options Опции виджета
     * @return string HTML
     */
    public function get_widget_html(array $options = []): string {
        $bot_username = $this->get_client_id();

        if (empty($bot_username)) {
            return '<!-- Telegram bot not configured -->';
        }

        $callback_url = $this->get_callback_url();
        $size = $options['size'] ?? 'large'; // small, medium, large
        $radius = $options['radius'] ?? 10;
        $request_access = $options['request_access'] ?? 'write';

        return sprintf(
            '<script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="%s"
                data-size="%s"
                data-radius="%d"
                data-auth-url="%s"
                data-request-access="%s"></script>',
            htmlspecialchars($bot_username),
            htmlspecialchars($size),
            (int)$radius,
            htmlspecialchars($callback_url),
            htmlspecialchars($request_access)
        );
    }

    /**
     * Генерация HTML-кода кнопки с редиректом (без виджета)
     *
     * @param string $text Текст кнопки
     * @return string HTML
     */
    public function get_button_html(string $text = 'Войти через Telegram'): string {
        $bot_username = $this->get_client_id();

        if (empty($bot_username)) {
            return '';
        }

        $callback_url = urlencode($this->get_callback_url());

        // URL для открытия в Telegram
        $telegram_url = "https://oauth.telegram.org/auth?bot_id={$bot_username}&origin=" . urlencode($this->nc_core->HTTP_ROOT_PATH) . "&return_to={$callback_url}";

        return sprintf(
            '<a href="%s" class="nc-auth-btn nc-auth-btn--telegram">%s</a>',
            htmlspecialchars($telegram_url),
            htmlspecialchars($text)
        );
    }
}
