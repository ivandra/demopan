<?php

class nc_content_changer_subdivision extends nc_content_changer {


    private static $file_fields = array("ncIcon", "ncImage", "ncSMO_Image");


    /**
     * @inheritDoc
     */
    protected function validate() {
        if ($this->instruction->get_action() == "delete") {
            return true;
        }

        $dataset = $this->instruction->get_dataset();

        return !empty($dataset);
    }

    /**
     * @return bool
     */
    protected function do_create() {
        if (!$this->resolve_dependencies()) {
            return false;
        }


        $dataset = $this->instruction->get_dataset();
        $dataset = nc_is_nested_array($dataset) ? $dataset : array($dataset);
        $catalogue = nc_core::get_object()->catalogue;

        foreach ($dataset as $data) {
            $data["Catalogue_ID"] = nc_array_value(
                $data,
                "Catalogue_ID",
                $catalogue->get_current("Catalogue_ID")
            );

            $data["Parent_Sub_ID"] = $this->retrieve_parent_subdivision_id($data);

            try {
                $subdivision_id = nc_core::get_object()->subdivision->create($this->remove_fileable_fields_from_array($data));

                if (isset($data["EnglishName"])) {
                    $this->instruction->set_subdivision_keyword($data["EnglishName"]);
                }

                $this->update_file_fields($data, $subdivision_id);
            } catch (Exception $e) {
                continue;
            }

        }

        return true;
    }

    /**
     * @return bool
     */
    protected function do_update() {
        if (!$this->resolve_dependencies()) {
            return false;
        }

        $subdivision_table = $this->get_subdivision_table_by_keyword();
        $dataset = $this->instruction->get_dataset();

        // Файловые поля намеренно меняются до обновления не файловых, потому что может быть изменен EnglishName, если он передан в dataset
        $this->update_file_fields($dataset);

        $subdivision_table->update($this->remove_fileable_fields_from_array($dataset));

        return true;
    }

    /**
     * @return bool
     */
    protected function do_delete() {
        $subdivision_table = $this->get_subdivision_table_by_keyword();

        return (bool)nc_core::get_object()->subdivision->delete_by_id($subdivision_table->get_value("Subdivision_ID"));
    }

    /**
     * @return array
     */
    private function remove_fileable_fields_from_array(array $data) {
        return array_diff_key($data, array_flip(self::$file_fields));
    }

    /**
     * @param int|null $subdivision_id
     *
     * @return void
     */
    private function update_file_fields(array $fields, $subdivision_id = null) {
        $subdivision_id = $subdivision_id ?: (int)$this->get_subdivision_table_by_keyword()->get_value("Subdivision_ID");

        if (!$subdivision_id) {
            return;
        }

        $files = nc_core::get_object()->files;

        foreach (self::$file_fields as $field) {
            if (isset($fields[$field])) {
                $files->field_save_file("Subdivision", $field, $subdivision_id, $fields[$field]);
            }
        }
    }

    /**
     * @param $data
     *
     * @return int
     */
    private function retrieve_parent_subdivision_id($data) {
        if (!isset($data["Parent_Sub_ID"])) {
            return 0;
        }

        if (!is_numeric($data["Parent_Sub_ID"])) {
            return (int)$this->get_subdivision_table_by_keyword($data["Parent_Sub_ID"])->get_value("Subdivision_ID");
        }

        return (int)$data["Parent_Sub_ID"];
    }

    /**
     * Назначение макета дизайна разделу
     *
     * Параметры dataset:
     * - template_id (int) — ID макета в таблице Template
     * - template_path (string) — альтернатива template_id: путь к макету (например: 'netcat_default/free_landing')
     *
     * Пример использования:
     * <pre>
     *   new nc_content_changer_subdivision_instruction('home-page', 'assign_template', [
     *       'template_path' => 'netcat_default/free_landing',
     *   ]);
     * </pre>
     *
     * @return bool
     */
    protected function do_assign_template() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        $subdivision_keyword = $this->instruction->get_subdivision_keyword();

        if (empty($subdivision_keyword)) {
            throw new Exception("subdivision_keyword is required for assign_template action");
        }

        // Находим раздел
        $subdivision_id = $this->get_subdivision_table_by_keyword()->get_value("Subdivision_ID");
        if (!$subdivision_id) {
            throw new Exception("Subdivision not found: $subdivision_keyword");
        }

        // Определяем template_id
        $template_id = null;

        if (!empty($dataset['template_id'])) {
            $template_id = (int)$dataset['template_id'];
        } elseif (!empty($dataset['template_path'])) {
            $file_path = '/' . trim($dataset['template_path'], '/') . '/';
            $template_id = nc_db()->get_var(
                "SELECT Template_ID FROM Template
                 WHERE File_Path = '" . nc_db()->escape($file_path) . "'"
            );
        }

        if (!$template_id) {
            throw new Exception("Template not found. Provide template_id or valid template_path");
        }

        // Обновляем раздел
        $nc_core->subdivision->update($subdivision_id, [
            'Template_ID' => $template_id,
        ]);

        $nc_core->template->clear_cache();
        $nc_core->subdivision->clear_cache();

        $this->log_operation_result('assign_template', [
            'subdivision_id' => $subdivision_id,
            'subdivision_keyword' => $subdivision_keyword,
            'template_id' => $template_id,
        ]);

        return true;
    }

    /**
     * Обновление визуальных настроек макета (TemplateSettings) для раздела
     *
     * Параметры dataset:
     * - settings (array, обязательный) — значения настроек (ключ => значение)
     * - ensure_settings_exist (array) — гарантировать наличие настроек в Template.CustomSettings
     *   Формат: ['имя_настройки' => ['type' => 'textarea', 'caption' => 'Описание']]
     *
     * Пример использования:
     * <pre>
     *   new nc_content_changer_subdivision_instruction('home-page', 'update_template_settings', [
     *       'settings' => [
     *           'css' => ':root { --primary-color: #2563eb; }',
     *       ],
     *       'ensure_settings_exist' => [
     *           'css' => ['type' => 'textarea', 'caption' => 'CSS-стили'],
     *       ],
     *   ]);
     * </pre>
     *
     * @return bool
     * @throws Exception
     */
    protected function do_update_template_settings() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        $subdivision_keyword = $this->instruction->get_subdivision_keyword();

        if (empty($subdivision_keyword)) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: subdivision_keyword");
            }
            return false;
        }

        if (empty($dataset['settings']) || !is_array($dataset['settings'])) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: settings (must be non-empty array)");
            }
            return false;
        }

        // Найти раздел по keyword
        $subdivision_id = $this->get_subdivision_table_by_keyword()->get_value("Subdivision_ID");

        if (!$subdivision_id) {
            throw new Exception("Subdivision not found: " . $subdivision_keyword);
        }

        // Получить Template_ID раздела (с учётом наследования от сайта)
        $template_id = $nc_core->subdivision->get_by_id($subdivision_id, 'Template_ID');

        if (!$template_id) {
            // Если у раздела нет своего шаблона, берём шаблон сайта
            $catalogue_id = $nc_core->catalogue->id();
            $template_id = $nc_core->catalogue->get_by_id($catalogue_id, 'Template_ID');
        }

        if (!$template_id) {
            throw new Exception("Template not found for subdivision: " . $subdivision_keyword);
        }

        // Убедиться, что настройки существуют в Template.CustomSettings
        if (!empty($dataset['ensure_settings_exist'])) {
            $this->ensure_template_settings_exist($template_id, $dataset['ensure_settings_exist']);
        }

        // Установить значения в Subdivision.TemplateSettings
        $custom_settings = $nc_core->template->get_custom_settings($template_id);

        $a2f = new nc_a2f($custom_settings, 'TemplateSettings');
        $a2f->set_initial_values();

        // Получаем текущие значения раздела
        $current_settings = $nc_core->subdivision->get_by_id($subdivision_id, 'TemplateSettings');
        if ($current_settings) {
            $this->assert_stored_settings_not_broken($current_settings, "Subdivision.TemplateSettings #{$subdivision_id}");
            $a2f->set_value($current_settings, "Subdivision.TemplateSettings #{$subdivision_id}");
        }

        // Устанавливаем новые значения
        $a2f->set_values($dataset['settings']);

        $template_settings = $a2f->get_values_as_string();
        $this->assert_value_fits_column('Subdivision', 'TemplateSettings', $template_settings);

        $nc_core->subdivision->update($subdivision_id, [
            'TemplateSettings' => $template_settings
        ]);

        // Сброс кэша
        $nc_core->template->clear_cache();
        $nc_core->subdivision->clear_cache();

        // Логирование
        $this->log_operation_result('update_template_settings', [
            'subdivision_id' => $subdivision_id,
            'subdivision_keyword' => $subdivision_keyword,
            'template_id' => $template_id,
            'settings_keys' => array_keys($dataset['settings']),
        ]);

        return true;
    }

    /**
     * Добавляет отсутствующие настройки в Template.CustomSettings
     *
     * @param int $template_id
     * @param array $required_settings
     * @return void
     */
    protected function ensure_template_settings_exist($template_id, array $required_settings) {
        $nc_core = nc_core::get_object();

        $current = $nc_core->template->get_by_id($template_id, 'CustomSettings');
        $current_array = nc_a2f::evaluate($current) ?: [];

        $changed = false;
        foreach ($required_settings as $name => $definition) {
            if (!isset($current_array[$name])) {
                $current_array[$name] = $definition;
                $changed = true;
            }
        }

        if ($changed) {
            $nc_core->template->update($template_id, [
                'CustomSettings' => var_export($current_array, true)
            ]);

            // Очистка кэша шаблона после изменения CustomSettings
            $nc_core->template->clear_cache();
        }
    }

}
