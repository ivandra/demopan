<?php

class nc_store_site {

    public static function download_by_id($site_id) {
        return nc_store::download('v1/site/get/', array('site_id' => (int)$site_id));
    }

    public static function get_list() {
        try {
            return nc_store::get('v1/site/list/');
        } catch (nc_store_exception $e) {
            throw new nc_store_site_exception(CONTROL_CONTENT_SITE_ADD_DATA_ERROR . " ({$e->getMessage()})");
        }
    }

}