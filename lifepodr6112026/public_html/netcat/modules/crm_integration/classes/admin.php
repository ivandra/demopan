<?php

class nc_crm_integration_admin {

    public function __construct() {
    }

    /**
     * URL главной страницы настроек модуля
     *
     * @return string
     */
    public function get_mainsettings_url() {
        return "#module.crm_integration";
    }

}
