<?php
class nc_mixin_file_controller extends nc_ui_controller {

    protected array $disallowed_extensions = ['php'];

    /**
     *
     */
    protected function action_save() {
        $nc_core = nc_core::get_object();

        $scope = $nc_core->input->fetch_get_post('scope');
        $infoblock_id = (int)$nc_core->input->fetch_get_post('infoblock_id');
        $mixin_keyword = $nc_core->input->fetch_get_post('mixin_keyword');

        if (!preg_match('/^\w+$/', $scope) || !preg_match('/^\w+$/', $mixin_keyword)) {
            $this->send_error('Incorrect data scope or mixin keyword');
        }

        global $perm;
        if (!$perm->isSubClassAdmin($infoblock_id)) {
            $this->send_error('Insufficient permissions');
        }

        // путь к загружаемому файлу
        $mixin_file_path = 'mixin/' . $scope . '/' . $infoblock_id . '/' . $mixin_keyword . '/';
        $upload_dir = $nc_core->FILES_FOLDER . $mixin_file_path;

        $uploaded_file = $nc_core->input->fetch_files('file');

        $local_file = $nc_core->input->fetch_post('local_file');
        $local_file_absolute_path = $nc_core->security->get_absolute_tmp_folder_file_path($local_file);
        if (!$local_file_absolute_path) {
            $local_file = null;
        }

        if (!$uploaded_file && !$local_file) {
            die(nc_array_json(['result' => 'ok']));
        }

        $use_local_file = !$uploaded_file && $local_file;

        if (!is_dir($upload_dir) && !mkdir($upload_dir, $nc_core->DIRCHMOD, true) && !is_dir($upload_dir)) {
            $this->send_error('Unable to use target directory');
        }

        $file_name = basename($use_local_file ? $local_file : $uploaded_file['name']);

        $mixin_file_name = nc_get_filename_for_original_fs($file_name, $upload_dir);
        $mixin_file_absolute_path = $upload_dir . $mixin_file_name;

        $extension = strtolower(pathinfo($mixin_file_name, PATHINFO_EXTENSION));
        if (in_array($extension, $this->disallowed_extensions, true)) {
            if ($local_file_absolute_path) {
                unlink($local_file_absolute_path);
            }
            $this->send_error('Incorrect file type');
        }

        $saved_successfully =
            ($uploaded_file && move_uploaded_file($uploaded_file['tmp_name'], $mixin_file_absolute_path)) ||
            ($use_local_file && rename($local_file_absolute_path, $mixin_file_absolute_path));

        if (!$saved_successfully || !file_exists($mixin_file_absolute_path)) {
            $this->send_error('Unable to move uploaded file'
            ."-- $local_file_absolute_path to $mixin_file_absolute_path"
            );
        }

        if (strtolower(pathinfo($mixin_file_name, PATHINFO_EXTENSION)) === 'svg') {
            nc_sanitize_svg_file($mixin_file_absolute_path);
        }

        $result = [
            'result' => 'ok',
            'url' => $nc_core->SUB_FOLDER . $nc_core->HTTP_FILES_PATH . $mixin_file_path . $mixin_file_name,
        ];

        die(nc_array_json($result));
        exit;
    }

    protected function send_error($message) {
        echo nc_array_json(['result' => 'error', 'message' => $message]);
        exit;
    }
}