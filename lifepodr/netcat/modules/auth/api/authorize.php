<?php
/**
 * OAuth2 Authorization Initiator
 *
 * Инициирует OAuth2 авторизацию - перенаправляет на страницу провайдера
 *
 * URL: /netcat/modules/auth/api/authorize.php?provider={provider_id}
 *
 * Параметры:
 * - provider: идентификатор провайдера (yandex, vkid, google, telegram)
 * - redirect_to: URL для возврата после авторизации (optional)
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

// Получаем параметры
$provider_id = $nc_core->input->fetch_get('provider');
$redirect_to = $nc_core->input->fetch_get('redirect_to');

if (empty($provider_id)) {
    http_response_code(400);
    die('Provider not specified');
}

// Список поддерживаемых провайдеров
$supported_providers = ['yandex', 'vkid', 'google', 'telegram'];

if (!in_array($provider_id, $supported_providers)) {
    http_response_code(400);
    die('Unknown provider: ' . htmlspecialchars($provider_id));
}

// Загружаем класс провайдера
$provider_class = 'nc_auth_oauth2_' . $provider_id;
$provider_file = dirname(__DIR__) . '/classes/oauth2/' . $provider_id . '.php';

if (!file_exists($provider_file)) {
    http_response_code(500);
    die('Provider class not found');
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

    // Сохраняем redirect_to в сессию
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Валидация redirect_to
    if ($redirect_to) {
        $parsed = parse_url($redirect_to);
        $host = $parsed['host'] ?? '';

        // Разрешаем только URL на том же домене или относительные
        if ($host && $host !== $_SERVER['HTTP_HOST']) {
            $redirect_to = ''; // Не доверяем внешним URL
        }
    }

    // Устанавливаем redirect_to в провайдере (будет сохранён в сессию при генерации state)
    $final_redirect = $redirect_to ?: $nc_core->HTTP_ROOT_PATH;
    $provider->set_redirect_to($final_redirect);

    // Для Telegram - особая обработка (используем виджет на промежуточной странице)
    if ($provider_id === 'telegram') {
        /** @var nc_auth_oauth2_telegram $provider */
        $bot_username = $provider->get_client_id();

        if (empty($bot_username)) {
            throw new Exception(NETCAT_MODULE_AUTH_OAUTH2_PROVIDER_DISABLED);
        }

        // Сохраняем данные в сессию (для Telegram не вызывается generate_state)
        $_SESSION['nc_oauth2_provider'] = 'telegram';
        $_SESSION['nc_oauth2_redirect_to'] = $final_redirect;

        // Редирект на страницу с виджетом
        $widget_url = $nc_core->HTTP_ROOT_PATH . 'modules/auth/api/telegram_widget.php';
        header('Location: ' . $widget_url);
        exit;
    }

    // Стандартный OAuth2 flow - получаем URL авторизации
    $authorization_url = $provider->get_authorization_url();

    // Перенаправляем на провайдера
    header('Location: ' . $authorization_url);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
