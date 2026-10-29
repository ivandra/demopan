<?php

class nc_store_block {

    /**
     * @param int $target_site_id
     * @return array
     */
    public static function update_library($target_site_id) {
        $nc_core = nc_core::get_object();

        // (1) Get library site ID
        $library_info = nc_store::get('v1/block_library/info/');

        if (!empty($library_info['error_text'])) {
            throw new nc_store_block_exception($library_info['error_text']);
        }

        // (2) Download library site
        $tmp_file_name = nc_store_site::download_by_id($library_info['site_id']);

        // (3) Import library site
        try {
            $result = $nc_core->backup->import(
                $tmp_file_name,
                array(
                    'target_site_id' => $target_site_id,
                    'type' => 'library',
                    'save_ids' => false,
                )
            );
            unlink($tmp_file_name);
        } catch (Exception $e) {
            throw new nc_store_block_exception($e->getMessage());
        }

        return $result;
    }


}