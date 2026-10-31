<?php

// Регистрация автозагрузки классов модуля
$nc_core->register_class_autoload_path('nc_external_scripts_', __DIR__ . '/classes');

// Инициализация обработчиков буфера (после загрузки всех модулей)
function nc_external_scripts_init() {
    $nc_core = nc_core::get_object();

    // Не инициализировать в админке
    if ($nc_core->admin_mode) {
        return;
    }

    // Не инициализировать при AJAX-запросах
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return;
    }

    // Экстренное отключение через GET-параметр (только для админов)
    if (isset($_GET['disable_external_scripts']) && $_GET['disable_external_scripts'] == '1') {
        global $perm;
        if ($perm instanceof Permission && $perm->isSupervisor()) {
            return;
        }
    }

    // Получаем текущий сайт
    $site_id = 0;
    try {
        $site_id = $nc_core->catalogue->get_current('Catalogue_ID');
    } catch (Exception $e) {
        // Сайт не определён
    }

    if (!$site_id) {
        return;
    }

    // Создаём процессор и регистрируем обработчик буфера
    $processor = new nc_external_scripts_processor($site_id);

    $nc_core->add_output_processor(function($buffer) use ($processor) {
        return $processor->process($buffer);
    });
}

nc_external_scripts_init();
