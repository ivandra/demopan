<?php

/**
 * Точка приёма журнала выбора в окне cookie-согласия.
 *
 * Вызывается фронтовым рантаймом nc-consent.js (sendBeacon) при выборе
 * посетителя, если журналирование включено в настройках сайта.
 * POST application/x-www-form-urlencoded: uid, categories, mode, text, sig, url.
 * Ответ — только HTTP-статус (см. nc_cookie_consent_log::RESULT_*).
 *
 * @see docs/proposals/cookie-consent-log.md
 */

// Сайт — только по хосту запроса. nc_catalogue::__construct() берёт текущий
// сайт из $_REQUEST['current_catalogue_id'], если параметр есть, — иначе
// можно было бы писать в журнал другого сайта установки (с его лимитами и
// подписью текста). Убираем параметр до подключения ядра.
unset(
    $_REQUEST['current_catalogue_id'],
    $_GET['current_catalogue_id'],
    $_POST['current_catalogue_id'],
    $_COOKIE['current_catalogue_id']
);

$NETCAT_FOLDER = realpath(__DIR__ . '/../../../');
putenv('DOCUMENT_ROOT=' . $NETCAT_FOLDER);
$_SERVER['DOCUMENT_ROOT'] = $NETCAT_FOLDER;
chdir($NETCAT_FOLDER);
require_once $NETCAT_FOLDER . '/vars.inc.php';
require_once $ROOT_FOLDER . 'connect_io.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (nc_array_value($_SERVER, 'REQUEST_METHOD') !== 'POST') {
    http_response_code(405);
    exit;
}

// Запрос только со страниц этого же сайта: Origin (sendBeacon/fetch его
// передают) или, если его нет, Referer должны указывать на текущий хост.
// Это защищает только от отправки с чужих страниц в браузере: скрипт вне
// браузера подставит любые заголовки. От массовой отправки — лимиты
// (nc_cookie_consent_log::check_limits()).
$host = strtolower((string)nc_array_value($_SERVER, 'HTTP_HOST'));
foreach (array('HTTP_ORIGIN', 'HTTP_REFERER') as $header) {
    $value = (string)nc_array_value($_SERVER, $header);
    if ($value === '') {
        continue;
    }
    $request_host = strtolower((string)parse_url($value, PHP_URL_HOST));
    $request_port = parse_url($value, PHP_URL_PORT);
    if ($request_port) {
        $request_host .= ':' . $request_port;
    }
    if ($request_host !== $host) {
        http_response_code(403);
        exit;
    }
    break;
}

require_once __DIR__ . '/nc_cookie_consent_log.class.php';

$nc_core = nc_core::get_object();
$data = array();
foreach (array('uid', 'categories', 'mode', 'text', 'sig', 'url') as $key) {
    $value = $nc_core->input->fetch_post($key);
    if ($value !== null && !is_scalar($value)) {
        http_response_code(400);
        exit;
    }
    $value = (string)$value;
    // рантайм шлёт UTF-8; на установках в windows-1251 приводим к кодировке БД
    $data[$key] = $nc_core->NC_UNICODE ? $value : $nc_core->utf8->utf2win($value);
}

http_response_code(nc_cookie_consent_log::log_choice($data));
