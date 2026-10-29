<?php

class nc_backup_component_comparator {

    protected $new_data;
    protected $dumper;

    public static function get_new_keyword(nc_backup_dumper $dumper, array $new_data) {
        $comparator = new self($dumper, $new_data);
        return $comparator->find_same();
    }

    protected function __construct(nc_backup_dumper $dumper, array $new_data) {
        $this->dumper = $dumper;
        $this->new_data = $new_data;
    }

    protected function find_same() {
        if (empty($this->new_data['Keyword'])) {
            return false; // Шаблон без ключевого слова будет добавлен как новый
        }

        return $this->new_data['ClassTemplate']
                ? $this->get_new_template_keyword()
                : $this->get_new_component_keyword();
    }

    protected function get_keyword_without_version() {
        return preg_replace('/\.v\d+$/', '', $this->new_data['Keyword']);
    }

    protected function get_component_versions() {
        $db = nc_db();

        $keyword = $this->get_keyword_without_version();

        $version_ids = (array)$db->get_results(
            "SELECT *, 
                    REPLACE(REPLACE(`Keyword`, 'netcat_base_text', ''), '.v', '') AS _Version
               FROM `Class`
              WHERE `Keyword` = '$keyword'
                 OR `Keyword` LIKE '$keyword.v%'
              ORDER BY `Keyword`",
            ARRAY_A,
            'Class_ID'
        );

        return $version_ids;
    }

    protected function get_dump_table_data($table) {
        // @todo caching, based on the main dump file name (?) and $table
        $result = array();

        $pk = $table . '_ID';
        $dump_data = $this->dumper->get_dump_info('data', $table);
        $file_names = (array)nc_array_value($dump_data, 'files');

        foreach ($file_names as $file_name) {
            $file_path = $this->dumper->get_dump_path($file_name);
            $data = $this->dumper->read_data($file_path);

            foreach ($data as $row) {
                $result[$row[$pk]] = $row;
            }
        }

        return $result;
    }

    protected function get_existing_component_fields($component_id) {
        $db = nc_db();
        $fields = $db->get_col(
            "SELECT CONCAT_WS(',', `Field_Name`, `TypeOfData_ID`, `NotNull`)
               FROM `Field`
              WHERE `Class_ID` = $component_id
              ORDER BY `Field_Name`"
        );
        return implode(';', $fields);
    }

    protected function get_new_component_keyword() {
        $existing_component_ids = $this->get_component_versions();

        if (!$existing_component_ids) {
            return false;
        }

        $dump_component_fields = $this->get_dump_component_fields();

        // Сравниваем набор полей
        foreach ($existing_component_ids as $component_id => $component_data) {
            $existing_component_fields = $this->get_existing_component_fields($component_id);
            if ($existing_component_fields === $dump_component_fields) {
                return $component_data['Keyword'];
            }
        }

        return false;
    }

    protected function get_dump_component_fields() {
        $fields = $this->get_dump_table_data('Field');
        $result = array();
        foreach ($fields as $field) {
            if ($field['Class_ID'] == $this->new_data['Class_ID']) {
                $result[] = "$field[Field_Name],$field[TypeOfData_ID],$field[NotNull]";
            }
        }
        return implode(';', $result);
    }

    protected function get_new_template_keyword() {

    }

}
