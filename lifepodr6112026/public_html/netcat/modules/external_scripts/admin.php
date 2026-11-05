<?php

/**
 * Административный интерфейс модуля внешних скриптов
 */

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)) . (strstr(__FILE__, "/") ? "/" : "\\");
require_once $NETCAT_FOLDER . 'vars.inc.php';
require_once $ADMIN_FOLDER . 'function.inc.php';
require_once $ADMIN_FOLDER . 'modules/ui.php';
require_once $nc_core->SYSTEM_FOLDER . '/admin/ui/components/nc_ui_controller.class.php';
require_once nc_module_folder('external_scripts') . 'ui_config.php';

// Названия типов для отображения
$type_names = [
    'yandex_metrika' => NETCAT_MODULE_EXTSCRIPTS_TYPE_YANDEX_METRIKA,
    'google_tag_manager' => NETCAT_MODULE_EXTSCRIPTS_TYPE_GTM,
    'google_analytics' => NETCAT_MODULE_EXTSCRIPTS_TYPE_GA4,
    'jivo' => NETCAT_MODULE_EXTSCRIPTS_TYPE_JIVO,
    'carrot_quest' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CARROT,
    'vk_pixel' => NETCAT_MODULE_EXTSCRIPTS_TYPE_VK_PIXEL,
    'calltouch' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CALLTOUCH,
    'custom' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CUSTOM,
];

// Получаем параметры
$input = nc_core::get_object()->input;
$phase = $input->fetch_get_post('phase') ?: 'index';
$snippet_id = (int)$input->fetch_get_post('snippet_id');
$type_id = (int)$input->fetch_get_post('type_id');
$provider_id = (int)$input->fetch_get_post('provider_id');

// Определяем site_id через стандартный механизм
$cookie_name = 'nc_admin_site_id';
$cookie_id = $nc_core->input->fetch_cookie($cookie_name);
$new_id = $nc_core->input->fetch_post_get('site_id');

if (strlen($new_id)) {
    $site_id = (int)$new_id;
    $nc_core->cookie->set($cookie_name, $site_id, 0);
} elseif (strlen($cookie_id)) {
    $site_id = (int)$cookie_id;
} else {
    $site_id = (int)$nc_core->catalogue->get_current('Catalogue_ID');
}

// Проверка существования сайта
if ($site_id) {
    try {
        $nc_core->catalogue->get_by_id($site_id);
    } catch (Exception $e) {
        $site_id = 0;
    }
}

// Если сайт не определён, берём первый доступный
if (!$site_id) {
    $all_sites = $nc_core->catalogue->get_all();
    if ($all_sites) {
        $site_id = (int)key($all_sites);
    }
}

// Проверка прав
$perm->ExitIfNotAccess([NC_PERM_MODULE, 'external_scripts'], 0, 0, 0, 1);

// Получаем название типа для заголовка
$type_display_name = '';
if ($type_id) {
    $type_info = nc_external_scripts_snippet::get_type_info($type_id);
    if ($type_info) {
        $type_display_name = $type_names[$type_info['SnippetType_Name']] ?? $type_info['SnippetType_Name'];
    }
} elseif ($snippet_id) {
    // Получаем тип из существующего сниппета
    $snippet_row = nc_db()->get_row(
        "SELECT st.SnippetType_Name FROM `ExternalScripts_Snippet` es
         JOIN `ExternalScripts_Type` st ON es.SnippetType_ID = st.SnippetType_ID
         WHERE es.Snippet_ID = " . (int)$snippet_id,
        ARRAY_A
    );
    if ($snippet_row) {
        $type_display_name = $type_names[$snippet_row['SnippetType_Name']] ?? $snippet_row['SnippetType_Name'];
    }
}

// UI Config
$UI_CONFIG = new ui_config_module_external_scripts($phase, $site_id, $snippet_id, $type_id, $type_display_name, $provider_id);

// Breadcrumb
$Delimeter = ' &gt; ';
$Title1 = NETCAT_MODULE_EXTSCRIPTS;
$Title2 = "<a href='{$ADMIN_PATH}modules/'>" . NETCAT_MODULES . "</a>";
$Title3 = "<a href='" . nc_module_path('external_scripts') . "admin.php'>{$Title1}</a>";

$site_name = $nc_core->catalogue->get_by_id($site_id, 'Catalogue_Name');
$Title4 = $Title2 . $Delimeter . $Title3 . ($site_id ? $Delimeter . $site_name : '');

// AJAX-обработка предпросмотра кода (до BeginHtml)
$action = $input->fetch_get_post('action');
if ($action === 'preview') {
    header('Content-Type: application/json; charset=utf-8');

    $preview_type_id = (int)$input->fetch_post('type_id');
    $preview_settings = $input->fetch_post('settings') ?: [];

    if (!$preview_type_id) {
        echo json_encode(['success' => false, 'error' => 'Type not specified']);
        exit;
    }

    $type_info = nc_external_scripts_snippet::get_type_info($preview_type_id);
    if (!$type_info || !class_exists($type_info['SnippetType_Class'])) {
        echo json_encode(['success' => false, 'error' => 'Unknown type']);
        exit;
    }

    $class_name = $type_info['SnippetType_Class'];
    $snippet = new $class_name();
    $snippet->set_settings($preview_settings);

    // Собираем код для всех позиций
    $positions = [
        'head_start' => NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_START,
        'head_end' => NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_END,
        'body_start' => NETCAT_MODULE_EXTSCRIPTS_POS_BODY_START,
        'body_end' => NETCAT_MODULE_EXTSCRIPTS_POS_BODY_END,
    ];

    $html = '';
    foreach ($positions as $pos => $label) {
        $method = 'get_code_' . $pos;
        if (method_exists($snippet, $method)) {
            $code = $snippet->$method();
            if (trim($code)) {
                $html .= '<div style="margin-bottom: 15px;">';
                $html .= '<strong>' . $label . ':</strong><br>';
                $html .= '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">';
                $html .= htmlspecialchars($code);
                $html .= '</pre></div>';
            }
        }
    }

    if (!$html) {
        $html = '<p style="color: #666;">' . NETCAT_MODULE_EXTSCRIPTS_ERROR_CUSTOM_EMPTY . '</p>';
    }

    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

BeginHtml($Title1, $Title4, "http://{$DOC_DOMAIN}/settings/modules/external_scripts/");

// Обработка действий
$message = '';
$message_type = '';

if ($action && $site_id) {
    // Проверка токена для изменяющих действий
    if (in_array($action, ['save', 'delete', 'reorder'])) {
        if (!$nc_core->token->verify()) {
            nc_print_status(NETCAT_TOKEN_INVALID, 'error');
            EndHtml();
            exit;
        }
    }

    switch ($action) {
        case 'save':
            // Приоритет: для существующего скрипта сохраняем текущий, для нового - max + 1
            if ($snippet_id) {
                $existing = nc_external_scripts_snippet::get_by_id($snippet_id);
                $new_priority = $existing ? $existing->get_priority() : 1;
            } else {
                // Для нового - находим максимальный приоритет и добавляем 1
                $max_priority = (int)nc_db()->get_var(
                    "SELECT MAX(Priority) FROM `ExternalScripts_Snippet` WHERE `Catalogue_ID` = " . (int)$site_id
                );
                $new_priority = $max_priority + 1;
            }

            $data = [
                'id' => $snippet_id,
                'site_id' => $site_id,
                'type_id' => $type_id ?: (int)$input->fetch_post('type_id'),
                'name' => $input->fetch_post('name'),
                'priority' => $new_priority,
                'enabled' => (int)$input->fetch_post('enabled'),
                'settings' => $input->fetch_post('settings') ?: [],
                'conditions' => $input->fetch_post('conditions') ?: [],
            ];

            nc_external_scripts_snippet::save($data);
            $message = NETCAT_MODULE_EXTSCRIPTS_MSG_SAVED;
            $message_type = 'ok';
            $phase = 'index';
            break;

        case 'delete':
            if ($snippet_id) {
                nc_external_scripts_snippet::delete($snippet_id);
                $message = NETCAT_MODULE_EXTSCRIPTS_MSG_DELETED;
                $message_type = 'ok';
            }
            $phase = 'index';
            break;

        case 'toggle':
            // nc_ui_controls::toggle_button() sends 'enable' parameter
            $enabled = (int)$input->fetch_get_post('enable');
            nc_external_scripts_snippet::toggle($snippet_id, (bool)$enabled);
            $message = $enabled ? NETCAT_MODULE_EXTSCRIPTS_MSG_ENABLED : NETCAT_MODULE_EXTSCRIPTS_MSG_DISABLED;
            $message_type = 'ok';
            $phase = 'index';
            break;

        case 'reorder':
            $priorities = $input->fetch_post('priority');
            if (is_array($priorities)) {
                nc_external_scripts_snippet::update_priorities($priorities);
                $message = NETCAT_MODULE_EXTSCRIPTS_MSG_ORDER_SAVED;
                $message_type = 'ok';
            }
            $phase = 'index';
            break;
    }
}

// Реестр провайдеров (карты/видео) — общий для всех сайтов, не привязан
// к $site_id, поэтому обрабатывается отдельно от блока выше.
if ($action && in_array($action, ['provider_save', 'provider_delete', 'provider_toggle'], true)) {
    if (in_array($action, ['provider_save', 'provider_delete'], true) && !$nc_core->token->verify()) {
        nc_print_status(NETCAT_TOKEN_INVALID, 'error');
        EndHtml();
        exit;
    }

    switch ($action) {
        case 'provider_save':
            $pattern = trim((string)$input->fetch_post('pattern'));
            $is_regex = (int)$input->fetch_post('is_regex');

            // Самостоятельная regex-строка должна компилироваться — иначе
            // providers() в nc_consent тихо пропустит её при каждом запросе.
            if ($is_regex && @preg_match('#' . $pattern . '#i', '') === false) {
                $message = NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ERROR_REGEX;
                $message_type = 'error';
                $phase = $provider_id ? 'provider_edit' : 'provider_add';
                break;
            }

            nc_external_scripts_provider::save([
                'id' => $provider_id,
                'label' => $input->fetch_post('label'),
                'pattern' => $pattern,
                'is_regex' => $is_regex,
                'category' => $input->fetch_post('category'),
                'priority' => (int)$input->fetch_post('priority'),
                'enabled' => (int)$input->fetch_post('enabled'),
            ]);
            $message = NETCAT_MODULE_EXTSCRIPTS_MSG_SAVED;
            $message_type = 'ok';
            $phase = 'providers';
            break;

        case 'provider_delete':
            if ($provider_id) {
                nc_external_scripts_provider::delete($provider_id);
                $message = NETCAT_MODULE_EXTSCRIPTS_MSG_DELETED;
                $message_type = 'ok';
            }
            $phase = 'providers';
            break;

        case 'provider_toggle':
            $enabled = (int)$input->fetch_get_post('enable');
            nc_external_scripts_provider::toggle($provider_id, (bool)$enabled);
            $message = $enabled ? NETCAT_MODULE_EXTSCRIPTS_MSG_ENABLED : NETCAT_MODULE_EXTSCRIPTS_MSG_DISABLED;
            $message_type = 'ok';
            $phase = 'providers';
            break;
    }
}

// Вывод сообщения
if ($message) {
    nc_print_status($message, $message_type);
}

// Отображение страницы в зависимости от фазы
switch ($phase) {
    case 'index':
        include nc_module_folder('external_scripts') . 'admin/views/index.view.php';
        break;

    case 'add':
        if (!$site_id) {
            header('Location: admin.php');
            exit;
        }
        include nc_module_folder('external_scripts') . 'admin/views/edit.view.php';
        break;

    case 'edit':
        if (!$site_id || !$snippet_id) {
            header('Location: admin.php');
            exit;
        }
        include nc_module_folder('external_scripts') . 'admin/views/edit.view.php';
        break;

    case 'providers':
        include nc_module_folder('external_scripts') . 'admin/views/providers.view.php';
        break;

    case 'provider_add':
    case 'provider_edit':
        include nc_module_folder('external_scripts') . 'admin/views/provider_edit.view.php';
        break;
}

EndHtml();
