<?php

require_once "../../require/cron_api.inc.php";


// Удаление пользователей, с неподтвержденной регистрацией
if (($confirm_time = intval($nc_core->get_settings('confirm_time', 'auth')))) {
    $users_to_drop = $db->get_col(
        "SELECT `User_ID` FROM `User`
           WHERE `Confirmed` = 0
             AND `Checked` = 0
             AND `RegistrationCode` <> ''
             AND (UNIX_TIMESTAMP(`Created`) + " . $confirm_time . "*3600) < UNIX_TIMESTAMP(NOW()) "
    );

    if ($users_to_drop) {
        $nc_core->user->delete_by_id($users_to_drop);
    }
}

// Очистка устаревших записей rate limiter
$rate_limiter_file = dirname(__FILE__) . '/classes/rate_limiter.php';
if (file_exists($rate_limiter_file)) {
    require_once $rate_limiter_file;
    $rate_limiter = nc_auth_rate_limiter::get_object();
    if ($rate_limiter->table_exists()) {
        $rate_limiter->cleanup();
    }
}

// Очистка устаревших кодов верификации
$verify_code_file = dirname(__FILE__) . '/classes/verify_code.php';
if (file_exists($verify_code_file)) {
    require_once $verify_code_file;
    $verify_code = nc_auth_verify_code::get_object();
    if ($verify_code->table_exists()) {
        $verify_code->cleanup_expired();
    }
}
