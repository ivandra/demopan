<?php
/**
 * OAuth2 Callback Handler
 *
 * Обрабатывает callback от OAuth2 провайдеров (Яндекс, VK ID, Google, Telegram)
 *
 * URL: /netcat/modules/auth/api/callback.php
 * Провайдер определяется из сессии (nc_oauth2_provider)
 *
 * @package nc_auth
 */

// Подключение ядра (без требования админ-авторизации)
$NETCAT_FOLDER = realpath(__DIR__ . '/../../../../');
putenv('DOCUMENT_ROOT=' . $NETCAT_FOLDER);
$_SERVER['DOCUMENT_ROOT'] = $NETCAT_FOLDER;
chdir($NETCAT_FOLDER);
require_once $NETCAT_FOLDER . '/vars.inc.php';
require_once $ROOT_FOLDER . 'connect_io.php';
require_once $INCLUDE_FOLDER . 's_common.inc.php';

$nc_core = nc_Core::get_object();

// Загружаем языковой файл модуля
require_once dirname(__DIR__) . '/ru_utf8.lang.php';

// Получаем идентификатор провайдера из query или сессии
// (VK ID и некоторые другие не разрешают query-параметры в redirect_uri)
$provider_id = $nc_core->input->fetch_get('provider');

if (empty($provider_id)) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $provider_id = $_SESSION['nc_oauth2_provider'] ?? '';
}

if (empty($provider_id)) {
    redirect_to_error('Provider not specified');
}

// Список поддерживаемых провайдеров
$supported_providers = ['yandex', 'vkid', 'google', 'telegram'];

if (!in_array($provider_id, $supported_providers)) {
    redirect_to_error('Unknown provider: ' . $provider_id);
}

// Загружаем класс провайдера
$provider_class = 'nc_auth_oauth2_' . $provider_id;
$provider_file = dirname(__DIR__) . '/classes/oauth2/' . $provider_id . '.php';

if (!file_exists($provider_file)) {
    redirect_to_error('Provider class not found');
}

require_once dirname(__DIR__) . '/classes/oauth2/provider.php';
require_once $provider_file;

try {
    /** @var nc_auth_oauth2_provider $provider */
    $provider = new $provider_class();

    // Проверяем, включён ли провайдер
    if (!$provider->is_enabled()) {
        throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_PROVIDER_DISABLED);
    }

    // Обработка в зависимости от провайдера
    if ($provider_id === 'telegram') {
        // Telegram передаёт данные через GET-параметры
        $auth_data = [];
        $telegram_fields = ['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash'];
        foreach ($telegram_fields as $field) {
            $value = $nc_core->input->fetch_get($field);
            if ($value !== null && $value !== '') {
                $auth_data[$field] = $value;
            }
        }

        if (empty($auth_data['id']) || empty($auth_data['hash'])) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_USER_CANCELLED);
        }

        /** @var nc_auth_oauth2_telegram $provider */
        $profile = $provider->process_telegram_auth($auth_data);

        // Добавляем псевдо-токены для совместимости
        $profile['_access_token'] = null;
        $profile['_refresh_token'] = null;
        $profile['_token_expires'] = null;

    } else {
        // Стандартный OAuth2 flow
        $code = $nc_core->input->fetch_get('code');
        $state = $nc_core->input->fetch_get('state');
        $error = $nc_core->input->fetch_get('error');

        // Пользователь отменил авторизацию
        if ($error) {
            $error_description = $nc_core->input->fetch_get('error_description');
            throw new Exception($error_description ?: NETCAT_MODULE_AUTH_OAUTH2_USER_CANCELLED);
        }

        if (empty($code)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_ERROR);
        }

        if (empty($state)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_INVALID_STATE);
        }

        // Обрабатываем callback
        $profile = $provider->process_callback($code, $state);
    }

    // Ищем или создаём пользователя
    $user_id = $provider->find_or_create_user($profile);

    // Авторизуем пользователя
    $provider->authorize_user($user_id);

    // Получаем URL для редиректа
    $redirect_to = get_saved_redirect_to();

    // Редирект на целевую страницу
    header('Location: ' . $redirect_to);
    exit;

} catch (Exception $e) {
    // Логируем ошибку для отладки
    error_log('OAuth callback error [' . $provider_id . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    redirect_to_error($e->getMessage());
} catch (Error $e) {
    // Ловим фатальные ошибки PHP
    error_log('OAuth callback fatal [' . $provider_id . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    redirect_to_error($e->getMessage());
}

/**
 * Редирект на страницу с ошибкой
 *
 * @param string $message
 */
function redirect_to_error(string $message): void {
    global $nc_core;

    // Сохраняем ошибку в сессию
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['nc_auth_error'] = $message;

    // Получаем redirect_to или используем главную
    $redirect_to = get_saved_redirect_to();

    // Добавляем параметр ошибки
    $separator = strpos($redirect_to, '?') !== false ? '&' : '?';
    $redirect_to .= $separator . 'auth_error=1';

    header('Location: ' . $redirect_to);
    exit;
}

/**
 * Получить сохранённый redirect_to из сессии
 */
function get_saved_redirect_to(): string {
    global $nc_core;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $redirect_to = $_SESSION['nc_oauth2_redirect_to'] ?? '';
    unset($_SESSION['nc_oauth2_redirect_to']);

    // Валидация URL — должен быть на том же домене
    if ($redirect_to) {
        $parsed = parse_url($redirect_to);
        $host = $parsed['host'] ?? '';

        if ($host && $host !== $_SERVER['HTTP_HOST']) {
            $redirect_to = ''; // Не доверяем внешним URL
        }
    }

    return $redirect_to ?: $nc_core->HTTP_ROOT_PATH;
}
