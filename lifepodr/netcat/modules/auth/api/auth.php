<?php

/**
 * REST API для современной авторизации
 *
 * Действия:
 * - check_email: проверка существования пользователя
 * - send_code: отправка кода верификации
 * - verify_code: проверка кода
 * - authorize: авторизация (по коду или паролю)
 * - set_password: установка пароля после входа по коду
 * - register: регистрация нового пользователя
 *
 * @package nc_auth
 */

// Подключение ядра
$NETCAT_FOLDER = realpath(__DIR__ . '/../../../../');
putenv('DOCUMENT_ROOT=' . $NETCAT_FOLDER);
$_SERVER['DOCUMENT_ROOT'] = $NETCAT_FOLDER;
chdir($NETCAT_FOLDER);
require_once $NETCAT_FOLDER . '/vars.inc.php';
require_once $ROOT_FOLDER . 'connect_io.php';
require_once $INCLUDE_FOLDER . 's_common.inc.php';

// Заголовки для JSON API
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CSRF защита
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

// Проверка Referer (если передан)
if ($referer && parse_url($referer, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_error', 'message' => 'Invalid request origin']);
    exit;
}

// Список мутирующих операций, требующих дополнительной проверки
$mutating_actions = ['authorize', 'register', 'set_password', 'reset_password', 'logout'];
$request_action = $_POST['action'] ?? $_GET['action'] ?? '';

// Для мутирующих операций проверяем X-Requested-With заголовок (защита от простых CSRF)
if (in_array($request_action, $mutating_actions, true)) {
    $xhr_header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    // Разрешаем только AJAX-запросы или запросы с корректным Referer
    if (empty($xhr_header) && empty($referer)) {
        http_response_code(403);
        echo json_encode(['error' => 'csrf_error', 'message' => 'AJAX header required']);
        exit;
    }
}

// Получаем данные запроса
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

// Глобальный обработчик ошибок для API
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Подключаем классы
require_once dirname(__FILE__) . '/../classes/verify_code.php';
require_once dirname(__FILE__) . '/../classes/rate_limiter.php';
require_once dirname(__FILE__) . '/../classes/password.php';
require_once dirname(__FILE__) . '/../classes/captcha_policy.php';

// В этой точке входа модули не загружаются автоматически — подключаем модуль
// капчи явно, чтобы были доступны nc_captcha_formfield()/nc_captcha_verify_code().
if (nc_module_check_by_keyword('captcha')) {
    $nc_captcha_function_file = nc_module_folder('captcha') . 'function.inc.php';
    if (is_file($nc_captcha_function_file)) {
        require_once $nc_captcha_function_file;
    }
    // Языковые константы модуля капчи (NETCAT_MODULE_CAPTCHA_REFRESH и др.):
    // в этой точке входа модули не грузятся автоматически, а провайдеры капчи
    // используют их в разметке. Без lang image-провайдер падает с
    // «Undefined constant» → 500 (ru_utf8 — как и собственный lang auth ниже).
    $nc_captcha_lang_file = nc_module_folder('captcha') . 'ru_utf8.lang.php';
    if (is_file($nc_captcha_lang_file)) {
        require_once $nc_captcha_lang_file;
    }
}

$nc_core = nc_Core::get_object();
$db = $nc_core->db;

/**
 * Безопасное получение поля авторизации (whitelist)
 * Защита от SQL injection через AUTHORIZE_BY
 */
function get_safe_auth_field(nc_Core $nc_core): string {
    $auth_by = $nc_core->AUTHORIZE_BY;
    $allowed = ['Email', 'Login'];
    return in_array($auth_by, $allowed, true) ? $auth_by : 'Email';
}

/**
 * Нужна ли капча для действия (делегирует единой политике nc_auth_captcha_policy
 * через nc_user::captcha_is_required). Возвращает false, если модуль капчи недоступен.
 *
 * @param string $action  login | register | recovery
 */
function auth_captcha_required(nc_Core $nc_core, string $action, ?string $email = null): bool {
    if (!function_exists('nc_captcha_formfield')) {
        return false;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return (bool)$nc_core->user->captcha_is_required($action, $email, $ip);
}

/**
 * HTML-блок капчи для попапа: виджет провайдера в контексте динамического DOM
 * (context = js_template — скрипты выводятся при каждом запросе). Для встроенной
 * (символьной) капчи добавляется отдельное поле ввода.
 */
function auth_captcha_html(nc_Core $nc_core): string {
    if (!function_exists('nc_captcha_formfield')) {
        return '';
    }

    $widget = nc_captcha_formfield('', '', '', '', '', false, 'authpopup', array('context' => 'js_template'));

    $needs_input = false;
    if (class_exists('nc_captcha')) {
        $needs_input = nc_captcha::get_instance()->get_provider()->requires_separate_input_field();
    }

    $label = defined('NETCAT_MODERATION_CAPTCHA') ? NETCAT_MODERATION_CAPTCHA : '';

    $out  = '<div class="nc-auth-captcha">';
    $out .= '<div class="nc-auth-captcha__widget">' . $widget . '</div>';
    if ($needs_input) {
        $out .= '<input type="text" name="nc_captcha_code" class="nc-auth-field__input nc-auth-captcha__input"'
              . ' autocomplete="off" placeholder="' . htmlspecialchars($label, ENT_QUOTES) . '">';
    }
    $out .= '<div class="nc-auth-error" data-error="captcha"></div>';
    $out .= '</div>';

    return $out;
}

/**
 * Проверяет ответ капчи из запроса. Возвращает true, если капча не требуется
 * для действия или пройдена; false — если требуется и не пройдена.
 *
 * @param string $action  login | register | recovery
 */
function auth_verify_captcha(nc_Core $nc_core, array $input, string $action, ?string $email = null): bool {
    if (!auth_captcha_required($nc_core, $action, $email)) {
        return true;
    }
    if (!function_exists('nc_captcha_verify_code')) {
        return true;
    }
    $token = (string)($input['captcha_token'] ?? '');
    $hash  = (string)($input['captcha_hash'] ?? '');
    if ($token === '') {
        return false;
    }
    return (bool)nc_captcha_verify_code($token, $hash, true);
}

/**
 * Формирует JSON-ответ с требованием капчи (новый HTML виджета прилагается).
 *
 * @param bool $wrong  true — капча введена неверно; false — капча требуется
 */
function auth_captcha_response(nc_Core $nc_core, bool $wrong = false) {
    echo json_encode(array(
        'success' => false,
        'error' => $wrong ? 'captcha_wrong' : 'captcha_required',
        'message' => defined('NETCAT_MODULE_CAPTCHA_WRONG_CODE') ? NETCAT_MODULE_CAPTCHA_WRONG_CODE : 'Подтвердите, что вы не робот',
        'captcha_html' => auth_captcha_html($nc_core),
    ));
}

// Загружаем языковой файл модуля
require_once dirname(__FILE__) . '/../ru_utf8.lang.php';

// Rate limiter
$rate_limiter = nc_auth_rate_limiter::get_object();
$ip = $_SERVER['REMOTE_ADDR'];

// Проверка блокировки
if ($rate_limiter->is_blocked($ip)) {
    $unlock_time = $rate_limiter->get_unlock_time($ip);
    http_response_code(429);
    echo json_encode([
        'error' => 'rate_limited',
        'message' => NETCAT_MODULE_AUTH_API_RATE_LIMITED,
        'retry_after' => $unlock_time,
    ]);
    exit;
}

try {
    switch ($action) {
        case 'check_email':
            handle_check_email($input, $nc_core, $db);
            break;

        case 'send_code':
            handle_send_code($input, $nc_core);
            break;

        case 'verify_code':
            handle_verify_code($input, $nc_core);
            break;

        case 'authorize':
            handle_authorize($input, $nc_core, $db, $rate_limiter, $ip);
            break;

        case 'set_password':
            handle_set_password($input, $nc_core);
            break;

        case 'reset_password':
            handle_reset_password($input, $nc_core, $db);
            break;

        case 'register':
            handle_register($input, $nc_core, $db);
            break;

        case 'logout':
            handle_logout($nc_core);
            break;

        case 'get_user':
            handle_get_user($nc_core);
            break;

        case 'get_register_fields':
            handle_get_register_fields($nc_core);
            break;

        case 'get_oauth_providers':
            handle_get_oauth_providers($nc_core);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'invalid_action', 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);

    // Логируем полную ошибку для отладки
    error_log('Auth API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    // В production не показываем детали ошибки
    $is_debug = defined('NC_DEBUG') && NC_DEBUG;

    $error_response = [
        'error' => 'server_error',
        'message' => $is_debug ? $e->getMessage() : 'Internal server error',
    ];

    // Детали только в debug-режиме
    if ($is_debug) {
        $error_response['file'] = basename($e->getFile());
        $error_response['line'] = $e->getLine();
    }

    echo json_encode($error_response);
}

exit;

/**
 * Проверяет существование пользователя по email
 */
function handle_check_email(array $input, nc_Core $nc_core, nc_Db $db) {
    $email = strtolower(trim($input['email'] ?? ''));

    if (!nc_check_email($email)) {
        echo json_encode([
            'success' => false,
            'error' => 'invalid_email',
            'message' => NETCAT_MODULE_AUTH_API_INVALID_EMAIL,
        ]);
        return;
    }

    $escaped_email = $db->escape($email);
    $auth_by = get_safe_auth_field($nc_core);

    // Проверяем привязку к каталогу
    $bind_to_catalogue = '';
    if ($nc_core->get_settings('bind_to_catalogue', 'auth')) {
        $catalogue = $nc_core->catalogue->id();
        $bind_to_catalogue = " AND `Catalogue_ID` IN (0, {$catalogue})";
    }

    $user = $db->get_row(
        "SELECT `User_ID`, `Password`, `Checked` FROM `User`
         WHERE `{$auth_by}` = '{$escaped_email}'{$bind_to_catalogue}
         LIMIT 1",
        ARRAY_A
    );

    $exists = !empty($user);
    $has_password = $exists && !empty($user['Password']);
    $is_active = $exists && $user['Checked'] == 1;

    // Режим использования пароля: password, both, code
    $password_mode = get_password_mode($nc_core);

    // Капча для входа (если уже превышен порог неудачных попыток)
    $login_captcha_needed = $exists && auth_captcha_required($nc_core, 'login', $email);

    echo json_encode([
        'success' => true,
        'exists' => $exists,
        'has_password' => $has_password,
        'is_active' => $is_active,
        'password_mode' => $password_mode,
        'need_captcha' => $login_captcha_needed,
        'captcha_html' => $login_captcha_needed ? auth_captcha_html($nc_core) : '',
    ]);
}

/**
 * Отправляет код верификации
 */
function handle_send_code(array $input, nc_Core $nc_core) {
    $email = strtolower(trim($input['email'] ?? ''));
    $purpose = $input['purpose'] ?? nc_auth_verify_code::PURPOSE_LOGIN;

    // Капча: для входа по коду — политика login (после N неудач), для
    // восстановления — политика recovery. Закрывает обход капчи через код.
    $captcha_action = ($purpose === nc_auth_verify_code::PURPOSE_RECOVERY) ? 'recovery' : 'login';
    if (auth_captcha_required($nc_core, $captcha_action, $email)
        && !auth_verify_captcha($nc_core, $input, $captcha_action, $email)) {
        auth_captcha_response($nc_core, trim((string)($input['captcha_token'] ?? '')) !== '');
        return;
    }

    $verify_code = nc_auth_verify_code::get_object();

    // Получаем user_id если пользователь существует
    $db = $nc_core->db;
    $escaped_email = $db->escape($email);
    $user_id = $db->get_var("SELECT `User_ID` FROM `User` WHERE `Email` = '{$escaped_email}'");

    // Сохраняем return_url для редиректа после подтверждения по ссылке
    $return_url = $input['return_url'] ?? '';
    if (empty($return_url)) {
        $return_url = $_SERVER['HTTP_REFERER'] ?? '/';
    }
    // Убираем параметры авторизации из return_url
    $return_url = preg_replace('/[?&]nc_auth_action=[^&]*/', '', $return_url);
    $return_url = preg_replace('/[?&]from_recovery=[^&]*/', '', $return_url);
    $_SESSION['nc_auth_return_url'] = $return_url;

    $result = $verify_code->send($email, $purpose, $user_id);

    echo json_encode($result);
}

/**
 * Проверяет код верификации
 */
function handle_verify_code(array $input, nc_Core $nc_core) {
    $email = strtolower(trim($input['email'] ?? ''));
    $code = trim($input['code'] ?? '');

    $verify_code = nc_auth_verify_code::get_object();
    $result = $verify_code->verify($email, $code);

    // Если это checkout верификация, сохраняем в сессию
    if ($result['valid'] && $result['purpose'] === nc_auth_verify_code::PURPOSE_CHECKOUT) {
        $verify_code->set_checkout_verified($email);
    }

    echo json_encode($result);
}

/**
 * Авторизует пользователя
 */
function handle_authorize(array $input, nc_Core $nc_core, nc_Db $db, $rate_limiter, $ip) {
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? null;
    $code = $input['code'] ?? null;

    if (!nc_check_email($email)) {
        echo json_encode([
            'success' => false,
            'error' => 'invalid_email',
            'message' => NETCAT_MODULE_AUTH_API_INVALID_EMAIL,
        ]);
        return;
    }

    // Капча для входа (после N неудачных попыток — по единой политике).
    // Покрывает оба способа входа: и по паролю, и по коду.
    if (auth_captcha_required($nc_core, 'login', $email)) {
        $captcha_provided = trim((string)($input['captcha_token'] ?? '')) !== '';
        if (!auth_verify_captcha($nc_core, $input, 'login', $email)) {
            if ($captcha_provided) {
                $rate_limiter->record_attempt($ip, $email, false);
            }
            auth_captcha_response($nc_core, $captcha_provided);
            return;
        }
    }

    $escaped_email = $db->escape($email);
    $auth_by = get_safe_auth_field($nc_core);

    // Привязка к каталогу
    $bind_to_catalogue = '';
    if ($nc_core->get_settings('bind_to_catalogue', 'auth')) {
        $catalogue = $nc_core->catalogue->id();
        $bind_to_catalogue = " AND `Catalogue_ID` IN (0, {$catalogue})";
    }

    // Авторизация по коду
    if ($code) {
        $verify_code = nc_auth_verify_code::get_object();
        $verify_result = $verify_code->verify($email, $code);

        if (!$verify_result['valid']) {
            $rate_limiter->record_attempt($ip, $email, false);
            echo json_encode([
                'success' => false,
                'error' => $verify_result['error'],
                'message' => $verify_result['message'],
                'attempts_left' => $verify_result['attempts_left'] ?? null,
            ]);
            return;
        }

        // Код верный - получаем пользователя
        $user = $db->get_row(
            "SELECT * FROM `User` WHERE `{$auth_by}` = '{$escaped_email}'{$bind_to_catalogue} LIMIT 1",
            ARRAY_A
        );

        if (!$user) {
            // Пользователь не найден - нужна регистрация
            echo json_encode([
                'success' => false,
                'error' => 'user_not_found',
                'message' => NETCAT_MODULE_AUTH_API_USER_NOT_FOUND,
                'need_registration' => true,
                'email_verified' => true,
            ]);
            return;
        }

        // Если пользователь не активирован и код был для регистрации - активируем
        $just_registered = false;
        if (!$user['Checked']) {
            $code_purpose = $verify_result['purpose'] ?? '';

            if ($code_purpose === nc_auth_verify_code::PURPOSE_REGISTRATION) {
                // Активируем пользователя после подтверждения email.
                // Confirmed = 1 — для паритета с подтверждением по ссылке (confirm.php),
                // иначе флаг «email подтверждён» оставался бы 0 у зарегистрированных через код.
                $db->query("UPDATE `User` SET `Checked` = 1, `Confirmed` = 1, `RegistrationCode` = '' WHERE `User_ID` = " . (int)$user['User_ID']);
                $user['Checked'] = 1;
                $just_registered = true;
            } else {
                // Код не для регистрации, а пользователь выключен - ошибка
                echo json_encode([
                    'success' => false,
                    'error' => 'user_disabled',
                    'message' => NETCAT_MODULE_AUTH_API_USER_DISABLED,
                ]);
                return;
            }
        }

        // Авторизуем
        $user_id = $nc_core->user->authorize_by_id($user['User_ID'], NC_AUTHTYPE_LOGIN);
        $rate_limiter->record_attempt($ip, $email, true);

        // Режим использования пароля
        $password_mode = get_password_mode($nc_core);

        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'user' => get_safe_user_data($user),
            'has_password' => !empty($user['Password']),
            'password_mode' => $password_mode,
            'just_registered' => $just_registered,
        ]);
        return;
    }

    // Авторизация по паролю
    if ($password) {
        $user = $db->get_row(
            "SELECT * FROM `User` WHERE `{$auth_by}` = '{$escaped_email}'{$bind_to_catalogue} LIMIT 1",
            ARRAY_A
        );

        if (!$user) {
            $rate_limiter->record_attempt($ip, $email, false);
            echo json_encode([
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => NETCAT_MODULE_AUTH_API_INVALID_CREDENTIALS,
            ]);
            return;
        }

        // Проверка пароля с поддержкой legacy хешей
        $password_handler = nc_auth_password::get_object();
        $password_valid = $password_handler->verify($password, $user['Password']);

        if (!$password_valid) {
            $rate_limiter->record_attempt($ip, $email, false);
            echo json_encode([
                'success' => false,
                'error' => 'invalid_credentials',
                'message' => NETCAT_MODULE_AUTH_API_INVALID_CREDENTIALS,
            ]);
            return;
        }

        if (!$user['Checked']) {
            echo json_encode([
                'success' => false,
                'error' => 'user_disabled',
                'message' => NETCAT_MODULE_AUTH_API_USER_DISABLED,
            ]);
            return;
        }

        // Миграция пароля на Argon2 если нужно
        $password_handler->migrate_if_needed($user['User_ID'], $password);

        // Авторизуем
        $user_id = $nc_core->user->authorize_by_id($user['User_ID'], NC_AUTHTYPE_LOGIN);
        $rate_limiter->record_attempt($ip, $email, true);

        // Режим использования пароля
        $password_mode = get_password_mode($nc_core);

        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'user' => get_safe_user_data($user),
            'password_mode' => $password_mode,
        ]);
        return;
    }

    echo json_encode([
        'success' => false,
        'error' => 'missing_credentials',
        'message' => NETCAT_MODULE_AUTH_API_MISSING_CREDENTIALS,
    ]);
}

/**
 * Устанавливает пароль для пользователя
 */
function handle_set_password(array $input, nc_Core $nc_core) {
    // Получаем user_id из сессии - поддержка и session, и cookie авторизации
    $user_id = get_authorized_user_id($nc_core);

    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'not_authorized',
            'message' => NETCAT_MODULE_AUTH_API_NOT_AUTHORIZED,
        ]);
        return;
    }

    $AUTH_USER_ID = $user_id;

    $password = $input['password'] ?? '';
    $min_length = (int)$nc_core->get_settings('pass_min', 'auth') ?: 6;

    if (strlen($password) < $min_length) {
        echo json_encode([
            'success' => false,
            'error' => 'password_too_short',
            'message' => sprintf(NETCAT_MODULE_AUTH_API_PASSWORD_TOO_SHORT, $min_length),
        ]);
        return;
    }

    $password_handler = nc_auth_password::get_object();
    $result = $password_handler->set_user_password($AUTH_USER_ID, $password);

    echo json_encode([
        'success' => $result,
        'message' => $result ? NETCAT_MODULE_AUTH_API_PASSWORD_SET : NETCAT_MODULE_AUTH_API_ERROR,
    ]);
}

/**
 * Сбрасывает пароль пользователя по коду верификации
 */
function handle_reset_password(array $input, nc_Core $nc_core, nc_Db $db) {
    $email = strtolower(trim($input['email'] ?? ''));
    $code = trim($input['code'] ?? '');
    $password = $input['password'] ?? '';

    // Проверка email
    if (!nc_check_email($email)) {
        echo json_encode([
            'success' => false,
            'error' => 'invalid_email',
            'message' => NETCAT_MODULE_AUTH_API_INVALID_EMAIL,
        ]);
        return;
    }

    // Проверка пароля
    $min_length = (int)$nc_core->get_settings('pass_min', 'auth') ?: 6;
    if (strlen($password) < $min_length) {
        echo json_encode([
            'success' => false,
            'error' => 'password_too_short',
            'message' => sprintf(NETCAT_MODULE_AUTH_API_PASSWORD_TOO_SHORT, $min_length),
        ]);
        return;
    }

    // Проверка ранее верифицированного кода
    $verify_code = nc_auth_verify_code::get_object();
    $verify_result = $verify_code->check_verified_code($email, $code);

    if (!$verify_result['valid']) {
        echo json_encode([
            'success' => false,
            'error' => $verify_result['error'],
            'message' => $verify_result['message'],
            'attempts_left' => $verify_result['attempts_left'] ?? null,
        ]);
        return;
    }

    // Проверяем что код был для сброса пароля
    if (($verify_result['purpose'] ?? '') !== 'recovery') {
        echo json_encode([
            'success' => false,
            'error' => 'invalid_code_purpose',
            'message' => NETCAT_MODULE_AUTH_API_INVALID_CODE,
        ]);
        return;
    }

    // Находим пользователя
    $escaped_email = $db->escape($email);
    $auth_by = get_safe_auth_field($nc_core);

    $bind_to_catalogue = '';
    if ($nc_core->get_settings('bind_to_catalogue', 'auth')) {
        $catalogue = $nc_core->catalogue->id();
        $bind_to_catalogue = " AND `Catalogue_ID` IN (0, {$catalogue})";
    }

    $user = $db->get_row(
        "SELECT * FROM `User` WHERE `{$auth_by}` = '{$escaped_email}'{$bind_to_catalogue} LIMIT 1",
        ARRAY_A
    );

    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'user_not_found',
            'message' => NETCAT_MODULE_AUTH_API_USER_NOT_FOUND,
        ]);
        return;
    }

    // Устанавливаем новый пароль
    $password_handler = nc_auth_password::get_object();
    $result = $password_handler->set_user_password($user['User_ID'], $password);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => 'password_update_failed',
            'message' => NETCAT_MODULE_AUTH_API_ERROR,
        ]);
        return;
    }

    // Авторизуем пользователя
    $user_id = $nc_core->user->authorize_by_id($user['User_ID'], NC_AUTHTYPE_LOGIN);

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'user' => get_safe_user_data($user),
        'message' => NETCAT_MODULE_AUTH_API_PASSWORD_SET,
    ]);
}

/**
 * Регистрирует нового пользователя
 */
function handle_register(array $input, nc_Core $nc_core, nc_Db $db) {
    // Проверка, разрешена ли регистрация
    if ($nc_core->get_settings('deny_reg', 'auth')) {
        echo json_encode([
            'success' => false,
            'error' => 'registration_disabled',
            'message' => NETCAT_MODULE_AUTH_SELFREG_DISABLED,
        ]);
        return;
    }

    $email = strtolower(trim($input['email'] ?? ''));
    $email_verified = (bool)($input['email_verified'] ?? false);
    $fields_data = $input['fields'] ?? [];

    $auth_settings = $nc_core->get_settings('', 'auth');

    // Проверка согласия на обработку ПД
    $agreed_pd = (bool)($input['agreed_pd'] ?? $input['agreed'] ?? false);
    $consent_pd_required = isset($input['consent_pd_required'])
        ? (bool)$input['consent_pd_required']
        : (isset($input['consent_required']) ? (bool)$input['consent_required'] : !empty($auth_settings['agreed']));
    if ($consent_pd_required && !$agreed_pd) {
        echo json_encode([
            'success' => false,
            'error' => 'consent_pd_required',
            'message' => NETCAT_MODULE_AUTH_API_CONSENT_PD_REQUIRED,
        ]);
        return;
    }

    // Проверка согласия с правилами сайта
    $agreed_rules = (bool)($input['agreed_rules'] ?? false);
    $consent_rules_required = isset($input['consent_rules_required'])
        ? (bool)$input['consent_rules_required']
        : !empty($auth_settings['agreed_rules']);
    if ($consent_rules_required && !$agreed_rules) {
        echo json_encode([
            'success' => false,
            'error' => 'consent_rules_required',
            'message' => NETCAT_MODULE_AUTH_API_CONSENT_RULES_REQUIRED,
        ]);
        return;
    }

    // Валидация email
    if (!nc_check_email($email)) {
        echo json_encode([
            'success' => false,
            'error' => 'invalid_email',
            'message' => NETCAT_MODULE_AUTH_API_INVALID_EMAIL,
        ]);
        return;
    }

    // Проверка существования пользователя
    $check_result = $nc_core->user->check_login($email);
    if ($check_result !== NC_AUTH_LOGIN_OK) {
        echo json_encode([
            'success' => false,
            'error' => 'email_exists',
            'message' => NETCAT_MODULE_AUTH_API_EMAIL_EXISTS,
        ]);
        return;
    }

    // Капча регистрации (по умолчанию — всегда, по единой политике)
    if (auth_captcha_required($nc_core, 'register', $email)
        && !auth_verify_captcha($nc_core, $input, 'register', $email)) {
        auth_captcha_response($nc_core, trim((string)($input['captcha_token'] ?? '')) !== '');
        return;
    }

    // Получаем разрешённые поля из компонента User
    $component = new nc_Component(0, 3);
    $all_fields = $component->get_fields();
    $allowed_field_names = [];
    $required_fields = [];
    $authorize_by = $nc_core->AUTHORIZE_BY;

    $allowed_fields_setting = !empty($auth_settings['field_custom'])
        ? explode(',', $auth_settings['field_custom'])
        : [];
    $show_all_fields = !empty($auth_settings['field_all']);

    foreach ($all_fields as $field) {
        // Пропускаем служебные поля
        if ($field['name'] === $authorize_by) {
            continue;
        }
        if ((int)$field['edit_type'] === NC_FIELD_PERMISSION_ADMIN) {
            continue;
        }
        if ((int)$field['edit_type'] === NC_FIELD_PERMISSION_NOONE) {
            continue;
        }
        if (!$show_all_fields && !in_array($field['name'], $allowed_fields_setting, true)) {
            continue;
        }
        // Пропускаем сложные типы
        if (in_array((int)$field['type'], [NC_FIELDTYPE_FILE, NC_FIELDTYPE_MULTIFILE, NC_FIELDTYPE_RELATION])) {
            continue;
        }

        $allowed_field_names[] = $field['name'];
        if ($field['not_null']) {
            $required_fields[$field['name']] = $field['description'];
        }
    }

    // Валидация обязательных полей
    foreach ($required_fields as $field_name => $field_label) {
        $value = trim($fields_data[$field_name] ?? '');
        if ($value === '') {
            echo json_encode([
                'success' => false,
                'error' => 'field_required',
                'field' => $field_name,
                'message' => sprintf(NETCAT_MODULE_AUTH_API_FIELD_REQUIRED, $field_label),
            ]);
            return;
        }
    }

    // Группы для регистрации
    $groups = $nc_core->get_settings('group', 'auth');

    $need_confirmation = !$email_verified && !empty($auth_settings['confirm']);
    $need_premoderation = !empty($auth_settings['premoderation']);

    // Режим использования пароля
    $password_mode = get_password_mode($nc_core);
    $input_password = trim($input['password'] ?? '');

    // В режиме 'password' пароль обязателен при регистрации
    if ($password_mode === 'password') {
        $min_password_length = (int)$nc_core->get_settings('pass_min', 'auth') ?: 6;
        if (strlen($input_password) < $min_password_length) {
            echo json_encode([
                'success' => false,
                'error' => 'password_required',
                'message' => sprintf(NETCAT_MODULE_AUTH_API_PASSWORD_TOO_SHORT, $min_password_length),
            ]);
            return;
        }
        $password = $input_password;
    } else {
        // В режимах 'both' и 'code' - генерируем случайный пароль
        // В 'both' пользователь сможет установить его позже
        $password = bin2hex(random_bytes(16));
    }

    // Код подтверждения
    $registration_code = $need_confirmation ? md5(uniqid('', true)) : '';

    try {
        // Формируем массив полей для создания пользователя
        $user_fields = [
            'Email' => $email,
        ];

        // Добавляем только разрешённые поля
        foreach ($fields_data as $field_name => $value) {
            if (in_array($field_name, $allowed_field_names, true)) {
                $user_fields[$field_name] = trim($value);
            }
        }

        $add_fields = [
            'Checked' => ($need_confirmation || $need_premoderation) ? 0 : 1,
        ];

        $user_id = $nc_core->user->add($user_fields, $groups, $password, $add_fields, $registration_code);

        // Журнал согласий 152-ФЗ: согласия при регистрации через попап/API.
        // Текст галочек — серверно из настроек auth (consentPDText/consentRulesText) с
        // фолбэком на константу; факт отметки — из проверенных выше agreed_pd/agreed_rules.
        // Логируем только если модуль «Логирование» включён. В API-эндпоинте модули не
        // загружены через load_env, поэтому класс подгружаем вручную — но лишь когда модуль
        // реально включён (get_by_keyword читает БД и уважает флаг Checked).
        $logging_enabled = $user_id && (bool)$nc_core->modules->get_by_keyword('logging');
        if ($logging_enabled && !class_exists('nc_consent_log') && function_exists('nc_module_folder')) {
            @include_once nc_module_folder('logging') . 'nc_consent_log.class.php';
        }
        if ($logging_enabled && class_exists('nc_consent_log')) {
            $consents = array();
            if ($consent_pd_required && $agreed_pd) {
                // Фолбэк-текст нужен лишь когда consentPDText не задан. Ссылку в %USER_AGR
                // строит nc_auth_regform_url() из бутстрапа модуля (function.inc.php), который
                // в API-эндпоинте не подключается — поэтому вызываем только если функция есть.
                // Иначе fatal: аргументы resolve() вычисляются до вызова, даже если фолбэк
                // в итоге не используется (текст настроен).
                $pd_fallback = NETCAT_MODULE_AUTH_USER_AGREEMENT;
                if (function_exists('nc_auth_regform_url')) {
                    $pd_fallback = str_replace('%USER_AGR', $nc_core->SUB_FOLDER . nc_auth_regform_url(0, 0) . 'agreed/', $pd_fallback);
                }
                $text = nc_consent_text::resolve(nc_consent_text::SLOT_PD, $auth_settings['consentPDText'] ?? '', null, $pd_fallback);
                $consents[] = array('label_html' => $text, 'type' => 'pd', 'action' => 'grant');
            }
            if ($consent_rules_required && $agreed_rules) {
                $text = nc_consent_text::resolve(nc_consent_text::SLOT_RULES, $auth_settings['consentRulesText'] ?? '', null, NETCAT_MODULE_AUTH_USER_AGREEMENT);
                $consents[] = array('label_html' => $text, 'type' => 'rules', 'action' => 'grant');
            }
            if ($consents) {
                // имя резолвит фасад по user_id (User.Name) — здесь SELECT не дублируем
                nc_consent_log::log_consent(
                    array('user_id' => (int)$user_id, 'email' => $email, 'source_table' => 'User', 'source_object_id' => (int)$user_id),
                    $consents
                );
            }
        }

        // Если email уже верифицирован (через код) - активируем.
        // Confirmed = 1 — для паритета с подтверждением по ссылке (confirm.php).
        if ($email_verified && !$need_premoderation) {
            $db->query("UPDATE `User` SET `Checked` = 1, `Confirmed` = 1, `RegistrationCode` = '' WHERE `User_ID` = {$user_id}");
        }

        // Отправка письма подтверждения если нужно
        if ($need_confirmation && !$email_verified) {
            $code_sent = false;
            $error_message = '';

            try {
                $verify_code = nc_auth_verify_code::get_object();
                // force=true чтобы пропустить проверку интервала при новой регистрации
                $send_result = $verify_code->send($email, nc_auth_verify_code::PURPOSE_REGISTRATION, $user_id, true);
                $code_sent = !empty($send_result['success']);
                if (!$code_sent && !empty($send_result['message'])) {
                    $error_message = $send_result['message'];
                }
            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }

            echo json_encode([
                'success' => true,
                'user_id' => $user_id,
                'need_confirmation' => true,
                'code_sent' => $code_sent,
                'password_mode' => $password_mode,
                'message' => $code_sent
                    ? NETCAT_MODULE_AUTH_API_REGISTRATION_CODE_SENT
                    : ($error_message ?: NETCAT_MODULE_AUTH_VERIFY_EMAIL_ERROR),
            ]);
            return;
        }

        // Премодерация - пользователь создан, но не активен
        if ($need_premoderation) {
            echo json_encode([
                'success' => true,
                'user_id' => $user_id,
                'need_premoderation' => true,
                'password_mode' => $password_mode,
                'message' => NETCAT_MODULE_AUTH_REG_ADMIN,
            ]);
            return;
        }

        // Автоавторизация если включена (пользователь уже активен)
        if (!empty($auth_settings['autoauthorize'])) {
            $nc_core->user->authorize_by_id($user_id, NC_AUTHTYPE_LOGIN);
        }

        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'auto_authorized' => !empty($auth_settings['autoauthorize']),
            'password_mode' => $password_mode,
            'message' => NETCAT_MODULE_AUTH_API_REGISTRATION_SUCCESS,
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'registration_error',
            'message' => $e->getMessage(),
        ]);
    }
}

/**
 * Выход из системы
 */
function handle_logout(nc_Core $nc_core) {
    Unauthorize();
    echo json_encode([
        'success' => true,
        'message' => NETCAT_MODULE_AUTH_MSG_AUTHUPISOK,
    ]);
}

/**
 * Получает данные текущего пользователя
 */
function handle_get_user(nc_Core $nc_core) {
    // Получаем user_id - поддержка и session, и cookie авторизации
    $user_id = get_authorized_user_id($nc_core);

    if (!$user_id) {
        echo json_encode([
            'authorized' => false,
        ]);
        return;
    }

    $user = $nc_core->user->get_by_id($user_id);

    echo json_encode([
        'authorized' => true,
        'user' => get_safe_user_data($user),
    ]);
}

/**
 * Возвращает поля для формы регистрации
 * Учитывает настройки модуля и права доступа к полям
 */
function handle_get_register_fields(nc_Core $nc_core) {
    $component = new nc_Component(0, 3); // User table
    $all_fields = $component->get_fields();

    $auth_settings = $nc_core->get_settings('', 'auth');
    $allowed_fields = !empty($auth_settings['field_custom'])
        ? explode(',', $auth_settings['field_custom'])
        : [];
    $show_all_fields = !empty($auth_settings['field_all']);
    $authorize_by = $nc_core->AUTHORIZE_BY;

    $result_fields = [];

    // Маппинг типов полей для фронтенда
    $type_map = [
        NC_FIELDTYPE_STRING => 'string',
        NC_FIELDTYPE_INT => 'int',
        NC_FIELDTYPE_TEXT => 'text',
        NC_FIELDTYPE_SELECT => 'select',
        NC_FIELDTYPE_BOOLEAN => 'boolean',
        NC_FIELDTYPE_FILE => 'file',
        NC_FIELDTYPE_FLOAT => 'float',
        NC_FIELDTYPE_DATETIME => 'datetime',
        NC_FIELDTYPE_RELATION => 'relation',
        NC_FIELDTYPE_MULTISELECT => 'multiselect',
        NC_FIELDTYPE_MULTIFILE => 'multifile',
    ];

    foreach ($all_fields as $field) {
        // Пропускаем поле авторизации (Email) - оно уже введено
        if ($field['name'] === $authorize_by) {
            continue;
        }

        // Пропускаем поля только для админа
        if ((int)$field['edit_type'] === NC_FIELD_PERMISSION_ADMIN) {
            continue;
        }

        // Пропускаем поля недоступные никому
        if ((int)$field['edit_type'] === NC_FIELD_PERMISSION_NOONE) {
            continue;
        }

        // Проверяем настройки показа полей
        if (!$show_all_fields && !in_array($field['name'], $allowed_fields, true)) {
            continue;
        }

        // Пропускаем сложные типы полей для попапа
        if (in_array((int)$field['type'], [
            NC_FIELDTYPE_FILE,
            NC_FIELDTYPE_MULTIFILE,
            NC_FIELDTYPE_RELATION,
        ])) {
            continue;
        }

        $field_data = [
            'name' => $field['name'],
            'type' => $type_map[(int)$field['type']] ?? 'string',
            'label' => $field['description'],
            'required' => (bool)$field['not_null'],
            'default' => $field['default'] ?? '',
        ];

        // Для select/multiselect добавляем опции
        if (in_array((int)$field['type'], [NC_FIELDTYPE_SELECT, NC_FIELDTYPE_MULTISELECT])) {
            $field_data['options'] = get_field_options($field, $nc_core);
        }

        $result_fields[] = $field_data;
    }

    // Капча регистрации — показываем сразу на экране регистрации, если требуется
    $register_captcha_needed = auth_captcha_required($nc_core, 'register');

    echo json_encode([
        'success' => true,
        'fields' => $result_fields,
        'require_agreement' => !empty($auth_settings['agreed']),
        'need_captcha' => $register_captcha_needed,
        'captcha_html' => $register_captcha_needed ? auth_captcha_html($nc_core) : '',
    ]);
}

/**
 * Получает опции для поля select/multiselect
 */
function get_field_options(array $field, nc_Core $nc_core): array {
    $options = [];
    $format = $field['format'] ?? '';

    // Формат: "table_name:value_field:label_field" или просто список
    if (strpos($format, ':') !== false) {
        $parts = explode(':', $format);
        $table = $parts[0] ?? '';

        if ($table && $nc_core->db->get_var("SHOW TABLES LIKE '{$table}'")) {
            $rows = $nc_core->db->get_results(
                "SELECT * FROM `{$table}` ORDER BY `Priority`, `{$table}_ID`",
                ARRAY_A
            );
            foreach ($rows as $row) {
                $id = $row[$table . '_ID'] ?? $row['id'] ?? reset($row);
                $name = $row[$table . '_Name'] ?? $row['Name'] ?? $row['name'] ?? $id;
                $options[] = ['value' => $id, 'label' => $name];
            }
        }
    }

    return $options;
}

/**
 * Вычисляет режим использования пароля на основе настроек authtype_site
 *
 * @param nc_Core $nc_core
 * @return string 'password' | 'code' | 'both'
 */
function get_password_mode(nc_Core $nc_core): string {
    $authtype_site = (int)$nc_core->get_settings('authtype_site', 'auth');
    $login_enabled = (bool)($authtype_site & NC_AUTHTYPE_LOGIN);
    $hash_enabled = (bool)($authtype_site & NC_AUTHTYPE_HASH);

    if ($login_enabled && $hash_enabled) {
        return 'both';
    }
    if ($hash_enabled && !$login_enabled) {
        return 'code';
    }
    // По умолчанию - только пароль
    return 'password';
}

/**
 * Возвращает безопасные данные пользователя (без пароля и т.п.)
 */
function get_safe_user_data($user): array {
    $safe_fields = ['User_ID', 'Email', 'Name', 'Login', 'Phone'];
    $result = [];

    foreach ($safe_fields as $field) {
        if (isset($user[$field])) {
            $result[$field] = $user[$field];
        }
    }

    return $result;
}

/**
 * Возвращает список включённых OAuth2 провайдеров
 */
function handle_get_oauth_providers(nc_Core $nc_core): void {
    $catalogue_id = $nc_core->catalogue->id();

    // Проверяем, включена ли авторизация через внешние сервисы в общих настройках
    $authtype_site = (int)$nc_core->get_settings('authtype_site', 'auth');
    if (!($authtype_site & NC_AUTHTYPE_EX)) {
        // Авторизация через внешние сервисы отключена
        echo json_encode([
            'success' => true,
            'providers' => [],
        ]);
        return;
    }

    // Получаем настройки OAuth2 провайдеров
    $settings_raw = $nc_core->get_settings('oauth2_providers', 'auth', false, $catalogue_id);
    $providers_settings = $settings_raw ? @unserialize($settings_raw) : [];

    // Список поддерживаемых провайдеров с названиями
    $provider_names = [
        'yandex' => defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_YANDEX') ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_YANDEX : 'Яндекс ID',
        'vkid' => defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_VKID') ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_VKID : 'VK ID',
        'google' => defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_GOOGLE') ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_GOOGLE : 'Google',
        'telegram' => defined('NETCAT_MODULE_AUTH_ADMIN_OAUTH2_TELEGRAM') ? NETCAT_MODULE_AUTH_ADMIN_OAUTH2_TELEGRAM : 'Telegram',
    ];

    $enabled_providers = [];

    foreach ($provider_names as $id => $name) {
        $provider_config = $providers_settings[$id] ?? [];

        // Проверяем, включён ли провайдер и настроен ли он
        if (empty($provider_config['enabled'])) {
            continue;
        }

        // Проверяем наличие обязательных настроек
        $is_configured = false;
        switch ($id) {
            case 'yandex':
            case 'google':
                $is_configured = !empty($provider_config['client_id']) && !empty($provider_config['client_secret']);
                break;
            case 'vkid':
                $is_configured = !empty($provider_config['app_id']) && !empty($provider_config['secure_key']);
                break;
            case 'telegram':
                $is_configured = !empty($provider_config['bot_token']) && !empty($provider_config['bot_username']);
                break;
        }

        if ($is_configured) {
            $enabled_providers[] = [
                'id' => $id,
                'name' => $name,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'providers' => $enabled_providers,
    ]);
}

/**
 * Получает ID авторизованного пользователя
 * Поддерживает оба типа авторизации: session и cookie
 *
 * @param nc_Core $nc_core
 * @return int User ID или 0 если не авторизован
 */
function get_authorized_user_id(nc_Core $nc_core): int {
    $db = $nc_core->db;

    // Для session авторизации
    if ($nc_core->AUTHORIZATION_TYPE === 'session') {
        return (int)($_SESSION['User']['ID'] ?? 0);
    }

    // Для cookie авторизации - проверяем таблицу Session
    $session_id = $_COOKIE['PHP_AUTH_SID'] ?? '';
    if (!$session_id) {
        return 0;
    }

    $session_id = $db->escape($session_id);
    $user_id = (int)$db->get_var(
        "SELECT s.`User_ID`
         FROM `Session` s
         INNER JOIN `User` u ON u.`User_ID` = s.`User_ID`
         WHERE s.`Session_ID` = '{$session_id}'
         AND s.`SessionTime` > " . time() . "
         AND u.`Checked` = 1"
    );

    return $user_id;
}
