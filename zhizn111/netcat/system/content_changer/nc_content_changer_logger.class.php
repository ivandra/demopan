<?php

class nc_content_changer_logger {

    const TABLE_NAME = 'ContentChanger_Log';

    /**
     * @var array
     */
    private $log_entry = array();

    /**
     * @var float
     */
    private $start_time;

    /**
     * @param nc_content_changer_instruction $instruction
     */
    public function __construct(nc_content_changer_instruction $instruction = null) {
        $this->start_time = microtime(true);
        
        if ($instruction) {
            $this->log_entry = array(
                'Instruction_Json' => $instruction->to_json(),
                'Entity_Type' => $instruction->get_entity_type(),
                'Action' => $instruction->get_action(),
                'Execution_Status' => 'pending',
                'Execution_Result' => '',
                'Error_Message' => '',
                'Search_Criteria' => '',
                'Found_Entities' => '',
                'Execution_Time' => date('Y-m-d H:i:s'),
                'Execution_Duration' => 0,
                'Site_ID' => nc_core::get_object()->catalogue->get_current('Catalogue_ID')
            );
        }
    }

    /**
     * Устанавливает критерии поиска
     * @param string $criteria
     * @return void
     */
    public function set_search_criteria($criteria) {
        $this->log_entry['Search_Criteria'] = $criteria;
    }

    /**
     * Устанавливает найденные сущности
     * @param array $entities
     * @return void
     */
    public function set_found_entities($entities) {
        if (is_array($entities)) {
            $this->log_entry['Found_Entities'] = json_encode($entities, JSON_UNESCAPED_UNICODE);
        } else {
            $this->log_entry['Found_Entities'] = (string)$entities;
        }
    }

    /**
     * Устанавливает результат выполнения
     * @param mixed $result
     * @return void
     */
    public function set_execution_result($result) {
        if (is_array($result)) {
            $this->log_entry['Execution_Result'] = json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            $this->log_entry['Execution_Result'] = (string)$result;
        }
    }

    /**
     * Устанавливает статус выполнения как успешный
     * @return void
     */
    public function set_success() {
        $this->log_entry['Execution_Status'] = 'success';
        $this->log_entry['Execution_Duration'] = round(microtime(true) - $this->start_time, 4);
    }

    /**
     * Устанавливает статус выполнения как ошибка
     * @param string $error_message
     * @return void
     */
    public function set_error($error_message) {
        $this->log_entry['Execution_Status'] = 'error';
        $this->log_entry['Error_Message'] = $error_message;
        $this->log_entry['Execution_Duration'] = round(microtime(true) - $this->start_time, 4);
    }

    /**
     * Предупреждения при успешном выполнении (например, ошибки SQL по пути): статус не меняется,
     * текст пишется в Error_Message с пометкой
     * @param string[] $warnings
     * @return void
     */
    public function set_warnings(array $warnings) {
        if ($warnings) {
            $this->log_entry['Error_Message'] = trim(
                $this->log_entry['Error_Message'] . "\nWarnings:\n" . implode("\n", $warnings)
            );
        }
    }

    /**
     * Сообщение об ошибке, записанное обработчиком (set_error()/set_skipped()), или пустая строка
     * @return string
     */
    public function get_error_message() {
        return (string)nc_array_value($this->log_entry, 'Error_Message', '');
    }

    /**
     * Устанавливает статус выполнения как пропущено
     * @param string $reason
     * @return void
     */
    public function set_skipped($reason = '') {
        $this->log_entry['Execution_Status'] = 'skipped';
        $this->log_entry['Error_Message'] = $reason;
        $this->log_entry['Execution_Duration'] = round(microtime(true) - $this->start_time, 4);
    }

    /**
     * Сохраняет запись в базу данных
     * @return bool
     */
    public function save() {
        try {
            // Проверяем существование таблицы
            if (!$this->table_exists()) {
                return false;
            }

            $table = nc_db_table::make(self::TABLE_NAME);
            return (bool)$table->insert($this->log_entry);
        } catch (Exception $e) {
            trigger_error("Content changer logger error: " . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Проверяет существование таблицы логов
     * @return bool
     */
    private function table_exists() {
        $result = nc_db()->get_var("SHOW TABLES LIKE '" . self::TABLE_NAME . "'");
        return !empty($result);
    }

    /**
     * Статическая проверка существования таблицы
     * @return bool
     */
    private static function table_exists_static() {
        $result = nc_db()->get_var("SHOW TABLES LIKE '" . self::TABLE_NAME . "'");
        return !empty($result);
    }

    /**
     * Логирует поиск сущностей с детализацией
     * @param string $search_type Тип поиска (infoblock, object, etc.)
     * @param mixed $search_params Параметры поиска
     * @param array $found_results Найденные результаты
     * @return void
     */
    public function log_search($search_type, $search_params, $found_results) {
        $criteria = array(
            'type' => $search_type,
            'params' => $search_params
        );
        
        $this->set_search_criteria(json_encode($criteria, JSON_UNESCAPED_UNICODE));
        
        if (is_array($found_results)) {
            $entities = array();
            foreach ($found_results as $id => $data) {
                if (is_array($data)) {
                    $entities[] = array('id' => $id, 'data' => $data);
                } else {
                    $entities[] = array('id' => $data);
                }
            }
            $this->set_found_entities($entities);
        } else {
            $this->set_found_entities($found_results);
        }
    }

}
