<?php

class nc_content_changer_infoblock extends nc_content_changer {

    /**
     * @inheritDoc
     */
    protected function validate() {
        if ($this->action == "delete") {
            return true;
        }

        $dataset = $this->instruction->get_dataset();

        return !empty($dataset);
    }

    /**
     * Создание нового инфоблока
     *
     * Формат dataset:
     * - component_keyword (string) — keyword компонента (обязательный если нет class_id)
     * - class_id (int) — ID компонента напрямую (0 для Flexbox-контейнера)
     * - class_template_keyword (string) — keyword шаблона компонента (для использования альтернативной вёрстки)
     * - subdivision_keyword (string, обязательный) — keyword раздела
     * - container_keyword (string) — keyword родительского контейнера (для вложенных блоков)
     * - name (string) — название инфоблока
     * - english_name (string) — keyword инфоблока
     * - priority (int) — приоритет отображения (по умолчанию 100)
     *
     * @return int|false ID созданного инфоблока или false при ошибке
     */
    protected function do_create() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        // Валидация: нужен либо component_keyword, либо class_id
        if (empty($dataset['component_keyword']) && !isset($dataset['class_id'])) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: component_keyword or class_id");
            }
            return false;
        }

        if (empty($dataset['subdivision_keyword'])) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: subdivision_keyword");
            }
            return false;
        }

        try {
            // Получение Class_ID: либо напрямую, либо по keyword
            if (isset($dataset['class_id'])) {
                // Прямое указание class_id (0 для контейнеров)
                $component_id = (int)$dataset['class_id'];
            } else {
                // Получение Class_ID по keyword компонента (только базовые компоненты)
                $component_id = nc_db_table::make('Class')
                    ->where('Keyword', $dataset['component_keyword'])
                    ->where('ClassTemplate', 0)
                    ->get_value('Class_ID');

                if (!$component_id) {
                    throw new Exception("Component not found: " . $dataset['component_keyword']);
                }
            }

            // Получение Class_Template_ID по keyword шаблона (если указан)
            $class_template_id = 0;
            if (!empty($dataset['class_template_keyword'])) {
                $class_template_id = nc_db_table::make('Class')
                    ->where('Keyword', $dataset['class_template_keyword'])
                    ->get_value('Class_ID');

                if (!$class_template_id) {
                    throw new Exception("Component template not found: " . $dataset['class_template_keyword']);
                }
            }

            // Subdivision_ID можно передать напрямую (сквозной блок: subdivision_id=0
            // для вывода на всех страницах) или через keyword раздела.
            if (isset($dataset['subdivision_id']) && is_numeric($dataset['subdivision_id'])) {
                $subdivision_id = (int)$dataset['subdivision_id'];
            } else {
                $subdivision_id = nc_db_table::make('Subdivision')
                    ->where('Catalogue_ID', $nc_core->catalogue->id())
                    ->where('EnglishName', $dataset['subdivision_keyword'])
                    ->get_value('Subdivision_ID');

                if (!$subdivision_id) {
                    throw new Exception("Subdivision not found: " . $dataset['subdivision_keyword']);
                }
            }

            // Получение Parent_Sub_Class_ID по keyword контейнера (если указан)
            $parent_sub_class_id = 0;
            if (!empty($dataset['container_keyword'])) {
                $parent_sub_class_id = nc_db_table::make('Sub_Class')
                    ->where('Catalogue_ID', $nc_core->catalogue->id())
                    ->where('EnglishName', $dataset['container_keyword'])
                    ->get_value('Sub_Class_ID');

                if (!$parent_sub_class_id) {
                    throw new Exception("Container not found: " . $dataset['container_keyword']);
                }
            }

            // Определение типа инфоблока: container для Class_ID=0, иначе object_list
            $sub_class_type = ($component_id === 0) ? 'container' : 'object_list';

            // Данные инфоблока
            $infoblock_data = [
                'Catalogue_ID' => $nc_core->catalogue->id(),
                'Subdivision_ID' => (int)$subdivision_id,
                'Class_ID' => (int)$component_id,
                'Class_Template_ID' => (int)$class_template_id,
                'Parent_Sub_Class_ID' => (int)$parent_sub_class_id,
                'Sub_Class_Type' => $sub_class_type,
                'Sub_Class_Name' => $dataset['name'] ?? $dataset['english_name'] ?? ($dataset['component_keyword'] ?? 'Container'),
                'EnglishName' => $dataset['english_name'] ?? ($dataset['component_keyword'] ?? 'container_' . time()),
                'Checked' => 1,
                'Priority' => (int)($dataset['priority'] ?? 100),
                'RecordsPerPage' => 0,
                'SortBy' => '',
                'CustomSettings' => '',
                'AllowTags' => 1,
            ];

            if (isset($dataset['max_records'])) {
                $infoblock_data['MaxRecordsInInfoblock'] = (int)$dataset['max_records'];
            }

            // Постраничный вывод списка (records_per_page > 0): страницу считает ядро,
            // навигацию печатает nc_browse_messages() из суффикса компонента
            if (isset($dataset['records_per_page'])) {
                $infoblock_data['RecordsPerPage'] = max(0, (int)$dataset['records_per_page']);
            }

            if (isset($dataset['code_settings'])) {
                $infoblock_data['Code_Settings'] = json_encode(
                    nc_code_settings::sanitize($dataset['code_settings']),
                    JSON_UNESCAPED_UNICODE
                );
            }

            // AreaKeyword — зона вывода (например, 'header'/'footer' для сквозных блоков).
            // Без этого nc_area($keyword) не подхватит инфоблок.
            if (!empty($dataset['area_keyword'])) {
                $infoblock_data['AreaKeyword'] = (string)$dataset['area_keyword'];
            }

            $infoblock_id = nc_db_table::make('Sub_Class')->insert($infoblock_data);

            if (!$infoblock_id) {
                throw new Exception("Failed to create infoblock" . (isset($dataset['component_keyword']) ? " for component: " . $dataset['component_keyword'] : ""));
            }

            // Сброс кэша
            $nc_core->sub_class->clear_cache();

            // Логирование
            if ($this->logger) {
                $this->log_operation_result('infoblock_create', [
                    'infoblock_id' => $infoblock_id,
                    'class_id' => $component_id,
                    'class_template_id' => $class_template_id,
                    'component_keyword' => $dataset['component_keyword'] ?? null,
                    'class_template_keyword' => $dataset['class_template_keyword'] ?? null,
                    'subdivision_keyword' => $dataset['subdivision_keyword'],
                    'container_keyword' => $dataset['container_keyword'] ?? null,
                    'parent_sub_class_id' => $parent_sub_class_id,
                    'english_name' => $infoblock_data['EnglishName'],
                ]);
            }

            return (int)$infoblock_id;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->set_error($e->getMessage());
            }
            return false;
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function do_update() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies");
            }
            return false;
        }

        $dataset = $this->instruction->get_dataset();
        $updated_infoblocks = $this->get_infoblock_ids();
        $operations_performed = array();

        if (isset($dataset["custom_settings"])) {
            $result = $this->update_custom_settings();
            $operations_performed[] = array('custom_settings' => $result);
        }

        if (isset($dataset["fields"])) {
            $result = $this->update_fields();
            $operations_performed[] = array('fields' => $result);
        }

        if (isset($dataset["files"])) {
            $result = $this->update_files();
            $operations_performed[] = array('files' => $result);
            $this->delete_tmp_directory();
        }

        if (isset($dataset["mixins"])) {
            $this->update_mixins();
            $operations_performed[] = array('mixins' => 'completed');
        }

        // records_per_page — до conditions: update_conditions() с condition_limit сам выставляет
        // RecordsPerPage (при 0 CMS игнорирует ConditionLimit) с учётом records_per_page
        if (isset($dataset["records_per_page"])) {
            $result = (bool)$this->get_infoblock_table_by_keyword()->update(array(
                'RecordsPerPage' => max(0, (int)$dataset['records_per_page']),
            ));
            $operations_performed[] = array('records_per_page' => $result);
        }

        if (isset($dataset["conditions"])) {
            $result = $this->update_conditions();
            $operations_performed[] = array('conditions' => $result);
        }

        if (isset($dataset["code_settings"])) {
            $this->update_code_settings();
            $operations_performed[] = array('code_settings' => 'completed');
        }

        if (isset($dataset["requests_button_settings"])) {
            $result = $this->update_requests_button_settings();
            $operations_performed[] = array('requests_button_settings' => $result);
        }

        // Логируем результат операции
        if ($this->logger) {
            $result_data = array(
                'updated_infoblocks' => $updated_infoblocks,
                'operations' => $operations_performed
            );
            $this->log_operation_result('infoblock_update', $result_data);
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function do_paste() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies for paste operation");
            }
            return false;
        }

        $infoblock_id = (int)$this->get_infoblock_table_by_keyword()->get_value("Sub_Class_ID");
        $dataset = $this->instruction->get_dataset();

        if (!$infoblock_id || !isset($dataset["subdivision_id"])) {
            if ($this->logger) {
                $error = "Missing source infoblock ID or subdivision ID";
                $this->logger->set_error($error);
            }
            return false;
        }

        if (!is_numeric($dataset["subdivision_id"])) {
            $dataset["subdivision_id"] = (int)$this->get_subdivision_table_by_keyword($dataset["subdivision_id"])->get_value(
                "Subdivision_ID"
            );
        }

        if (isset($dataset["target_infoblock_id"]) && !is_numeric($dataset["target_infoblock_id"])) {
            $dataset["target_infoblock_id"] = (int)$this->get_infoblock_table_by_keyword($dataset["target_infoblock_id"])->get_value(
                "Sub_Class_ID"
            );
        }

        $nc_core = nc_core::get_object();

        $dataset["mode"] = nc_array_value($dataset, "mode", "copy");
        $dataset["position"] = nc_array_value($dataset, "position", "after");
        $dataset["main_axis"] = nc_array_value($dataset, "main_axis", "horizontal");
        $dataset["site_id"] = nc_array_value($dataset, "site_id", (int)$nc_core->catalogue->get_current("Catalogue_ID"));

        if (isset($dataset["container_id"]) && !is_numeric($dataset["container_id"])) {
            $dataset["container_id"] = (int)$this->get_infoblock_table_by_keyword($dataset["container_id"])->get_value("Sub_Class_ID");
        }

        if (isset($dataset["object_keyword"]) && isset($dataset["object_infoblock_keyword"])) {
            $infoblock_data = $this->get_infoblock_table_by_keyword($dataset["object_infoblock_keyword"])->get_row();
            $class_id = $infoblock_data["Class_ID"];
            $object_id = (int)nc_db()->get_var(
                "SELECT `Message_ID` FROM `Message$class_id` WHERE `Sub_Class_ID` = $infoblock_data[Sub_Class_ID] AND `Keyword` = '$dataset[object_keyword]'"
            ) ?: -1;

            if (!$object_id) {
                if ($this->logger) {
                    $this->logger->set_error("Object not found by keyword: " . $dataset["object_keyword"]);
                }
                return false;
            }

            $component = $nc_core->get_component($class_id);
            $dataset["area_keyword"] = $component->get_full_page_area_keyword($object_id);
        }

        $properties = array(
            "Subdivision_ID" => $dataset["subdivision_id"],
            "AreaKeyword" => nc_array_value($dataset, "area_keyword", ""),
            "Parent_Sub_Class_ID" => nc_array_value($dataset, "container_id", 0),
        );

        if (isset($dataset["target_infoblock_id"]) && ($dataset["position"] === "before" || $dataset["position"] === "after")) {
            $properties["Priority"] = array(
                "Position" => $dataset["position"],
                "Sub_Class_ID" => $dataset["target_infoblock_id"],
            );
        }

        try {
            if ($dataset["position"] === "wrap_before" || $dataset["position"] === "wrap_after") {
                $properties["Parent_Sub_Class_ID"] = $nc_core->sub_class->wrap_with_flexbox($infoblock_id, $dataset["main_axis"]) ?: 0;
            }

            $new_infoblock_id = $nc_core->sub_class->duplicate($infoblock_id, $properties, nc_array_value($dataset, "copy_objects", true));
            $fields_to_update = [];

            if (isset($dataset["name"])) {
                $fields_to_update["Sub_Class_Name"] = $dataset["name"];
            }

            if (isset($dataset["english_name"])) {
                $fields_to_update["EnglishName"] = $dataset["english_name"];
            }

            if ($fields_to_update) {
                nc_db_table::make("Sub_Class")->where_id($new_infoblock_id)->update($fields_to_update);
            }

            nc_core::get_object()->sub_class->clear_cache();

            // Логируем успешное выполнение
            if ($this->logger) {
                $result_data = array(
                    'source_infoblock_id' => $infoblock_id,
                    'new_infoblock_id' => $new_infoblock_id,
                    'subdivision_id' => $dataset["subdivision_id"],
                    'position' => $dataset["position"],
                    'updated_fields' => $fields_to_update
                );
                $this->log_operation_result('infoblock_paste', $result_data);
            }

            return true;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->set_error("Exception during paste operation: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function do_delete() {
        try {
            $infoblock_id = (int)$this->get_infoblock_table_by_keyword()->get_value("Sub_Class_ID");
            
            if (!$infoblock_id) {
                if ($this->logger) {
                    $this->logger->set_error("Infoblock not found for deletion");
                }
                return false;
            }

            // Получаем информацию о блоке перед удалением для логирования
            $infoblock_info = nc_db_table::make("Sub_Class")->where_id($infoblock_id)->get_row();
            
            nc_core::get_object()->sub_class->delete($infoblock_id);

            // Логируем успешное удаление
            if ($this->logger) {
                $result_data = array(
                    'deleted_infoblock_id' => $infoblock_id,
                    'infoblock_name' => $infoblock_info['Sub_Class_Name'] ?? 'Unknown',
                    'english_name' => $infoblock_info['EnglishName'] ?? 'Unknown'
                );
                $this->log_operation_result('infoblock_delete', $result_data);
            }

            return true;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->set_error("Exception during delete operation: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * @return bool
     */
    private function update_custom_settings() {
        $dataset = $this->instruction->get_dataset();

        foreach ($this->get_infoblock_ids() as $infoblock_id) {
            $a2f = $this->get_custom_settings_instance($infoblock_id);
            $a2f->set_values($dataset["custom_settings"]);

            if (!$a2f->validate($a2f->get_values_as_array())) {
                return false;
            }

            $custom_settings = $a2f->get_values_as_string();
            $this->assert_value_fits_column("Sub_Class", "CustomSettings", $custom_settings);

            nc_db_table::make("Sub_Class")
                       ->where_id($infoblock_id)
                       ->update(array("CustomSettings" => $custom_settings));

        }

        return true;
    }

    /**
     * @return bool
     */
    private function update_fields() {
        $dataset = $this->instruction->get_dataset();

        return (bool)$this->get_infoblock_table_by_keyword()->update($dataset["fields"]);
    }

    /**
     * @return bool
     */
    private function update_files() {
        $dataset = $this->instruction->get_dataset();

        foreach ($this->get_infoblock_ids() as $infoblock_id) {
            $a2f = $this->get_custom_settings_instance($infoblock_id);

            foreach ($dataset["files"] as $key => $data) {
                if (strpos($data["file"], "http") !== false) {
                    $data["file"] = $this->save_remote_file($data["file"]);
                }

                $file_path = realpath($data["file"]);

                if ($data["file"] == "" || !file_exists($file_path)) {
                    continue;
                }

                $tmp_file_path = $this->get_tmp_directory() . "/" . basename($file_path);

                if ($file_path != realpath($tmp_file_path)) {
                    $file_moved = rename($file_path, $tmp_file_path);

                    if (!$file_moved) {
                        continue;
                    }
                }

                $data["file"] = $this->translate_tmp_directory_to_relative($tmp_file_path);
                $a2f->save(array($key => $data));
            }

            $custom_settings = $a2f->get_values_as_string();
            $this->assert_value_fits_column("Sub_Class", "CustomSettings", $custom_settings);

            nc_db_table::make("Sub_Class")
                       ->where_id($infoblock_id)
                       ->update(array("CustomSettings" => $custom_settings));
        }

        return true;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function update_mixins() {
        $nc_core = nc_core::get_object();

        $current_index_mixins = json_decode($this->get_infoblock_table_by_keyword()->get_value("Index_Mixin_Settings") ?: "[]", true);
        $dataset = $this->instruction->get_dataset();

        if (empty($current_index_mixins)) {
            $current_index_mixins[""] = array();
        }

        $dataset = $dataset["mixins"];
        $infoblock_id = $this->get_infoblock_table_by_keyword()->get_value("Sub_Class_ID");

        if (isset($dataset["Index_Mixin_Settings"])) {
            $index_mixin_settings = array();

            if (!isset($dataset["Index_Mixin_Settings"][""])) {
                $index_mixin_settings = array("" => $dataset["Index_Mixin_Settings"]);
            }

            $index_mixin_settings = array_replace_recursive($current_index_mixins, $index_mixin_settings);

            if (isset($index_mixin_settings[""]["background"])) {
                $storage_path = $nc_core->FILES_FOLDER . "mixin/Index/$infoblock_id/netcat_background/";

                if (!file_exists($storage_path)) {
                    @mkdir($storage_path, $nc_core->DIRCHMOD, true);
                }

                foreach ($index_mixin_settings[""]["background"] as $breakpoint => $data) {
                    foreach ($data["settings"] as $i => $setting) {
                        if ($setting["background_url"]) {
                            $file_path = $this->save_remote_file($setting["background_url"]);
                            $file_name = basename($file_path);

                            if (rename($file_path, $storage_path . $file_name)) {
                                $index_mixin_settings[""]["background"][$breakpoint]["settings"][$i]["background_url"] = str_replace(
                                    $nc_core->DOCUMENT_ROOT,
                                    "",
                                    $storage_path .
                                    $file_name
                                );
                            }
                        }
                    }
                }
            }

            $this->get_infoblock_table_by_keyword()->update(
                array("Index_Mixin_Settings" => json_encode($index_mixin_settings, JSON_UNESCAPED_SLASHES))
            );
        }

        $nc_core->sub_class->get_by_id($infoblock_id, '', 0, true);
        nc_tpl_mixin_cache::generate_block_files($infoblock_id, false);
    }

    private function apply_condition_finders($condition) {
        if (nc_array_value($condition, "value") instanceof nc_content_changer_finder) {
            $condition["value"] = (string)$condition["value"];
            if (!strlen($condition["value"])) {
                return null;
            }
        }

        if (!empty($condition["conditions"])) {
            foreach ($condition["conditions"] as $k => $v) {
                $condition["conditions"][$k] = $this->apply_condition_finders($v);
            }

            $condition["conditions"] = array_filter($condition["conditions"], "is_array");

            if (empty($condition["conditions"])) {
                return null;
            }
        }

        return $condition;
    }

    /**
     * @return bool
     */
    private function update_conditions() {
        $dataset = $this->instruction->get_dataset();
        $conditions = $dataset["conditions"];
        $conditions = $this->apply_condition_finders($conditions);

        if (empty($conditions)) {
            return false;
        }

        $infoblock_condition_translator = new nc_condition_infoblock_translator(
            $conditions,
            $this->get_infoblock_table_by_keyword()->get_value("Sub_Class_ID")
        );

        $update_data = [
            "Condition" => nc_array_json($conditions),
            "ConditionQuery" => $infoblock_condition_translator->get_sql_condition(),
        ];

        // Лимит записей из источника (ConditionLimit)
        if (isset($dataset['condition_limit'])) {
            $limit = max(1, (int)$dataset['condition_limit']);
            $update_data['ConditionLimit'] = $limit;
            // RecordsPerPage должен быть > 0, иначе CMS игнорирует LIMIT в запросе. Явный
            // records_per_page (постраничный вывод внутри лимита) не больше лимита сохраняется,
            // 0/не задан — одна страница на весь лимит
            $records_per_page = isset($dataset['records_per_page']) ? max(0, (int)$dataset['records_per_page']) : 0;
            $update_data['RecordsPerPage'] = $records_per_page > 0 ? min($records_per_page, $limit) : $limit;
        }

        // Максимум записей в инфоблоке
        if (isset($dataset['max_records'])) {
            $update_data['MaxRecordsInInfoblock'] = (int)$dataset['max_records'];
        }

        return (bool)$this->get_infoblock_table_by_keyword()->update($update_data);
    }

    /**
     * @return bool
     */
    private function update_code_settings() {
        $dataset = $this->instruction->get_dataset();
        $sanitized = nc_code_settings::sanitize($dataset['code_settings']);

        foreach ($this->get_infoblock_ids() as $infoblock_id) {
            nc_db_table::make('Sub_Class')
                ->where_id($infoblock_id)
                ->update(['Code_Settings' => json_encode($sanitized, JSON_UNESCAPED_UNICODE)]);
        }

        nc_core::get_object()->sub_class->clear_cache();

        return true;
    }

    /**
     * @param $file_url
     *
     * @return string
     */
    private function save_remote_file($file_url) {
        try {
            $http = new nc_http();
            $file_data = $http->make_get_request(str_replace(" ", "%20", $file_url));
            $file_path = $this->get_tmp_directory() . DIRECTORY_SEPARATOR . basename($file_url);

            file_put_contents($file_path, $file_data);

            return $file_path;
        } catch (nc_http_exception $e) {
            return "";
        }
    }


    /**
     * @param $infoblock_id
     *
     * @return nc_a2f|null
     */
    private function get_custom_settings_instance($infoblock_id) {
        $nc_core = nc_core::get_object();

        // Не в try: повреждённые настройки должны остановить инструкцию, а не перезаписаться
        $this->assert_stored_settings_not_broken(
            $nc_core->sub_class->get_by_id($infoblock_id, "CustomSettings"),
            "Sub_Class.CustomSettings #{$infoblock_id}"
        );

        try {
            $custom_settings_template = $nc_core->sub_class->get_by_id($infoblock_id, "CustomSettingsTemplate");
            $custom_settings = $nc_core->sub_class->get_by_id($infoblock_id, "CustomSettings");

            $a2f = new nc_a2f($custom_settings_template, "CustomSettings");
            $a2f->set_value($custom_settings, "Sub_Class.CustomSettings #{$infoblock_id}");

            return $a2f;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Установка настроек кнопки формы заявки (модуль requests)
     *
     * Работает через nc_requests_form_settings_infoblock: если модуль requests
     * установлен, создаёт или обновляет настройки кнопки для инфоблока.
     *
     * Dataset: ['requests_button_settings' => [
     *     'form_type' => 'default',
     *     'OpenPopupButton_Text' => 'Заказать',
     *     // ... другие настройки nc_requests_form_settings_infoblock
     * ]]
     *
     * @return bool
     */
    private function update_requests_button_settings() {
        if (!class_exists('nc_requests_form_settings_infoblock')) {
            return false;
        }

        $dataset = $this->instruction->get_dataset();
        $settings_data = $dataset['requests_button_settings'];
        $form_type = $settings_data['form_type'] ?? 'default';
        unset($settings_data['form_type']);

        foreach ($this->get_infoblock_ids() as $infoblock_id) {
            $settings = nc_requests_form_settings_infoblock::for_infoblock($infoblock_id, $form_type);
            foreach ($settings_data as $key => $value) {
                $settings->set($key, $value);
            }
            $settings->save();
        }

        return true;
    }

}
