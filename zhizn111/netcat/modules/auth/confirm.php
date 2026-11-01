<?php

ob_start();

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)).( strstr(__FILE__, "/") ? "/" : "\\" );
include_once ($NETCAT_FOLDER."vars.inc.php");

require_once ($ROOT_FOLDER."connect_io.php");
require_once ($ADMIN_FOLDER."admin.inc.php");

$url = $parsed_url['path'];
$url = str_replace($nc_core->SUB_FOLDER, '', $url);

$current_catalogue = $nc_core->catalogue->get_by_host_name($_SERVER['HTTP_HOST']);

if ($current_catalogue && (!isset($sub) || !(int)$sub)) {
    $catalogue = $current_catalogue['Catalogue_ID'];
    $sub = $db->get_var(
        "SELECT `Subdivision_ID`
           FROM `Subdivision`
          WHERE `Catalogue_ID` = $current_catalogue[Catalogue_ID]
            AND `ExternalURL` LIKE '%" . $db->escape($url) . "%'"
    );
    if (!$sub && !isset($template)) {
        $template = $current_catalogue['Template_ID'];
        $nc_use_site_template_settings = 1;
    }
}

require_once ($INCLUDE_FOLDER."index.php");

// =====================================================================
// Обработка verify_hash (новая система авторизации по коду/ссылке)
// =====================================================================
$verify_hash = isset($_GET['verify_hash']) ? trim($_GET['verify_hash']) : '';

if ($verify_hash) {
    require_once dirname(__FILE__) . '/classes/verify_code.php';

    $verify_code = nc_auth_verify_code::get_object();
    $result = $verify_code->verify_link($verify_hash);

    if ($result['valid']) {
        $purpose = $result['purpose'] ?? '';
        $email = $result['email'] ?? '';
        $user_id = $result['user_id'] ?? 0;

        // Получаем return_url из сессии или используем главную страницу
        $return_url = isset($_SESSION['nc_auth_return_url']) ? $_SESSION['nc_auth_return_url'] : '/';
        unset($_SESSION['nc_auth_return_url']);

        // Получаем данные пользователя
        $user = $user_id ? $db->get_row("SELECT * FROM `User` WHERE `User_ID` = " . (int)$user_id, ARRAY_A) : null;

        // Определяем нужно ли показывать установку пароля
        $password_mode = 'both';
        $authtype_site = (int)$nc_core->get_settings('authtype_site', 'auth');
        if (defined('NC_AUTHTYPE_LOGIN') && defined('NC_AUTHTYPE_HASH')) {
            $login_enabled = (bool)($authtype_site & NC_AUTHTYPE_LOGIN);
            $hash_enabled = (bool)($authtype_site & NC_AUTHTYPE_HASH);
            if ($login_enabled && $hash_enabled) {
                $password_mode = 'both';
            } elseif ($hash_enabled && !$login_enabled) {
                $password_mode = 'code';
            } else {
                $password_mode = 'password';
            }
        }

        $show_set_password = false;
        $redirect_params = [];

        switch ($purpose) {
            case 'login':
                // Просто авторизуем
                if ($user && $user['Checked']) {
                    $nc_core->user->authorize_by_id($user_id, NC_AUTHTYPE_HASH);
                }
                break;

            case 'registration':
                // Активируем пользователя
                if ($user) {
                    $IsChecked = $nc_core->get_settings('premoderation', 'auth') ? 0 : 1;
                    $db->query("UPDATE `User` SET `Checked` = {$IsChecked}, `Confirmed` = 1, `RegistrationCode` = '' WHERE `User_ID` = " . (int)$user_id);

                    // Уведомление администратора
                    if ($nc_core->get_settings('notify_admin', 'auth')) {
                        $mailer = new CMIMEMail();
                        $mailinfo = $nc_auth->get_notify_admin_mail($user_id);
                        $mailer->mailbody(strip_tags($mailinfo['body']), $mailinfo['html'] ? $mailinfo['body'] : "");
                        $mailer->send(
                            $nc_core->get_settings('admin_notification_email', 'auth') ?: $system_env['NotificationEmail'],
                            $system_env['SpamFromEmail'],
                            $system_env['SpamFromEmail'],
                            $mailinfo['subject'],
                            $system_env['SpamFromName']
                        );
                    }

                    // Авторизуем если включен и autoauthorize
                    if ($IsChecked) {
                        $nc_core->user->authorize_by_id($user_id, NC_AUTHTYPE_HASH);
                        // В режиме both предлагаем установить пароль
                        if ($password_mode === 'both' && empty($user['Password'])) {
                            $show_set_password = true;
                        }
                    }
                }
                break;

            case 'recovery':
                // Авторизуем и предлагаем установить пароль
                if ($user && $user['Checked']) {
                    $nc_core->user->authorize_by_id($user_id, NC_AUTHTYPE_HASH);
                    $show_set_password = true;
                    $redirect_params['from_recovery'] = '1';
                }
                break;

            case 'checkout':
                // Просто подтверждаем email в сессии
                $verify_code->set_checkout_verified($email);
                break;
        }

        // Формируем URL для редиректа
        if ($show_set_password) {
            $redirect_params['nc_auth_action'] = 'set_password';
        }

        if (!empty($redirect_params)) {
            $separator = (strpos($return_url, '?') !== false) ? '&' : '?';
            $return_url .= $separator . http_build_query($redirect_params);
        }

        // Редирект
        header('Location: ' . $return_url);
        exit;

    } else {
        // Ошибка верификации по ссылке
        $warnText = $result['message'] ?? NETCAT_MODULE_AUTH_VERIFY_LINK_EXPIRED;

        $nc_result_msg = ob_get_clean();
        ob_start();

        $warn_msg = output_auth_field('confirm_after_warn', $File_Mode);
        echo $warn_msg ? $warn_msg : "<div id='confirm_warn'>" . htmlspecialchars($warnText) . "</div>";

        $nc_result_msg = ob_get_clean();

        if ($File_Mode) {
            require_once $INCLUDE_FOLDER . 'index_fs.inc.php';
        } else {
            nc_evaluate_template($template_header, $template_footer, false);
        }

        $nc_core->output_page($template_header, $nc_result_msg, $template_footer);
        exit;
    }
}

// =====================================================================
// Классическая логика подтверждения (по id и RegistrationCode)
// =====================================================================
$id = intval($id);
$code = $db->escape($code);
$nc_user_confirm = 0;

if ($id && $code) {
    $IsChecked = ( $nc_core->get_settings('premoderation', 'auth') ) ? 0 : 1;

    // подтверждение пользователя
    $res = $db->query("UPDATE `User`
		SET `Confirmed` = '1', `RegistrationCode` = ''".($IsChecked ? ", `Checked` = '".$IsChecked."'" : "")."
		WHERE `RegistrationCode` = '".$code."' AND `User_ID` = '".$id."'");
    $nc_user_confirm = $db->rows_affected;
    unset($res);

    // оповещение администратора
    if ($nc_user_confirm && $nc_core->get_settings('notify_admin', 'auth')) {
        $mailer = new CMIMEMail();
        $mailinfo = $nc_auth->get_notify_admin_mail($id);
        $mailer->mailbody(strip_tags($mailinfo['body']), $mailinfo['html'] ? $mailinfo['body'] : "");
        $mailer->send($nc_core->get_settings('admin_notification_email', 'auth') ?: $system_env['NotificationEmail'], $system_env['SpamFromEmail'], $system_env['SpamFromEmail'], $mailinfo['subject'], $system_env['SpamFromName']);
    }

    // если пользователь включен и стоит опция "авторизация после подтверждения"
    if ($nc_user_confirm && $IsChecked && $nc_core->get_settings('autoauthorize', 'auth')) {
        Authorize($id, 'authorize');
    }
}

if ($nc_user_confirm) { // успешное подтверждение
    $CheckActionTemplate = $db->get_var("SELECT `CheckActionTemplate` FROM `Class`
                                       WHERE `System_Table_ID`=3 AND `ClassTemplate` = 0 ");

    if ($CheckActionTemplate) eval(nc_check_eval("echo \"".$CheckActionTemplate."\";"));

    if ($nc_core->get_settings('confirm_after_mail', 'auth')) {
        $fromname = $nc_core->get_settings('SpamFromName');
        $fromemail = $nc_core->get_settings('SpamFromEmail');
        $EmailField = $nc_core->get_settings('UserEmailField');

        $mail_info = $nc_auth->get_confirm_after_mail($id);

        $mail_body = $nc_core->mail->attachment_attach($mail_info['body'], 'auth_confirm_after_' . $catalogue);
        $nc_core->mail->mailbody(strip_tags($mail_body), $mail_info['html'] ? $mail_body : '');
        $nc_core->mail->send($mail_info['user_email'], $fromemail, $fromemail, $mail_info['subject'], $fromname);
    }

    $after_msg = output_auth_field('confirm_after', $File_Mode);
    echo $after_msg ? $after_msg : "<div id='confirm_after'>" . NETCAT_MODULE_AUTH_REG_OK . "</div>";
    if (!$IsChecked) {
        echo "<div>" . NETCAT_MODULE_AUTH_REG_ADMIN . "</div>";
    }
    
} elseif (!$id || !$code) { // неправильная ссылка
    $warnText = NETCAT_MODULE_AUTH_REG_INVALIDLINK;
} elseif ($db->get_var("SELECT COUNT(*) FROM `User` WHERE `User_ID` = '" . $id . "' AND `RegistrationCode` = ''")) {
    // пользователь уже активирован
    echo "<div>".NETCAT_MODULE_AUTH_REG_AGAIN."</div>"; 
    if (!$IsChecked && $db->get_var("SELECT `Checked` FROM `User` WHERE `User_ID` = '" . $id . "'")) {
        $IsChecked = 1;
    }
    if (!$IsChecked) {
        echo "<div>".NETCAT_MODULE_AUTH_REG_ADMIN."</div>";
    }
} else { // пользователь не найден
    $warnText = NETCAT_MODULE_AUTH_REG_ERROR;
}

if ($warnText) {
    $warn_msg = output_auth_field('confirm_after_warn', $File_Mode);
    echo $warn_msg ? $warn_msg : "<div id='confirm_warn'>$warnText</div>";
}

$nc_result_msg = ob_get_clean();

if ($File_Mode) {
    require_once $INCLUDE_FOLDER.'index_fs.inc.php';
} else {
    nc_evaluate_template($template_header, $template_footer, false);
}

$nc_core->output_page($template_header, $nc_result_msg, $template_footer);

function output_auth_field($field, $file_mode) {
    global $warnText;
    ob_start();

    if ($file_mode) {
        $auth_view = new nc_tpl_module_view();
        $auth_view->load('auth', nc_core()->get_interface());
        $field_path = $auth_view->get_field_path($field);
        if (file_get_contents($field_path)) {
            include($field_path);
        }
    }
    else {
        eval(nc_check_eval("echo \"" . nc_core()->get_settings($field, 'auth') . "\";"));
    }

    return ob_get_clean();
}