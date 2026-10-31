<?php

require_once __DIR__ . "/nc_requests.class.php";
$nc_core = nc_Core::get_object();

$nc_core->register_class_autoload_path('nc_requests_', __DIR__ . "/classes");

$nc_core->event->add_listener(
    nc_Event::AFTER_SUBDIVISION_DELETED,
    array('nc_requests_form_settings_subdivision', 'delete_subdivision_settings')
);

$nc_core->event->add_listener(
    nc_Event::AFTER_INFOBLOCK_DELETED,
    array('nc_requests_form_settings_infoblock', 'delete_infoblock_settings')
);

// Журнал согласий 152-ФЗ: регистрируем отмеченные галочки согласия заявки в действии
// после добавления — по тому же принципу, что заказ (netshop) и регистрация (auth/ЛК).
// Модуль сам знает свои consent-поля (AgreementRules / AgreementRules2) и текст, без
// эвристик generic-слушателя. Только публичный сабмит, не админка.
$nc_core->event->add_listener(
    nc_Event::AFTER_OBJECT_CREATED,
    function ($catalogue, $sub, $cc, $class_id, $msg_id) {
        $nc_core = nc_Core::get_object();
        if ($nc_core->inside_admin || $nc_core->admin_mode || !class_exists('nc_consent_log')) {
            return;
        }
        $requests = nc_requests::get_instance($catalogue);
        try {
            if ((int)$class_id !== (int)$requests->get_request_component_id()) {
                return; // не компонент заявок
            }
        } catch (Exception $e) {
            return; // компонент заявок не установлен
        }
        $requests->log_form_consent($msg_id, $cc);
    }
);