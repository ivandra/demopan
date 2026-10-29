<?php

class nc_store_request {

    protected $base_url;

    public function __construct($base_url) {
        $this->base_url = $base_url;
    }

    protected function get_common_request_parameters() {
        $nc_core = nc_core::get_object();
        $modules = $nc_core->db->get_col("SELECT `Keyword` FROM `Module`");

        return array(
            'copy_id' => $nc_core->get_copy_id(),
            'version' => $nc_core->get_full_version_number(),
            'edition' => $nc_core->get_edition_name(),
            'modules' => join(',', $modules),
            'host' => $_SERVER['HTTP_HOST'],
            'license' => $nc_core->get_settings('ProductNumber'),
            'code' => $nc_core->get_settings('Code'),
            'language' => $nc_core->lang->detect_lang(true),
        );
    }

    public function get($path, array $data = array()) {
        $parameters = $data + $this->get_common_request_parameters();
        $http = new nc_http($this->base_url);
        try {
            return $http->make_get_request($path, $parameters);
        } catch (nc_http_exception $e) {
            $message = $e->getCode() ?: $e->getMessage();
            throw new nc_store_request_exception($message);
        }
    }

    public function download($path, array $data = array(), $file_name_template = 'store/site-%s.tgz') {
        $nc_core = nc_core::get_object();

        $url =
            $this->base_url . $path . '?' .
            http_build_query($data + $this->get_common_request_parameters(), '', '&');

        // На некоторых серверах, особенно на Windows, неправильно сконфигурирована OpenSSL,
        // что приводит к невозможности импорта сайта из store.netcat.ru
        $context_options = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        );

        $source_file_handle = fopen($url, 'rb', false, stream_context_create($context_options));

        if (!$source_file_handle) {
            throw new nc_store_request_exception(CONTROL_CONTENT_SITE_ADD_DOWNLOADING_ERROR);
        }

        $tmp_file_name = $nc_core->TMP_FOLDER . sprintf($file_name_template, uniqid('', true));
        $tmp_file_path = dirname($tmp_file_name);

        if (!file_exists($tmp_file_path)) {
            if (!mkdir($tmp_file_path, $nc_core->DIRCHMOD, true) && !is_dir($tmp_file_path)) {
                throw new nc_store_request_exception(sprintf(NETCAT_DIR_NOT_FOUND, $tmp_file_path));
            }
        }

        $tmp_file_handle = fopen($tmp_file_name, 'wb');

        while (!feof($source_file_handle)) {
            fwrite($tmp_file_handle, fread($source_file_handle, 65535));
        }

        fclose($source_file_handle);
        fclose($tmp_file_handle);

        if (!file_exists($tmp_file_name) || !filesize($tmp_file_name)) {
            throw new nc_store_request_exception(CONTROL_CONTENT_SITE_ADD_DOWNLOADING_ERROR);
        }

        return $tmp_file_name;
    }

}