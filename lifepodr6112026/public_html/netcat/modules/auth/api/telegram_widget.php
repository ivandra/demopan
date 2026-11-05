<?php
/**
 * Страница с Telegram Login Widget
 *
 * Telegram не поддерживает стандартный OAuth редирект,
 * поэтому используем виджет на промежуточной странице.
 */

$NETCAT_FOLDER = realpath(__DIR__ . '/../../../../');
putenv('DOCUMENT_ROOT=' . $NETCAT_FOLDER);
$_SERVER['DOCUMENT_ROOT'] = $NETCAT_FOLDER;
chdir($NETCAT_FOLDER);
require_once $NETCAT_FOLDER . '/vars.inc.php';
require_once $ROOT_FOLDER . 'connect_io.php';
require_once $INCLUDE_FOLDER . 's_common.inc.php';

$nc_core = nc_Core::get_object();

// Загружаем настройки Telegram
$catalogue_id = $nc_core->catalogue->id();
$settings_raw = $nc_core->get_settings('oauth2_providers', 'auth', false, $catalogue_id);
$providers_settings = $settings_raw ? @unserialize($settings_raw) : [];
$telegram_settings = $providers_settings['telegram'] ?? [];

$bot_username = $telegram_settings['bot_username'] ?? '';

if (empty($bot_username)) {
    die('Telegram bot not configured');
}

// Callback URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$callback_url = $protocol . $host . $nc_core->HTTP_ROOT_PATH . 'modules/auth/api/callback.php';

// Сохраняем redirect_to в сессию
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['nc_oauth2_provider'] = 'telegram';
// redirect_to уже должен быть сохранён в authorize.php

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход через Telegram</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 24px;
            color: #333;
        }
        p {
            margin: 0 0 30px;
            color: #666;
        }
        .back-link {
            display: block;
            margin-top: 20px;
            color: #999;
            text-decoration: none;
        }
        .back-link:hover {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Вход через Telegram</h1>
        <p>Нажмите кнопку для авторизации</p>

        <script async src="https://telegram.org/js/telegram-widget.js?22"
            data-telegram-login="<?= htmlspecialchars($bot_username) ?>"
            data-size="large"
            data-radius="8"
            data-auth-url="<?= htmlspecialchars($callback_url) ?>"
            data-request-access="write">
        </script>

        <a href="javascript:history.back()" class="back-link">← Вернуться назад</a>
    </div>
</body>
</html>
