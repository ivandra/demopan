<?php

abstract class nc_content_changer {

    /**
     * @var nc_content_changer_instruction
     */
    protected $instruction;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $tmp_folder = "init_content";

    /**
     * @var array
     */
    private $infoblocks_cache = array();

    /**
     * @var nc_content_changer_logger
     */
    protected $logger;


    /**
     * @param nc_content_changer_instruction $instruction
     */
    public function __construct(nc_content_changer_instruction $instruction) {
        $this->action = $instruction->get_action();
        $this->instruction = $instruction;
    }

    /**
     * @param nc_content_changer_logger $logger
     * @return void
     */
    public function set_logger(nc_content_changer_logger $logger) {
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    abstract protected function validate();

    /**
     * @return mixed Результат выполнения операции
     */
    public function apply() {
        if (!$this->validate()) {
            return false;
        }

        $method = "do_" . $this->action;

        if (method_exists($this, $method)) {
            if ($this->check_site_id()) {
                return $this->$method();
            }
            return false;
        } else {
            trigger_error("Unknown action: $this->action", E_USER_WARNING);
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function check_site_id() {
        $instruction_site_id = $this->instruction->get_meta('site_id');
        if ($instruction_site_id) {
            $nc_core = nc_core::get_object();
            if ($nc_core->catalogue->get_current('Catalogue_ID') != $instruction_site_id) {
                if (!$nc_core->catalogue->set_current_by_id($instruction_site_id)) {
                    trigger_error("Site with ID = $instruction_site_id doesn't  exist", E_USER_WARNING);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function resolve_dependencies() {
        if (!$this->instruction->has_dependencies()) {
            return true;
        }

        $resolved = true;
        $current_instruction = $this->instruction;
        $dependencies = $this->instruction->get_dependencies();

        foreach ($dependencies as $dependency) {
            $method = "do_" . $dependency->get_action();

            if (method_exists($this, $method)) {
                try {
                    $this->instruction = $dependency;
                    $resolved = $this->$method();
                    $this->instruction = $current_instruction;
                    array_shift($dependencies);
                    $this->instruction->set_dependencies($dependencies);
                } catch (Exception $e) {
                    $resolved = false;
                    $this->instruction = $current_instruction;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param string|nc_content_changer_infoblock_finder|null $keyword_or_finder
     *
     * @return nc_db_table
     */
    protected function get_infoblock_table_by_keyword($keyword_or_finder = null) {
        if (is_string($keyword_or_finder) && $keyword_or_finder !== "") {
            $sub_class_table = nc_db_table::make("Sub_Class")
                ->where("EnglishName", $keyword_or_finder)
                ->where_in("Catalogue_ID", array(0, nc_core::get_object()->catalogue->id()));
            
            // Логируем поиск по ключевому слову
            if ($this->logger) {
                $search_params = array(
                    'EnglishName' => $keyword_or_finder,
                    'Catalogue_ID' => array(0, nc_core::get_object()->catalogue->id())
                );
                $this->log_search_with_results('infoblock_by_keyword', $search_params, $sub_class_table);
            }
        } else if ($keyword_or_finder instanceof nc_content_changer_infoblock_finder) {
            $sub_class_table = $keyword_or_finder->get_table();
            
            // Логируем поиск через finder
            if ($this->logger) {
                $this->log_search_with_results('infoblock_by_finder', 'nc_content_changer_infoblock_finder', $sub_class_table);
            }
        } else if ($this->instruction->get_infoblock_finder()) {
            $sub_class_table = $this->instruction->get_infoblock_finder()->get_table();
            
            // Логируем поиск через instruction finder
            if ($this->logger) {
                $this->log_search_with_results('infoblock_by_instruction_finder', 'from_instruction', $sub_class_table);
            }
        } else {
            $sub_class_table = nc_db_table::make("Sub_Class");
        }

        if ($this->instruction->get_component_keyword()) {
            $sub_class_table->where("Class_ID", $this->get_component_id_by_keyword());
        }

        return $sub_class_table;
    }

    /**
     * @param string|null $keyword
     *
     * @return null|nc_db_table
     */
    protected function get_subdivision_table_by_keyword($keyword = null) {
        return nc_db_table::make("Subdivision")
                          ->where("Catalogue_ID", nc_core::get_object()->catalogue->id())
                          ->where("EnglishName", $keyword ?: $this->instruction->get_subdivision_keyword());
    }

    /**
     * @return int
     */
    protected function get_component_id_by_keyword() {
        $keyword = $this->instruction->get_component_keyword();

        return (int)nc_db()->get_var("SELECT `Class_ID` FROM `Class` WHERE `Keyword` = '$keyword'") ?: -1;
    }

    /**
     * @param string|null $keyword
     *
     * @return array
     */
    protected function get_infoblock_ids($keyword = null) {
        $keyword = $keyword ?: $this->instruction->get_infoblock_finder();

        $cache_key = md5(serialize($keyword));

        if (isset($this->infoblocks_cache[$cache_key])) {
            return $this->infoblocks_cache[$cache_key];
        }

        $this->infoblocks_cache[$cache_key] = $this->get_infoblock_table_by_keyword($keyword)->get_list("Sub_Class_ID");

        return $this->infoblocks_cache[$cache_key];
    }

    /**
     * @return string
     */
    protected function get_tmp_directory() {
        $nc_core = nc_core::get_object();
        $dir = $nc_core->TMP_FOLDER . $this->tmp_folder;

        if (!is_dir($dir)) {
            mkdir($dir, $nc_core->DIRCHMOD, true);
        }

        return realpath($dir);
    }

    /**
     * @param $path
     *
     * @return string
     */
    protected function translate_tmp_directory_to_relative($path) {
        return str_replace(realpath(nc_core::get_object()->DOCUMENT_ROOT), "", realpath($path));
    }

    /**
     * @return void
     */
    protected function delete_tmp_directory() {
        nc_delete_dir(nc_core::get_object()->TMP_FOLDER . $this->tmp_folder);
    }

    /**
     * Логирует поиск с результатами
     * @param string $search_type
     * @param mixed $search_params
     * @param nc_db_table $table
     * @return void
     */
    protected function log_search_with_results($search_type, $search_params, $table) {
        if (!$this->logger) {
            return;
        }

        try {
            // Создаем копию таблицы для логирования, чтобы не влиять на основную логику
            $table_copy = clone $table;
            
            // Получаем результаты поиска для логирования
            $results = $table_copy->get_list("Sub_Class_ID", "Sub_Class_Name");
            
            // Логируем количество найденных результатов
            $count = count($results);
            if ($count > 1) {
                // Предупреждение о множественных результатах
                $warning = "Found multiple infoblocks ($count) for search criteria. This may cause incorrect content changes.";
                trigger_error($warning, E_USER_NOTICE);
            }
            
            $this->logger->log_search($search_type, $search_params, $results);
        } catch (Exception $e) {
            // Если не удалось получить результаты, логируем только параметры поиска
            $this->logger->log_search($search_type, $search_params, 'Error getting results: ' . $e->getMessage());
        }
    }

    /**
     * Проверяет, что значение помещается в колонку таблицы целиком.
     *
     * Ядро отключает strict-режим MySQL (SET sql_mode=''), поэтому слишком длинное
     * значение молча обрезается. Для полей с исполняемым PHP-кодом (TemplateSettings)
     * обрезка означает Parse error в eval() и 500 на всём сайте — лучше не писать вовсе.
     *
     * @param string $table
     * @param string $column
     * @param string $value
     * @throws Exception если значение длиннее колонки
     */
    protected function assert_value_fits_column($table, $column, $value) {
        static $max_lengths = array();

        $key = "$table.$column";
        if (!array_key_exists($key, $max_lengths)) {
            $db = nc_core::get_object()->db;
            $max_lengths[$key] = $db->get_var(
                "SELECT CHARACTER_OCTET_LENGTH FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '" . $db->escape($table) . "'
                    AND COLUMN_NAME = '" . $db->escape($column) . "'"
            );

            if (!$max_lengths[$key]) {
                // Не блокируем запись (например, нет доступа к information_schema), но и не молчим
                trigger_error("nc_content_changer: cannot determine max length of {$key}, length check skipped", E_USER_WARNING);
            }
        }

        $max_length = $max_lengths[$key];
        $length = strlen($value);
        if ($max_length && $length > $max_length) {
            throw new Exception(
                "{$table}.{$column} value is too long ({$length} bytes, column limit {$max_length}); not saved"
            );
        }
    }

    /**
     * Останавливает инструкцию, если сохранённое значение настроек повреждено:
     * слияние с пустым набором значений затёрло бы остаток настроек.
     *
     * @param string|null $stored_value
     * @param string $context  например, "Catalogue.TemplateSettings #1"
     * @throws Exception
     */
    protected function assert_stored_settings_not_broken($stored_value, $context) {
        if (nc_a2f::is_stored_value_broken($stored_value)) {
            throw new Exception("{$context}: stored settings are broken (PHP parse error); not saved, restore the value first");
        }
    }

    /**
     * Логирует результат операции
     * @param string $operation
     * @param mixed $result
     * @return void
     */
    protected function log_operation_result($operation, $result) {
        if (!$this->logger) {
            return;
        }

        $result_data = array(
            'operation' => $operation,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        );

        $this->logger->set_execution_result($result_data);
    }
}
