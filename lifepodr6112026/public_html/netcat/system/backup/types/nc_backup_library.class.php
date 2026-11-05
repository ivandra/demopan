<?php

require_once __DIR__ . '/nc_backup_site.class.php';

/**
 * Частичный импорт архива сайта (только раздел библиотеки)
 *
 * Особые настройки:
 * target_site_id — сайт, на который будет добавлена библиотека
 */

class nc_backup_library extends nc_backup_site {

    protected $type = 'site';
    protected $site_id;
    protected $site_library_sub_id;
    protected $site_library_path;
    protected $dump_library_path;

    protected $imported_subdivisions = array();
    protected $imported_infoblocks = array();
    protected $imported_objects = array();

    protected function reset() {
        parent::reset();
        $this->site_id = null;
        $this->site_library_sub_id = null;
        $this->site_library_path = null;
        $this->dump_library_path = null;
    }

    protected function export_process() {
        throw new Exception('nc_backup_library cannot be used for export, use nc_backup_site instead');
    }

    protected function import_process() {
        $nc_core = nc_core::get_object();

        $this->site_id = $this->new_id = $this->dumper->get_import_settings('target_site_id');
        $this->dumper->set_dict('Catalogue_ID', $this->dumper->get_dump_info('id'), $this->site_id);

        $this->site_library_sub_id = $nc_core->catalogue->get_or_create_block_library_sub_id($this->site_id);
        $this->site_library_path = $nc_core->subdivision->get_by_id($this->site_library_sub_id, 'Hidden_URL');

        $this->dump_library_path = $this->dumper->get_dump_info('block_library_path') ?: '/library/';

        parent::import_process();
    }

    protected function import_validation() {
        $site_id = $this->dumper->get_import_settings('target_site_id');

        if (!$site_id) {
            $this->set_validation_error(CONTROL_CONTENT_CATALOUGE_FUNCS_ERROR_NOCATALOGUE);
            return false;
        }

        try {
            nc_core::get_object()->catalogue->get_by_id($site_id);
        } catch (Exception $e) {
            $this->set_validation_error(CONTROL_CONTENT_CATALOUGE_FUNCS_ERROR_NOCATALOGUE . " $site_id");
            return false;
        }

        return true;
    }

    protected function trigger_system_event_before_import() {
        nc_core::get_object()->event->execute(nc_event::BEFORE_BLOCK_LIBRARY_IMPORTED, $this->site_id);
    }

    protected function trigger_system_event_after_import() {
        nc_core::get_object()->event->execute(nc_event::AFTER_BLOCK_LIBRARY_IMPORTED, $this->site_id);
    }

    protected function event_before_insert_field($row) {
        if ($row['System_Table_ID']) {
            return false;
        }
        return parent::event_before_insert_field($row);
    }

    protected function event_before_insert_catalogue($row) {
        return false;
    }

    protected function event_before_insert_subdivision($row) {
        if ($row['Hidden_URL'] === $this->dump_library_path) {
            $this->dumper->set_dict('Subdivision_ID', $row['Subdivision_ID'], $this->site_library_sub_id);
            return false;
        }

        if (strpos($row['Hidden_URL'], $this->dump_library_path) !== 0) {
            return false;
        }

        $path = $this->site_library_path . substr($row['Hidden_URL'], strlen($this->dump_library_path));

        $db = nc_core::get_object()->db;
        $existing_subdivision_id = $db->get_var(
            "SELECT `Subdivision_ID`
               FROM `Subdivision`
              WHERE `Catalogue_ID` = $this->site_id 
                AND `Hidden_URL` = '{$db->escape($path)}'"
        );

        if ($existing_subdivision_id) {
            $this->dumper->set_dict('Subdivision_ID', $row['Subdivision_ID'], $existing_subdivision_id);
            return false;
        }

        $row['Hidden_URL'] = $path;

        return $row;
    }

    protected function event_after_insert_subdivision($row, $new_id) {
        $this->imported_subdivisions[$new_id] = 1;
        parent::event_after_insert_subdivision($row, $new_id);
    }

    protected function event_before_insert_sub_class($row) {
        $subdivision_id = $this->dumper->get_dict('Subdivision_ID', $row['Subdivision_ID']);

        if (!isset($this->imported_subdivisions[$subdivision_id])) {
            return false;
        }

        $db = nc_core::get_object()->db;
        $existing_infoblock_id = $db->get_var(
            "SELECT `Sub_Class_ID`
               FROM `Sub_Class`
              WHERE `Subdivision_ID` = $subdivision_id 
                AND `EnglishName` = '{$db->escape($row['EnglishName'])}'"
        );

        if ($existing_infoblock_id) {
            $this->dumper->set_dict('Sub_Class_ID', $row['Sub_Class_ID'], $existing_infoblock_id);
            return false;
        }

        $this->imported_infoblocks[$row['Sub_Class_ID']] = 1;

        return parent::event_before_insert_sub_class($row);
    }

    protected function event_after_insert_sub_class($row, $new_id) {
        $this->imported_infoblocks[$new_id] = 1;
    }

    protected function event_before_insert_message($component_id, $row) {
        $infoblock_id = $this->dumper->get_dict('Sub_Class_ID', $row['Sub_Class_ID']);

        if (!isset($this->imported_infoblocks[$infoblock_id])) {
            return false;
        }

        $db = nc_core::get_object()->db;
        $existing_object_id = $db->get_var(
            "SELECT `Message_ID`
               FROM `Message$component_id`
              WHERE `Sub_Class_ID` = $infoblock_id 
                AND `Keyword` = '{$db->escape($row['Keyword'])}'"
        );

        if ($existing_object_id) {
            $this->dumper->set_dict("Message$component_id", $row['Message_ID'], $existing_object_id);
            return false;
        }

        return parent::event_before_insert_message($component_id, $row);
    }

    protected function event_after_insert_message($component_id, $object_data, $new_object_id, $old_object_id) {
        $this->imported_objects[$component_id][$new_object_id] = 1;
        parent::event_after_insert_message($component_id, $object_data, $new_object_id, $old_object_id);
    }

    protected function event_before_insert_filetable($row) {
        $file_path = explode('/', $row['File_Path']);

        if (!is_numeric($file_path[1])) {
            return false; // не файлы разделов или объектов
        }

        // Файлы разделов: "/123/"
        if (count($file_path) === 3) {
            $new_subdivision_id = $this->dumper->get_dict('Subdivision_ID', $file_path[1]);
            if (!isset($this->imported_subdivisions[$new_subdivision_id])) {
                return false;
            }
        }

        // Файлы объектов: "/123/456/"
        if (count($file_path) === 4 && is_numeric($file_path[2])) {
            $new_component_id = $this->get_component_id_by_field_id($row['Field_ID']);
            $new_object_id = $this->dumper->get_dict("Message{$new_component_id}.Message_ID", $row['Message_ID']);
            if (!isset($this->imported_objects[$new_component_id][$new_object_id])) {
                return false;
            }
        }

        return parent::event_before_insert_filetable($row);
    }

    protected function event_before_insert_multifield($row) {
        $new_component_id = $this->get_component_id_by_field_id($row['Field_ID']);
        $new_object_id = $this->dumper->get_dict("Message{$new_component_id}.Message_ID", $row['Message_ID']);

        if (!isset($this->imported_objects[$new_component_id][$new_object_id])) {
            return false;
        }

        return parent::event_before_insert_multifield($row);
    }

    protected function event_before_insert_sub_class_areacondition_subdivision() {
        return false;
    }

    protected function event_before_insert_sub_class_areacondition_subdivision_exception() {
        return false;
    }

    protected function event_before_insert_sub_class_areacondition_class() {
        return false;
    }

    protected function event_before_insert_sub_class_areacondition_class_exception() {
        return false;
    }

    protected function event_before_insert_sub_class_areacondition_message() {
        return false;
    }

    protected function event_before_insert_filter($row) {
        return false;
    }

    protected function event_before_insert_settings($row) {
        return false;
    }

    protected function event_before_copy_file($path, $file, $src, $meta) {
        if ($this->detect_type_by_path($path) === 'template') {
            return false;
        }
        
        $nc_core = nc_core::get_object();
        $target_path = parent::event_before_copy_file($path, $file, $src, $meta);

        if (!$target_path) {
            return false;
        }

        $full_path = $nc_core->DOCUMENT_ROOT . $nc_core->SUB_FOLDER . $target_path;

        if (file_exists($full_path)) {
            return false;
        }

        return $target_path;
    }

    protected function import_modules() {
        return false;
    }

    protected function update_site_properties() {
        // do not update site properties
    }

}