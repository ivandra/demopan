<?php
/* $id$ */

class nc_auth_admin {

    protected $core, $db;

    public function __construct() {
        $this->core = nc_Core::get_object();
        // global variables to internal
        $this->db = & $this->core->db;
    }

    public function get_mainsettings_url() {
        return "#module.auth.general";
    }

    /**
     * Вывод информации о модуле
     */
    public function info_show() {
        $all_user_count = $this->db->get_var("SELECT COUNT(`User_ID`) FROM `User`");
        $all_user_unckecked = $this->db->get_var("SELECT COUNT(`User_ID`) FROM `User` WHERE `Checked` = 0");
        $all_user_nonconfirmed = $this->db->get_var("SELECT COUNT(`User_ID`) FROM `User` WHERE `Checked` = 0 AND `Confirmed` = 0 AND `RegistrationCode` <> '' ");

        echo "<br /><div style='margin-bottom: 20px;'>" . NETCAT_MODULE_AUTH_DESCRIPTION . "</div>";
        echo "<div style='margin-bottom: 4px;'>" . NETCAT_MODULE_AUTH_ADMIN_INFO_USER_COUNT . ": " . ($all_user_count ? "<a href='" . $this->core->ADMIN_PATH . "user/'>" . $all_user_count . "</a>" : NETCAT_MODULE_AUTH_ADMIN_INFO_NONE) . "</div>";
        echo "<div style='margin-bottom: 4px;'>" . NETCAT_MODULE_AUTH_ADMIN_INFO_USER_COUNT_UNCHECKED . ": " . ($all_user_unckecked ? "<a href='" . $this->core->ADMIN_PATH . "user/?Checked=2'>" . $all_user_unckecked . "</a>" : NETCAT_MODULE_AUTH_ADMIN_INFO_NONE) . "</div>";
        echo "<div>" . NETCAT_MODULE_AUTH_ADMIN_INFO_USER_COUNT_UNCONFIRMED . ": " . ($all_user_nonconfirmed ? "<a href='" . $this->core->ADMIN_PATH . "user/?nonConfirmed=1'>" . $all_user_nonconfirmed . "</a>" : NETCAT_MODULE_AUTH_ADMIN_INFO_NONE) . "</div>";
    }

    /**
     * dummy
     */
    public function info_save() {
        return;
    }

    /**
     * Настройки регистрации по логину и паролю
     */
    public function classic_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_reg_toolbar();
        // настройки
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());

        // поля из системной таблицы
        $st = new nc_Component(0, 3);
        $fields = array();
        foreach ($st->get_fields() as $v) {
            if ($v['edit_type'] != 1)
                continue;
            if ($v['name'] == $this->core->AUTHORIZE_BY)
                continue;
            $fields[$v['name']] = $v['description'];
        }

        // группы пользователя
        $groups = array();
        $res = $this->db->get_results("SELECT `PermissionGroup_ID` as `id`, `PermissionGroup_Name` as `name` FROM `PermissionGroup` ORDER BY `PermissionGroup_ID`", ARRAY_A);
        if (!empty($res))
            foreach ($res as $v)
                $groups[$v['id']] = $v['name'];

        // основные настройки
        echo "<form method='post' action='admin.php' class='nc-form' id='adminForm' style='padding:0; margin:0;'>\n";
        echo $this->catalogue_select_field('classic');
        echo "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_MAIN_SETTINGS_TITLE . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_DENY_REG, 'deny_reg', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_DENY_RECOVERY, 'deny_recovery', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_ALLOW_CYRILLIC, 'allow_cyrillic', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_ALLOW_SPECIALCHARS, 'allow_specialchars', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_ALLOW_CHANGE_LOGIN, 'allow_change_login', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_BING_TO_CATALOGUE, 'bind_to_catalogue', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_WITH_SUBDOMAIN, 'with_subdomain', $settings);
        echo nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_PASS_MIN, 'pass_min', $settings, 1);

        $captcha_settings_link = nc_core::get_object()->ADMIN_PATH . '#security.auth.captcha';
        echo '<div><a href="' . $captcha_settings_link . '" target="_top">' . NETCAT_MODULE_AUTH_ADMIN_CLASSIC_AUTH_CAPTCHA . '</a></div>';

        echo "</fieldset>\n";

        // форма регистрации
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_CLASSIC_REGISTRATION_FORM . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CHECK_LOGIN, 'check_login', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CHECK_PASS, 'check_pass', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CHECK_PASS2, 'check_pass2', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CHECK_AGREED, 'agreed', $settings);
        // Пустой текст → дефолт из настроек сайта (nc_consent_text), поэтому
        // placeholder показывает встроенный текст, а не перетирает поле.
        echo "<div class='ncf_row' style='margin-left:20px'><label>" . NETCAT_MODULE_AUTH_ADMIN_CONSENT_PD_TEXT . "</label><br/>";
        echo "<input type='text' name='consentPDText' id='consentPDText' style='width:100%' value='" . htmlspecialchars($settings['consentPDText'] ?? '') . "' placeholder='" . htmlspecialchars(nc_consent_text::fallback_text(nc_consent_text::SLOT_PD)) . "'></div>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CHECK_AGREED_RULES, 'agreed_rules', $settings);
        echo "<div class='ncf_row' style='margin-left:20px'><label>" . NETCAT_MODULE_AUTH_ADMIN_CONSENT_RULES_TEXT . "</label><br/>";
        echo "<input type='text' name='consentRulesText' id='consentRulesText' style='width:100%' value='" . htmlspecialchars($settings['consentRulesText'] ?? '') . "' placeholder='" . htmlspecialchars(nc_consent_text::fallback_text(nc_consent_text::SLOT_RULES)) . "'></div>\n";

        echo NETCAT_MODULE_AUTH_ADMIN_CLASSIC_FIELDS_IN_REG_FORM . ":<br/>";
        echo "<input type='radio' name='field_all' id='field_all' value='1' " . ($settings['field_all'] ? "checked='checked'" : "") . "><label for='field_all'>" . NETCAT_MODULE_AUTH_ADMIN_CLASSIC_FIELDS_IN_REG_FORM_ALL . "</label><br/>";
        echo "<input type='radio' name='field_all' id='field_custom' value='0' " . (!$settings['field_all'] ? "checked='checked'" : "") . "><label for='field_custom'>" . NETCAT_MODULE_AUTH_ADMIN_CLASSIC_FIELDS_IN_REG_FORM_CUSTOM . "</label>";
        echo "<div style='padding-left: 15px;'>";
        $f = explode(',', $settings['field_custom']);
        foreach ($fields as $k => $v) {
            echo nc_admin_checkbox($v, 'field_custom_' . $k, in_array($k, $f));
        }
        echo "</div>";
        echo "</fieldset>\n";


        // активация
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_CLASSIC_ACTIVATION . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CONFIRM, 'confirm', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CONFIRM_AFTER_MAIL, 'confirm_after_mail', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_PREMODARATION, 'premoderation', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_NOTIFY_ADMIN, 'notify_admin', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_AUTHAUTORIZE, 'autoauthorize', $settings);
        echo nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_CLASSIC_CONFIRM_TIME, 'confirm_time', $settings, 4);
        echo "</fieldset>\n";

        // группы пользователей
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE . "\n" .
            "</legend>\n";
        $f = explode(',', $settings['group']);
        if (empty($f[0])) {
                echo nc_print_status(NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE_EMPTY, 'error', null, 1);
            }
        foreach ($groups as $g_id => $g_name) {
            echo nc_admin_checkbox($g_name, 'group_' . $g_id, in_array($g_id, $f));
        }
        echo "</fieldset>\n";

        if (!isset($Catalogue_ID)) {
            $Catalogue_ID = null;
        }
        echo "<input type='hidden' name='Catalogue_ID' value='" . $Catalogue_ID . "' />\n";
        echo $this->core->token->get_input() . "\n";
        echo "<input type='hidden' name='view' value='classic' />\n";
        echo "<input type='hidden' name='act' value='save' />\n";
        echo "</form>\n";


        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
    }

    public function classic_save() {
        $catalogue_id = $this->core->catalogue->id();

        $params = array(
            'deny_reg',
            'deny_recovery',
            'allow_cyrillic',
            'allow_specialchars',
            'allow_change_login',
            'pass_min',
            'bind_to_catalogue',
            'with_subdomain',
            'check_login',
            'check_pass',
            'check_pass2',
            'agreed',
            'agreed_rules',
            'confirm_time',
            'field_all',
            'confirm',
            'confirm_after_mail',
            'premoderation',
            'notify_admin',
            'autoauthorize'
        );

        // настройки
        foreach ($params as $v) {
            $this->core->set_settings($v, (int)$this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        // текстовые настройки согласий (не int, а строки)
        foreach (array('consentPDText', 'consentRulesText') as $v) {
            $this->core->set_settings($v, (string)$this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        // поля, выводимые при регистрации
        $f = array();
        foreach ($this->core->input->fetch_get_post() as $k => $v) {
            if (nc_preg_match('/field_custom_([a-z_]\w*)/i', $k, $match)) {
                $f[] = $match[1];
            }
        }
        $this->core->set_settings('field_custom', implode(',', $f), 'auth', $catalogue_id);

        // группа
        $f = array();
        foreach ($this->core->input->fetch_get_post() as $k => $v) {
            if (nc_preg_match('/group_([0-9]+)/i', $k, $match)) {
                $f[] = $match[1];
            }
        }
        $this->core->set_settings('group', implode(',', $f), 'auth', $catalogue_id);
    }

    /**
     * Показ формы настроек шаблонов вывода
     */
    public function templates_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();

        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $nc_auth = nc_auth::get_object();

        $auth_editor = new nc_tpl_module_subtypes_editor(true);
        $auth_editor->load('auth')->fill();

        ?>

        <script type="text/javascript">
            body = {
                user_login_form: "<?php echo nc_text_for_js(str_replace('"', '\\"', $nc_auth->tpl->get_user_login_form_default_fs())); ?>",
                change_password_form: "<?php echo nc_text_for_js(str_replace('"', '\\"', $nc_auth->tpl->get_change_password_form_default_fs())); ?>",
                recovery_password_form: "<?php echo nc_text_for_js(str_replace('"', '\\"', $nc_auth->tpl->get_recovery_password_form_default_fs())); ?>"
            };

            function recovery(type, name, confirmed){
                if (!confirmed) {
                    if (confirm(ncLang.WarnAuthMail)) {
                        recovery(type, name, 1);
                    }
                    return false;
                }

                selectedTextarea = jQuery("#" + type + "_" + name);
                selectedTextarea.val(body[name]);
                if (typeof selectedTextarea.codemirror == 'function') {
                    selectedTextarea.codemirror('setValue');
                }
            }
        </script>
        <br/>
        <?php
        $auth_settings = $auth_editor->get_all_fields();
        $auth_settings['old'] = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());
        echo "<form action='admin.php' name='AuthSettings' id='AuthSettings' method='post'>";
        echo $this->catalogue_select_field('templates');
        echo NETCAT_MODULE_AUTH_ADMIN_INFO;

        foreach ($auth_settings as $type => $settings) {
            $pref = ($type == 'old' ? '' : $type . '_');
            echo "
    <legend style='padding-bottom: 0px;'>" . constant("TITLE_" . strtoupper($type)) . "</legend>
    <fieldset>
        <div style='float:right; margin-right: 20px;'><a href='#' onclick=\"recovery('$type', 'user_login_form'); return false;\" >
        " . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
        " . nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_AUTH, $pref . 'user_login_form', nc_array_value($settings,'user_login_form'), 1, 0, "height:15em;") . "
    </fieldset>

    <fieldset>
        <div style='float:right; margin-right: 20px;'><a href='#' onclick=\"recovery('$type', 'change_password_form'); return false;\" >" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>" .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CHG_PASS, $pref . 'change_password_form', nc_array_value($settings,'change_password_form'), 1, 0, "height:15em;") .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CHG_PASS_AFTER, $pref . 'change_password_after', nc_array_value($settings,'change_password_after'), 1) .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CHG_PASS_WARNBLOCK, $pref . 'change_password_warn', nc_array_value($settings,'change_password_warn'), 1) . "
    </fieldset>

    <fieldset>
        <div style='float:right; margin-right: 20px;'>
        <a href='#' onclick=\"recovery('$type', 'recovery_password_form'); return false;\" >" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>" .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_REC_PASS, $pref . 'recovery_password_form', nc_array_value($settings,'recovery_password_form'), 1, 0, "height:15em;") .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_REC_PASS_AFTER, $pref . 'recovery_password_after', nc_array_value($settings,'recovery_password_after'), 1) .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CHG_PASS_WARNBLOCK, $pref . 'recovery_password_warn', nc_array_value($settings,'recovery_password_warn'), 1) .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CHG_PASS_DENY, $pref . 'recovery_password_deny', nc_array_value($settings,'recovery_password_deny'), 1) . "
    </fieldset>

    <fieldset>" .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CONFIRM_AFTER, $pref . 'confirm_after', nc_array_value($settings,'confirm_after'), 1) .
                nc_admin_textarea(NETCAT_MODULE_AUTH_FORM_CONFIRM_AFTER_WARNBLOCK, $pref . 'confirm_after_warn', nc_array_value($settings,'confirm_after_warn'), 1) . "
    </fieldset>
        ";
        }
        echo "<br />";

        echo $this->core->token->get_input() . "
    <input type='hidden' name='view' value='templates' />
    <input type='hidden' name='act' value='save' /></form>";

        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
        return 0;
    }

    public function templates_save() {
        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $catalogue_id = $this->core->catalogue->id();

        $params = array(
            'user_login_form',
            'user_login_form_disable',
            'change_password_form',
            'change_password_after',
            'change_password_warn',
            'recovery_password_form',
            'recovery_password_after',
            'recovery_password_warn',
            'recovery_password_deny',
            'confirm_after',
            'confirm_after_warn',
        );

        // настройки
        foreach ($params as $v) {
            $this->core->set_settings($v, $this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        $module_editor = new nc_tpl_module_subtypes_editor();
        $module_editor->load('auth')->save($_POST, true);
    }

    /**
     * Показ формы настроек авторизации через внешние сервисы (современные провайдеры)
     * Яндекс ID, VK ID, Telegram, Google
     */
    public function ex_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_reg_toolbar();

        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());

        // Получаем настройки современных провайдеров
        $providers_settings = nc_array_value($settings, 'oauth2_providers');
        $providers_settings = $providers_settings ? unserialize($providers_settings) : [];

        // Группы пользователей
        $groups = [];
        $res = $this->db->get_results(
            "SELECT `PermissionGroup_ID` as `id`, `PermissionGroup_Name` as `name`
             FROM `PermissionGroup` ORDER BY `PermissionGroup_ID`",
            ARRAY_A
        );
        if (!empty($res)) {
            foreach ($res as $v) {
                $groups[$v['id']] = $v['name'];
            }
        }

        // Callback URL для провайдеров (полный, с протоколом и доменом)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? $this->core->HTTP_HOST ?? 'localhost';
        $callback_url = $protocol . $host . $this->core->HTTP_ROOT_PATH . 'modules/auth/api/callback.php';

        echo "<form action='admin.php' name='adminForm' class='nc-form' id='adminForm' method='post'>";
        echo $this->catalogue_select_field('ex');
        echo $this->core->token->get_input();
        echo "<input type='hidden' name='view' value='ex' /><input type='hidden' name='act' value='save' />";

        // Описание
        echo "<div class='nc-info-box' style='margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;'>";
        echo "<strong>" . NETCAT_MODULE_AUTH_ADMIN_OAUTH2_DESCRIPTION . "</strong><br>";
        echo NETCAT_MODULE_AUTH_ADMIN_OAUTH2_CALLBACK_HINT . ":";
        echo "</div>";

        // Список провайдеров
        $providers = [
            'yandex' => [
                'name' => NETCAT_MODULE_AUTH_ADMIN_OAUTH2_YANDEX,
                'icon' => 'yandex.svg',
                'fields' => ['client_id', 'client_secret'],
                'help_url' => 'https://oauth.yandex.ru/',
            ],
            'vkid' => [
                'name' => NETCAT_MODULE_AUTH_ADMIN_OAUTH2_VKID,
                'icon' => 'vkid.svg',
                'fields' => ['app_id', 'secure_key', 'service_token'],
                'help_url' => 'https://id.vk.com/about/business/go',
            ],
            'telegram' => [
                'name' => NETCAT_MODULE_AUTH_ADMIN_OAUTH2_TELEGRAM,
                'icon' => 'telegram.svg',
                'fields' => ['bot_token', 'bot_username'],
                'help_url' => 'https://core.telegram.org/widgets/login',
            ],
            'google' => [
                'name' => NETCAT_MODULE_AUTH_ADMIN_OAUTH2_GOOGLE,
                'icon' => 'google.svg',
                'fields' => ['client_id', 'client_secret'],
                'help_url' => 'https://console.cloud.google.com/',
            ],
        ];

        $result = '';
        foreach ($providers as $provider_id => $provider_info) {
            $provider_settings = nc_array_value($providers_settings, $provider_id, []);

            $field = new nc_admin_fieldset($provider_info['name']);

            // Чекбокс включения
            $field->add(nc_admin_checkbox(
                NETCAT_MODULE_AUTH_ADMIN_OAUTH2_ENABLED,
                $provider_id . '_enabled',
                nc_array_value($provider_settings, 'enabled', 0)
            ));

            // Callback URL (одинаковый для всех провайдеров)
            $field->add(
                "<div style='margin: 10px 0; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px;'>" .
                "<small style='color: #666;'>Redirect URI (скопируйте в настройки приложения):</small><br>" .
                "<input type='text' readonly value='" . htmlspecialchars($callback_url) . "' " .
                "style='width: 100%; font-family: monospace; font-size: 12px; border: none; background: transparent; cursor: pointer;' " .
                "onclick='this.select(); document.execCommand(\"copy\"); alert(\"Скопировано!\");'>" .
                "</div>"
            );

            // Поля настроек
            foreach ($provider_info['fields'] as $field_name) {
                $label_const = 'NETCAT_MODULE_AUTH_ADMIN_OAUTH2_' . strtoupper($field_name);
                $label = defined($label_const) ? constant($label_const) : $field_name;
                $field->add(nc_admin_input(
                    $label,
                    $provider_id . '_' . $field_name,
                    nc_array_value($provider_settings, $field_name, ''),
                    0,
                    'width: 100%; margin-bottom: 5px;'
                ));
            }

            // Группы пользователей
            $html = "<div style='margin: 10px 0;'><strong>" . NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE . ":</strong></div>";
            $selected_groups = nc_array_value($provider_settings, 'groups', []);
            if (empty($selected_groups)) {
                $html .= nc_print_status(NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE_EMPTY, 'error', null, 1);
            }
            foreach ($groups as $g_id => $g_name) {
                $checked = in_array($g_id, (array)$selected_groups) ? " checked='checked'" : "";
                $html .= "<label style='display: block; margin: 3px 0;'>" .
                    "<input type='checkbox' name='{$provider_id}_groups[]' value='{$g_id}'{$checked}> {$g_name}" .
                    "</label>";
            }
            $field->add($html);

            // Ссылка на документацию
            $field->add(
                "<div style='margin-top: 10px;'>" .
                "<a href='" . $provider_info['help_url'] . "' target='_blank' class='nc-btn nc--light nc--small'>" .
                "<i class='nc-icon nc--help'></i> " . NETCAT_MODULE_AUTH_ADMIN_OAUTH2_DOCS .
                "</a></div>"
            );

            $result .= $field->result();
            unset($field);
        }

        echo $result;

        // Проверка, есть ли настроенные legacy-провайдеры
        if ($this->has_legacy_providers_configured()) {
            echo "<div class='nc-info-box' style='margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;'>";
            echo "<strong>" . NETCAT_MODULE_AUTH_ADMIN_LEGACY_NOTICE . "</strong><br>";
            echo NETCAT_MODULE_AUTH_ADMIN_LEGACY_DESCRIPTION . " ";
            echo "<a href='#module.auth.ex_legacy'>" . NETCAT_MODULE_AUTH_ADMIN_LEGACY_LINK . "</a>";
            echo "</div>";
        }

        echo "</form>";

        $UI_CONFIG->actionButtons[] = [
            "id" => "submit",
            "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
            "action" => "mainView.submitIframeForm('adminForm')"
        ];

        return true;
    }

    /**
     * Сохранение настроек современных OAuth2 провайдеров
     */
    public function ex_save() {
        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $catalogue_id = $this->core->catalogue->id();
        $input = $this->core->input->fetch_get_post();

        $providers = ['yandex', 'vkid', 'telegram', 'google'];
        $field_map = [
            'yandex' => ['client_id', 'client_secret'],
            'vkid' => ['app_id', 'secure_key', 'service_token'],
            'telegram' => ['bot_token', 'bot_username'],
            'google' => ['client_id', 'client_secret'],
        ];

        $providers_settings = [];

        foreach ($providers as $provider_id) {
            $provider_data = [
                'enabled' => (int)nc_array_value($input, $provider_id . '_enabled', 0),
                'groups' => nc_array_value($input, $provider_id . '_groups', []),
            ];

            // Сохраняем специфичные поля провайдера
            foreach ($field_map[$provider_id] as $field_name) {
                $provider_data[$field_name] = trim(nc_array_value($input, $provider_id . '_' . $field_name, ''));
            }

            $providers_settings[$provider_id] = $provider_data;
        }

        $this->core->set_settings('oauth2_providers', serialize($providers_settings), 'auth', $catalogue_id);

        return true;
    }

    /**
     * Проверяет, настроены ли legacy OAuth провайдеры (VK, FB, Twitter, OpenID)
     */
    protected function has_legacy_providers_configured(): bool {
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());
        $ex_enabled = unserialize(nc_array_value($settings, 'ex_enabled', ''));

        if (!is_array($ex_enabled)) {
            return false;
        }

        foreach ($ex_enabled as $provider => $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
    }

    /**
     * Показ формы настроек авторизации через внешние сервисы (устаревшие провайдеры)
     * Использует Hybrid_Auth: VK, Facebook, Twitter, OpenID
     */
    public function ex_legacy_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_reg_toolbar();

        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        // настройки
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());

        $settings['ex_enabled'] = unserialize($settings['ex_enabled']);
        $settings['ex_apps'] = unserialize($settings['ex_apps']);
        $settings['ex_group'] = unserialize($settings['ex_group']);
        $settings['ex_addaction_prep'] = unserialize($settings['ex_addaction_prep']);
        $settings['ex_addaction'] = unserialize($settings['ex_addaction']);
        $settings['ex_fields'] = unserialize($settings['ex_fields']);
        $settings['ex_openid_providers'] = unserialize($settings['ex_openid_providers']);
	    $settings['ex_oauth_providers'] = unserialize((nc_array_value($settings,'ex_oauth_providers')));
        if (!$settings['ex_group']) {
            $settings['ex_group'] = array();
        }

        // группы пользователя
        $groups = array();
        $res = $this->db->get_results("SELECT `PermissionGroup_ID` as `id`, `PermissionGroup_Name` as `name` FROM `PermissionGroup` ORDER BY `PermissionGroup_ID`", ARRAY_A);
        if (!empty($res)) {
            foreach ($res as $v) {
                $groups[$v['id']] = $v['name'];
            }
        }

        // поля из системной таблицы
        $utable = new nc_Component(0, 3);
        $field_user = $utable->get_fields();
        $js_field_user = array();
        if (!empty($field_user)) {
            foreach ($field_user as $v) {
                $js_field_user[] = "" . $v['name'] . ": '" . $v['description'] . "'";
            }
        }

        if (!$this->core->php_ext("curl")) {
            nc_print_status(NETCAT_MODULE_AUTH_ADMIN_EX_CURL_REQUIRED, 'info');
        }
        if (!$this->core->php_ext("json")) {
            nc_print_status(NETCAT_MODULE_AUTH_ADMIN_EX_JSON_REQUIRED, 'info');
        }

        echo "<form action='admin.php' name='adminForm' class='nc-form' id='adminForm' method='post'>";
        echo $this->catalogue_select_field('ex');
        echo $this->core->token->get_input();
        echo "<input type='hidden' name='view' value='ex' /><input type='hidden' name='act' value='save' />";

        // настройки каждого сервиса
        $types = array('vk', 'fb', 'twitter', 'openid', 'oauth');
        $result = '';
        foreach ($types as $v) {
            $field = new nc_admin_fieldset(constant("NETCAT_MODULE_AUTH_ADMIN_EX_" . strtoupper($v)));
            $field->add(nc_admin_checkbox(constant("NETCAT_MODULE_AUTH_ADMIN_EX_" . strtoupper($v) . "_ENABLED"), $v . '_enabled', $settings['ex_enabled'][$v]));
            if ($v !== 'openid' && $v !== 'oauth') {
                $field->add(
                    nc_admin_input(constant("NETCAT_MODULE_AUTH_APPLICATION_ID_" . strtoupper($v)), $v . '_app_id', $settings['ex_apps'][$v]['app_id'], 0, 'width:30%; margin-bottom: 5px;') .
                    nc_admin_input(constant("NETCAT_MODULE_AUTH_SECRET_KEY_" . strtoupper($v)), $v . '_app_key', $settings['ex_apps'][$v]['app_key'], 0, 'width:30%')
                );
            }

            // группы
            $html = "<div style='margin-bottom: 5px;'>" . NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE . ":</div>";

            if (empty($settings['ex_group'][$v])) {
                $html .= nc_print_status(NETCAT_MODULE_AUTH_GROUPS_WHERE_USER_WILL_BE_EMPTY, 'error', null, 1);
            }
            foreach ($groups as $g_id => $g_name) {
                $html .= "<input id='" . $v . "_group_" . $g_id . "' type='checkbox' name='" . $v . "_group[]' value='" . $g_id . "' " . (@in_array($g_id, $settings['ex_group'][$v]) ? " checked='checked' " : "") . " />
            <label for='" . $v . "_group_" . $g_id . "'>" . $g_name . "</label><br/>";
            }
            $field->add($html);

            // действия перед добавлением
            $field->add(nc_admin_textarea(NETCAT_MODULE_AUTH_ACTION_BEFORE_FIRST_AUTHORIZATION, $v . '_addaction_prep', $settings['ex_addaction_prep'][$v], 1));
            // действие после добавления
            $field->add(nc_admin_textarea(NETCAT_MODULE_AUTH_ACTION_AFTER_FIRST_AUTHORIZATION, $v . '_addaction', $settings['ex_addaction'][$v], 1));

            // соответствие полей
            $html = "<div style='font-weight: bold; margin-top: 10px;'>" . NETCAT_MODULE_AUTH_ACTION_FIELDS_MAPPING . "</div>";
            $html .= "<div id='" . $v . "_mapping'></div>
        <a href='#' onclick='nc_mf_" . $v . ".add(); return false;' class='nc-btn nc--light'>
          <i class='nc-icon nc--file-add'></i> " . NETCAT_MODULE_AUTH_ACTION_FIELDS_MAPPING_ADD . "
        </a>";
            $field->add($html);

            if ($v === 'openid') {
                $html = "<div id='openid_providers'></div>
        <a href='#' onclick='op.add(); return false' class='nc-btn nc--light'>
          <i class='nc-icon nc--file-add'></i> " . NETCAT_MODULE_AUTH_PROVIDER_ADD . "
        </a>";
                $field->add($html);
            }

	        if ($v === 'oauth') {
		        $html = "<div id='oauth_providers'>

		                    <div class='oauth_header'>
						        <div class='img'><img src='" . nc_module_path('auth') . "images/icons/oauth.png'></div>
								<div class='name'>".NETCAT_MODULE_AUTH_PROVIDER."</div>
								<div class='provider'>ID</div>
								<div class='imglink'>".NETCAT_MODULE_AUTH_PROVIDER_ICON."</div>
								<div class='appid'>".NETCAT_MODULE_AUTH_APPLICATION_ID."</div>
								<div class='pubkey'>".NETCAT_MODULE_AUTH_PUBLIC_KEY."</div>
								<div class='seckey'>".NETCAT_MODULE_AUTH_SECRET_KEY."</div>
								<div class='drop'></div>
								<div style='clear:both;'></div>
							</div>

		                </div>
        <a href='#' onclick='oap.add(); return false' class='nc-btn nc--light'>
          <i class='nc-icon nc--file-add'></i> " . NETCAT_MODULE_AUTH_PROVIDER_ADD . "
        </a>";
		        $field->add($html);
	        }

            $result .= $field->result();
            unset($field);
        }

        echo $result;

        // js для полей и openid провайдеров
        echo "<script type='text/javascript'>
                  MODULE_AUTH_OPENID_ICON_PATH = '" . nc_module_path('auth') . "images/icons/openid.png';
                  MODULE_AUTH_OAUTH_ICON_PATH = '" . nc_module_path('auth') . "images/icons/oauth.png';
                  nc_mf_vk = new nc_mapping_fields({ " . implode(',', $js_field_user) . " }, { 'uid' : 'ID', 'first_name': '" . NETCAT_MODULE_AUTH_FIRST_NAME . "', 'last_name': '" . NETCAT_MODULE_AUTH_LAST_NAME . "', 'nickname':'" . NETCAT_MODULE_AUTH_NICKNAME . "', 'photo_big' : '" . NETCAT_MODULE_AUTH_PHOTO . "', 'email':'Email'}, 'vk_mapping', 'nc_mf_vk', '" . NETCAT_MODULE_AUTH_ADMIN_EX_DATA_VK . "' );
                  nc_mf_fb = new nc_mapping_fields({ " . implode(',', $js_field_user) . " }, { 'id' : 'ID', 'name': '" . NETCAT_MODULE_AUTH_FIRST_NAME . "', 'email':'Email', 'picture' : '" . NETCAT_MODULE_AUTH_PHOTO . "'}, 'fb_mapping', 'nc_mf_fb', '" . NETCAT_MODULE_AUTH_ADMIN_EX_DATA_FB . "' );
                  nc_mf_twitter = new nc_mapping_fields({ " . implode(',', $js_field_user) . " }, { 'id' : 'ID', 'name': '" . NETCAT_MODULE_AUTH_FIRST_NAME . "', 'profile_image_url' : '" . NETCAT_MODULE_AUTH_PHOTO . "', 'screen_name':'" . NETCAT_MODULE_AUTH_LOGIN . "', 'email':'Email'}, 'twitter_mapping', 'nc_mf_twitter', '" . NETCAT_MODULE_AUTH_ADMIN_EX_DATA_TWITTER . "' );
                  nc_mf_openid = new nc_mapping_fields({ " . implode(',', $js_field_user) . " }, { 'nickname' : '" . NETCAT_MODULE_AUTH_NICKNAME . "', 'fullname': '" . NETCAT_MODULE_AUTH_FIRST_NAME . "', 'email':'Email'}, 'openid_mapping', 'nc_mf_openid', '" . NETCAT_MODULE_AUTH_ADMIN_EX_DATA_OPENID . "' );
                  nc_mf_oauth = new nc_mapping_fields({ " . implode(',', $js_field_user) . " }, { 'uid' : 'ID', 'name': '" . NETCAT_MODULE_AUTH_FIRST_NAME . "', 'nick': '" . NETCAT_MODULE_AUTH_NICKNAME . "', 'photo' : '" . NETCAT_MODULE_AUTH_PHOTO . "', 'email':'Email'}, 'oauth_mapping', 'nc_mf_oauth', '" . NETCAT_MODULE_AUTH_ADMIN_EX_DATA_OAUTH . "' );
                  op = new nc_openidproviders();
                  oap = new nc_oauthproviders();";

        // поля
        foreach ($types as $v) {
            if (!empty($settings['ex_fields'][$v])) {
                foreach ($settings['ex_fields'][$v] as $f1 => $f2) {
                    echo "nc_mf_" . $v . ".add('" . $f1 . "','" . $f2 . "');";
                }
            }
        }
        // провайдеры
        if (!empty($settings['ex_openid_providers'])) {
            foreach ($settings['ex_openid_providers'] as $provider) {
                echo "op.add('" . $provider['name'] . "','" . $provider['url'] . "', '" . $provider['imglink'] . "');";
            }
        }
	    // провайдеры
	    if (!empty($settings['ex_oauth_providers'])) {
            foreach ($settings['ex_oauth_providers'] as $provider) {
                echo "oap.add('" . $provider['imglink'] . "','" . $provider['name'] . "','" . $provider['provider'] . "', '" . $provider['appid'] . "', '" . $provider['pubkey'] . "', '" . $provider['seckey'] . "');";
            }
        }
        echo "</script>";
        echo nc_admin_js_resize();
        echo "</form>";

        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
        return true;
    }

    /**
     * Сохранение настроек "Авторизация через внешние сервисы" (устаревшие провайдеры)
     */
    public function ex_legacy_save() {
        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $nc_auth = nc_auth::get_object();
        $catalogue_id = $this->core->catalogue->id();

        $input = $this->core->input->fetch_get_post();

        $types = array('vk', 'fb', 'twitter', 'openid', 'oauth');

        // основные параметры
        foreach ($types as $v) {
            // возможность авторизации
            $ex_enabled[$v] = (int)nc_array_value($input,$v . '_enabled');
            // id приложения
            if (isset($input[$v . '_app_id'])) {
                $ex_apps[$v]['app_id'] = $input[$v . '_app_id'];
            }
            // секретный ключ
            if (isset($input[$v . '_app_key'])) {
                $ex_apps[$v]['app_key'] = $input[$v . '_app_key'];
            }
            // действие перед добавлением
            $ex_addaction_prep[$v] = $input[$v . '_addaction_prep'];
            // действие после добавления
            $ex_addaction[$v] = $input[$v . '_addaction'];
            // группы
            $ex_group[$v] = $input[$v . '_group'];
        }
        // соответствие полей и openid-провайдеры
        foreach ($input as $k => $v) {
            if (preg_match('/([a-z]+)_mapping_field1_value_(\d+)/i', $k, $match)) {
                $name = $match[1];
                $id = $match[2];
                $f1 = $input[$name . '_mapping_field1_value_' . $id];
                $f2 = $input[$name . '_mapping_field2_value_' . $id];
                $ex_fields[$name][$f1] = $f2;
            }

            if (preg_match('/openid_providers_name_(\d+)/i', $k, $match)) {
                $id = $match[1];
                if (!$input['openid_providers_name_' . $id]) {
                    continue;
                }
                $ex_openid_providers[$id] = array(
                    'name' => $input['openid_providers_name_' . $id],
                    'url' => $input['openid_providers_url_' . $id],
                    'imglink' => $input['openid_providers_imglink_' . $id]);
            }
	        if (preg_match('/oauth_providers_name_(\d+)/i', $k, $match)) {
		        $id = $match[1];
		        if (!$input['oauth_providers_name_' . $id]) {
                    continue;
                }
		        $ex_oauth_providers[$id] = array(
			        'provider' => $input['oauth_providers_provider_' . $id],
			        'name' => $input['oauth_providers_name_' . $id],
			        'appid' => $input['oauth_providers_appid_' . $id],
			        'pubkey' => $input['oauth_providers_pubkey_' . $id],
			        'seckey' => $input['oauth_providers_seckey_' . $id],
			        'imglink' => $input['oauth_providers_imglink_' . $id]);
	        }
        }

        $params = array(
            'ex_enabled',
            'ex_apps',
            'ex_addaction_prep',
            'ex_addaction',
            'ex_group',
            'ex_fields',
            'ex_openid_providers',
	        'ex_oauth_providers',
        );

        foreach ($params as $param) {
            $this->core->set_settings($param, isset($$param) ? serialize($$param) : null, 'auth', $catalogue_id);
        }

        return true;
    }

    /**
     * Форма общих настроек
     */
    public function general_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();

        // настройки
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());

        echo "<form method='post' action='admin.php' id='adminForm' class='nc-form' style='padding:0; margin:0;'>\n";
        echo $this->catalogue_select_field('general');

        // Способы авторизации на сайте
        $hash_enabled = $settings['authtype_site'] & NC_AUTHTYPE_HASH;
        echo "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_GENERAL_AUTH_SITE . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_LOGIN, 'authtype_site_login', $settings['authtype_site'] & NC_AUTHTYPE_LOGIN);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_HASH, 'authtype_site_hash', $hash_enabled, "onchange='toggleVerifySettings()'");
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_EX, 'authtype_site_ex', $settings['authtype_site'] & NC_AUTHTYPE_EX);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_TOKEN, 'authtype_site_token', $settings['authtype_site'] & NC_AUTHTYPE_TOKEN);
        echo "</fieldset>\n";

        // Настройки кодов верификации (зависят от authtype_site_hash)
        $disabled_attr = $hash_enabled ? '' : ' disabled';
        $disabled_class = $hash_enabled ? '' : ' style="opacity: 0.5;"';
        echo "<fieldset id='verify_settings_fieldset'{$disabled_class}>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_VERIFY_CODES . "\n" .
            "</legend>\n";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='verify_code_ttl' value='" . ((int)nc_array_value($settings, 'verify_code_ttl') ?: 10) . "' size='3'{$disabled_attr}>", NETCAT_MODULE_AUTH_ADMIN_VERIFY_CODE_TTL) . "</div>";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='verify_code_length' value='" . ((int)nc_array_value($settings, 'verify_code_length') ?: 6) . "' size='2'{$disabled_attr}>", NETCAT_MODULE_AUTH_ADMIN_VERIFY_CODE_LENGTH) . "</div>";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='verify_code_resend_interval' value='" . ((int)nc_array_value($settings, 'verify_code_resend_interval') ?: 60) . "' size='3'{$disabled_attr}>", NETCAT_MODULE_AUTH_ADMIN_VERIFY_RESEND_INTERVAL) . "</div>";
        echo "</fieldset>\n";

        echo "<script>
        function toggleVerifySettings() {
            var checkbox = document.getElementById('authtype_site_hash');
            var fieldset = document.getElementById('verify_settings_fieldset');
            if (!checkbox || !fieldset) return;
            var inputs = fieldset.querySelectorAll('input');
            if (checkbox.checked) {
                fieldset.style.opacity = '1';
                inputs.forEach(function(input) { input.disabled = false; });
            } else {
                fieldset.style.opacity = '0.5';
                inputs.forEach(function(input) { input.disabled = true; });
            }
        }
        // Привязка события при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            var checkbox = document.getElementById('authtype_site_hash');
            if (checkbox) {
                checkbox.addEventListener('change', toggleVerifySettings);
            }
        });
        </script>\n";

        // Настройки безопасности
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_SECURITY . "\n" .
            "</legend>\n";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='rate_limit_max_ip' value='" . ((int)nc_array_value($settings, 'rate_limit_max_ip') ?: 10) . "' size='3'>", NETCAT_MODULE_AUTH_ADMIN_RATE_LIMIT_IP) . "</div>";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='rate_limit_max_login' value='" . ((int)nc_array_value($settings, 'rate_limit_max_login') ?: 5) . "' size='3'>", NETCAT_MODULE_AUTH_ADMIN_RATE_LIMIT_LOGIN) . "</div>";
        echo "<div class='nc-field-row'>" . str_replace('%input', "<input type='text' name='rate_limit_period' value='" . ((int)nc_array_value($settings, 'rate_limit_period') ?: 900) . "' size='4'>", NETCAT_MODULE_AUTH_ADMIN_RATE_LIMIT_PERIOD) . "</div>";
        echo "</fieldset>\n";

        // Капча — единая политика по действиям (вход / регистрация / восстановление)
        $captcha_catalogue_id = $this->core->catalogue->id();
        $captcha_module_enabled = nc_module_check_by_keyword('captcha');
        $captcha_policy = class_exists('nc_auth_captcha_policy') ? nc_auth_captcha_policy::get_object() : null;
        echo "<fieldset>\n<legend>" . NETCAT_MODULE_AUTH_ADMIN_CAPTCHA . "</legend>\n";
        if (!$captcha_module_enabled) {
            echo "<div class='nc-field-row' style='opacity:0.7;'>" . NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_MODULE_DISABLED . "</div>";
        }
        // Режим "после N неудач" осмыслен только для входа (защита от перебора);
        // у регистрации/восстановления нет счётчика неудачных попыток — только вкл/выкл.
        $captcha_actions = array(
            'login'    => array('label' => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_LOGIN,    'modes' => array('off', 'always', 'after_n')),
            'register' => array('label' => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_REGISTER, 'modes' => array('off', 'always')),
            'recovery' => array('label' => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_RECOVERY, 'modes' => array('off', 'always')),
        );
        $captcha_mode_labels = array(
            'off'     => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_MODE_OFF,
            'always'  => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_MODE_ALWAYS,
            'after_n' => NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_MODE_AFTER_N,
        );
        $captcha_disabled_attr = $captcha_module_enabled ? '' : ' disabled';
        foreach ($captcha_actions as $captcha_action => $captcha_cfg) {
            $allowed_modes = $captcha_cfg['modes'];
            $cur_mode = $captcha_policy ? $captcha_policy->get_mode($captcha_action, $captcha_catalogue_id) : 'off';
            if (!in_array($cur_mode, $allowed_modes, true)) {
                $cur_mode = $allowed_modes[count($allowed_modes) > 1 ? 1 : 0]; // always для reg/recovery
            }
            $cur_attempts = $captcha_policy ? $captcha_policy->get_threshold($captcha_action, $captcha_catalogue_id) : 3;
            $select = "<select name='captcha_{$captcha_action}_mode' data-captcha-action='{$captcha_action}'{$captcha_disabled_attr}>";
            foreach ($allowed_modes as $mode_val) {
                $select .= "<option value='{$mode_val}'" . ($cur_mode === $mode_val ? ' selected' : '') . ">{$captcha_mode_labels[$mode_val]}</option>";
            }
            $select .= "</select>";
            echo "<div class='nc-field-row'>" . $captcha_cfg['label'] . ": " . $select;
            if (in_array('after_n', $allowed_modes, true)) {
                $attempts_input = "<input type='text' name='captcha_{$captcha_action}_attempts' value='" . (int)$cur_attempts . "' size='3'{$captcha_disabled_attr}>";
                $attempts_style = $cur_mode === 'after_n' ? '' : ' style="display:none;"';
                echo " <span class='nc-captcha-attempts-{$captcha_action}'{$attempts_style}>"
                    . NETCAT_MODULE_AUTH_ADMIN_CAPTCHA_ATTEMPTS . ": " . $attempts_input . "</span>";
            }
            echo "</div>";
        }
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('select[data-captcha-action]').forEach(function(sel) {
                sel.addEventListener('change', function() {
                    var span = document.querySelector('.nc-captcha-attempts-' + sel.getAttribute('data-captcha-action'));
                    if (span) span.style.display = (sel.value === 'after_n') ? '' : 'none';
                });
            });
        });
        </script>\n";
        echo "</fieldset>\n";

        // Способы авторизации в систему администрирования
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_GENERAL_AUTH_ADMIN . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_LOGIN, 'authtype_admin_login', $settings['authtype_admin'] & NC_AUTHTYPE_LOGIN);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_HASH, 'authtype_admin_hash', $settings['authtype_admin'] & NC_AUTHTYPE_HASH);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_METHOD_TOKEN, 'authtype_admin_token', $settings['authtype_admin'] & NC_AUTHTYPE_TOKEN);
        echo "<div style='height: 3px;'>&nbsp;</div>";
        //echo nc_admin_checkbox('Разрешить вход только по https-протоколу', 'admin_https', $settings['admin_https']);
        echo "</fieldset>\n";

        // основные настройки
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_GENERAL_PM . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_PM_ALLOW, 'pm_allow', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_PM_NOTIFY, 'pm_notify', $settings);
        echo "</fieldset>\n";

        // друзья
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_GENERAL_FRIEND_BANNED . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_FRIEND_ALLOW, 'friend_allow', $settings);
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_BANNED_ALLOW, 'banned_allow', $settings);
        echo "</fieldset>\n";

        // личный счет
        echo
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_AUTH_ADMIN_GENERAL_PA . "\n" .
            "</legend>\n";
        echo nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_GENERAL_PA_ALLOW, 'pa_allow', $settings);
        echo nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_GENERAL_PA_CURRENCY, 'pa_currency', $settings['pa_currency'], 4) . '<br/>';
        echo nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_GENERAL_PA_START, 'pa_start', $settings['pa_start'], 4);
        echo "</fieldset>\n";

        echo $this->core->token->get_input() . "\n";
        echo "<input type='hidden' value='general' name='view' />";
        echo "<input type='hidden' value='save' name='act' />";
        echo "</form>\n";


        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
    }

    /**
     * @throws Exception
     */
    public function general_save() {
        $catalogue_id = $this->core->catalogue->id();

        // включение/выключение Личного счета надо обрабатывать отдельно
        $cur_pa_allow = $this->core->get_settings('pa_allow', 'auth');
        if ($cur_pa_allow != $this->core->input->fetch_get_post('pa_allow')) {
            // компонент "Личный счет"
            if (($class_id = $this->core->get_settings('pa_class_id', 'auth'))) {
                $subs = $this->core->db->get_results("SELECT s.`Subdivision_ID` as `id`, s.`Subdivision_Name` as `name` FROM `Subdivision` as `s`, `Sub_Class` AS `sc` WHERE sc.Subdivision_ID = s.Subdivision_ID AND sc.Class_ID = '" . $class_id . "' ", ARRAY_A);
                $subs_id = $this->core->db->get_col(null, 0);
                $subs_name = $this->core->db->get_col(null, 1);
            }

            $field = $this->core->get_settings('pa_field', 'auth');
            $method = $cur_pa_allow ? 'uncheck_field' : 'check_field';

            // включение/выключение поля
            $this->core->$method('User', $field);

            // включение/выключение разделов
            if ($subs_id) {
                $this->core->db->query("UPDATE Subdivision SET Checked = '" . ($cur_pa_allow ? 0 : 1) . "' WHERE Subdivision_ID IN (" . join(',', $subs_id) . ") ");
            }
        }

        // способы авторизации на сайте
        $r = $this->core->input->fetch_get_post('authtype_site_login') ? NC_AUTHTYPE_LOGIN : 0;
        $r += $this->core->input->fetch_get_post('authtype_site_hash') ? NC_AUTHTYPE_HASH : 0;
        $r += $this->core->input->fetch_get_post('authtype_site_ex') ? NC_AUTHTYPE_EX : 0;
        $r += $this->core->input->fetch_get_post('authtype_site_token') ? NC_AUTHTYPE_TOKEN : 0;

        if ($r === 0) {
            throw new Exception(NETCAT_MODULE_AUTH_ADMIN_GENERAL_AUTH_SITE_NO_METHODS_PROVIDED_ERROR);
        }

        $this->core->set_settings('authtype_site', $r, 'auth', $catalogue_id);

        // способы авторизации в админку
        $r = $this->core->input->fetch_get_post('authtype_admin_login') ? NC_AUTHTYPE_LOGIN : 0;
        $r += $this->core->input->fetch_get_post('authtype_admin_hash') ? NC_AUTHTYPE_HASH : 0;
        $r += $this->core->input->fetch_get_post('authtype_admin_token') ? NC_AUTHTYPE_TOKEN : 0;

        if ($r === 0) {
            throw new Exception(NETCAT_MODULE_AUTH_ADMIN_GENERAL_NO_METHODS_PROVIDED_ERROR);
        }

        $this->core->set_settings('authtype_admin', $r, 'auth', $catalogue_id);

        $params = array('pm_allow', 'pm_notify', 'friend_allow', 'banned_allow', 'pa_allow', 'pa_start', 'pa_currency', 'admin_https',
            'verify_code_ttl', 'verify_code_length', 'verify_code_resend_interval',
            'rate_limit_max_ip', 'rate_limit_max_login', 'rate_limit_period');
        // настройки
        foreach ($params as $v) {
            $this->core->set_settings($v, $this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        // Капча — единая политика по действиям (scope system, как читает nc_auth_captcha_policy)
        $captcha_valid_modes = array('off', 'always', 'after_n');
        foreach (array('login', 'register', 'recovery') as $captcha_action) {
            $mode = $this->core->input->fetch_get_post("captcha_{$captcha_action}_mode");
            if (!in_array($mode, $captcha_valid_modes, true)) {
                $mode = 'off';
            }
            $attempts = (int)$this->core->input->fetch_get_post("captcha_{$captcha_action}_attempts");
            if ($attempts < 1) {
                $attempts = 3;
            }
            $this->core->set_settings("captcha_{$captcha_action}_mode", $mode, 'system', $catalogue_id);
            $this->core->set_settings("captcha_{$captcha_action}_attempts", $attempts, 'system', $catalogue_id);
        }

        // Синхронизация легаси-ключей от действия «вход» — для легаси-формы входа
        // и экрана «Безопасность → Капча» (обратная совместимость).
        $login_mode = $this->core->input->fetch_get_post('captcha_login_mode');
        if (!in_array($login_mode, $captcha_valid_modes, true)) {
            $login_mode = 'off';
        }
        $login_attempts = (int)$this->core->input->fetch_get_post('captcha_login_attempts') ?: 3;
        $this->core->set_settings('AuthCaptchaEnabled', $login_mode === 'off' ? 0 : 1, 'system', $catalogue_id);
        $this->core->set_settings('AuthCaptchaAttempts', $login_mode === 'after_n' ? $login_attempts : 0, 'system', $catalogue_id);
    }

    public function mail_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();

        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $nc_core = nc_Core::get_object();
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());
        $catalogue_id = $this->core->catalogue->id();

        // Дефолтные значения для шаблонов писем (если в БД пусто)
        $defaults = array(
            'verify_login_subject' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_SUBJECT,
            'verify_login_body' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_BODY,
            'verify_login_is_html' => 1,
            'verify_registration_subject' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_SUBJECT,
            'verify_registration_body' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_BODY,
            'verify_registration_is_html' => 1,
            'verify_checkout_subject' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_SUBJECT,
            'verify_checkout_body' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_BODY,
            'verify_checkout_is_html' => 1,
            'verify_recovery_subject' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_SUBJECT,
            'verify_recovery_body' => NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_BODY,
            'verify_recovery_is_html' => 1,
        );
        foreach ($defaults as $key => $default_value) {
            if (empty($settings[$key])) {
                $settings[$key] = $default_value;
            }
        }
        ?>
        <script type="text/javascript">
            // Дефолтные значения для классических шаблонов
            var mailDefaults = {
                body: {
                    mail_confirm: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_CONFIRM_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    mail_confirm_after: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_CONFIRM_AFTER_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    mail_recovery: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_PASSWORDRECOVERY_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    mail_notify_admin: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_ADMIN_NOTIFY_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    verify_login: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    verify_registration: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    verify_checkout: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_BODY, JSON_UNESCAPED_UNICODE); ?>,
                    verify_recovery: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_BODY, JSON_UNESCAPED_UNICODE); ?>
                },
                subject: {
                    mail_confirm: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_CONFIRM_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    mail_confirm_after: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_CONFIRM_AFTER_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    mail_recovery: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_PASSWORDRECOVERY_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    mail_notify_admin: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_MAIL_ADMIN_NOTIFY_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    verify_login: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_LOGIN_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    verify_registration: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_REGISTRATION_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    verify_checkout: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_CHECKOUT_SUBJECT, JSON_UNESCAPED_UNICODE); ?>,
                    verify_recovery: <?php echo json_encode(NETCAT_MODULE_AUTH_ADMIN_DEF_VERIFY_RECOVERY_SUBJECT, JSON_UNESCAPED_UNICODE); ?>
                }
            };

            // Для обратной совместимости
            var body = mailDefaults.body;
            var subject = mailDefaults.subject;

            function recovery(name, confirmed) {
                if (!confirmed) {
                    if (confirm(ncLang.WarnAuthMail)) {
                        recovery(name, 1);
                    }
                    return false;
                }

                jQuery("#" + name + "_subject").val(mailDefaults.subject[name] || '');
                var bodyTextArea = jQuery("#" + name + "_body");
                bodyTextArea.val(mailDefaults.body[name] || '');
                if (typeof bodyTextArea.codemirror === 'function') {
                    bodyTextArea.codemirror('setValue');
                }
                // Для новых шаблонов всегда HTML
                if (name.indexOf('verify_') === 0) {
                    jQuery("#" + name + "_is_html").prop('checked', true);
                }
            }

        </script>
        <style>
            .nc-mail-section { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; }
            .nc-mail-section-header { background: #f5f5f5; padding: 12px 15px; display: flex; align-items: center; }
            .nc-mail-section-icon { margin-right: 10px; font-size: 12px; color: #666; }
            .nc-mail-section-title { font-weight: bold; font-size: 14px; }
            .nc-mail-section-hint { color: #888; font-size: 12px; margin-left: 10px; }
            .nc-mail-section-content { padding: 15px; }
            .nc-mail-section-content fieldset { margin-bottom: 15px; }
            .nc-mail-vars-hint { background: #e8f4fd; padding: 8px 12px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; color: #1a5a96; }
        </style>
        <?php
        echo "<form action='admin.php' method='post' enctype='multipart/form-data'>";
        echo $this->catalogue_select_field('mail');

        // =====================================================================
        // СЕКЦИЯ: Авторизация по коду/ссылке
        // =====================================================================
        echo "<div class='nc-mail-section'>
            <div class='nc-mail-section-header'>
                <span class='nc-mail-section-icon'>▼</span>
                <span class='nc-mail-section-title'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SECTION_CODE . "</span>
                <span class='nc-mail-section-hint'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SECTION_CODE_HINT . "</span>
            </div>
            <div class='nc-mail-section-content'>
                <div class='nc-mail-vars-hint'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_VERIFY_VARS_HINT . "</div>";

        // Вход по коду
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_VERIFY_LOGIN . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"verify_login\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='verify_login_subject' name='verify_login_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'verify_login_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'verify_login_body', nc_array_value($settings, 'verify_login_body'), 1, 0, 'height:15em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'verify_login_is_html', $settings) . "
            </fieldset>";

        // Подтверждение регистрации (по коду)
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_VERIFY_REGISTRATION . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"verify_registration\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='verify_registration_subject' name='verify_registration_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'verify_registration_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'verify_registration_body', nc_array_value($settings, 'verify_registration_body'), 1, 0, 'height:15em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'verify_registration_is_html', $settings) . "
            </fieldset>";

        // Подтверждение email (checkout)
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_VERIFY_CHECKOUT . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"verify_checkout\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='verify_checkout_subject' name='verify_checkout_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'verify_checkout_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'verify_checkout_body', nc_array_value($settings, 'verify_checkout_body'), 1, 0, 'height:15em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'verify_checkout_is_html', $settings) . "
            </fieldset>";

        // Сброс пароля (по коду)
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_VERIFY_RECOVERY . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"verify_recovery\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='verify_recovery_subject' name='verify_recovery_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'verify_recovery_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'verify_recovery_body', nc_array_value($settings, 'verify_recovery_body'), 1, 0, 'height:15em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'verify_recovery_is_html', $settings) . "
            </fieldset>";

        echo "</div></div>"; // end code section

        // =====================================================================
        // СЕКЦИЯ: Классическая авторизация (по паролю)
        // =====================================================================
        echo "<div class='nc-mail-section'>
            <div class='nc-mail-section-header'>
                <span class='nc-mail-section-icon'>▼</span>
                <span class='nc-mail-section-title'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SECTION_CLASSIC . "</span>
                <span class='nc-mail-section-hint'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SECTION_CLASSIC_HINT . "</span>
            </div>
            <div class='nc-mail-section-content'>";

        // Подтверждение регистрации (классическое)
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_REG_CONFIRM . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"mail_confirm\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='mail_confirm_subject' name='mail_confirm_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'mail_confirm_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'mail_confirm_body', $settings, 1, 0, 'height:10em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'mail_confirm_is_html', $settings);
        echo nc_mail_attachment_form('auth_confirm_' . $catalogue_id);
        echo "</fieldset>";

        // После подтверждения регистрации
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_REG_CONFIRM_AFTER . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"mail_confirm_after\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='mail_confirm_after_subject' name='mail_confirm_after_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'mail_confirm_after_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'mail_confirm_after_body', $settings, 1, 0, 'height:10em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'mail_confirm_after_is_html', $settings);
        echo nc_mail_attachment_form('auth_confirm_after_' . $catalogue_id);
        echo "</fieldset>";

        // Восстановление пароля (классическое)
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_RECOVERY . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"mail_recovery\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='mail_recovery_subject' name='mail_recovery_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'mail_recovery_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'mail_recovery_body', $settings, 1, 0, 'height:10em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'mail_recovery_is_html', $settings);
        echo nc_mail_attachment_form('auth_recovery_' . $catalogue_id);
        echo "</fieldset>";

        echo "</div></div>"; // end classic section

        // =====================================================================
        // СЕКЦИЯ: Служебные
        // =====================================================================
        echo "<div class='nc-mail-section'>
            <div class='nc-mail-section-header'>
                <span class='nc-mail-section-icon'>▼</span>
                <span class='nc-mail-section-title'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SECTION_SERVICE . "</span>
            </div>
            <div class='nc-mail-section-content'>";

        // Уведомление администратора
        echo "<fieldset>
                <legend>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_NOTIFY . "</legend>
                <div style='float:left'>" . NETCAT_MODULE_AUTH_ADMIN_MAIL_SUBJECT . ":</div>
                <div style='float:right'><a href='#' onclick='recovery(\"mail_notify_admin\", 0); return false;'>" . NETCAT_MODULE_AUTH_RESTORE_DEF . "</a></div><br clear='all'>
                <input id='mail_notify_admin_subject' name='mail_notify_admin_subject' type='text' style='width:100%; margin-top:5px;' value='" . htmlspecialchars(nc_array_value($settings,'mail_notify_admin_subject'), ENT_QUOTES) . "'>
                " . nc_admin_textarea(NETCAT_MODULE_AUTH_ADMIN_MAIL_BODY, 'mail_notify_admin_body', $settings, 1, 0, 'height:10em;') . "
                " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_MAIL_HTML, 'mail_notify_admin_is_html', $settings);
        echo nc_mail_attachment_form('auth_notify_' . $catalogue_id);
        echo "</fieldset>";

        echo "</div></div>"; // end service section

        echo $this->core->token->get_input() . "
            <input type='hidden' name='view' value='mail' />
            <input type='hidden' name='act' value='save' />";
        echo "</form>";
        echo nc_admin_js_resize();

        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
    }

    public function mail_save() {
        /** @var Permission $perm */
        global $perm;
        $perm->ExitIfNotSupervisor();

        $catalogue_id = $this->core->catalogue->id();

        $params = array(
            // Классические шаблоны
            'mail_confirm_subject',
            'mail_confirm_body',
            'mail_confirm_is_html',
            'mail_confirm_after_subject',
            'mail_confirm_after_body',
            'mail_confirm_after_is_html',
            'mail_recovery_subject',
            'mail_recovery_body',
            'mail_recovery_is_html',
            'mail_notify_admin_subject',
            'mail_notify_admin_body',
            'mail_notify_admin_is_html',
            // Новые шаблоны (авторизация по коду/ссылке)
            'verify_login_subject',
            'verify_login_body',
            'verify_login_is_html',
            'verify_registration_subject',
            'verify_registration_body',
            'verify_registration_is_html',
            'verify_checkout_subject',
            'verify_checkout_body',
            'verify_checkout_is_html',
            'verify_recovery_subject',
            'verify_recovery_body',
            'verify_recovery_is_html',
        );

        foreach ($params as $v) {
            $this->core->set_settings($v, $this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        nc_mail_attachment_form_save('auth_confirm_' . $catalogue_id);
        nc_mail_attachment_form_save('auth_confirm_after_' . $catalogue_id);
        nc_mail_attachment_form_save('auth_recovery_' . $catalogue_id);
        nc_mail_attachment_form_save('auth_notify_' . $catalogue_id);
    }

    public function system_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $settings = $this->core->get_settings('', 'auth', false, $this->core->catalogue->id());
        echo "
            <style>
              select {width: 98%; margin-left: 10px; }
              .nc_t {width:70%}
              .nc_t .f {width:30%}
              .nc_t .l {width:70%}
            </style>";

        echo "<form action='admin.php' method='post'>";
        echo $this->catalogue_select_field('system');
        echo "<fieldset><legend>" . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_COMPONENTS_SUBS . "</legend>
            <table class='nc_t'>
            <tr>" . nc_admin_select_component('<td class="f">' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_COMPONENT_FRIENDS . '</td><td class="l">', 'friend_class_id', $settings) . "</td></tr>
            <tr>" . nc_admin_select_component('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_COMPONENT_PM . '</td><td>', 'pm_class_id', $settings) . "</td></tr>
            <tr>" . nc_admin_select_component('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_COMPONENT_PA . '</td><td>', 'pa_class_id', $settings) . "</td></tr>
            <tr>" . nc_admin_select_field('User', '<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_FIELD_PA . '</td><td>', 'pa_field', $settings) . "</td></tr>
            <tr>" . nc_admin_select_subdivision('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_SUB_MATERIALS . '</td><td>', 'materials_sub_id', $settings) . "</td></tr>
            <tr>" . nc_admin_input_in_text('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_SUB_MODIFY . '</td><td style="padding-left: 10px;">%input</td>', 'modify_sub', $settings, 0, 'width: 100%;') . "</tr>
            <tr>" . nc_admin_input_in_text('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_CC_USER_LIST . '</td><td style="padding-left: 10px;">%input</td>', 'user_list_cc', $settings, 0, 'width: 100%;') . "</tr>
            </table>
            </fieldset>

            <fieldset><legend>" . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_PSEUDO . "</legend>
            " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_PSEUDO_ALLOW, 'pseudo_enabled', $settings) . "
            " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_PSEUDO_CHECK_IP, 'pseudo_check_ip', $settings) . "
            <table>
            <tr>" . nc_admin_select_usergroup('<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_PSEUDO_GROUP . '</td><td>', 'pseudo_group', $settings['pseudo_group']) . "</td></tr>
            <tr>" . nc_admin_select_field('User', '<td>' . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_PSEUDO_FIELD . '</td><td>', 'pseudo_field', $settings) . "</td></tr>
            </table>
            </fieldset>

            <fieldset><legend>" . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_HASH . "</legend>
            " . nc_admin_checkbox(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_HASH_DELETE, 'hash_delete', $settings) . "
            " . nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_HASH_EXPIRE, 'hash_expire', $settings, 4) . "<br/>
            " . nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_HASH_DISABLED_SUBS, 'hash_disabled_subs', $settings, 12) . "
            </fieldset>

            <fieldset><legend>" . NETCAT_MODULE_AUTH_ADMIN_SYSTEM_OTHER . "</legend>
            " . nc_admin_checkbox(NETCAT_MODULE_AUTH_FORM_DISABLED, 'user_login_form_disable', $settings) . "
            " . nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_OTHER_ONLINE, 'online_timeleft', $settings, 4) . "<br/>
            " . nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_SYSTEM_OTHER_IP, 'ip_check_level', $settings, 4) . "<br/>
            " . nc_admin_input_in_text(NETCAT_MODULE_AUTH_ADMIN_NOTIFICATION_EMAIL, 'admin_notification_email', $settings, 15) . "
            </fieldset>

            " . $this->core->token->get_input() . "
            <input type='hidden' name='view' value='system' />
            <input type='hidden' name='act' value='save' />
            </form>";

        $UI_CONFIG->actionButtons[] =
            array("id" => "submit",
                "caption" => NETCAT_MODERATION_BUTTON_CHANGE,
                "action" => "mainView.submitIframeForm('adminForm')");
    }

    public function system_save() {
        $catalogue_id = $this->core->catalogue->id();

        $params = array(
            'friend_class_id',
            'pm_class_id',
            'pa_class_id',
            'pa_field',
            'materials_sub_id',
            'modify_sub',
            'user_list_cc',
            'pseudo_enabled',
            'pseudo_check_ip',
            'pseudo_group',
            'pseudo_field',
            'hash_delete',
            'hash_expire',
            'hash_disabled_subs',
            'online_timeleft',
            'ip_check_level',
            'user_login_form_disable',
            'admin_notification_email'
        );

        foreach ($params as $v) {
            $this->core->set_settings($v, $this->core->input->fetch_get_post($v), 'auth', $catalogue_id);
        }

        $materials_sub_id = $this->core->input->fetch_get_post('materials_sub_id');
        $sub_folder_length = strlen($this->core->SUB_FOLDER);
        $url = substr(nc_folder_path($materials_sub_id), $sub_folder_length);

        $this->core->set_settings('materials_url', $url, 'auth', $catalogue_id);
    }

    /**
     * Настройки верификации - перенаправление на общие настройки
     * @deprecated Настройки перенесены в general_show
     */
    public function verify_show() {
        // Настройки верификации перенесены в раздел "Общие"
        header('Location: admin.php?view=general');
        exit;
    }

    /**
     * Сохранение настроек верификации
     * @deprecated Настройки перенесены в general_save
     */
    public function verify_save() {
        // Настройки верификации перенесены в раздел "Общие"
        $this->general_save();
    }

    //FIXME: После ввода глобального переключателя сайтов метод и его вызовы (выше) можно удалять
    /**
     * Поле выбора редактируемого сайта
     *
     * @param string $view текущее представление настроек
     *
     * @return string
     */
    protected function catalogue_select_field($view) {
        static $options;

        if (is_null($options)) {
            $options = array('' => CONTROL_USER_SELECTSITEALL);
            $catalogues = $this->core->catalogue->get_all();

            foreach ($catalogues as $id => $row) {
                $options[$id] = $id . '. ' . $row['Catalogue_Name'];
            }
        }

        $url = "admin.php?view={$view}&current_catalogue_id=";
        $field = $this->core->ui->html->select('current_catalogue_id', $options, $this->core->catalogue->id())
            ->attr('onchange', 'window.location.href="' . $url . '"+this.value');

        return "<div>{$field}</div>";
    }
}
