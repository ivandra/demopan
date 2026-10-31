<?php

class nc_content_changer_object extends nc_content_changer {

    /**
     * @var nc_content_changer_object_instruction
     */
    protected $instruction;

    /**
     * @inheritDoc
     */
    protected function validate() {
        if ($this->instruction->get_action() == "delete") {
            return true;
        }

        $dataset = $this->instruction->get_dataset();

        return isset($dataset["fields"]) || isset($dataset["files"]);
    }

    /**
     * Обновление объектов
     *
     * Если указан object_index в dataset, обновляется только объект по этому индексу (0-indexed).
     * Иначе обновляются все найденные объекты.
     *
     * @return bool
     */
    protected function do_update() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies for object update");
            }
            return false;
        }

        $dataset = $this->instruction->get_dataset();
        $objects_by_component = $this->find_objects();
        $has_files = !empty($dataset["files"]);
        $has_fields = !empty($dataset["fields"]);
        $object_index = isset($dataset["object_index"]) ? (int)$dataset["object_index"] : null;

        if (empty($objects_by_component)) {
            if ($this->logger) {
                $this->logger->set_error("No objects found for update");
            }
            return false;
        }

        if ($has_files) {
            $nc_files = nc_core::get_object()->files;
        }

        $updated_objects = array();
        $operations_performed = array();
        $failed_updates = array();
        $db = nc_core::get_object()->db;

        foreach ($objects_by_component as $component_id => $objects) {
            $table = nc_db_table::make("Message" . $component_id);

            // Поле, которого у компонента нет, роняет весь UPDATE записи (как INSERT в do_create):
            // называем виновные поля заранее, вместо безымянной ошибки SQL
            $unknown_fields = array();
            if ($has_fields) {
                $known_columns = $this->get_component_columns($component_id);
                if ($known_columns !== null) {
                    foreach (array_keys($dataset["fields"]) as $field_name) {
                        if (!in_array(strtolower($field_name), $known_columns, true)) {
                            $unknown_fields[] = $field_name;
                        }
                    }
                }
            }

            // Если указан object_index, фильтруем до одного объекта
            if ($object_index !== null) {
                if (!isset($objects[$object_index])) {
                    if ($this->logger) {
                        $this->logger->set_error("Object at index $object_index not found (total objects: " . count($objects) . ")");
                    }
                    return false;
                }
                $objects = array($objects[$object_index]);
            }

            foreach ($objects as $object_id) {
                if ($has_files) {
                    foreach ($dataset["files"] as $field_name => $file) {
                        $nc_files->field_save_file($component_id, $field_name, $object_id, $file);
                        $operations_performed[] = array(
                            'operation' => 'file_update',
                            'component_id' => $component_id,
                            'object_id' => $object_id,
                            'field_name' => $field_name
                        );
                    }
                }

                if ($has_fields) {
                    if ($unknown_fields) {
                        $failed_updates[] = array(
                            'component_id' => $component_id,
                            'object_id' => $object_id,
                            'unknown_fields' => $unknown_fields,
                            'error' => "Fields not found in component $component_id: " . implode(', ', $unknown_fields)
                        );
                        continue;
                    }

                    // Результат UPDATE проверяем: ошибка SQL — отказ инструкции, а не успех
                    // с предупреждением. 0 затронутых строк (значения не изменились) — не ошибка.
                    $result = $table->where("Message_ID", $object_id)->update($dataset["fields"]);
                    $operations_performed[] = array(
                        'operation' => 'fields_update',
                        'component_id' => $component_id,
                        'object_id' => $object_id,
                        'updated_fields' => array_keys($dataset["fields"]),
                        'result' => $result
                    );
                    if ($result === false || $db->is_error) {
                        $failed_updates[] = array(
                            'component_id' => $component_id,
                            'object_id' => $object_id,
                            'error' => "UPDATE failed (see SQL error)"
                        );
                        continue;
                    }
                }

                $updated_objects[] = array(
                    'component_id' => $component_id,
                    'object_id' => $object_id
                );
            }
        }

        // Логируем результат операции
        if ($this->logger) {
            $result_data = array(
                'updated_objects' => $updated_objects,
                'failed_updates' => $failed_updates,
                'operations' => $operations_performed,
                'total_objects' => count($updated_objects),
                'total_failed' => count($failed_updates),
                'object_index' => $object_index
            );
            $this->log_operation_result('object_update', $result_data);
        }

        if ($failed_updates) {
            return $this->fail_with_reasons(
                'object_update',
                "Objects not updated: " . count($failed_updates) . " of " . (count($failed_updates) + count($updated_objects)),
                $failed_updates
            );
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function do_create() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies for object creation");
            }
            return false;
        }

        $objects_dataset = $this->instruction->get_dataset();
        $infoblock_ids = $this->get_infoblock_ids();
        $message = nc_core::get_object()->message;

        if (empty($infoblock_ids)) {
            if ($this->logger) {
                $this->logger->set_error("No infoblocks found for object creation");
            }
            return false;
        }

        $created_objects = array();
        $failed_creations = array();

        foreach ($infoblock_ids as $infoblock_id) {
            $known_columns = $this->get_object_columns($infoblock_id);

            foreach ($objects_dataset["fields"] as $fields) {
                if (empty($fields)) {
                    continue;
                }

                // Поля, которых у компонента нет, роняют ВЕСЬ INSERT: запись не создаётся,
                // а инструкция раньше рапортовала успех — контент терялся молча (типичный
                // источник: генератор пронумеровал поля по карточкам списка). Проверяем
                // заранее и называем виновные поля, вместо безымянной ошибки SQL.
                if ($known_columns !== null) {
                    // имена колонок MySQL регистронезависимы — сравниваем так же, иначе dataset
                    // с `title` вместо `Title`, который INSERT принимал, стал бы отвергаться
                    $unknown = array();
                    foreach (array_keys($fields) as $field_name) {
                        if (!in_array(strtolower($field_name), $known_columns, true)) {
                            $unknown[] = $field_name;
                        }
                    }
                    if ($unknown) {
                        $failed_creations[] = array(
                            'infoblock_id' => $infoblock_id,
                            'fields' => array_keys($fields),
                            'unknown_fields' => array_values($unknown),
                            'error' => "Fields not found in component: " . implode(', ', $unknown)
                        );
                        continue;
                    }
                }

                try {
                    $object_id = $message->create($infoblock_id, $fields);
                    $created_objects[] = array(
                        'infoblock_id' => $infoblock_id,
                        'object_id' => $object_id,
                        'fields' => array_keys($fields)
                    );
                } catch (Exception $e) {
                    $failed_creations[] = array(
                        'infoblock_id' => $infoblock_id,
                        'fields' => array_keys($fields),
                        'error' => $e->getMessage()
                    );
                    continue;
                }
            }
        }

        // Логируем результат операции
        if ($this->logger) {
            $result_data = array(
                'created_objects' => $created_objects,
                'failed_creations' => $failed_creations,
                'total_created' => count($created_objects),
                'total_failed' => count($failed_creations)
            );
            $this->log_operation_result('object_create', $result_data);
        }

        // Хотя бы одна несозданная запись — это НЕ успех инструкции. Раньше do_create
        // возвращал true при любом исходе, и раннер штамповал статус success поверх
        // накопленных failed_creations: потеря записей была видна только тому, кто
        // догадался прочитать Execution_Result.
        if ($failed_creations) {
            $reasons = array();
            foreach ($failed_creations as $failure) {
                $reasons[] = $failure['error'];
            }
            $reasons = array_unique($reasons);
            $message_text = "Objects not created: " . count($failed_creations)
                . " of " . (count($failed_creations) + count($created_objects))
                . ". " . implode('; ', array_slice($reasons, 0, 5));
            if ($this->logger) {
                $this->logger->set_error($message_text);
            }
            trigger_error("[content_changer:object_create] " . $message_text, E_USER_WARNING);
            return false;
        }

        return true;
    }

    /**
     * Имена колонок таблицы объектов инфоблока (пользовательские поля + системные),
     * в нижнем регистре — для регистронезависимого сравнения, как в MySQL.
     *
     * Источник истины для проверки dataset'а — сама таблица: именно её колонки участвуют
     * в INSERT. Если компонент/таблицу определить не удалось, возвращает null —
     * проверка тогда не выполняется (поведение как прежде).
     *
     * @param int $infoblock_id
     * @return array|null
     */
    protected function get_object_columns($infoblock_id) {
        $component_id = (int)nc_core::get_object()->sub_class->get_by_id((int)$infoblock_id, 'Class_ID');
        return $component_id ? $this->get_component_columns($component_id) : null;
    }

    /**
     * Имена колонок таблицы объектов компонента в нижнем регистре (null — не удалось определить).
     *
     * @param int $component_id
     * @return array|null
     */
    protected function get_component_columns($component_id) {
        $nc_core = nc_core::get_object();
        $component_id = (int)$component_id;
        if (!$component_id) {
            return null;
        }

        static $cache = array();
        if (isset($cache[$component_id])) {
            return $cache[$component_id];
        }

        try {
            $columns = $nc_core->db->get_col("SHOW COLUMNS FROM `Message" . $component_id . "`");
        } catch (Exception $e) {
            return null;
        }

        return $cache[$component_id] = ($columns ? array_map('strtolower', $columns) : null);
    }

    /**
     * @return bool
     */
    protected function do_delete() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies for object deletion");
            }
            return false;
        }

        $mapped_objects = $this->find_objects();
        $message = nc_core::get_object()->message;

        if (empty($mapped_objects)) {
            if ($this->logger) {
                $this->logger->set_error("No objects found for deletion");
            }
            return false;
        }

        $deleted_objects = array();
        $failed_deletions = array();

        foreach ($mapped_objects as $class_id => $objects_ids) {
            try {
                $message->delete_by_id($objects_ids, $class_id);
                $deleted_objects[] = array(
                    'component_id' => $class_id,
                    'object_ids' => $objects_ids,
                    'count' => count($objects_ids)
                );
            } catch (nc_Exception_DB_Error $e) {
                $failed_deletions[] = array(
                    'component_id' => $class_id,
                    'object_ids' => $objects_ids,
                    'error' => $e->getMessage()
                );
                if ($this->logger) {
                    $this->logger->set_error("Failed to delete objects from component $class_id: " . $e->getMessage());
                }
                return false;
            }
        }

        // Логируем результат операции
        if ($this->logger) {
            $result_data = array(
                'deleted_objects' => $deleted_objects,
                'failed_deletions' => $failed_deletions,
                'total_deleted' => array_sum(array_column($deleted_objects, 'count'))
            );
            $this->log_operation_result('object_delete', $result_data);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function do_substring_replace() {
        if (!$this->resolve_dependencies()) {
            if ($this->logger) {
                $this->logger->set_error("Failed to resolve dependencies for substring replace");
            }
            return false;
        }

        $objects_mapping = $this->find_objects();
        $object_dataset = $this->instruction->get_dataset();

        if (empty($objects_mapping)) {
            if ($this->logger) {
                $this->logger->set_error("No objects found for substring replace");
            }
            return false;
        }

        $replaced_objects = array();
        $operations_performed = array();
        $failed_replacements = array();
        $db = nc_core::get_object()->db;

        foreach ($objects_mapping as $class_id => $objects_ids) {
            foreach ($objects_ids as $object_id) {
                $object_failed = false;
                foreach ($object_dataset["fields"] as $field => $meta) {
                    $search = $meta["search"];
                    $replacement = $meta["replacement"];
                    
                    $result = nc_db()->query(
                        "UPDATE `Message$class_id` SET `$field` = REPLACE(`$field`, '$search', '$replacement') WHERE Message_ID = $object_id;"
                    );

                    $operations_performed[] = array(
                        'component_id' => $class_id,
                        'object_id' => $object_id,
                        'field' => $field,
                        'search' => $search,
                        'replacement' => $replacement,
                        'result' => $result !== false
                    );
                    if ($result === false || $db->is_error) {
                        $failed_replacements[] = array(
                            'component_id' => $class_id,
                            'object_id' => $object_id,
                            'field' => $field,
                            'error' => "UPDATE failed (see SQL error)"
                        );
                        $object_failed = true;
                    }
                }

                if ($object_failed) {
                    continue;
                }

                $replaced_objects[] = array(
                    'component_id' => $class_id,
                    'object_id' => $object_id
                );
            }
        }

        // Логируем результат операции
        if ($this->logger) {
            $result_data = array(
                'replaced_objects' => $replaced_objects,
                'failed_replacements' => $failed_replacements,
                'operations' => $operations_performed,
                'total_objects' => count($replaced_objects),
                'total_operations' => count($operations_performed)
            );
            $this->log_operation_result('object_substring_replace', $result_data);
        }

        if ($failed_replacements) {
            return $this->fail_with_reasons(
                'object_substring_replace',
                "Substring replace failed: " . count($failed_replacements) . " of " . count($operations_performed) . " operations",
                $failed_replacements
            );
        }

        return true;
    }

    /**
     * Отказ инструкции с перечнем причин: сообщение в логгер (его забирает раннер
     * в результат по Instruction_ID) и в журнал ошибок PHP.
     *
     * @param string $operation
     * @param string $summary
     * @param array $failures элементы с ключом 'error'
     * @return false
     */
    protected function fail_with_reasons($operation, $summary, array $failures) {
        $reasons = array_unique(array_column($failures, 'error'));
        $message_text = $summary . ". " . implode('; ', array_slice($reasons, 0, 5));
        if ($this->logger) {
            $this->logger->set_error($message_text);
        }
        trigger_error("[content_changer:$operation] " . $message_text, E_USER_WARNING);
        return false;
    }

    /**
     * @return array
     */
    protected function find_objects() {
        $objects = array();

        if ($this->instruction->has_infoblock_finder()) { // Объекты в инфоблоках
            $infoblock_table = $this->instruction->get_infoblock_finder()->get_table();

            $infoblock_ids = $infoblock_table->get_list("Class_ID");

            foreach ($infoblock_ids as $infoblock_id => $component_id) {
                $table = $this->instruction->get_object_finder($component_id)->get_table()
                    ->where("Sub_Class_ID", $infoblock_id);

                $objects[$component_id] = array_values($table->get_list("Message_ID"));
            }
        } else { // Объекты компонента по условиям
            $finder = $this->instruction->get_object_finder();
            $component_id = $finder->get_component_id();
            $objects[$component_id] = array_values($finder->get_table()->get_list("Message_ID"));
        }

        return $objects;
    }

}
