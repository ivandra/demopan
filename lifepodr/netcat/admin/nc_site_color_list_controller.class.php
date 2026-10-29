<?php

/**
 * Контроллер для AJAX-операций с палитрой сайта
 */
class nc_site_color_list_controller extends nc_ui_controller {

    /**
     * Отображение диалога редактирования палитры
     */
    protected function action_show_dialog() {
        $nc_core = nc_core::get_object();

        $site_id = (int)$nc_core->input->fetch_get_post('site_id');

        if ($site_id <= 0) {
            return $this->make_error_message('Invalid site id');
        }

        // Проверка прав доступа
        global $perm;
        if (!$perm->isCatalogueAdmin($site_id)) {
            return $this->make_error_message('Insufficient permissions');
        }

        $palette = nc_site_color_list::get($site_id);

        return $this->view('site/color_list_dialog', array(
            'site_id' => $site_id,
            'palette' => $palette
        ));
    }

    /**
     * Добавление цвета в палитру сайта
     *
     * Параметры POST:
     *   - site_id: ID сайта
     *   - color: HEX-цвет для добавления
     */
    protected function action_add_color() {
        $nc_core = nc_core::get_object();

        $site_id = (int)$nc_core->input->fetch_get_post('site_id');
        $color = $nc_core->input->fetch_get_post('color');

        // Валидация параметров
        if ($site_id <= 0) {
            $this->send_error('Invalid site_id');
        }

        if (empty($color)) {
            $this->send_error('Color is required');
        }

        // Проверка прав доступа
        global $perm;
        if (!$perm->isSupervisor() && !$perm->isCatalogueAdmin($site_id)) {
            $this->send_error('Insufficient permissions');
        }

        // Добавляем цвет в палитру
        $key = nc_site_color_list::add_color($site_id, $color);

        if ($key === false) {
            $this->send_error('Failed to add color to palette');
        }

        // Возвращаем успешный результат
        $result = array(
            'result' => 'ok',
            'key' => $key,
            'color' => nc_site_color_list::normalize_color($color),
            'palette' => nc_site_color_list::get($site_id)
        );

        die(nc_array_json($result));
    }

    /**
     * Сохранение палитры сайта
     *
     * Параметры POST:
     *   - site_id: ID сайта
     *   - palette: JSON-строка с палитрой {key => color}
     *   - deleted_colors: JSON-строка с удалёнными цветами [{key, hex}, ...]
     */
    protected function action_save() {
        $nc_core = nc_core::get_object();

        $site_id = (int)$nc_core->input->fetch_get_post('site_id');
        $palette_json = $nc_core->input->fetch_post('palette');
        $deleted_colors_json = $nc_core->input->fetch_post('deleted_colors');

        if ($site_id <= 0) {
            $this->send_error('Invalid site id');
        }

        // Проверка прав доступа
        global $perm;
        if (!$perm->isSupervisor() && !$perm->isCatalogueAdmin($site_id)) {
            $this->send_error('Insufficient permissions');
        }

        $palette = json_decode($palette_json, true);
        if (!is_array($palette)) {
            $this->send_error('Invalid palette format');
        }

        // Обработка удалённых цветов
        $deleted_colors = json_decode($deleted_colors_json, true);
        if (!empty($deleted_colors) && is_array($deleted_colors)) {
            // Заменяем ссылки на удалённые цвета на HEX-значения
            require_once $nc_core->ADMIN_FOLDER . 'catalogue/function.inc.php';
            nc_site_color_list::replace_deleted_colors($site_id, $deleted_colors);
        }

        $success = nc_site_color_list::save($site_id, $palette);

        if (!$success) {
            $this->send_error('Failed to save palette');
        }

        $result = array(
            'result' => 'ok',
            'palette' => nc_site_color_list::get($site_id)
        );

        die(nc_array_json($result));
    }

    /**
     * Создание сообщения об ошибке
     */
    protected function make_error_message($message) {
        return $this->view('error_message', ['message' => $message]);
    }

    /**
     * Отправка JSON-ошибки
     */
    protected function send_error($message) {
        echo nc_array_json(['result' => 'error', 'message' => $message]);
        exit;
    }
}
