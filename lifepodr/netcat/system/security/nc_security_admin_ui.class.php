<?php

class nc_security_admin_ui extends ui_config {

    public $headerText = SECTION_SECTIONS_OPTIONS_SECURITY;

    public $tabs = array(
        array('id' => 'filter', 'caption' => NETCAT_SECURITY_SETTINGS_INPUT_FILTER, 'location' => 'security.filter.settings(0)'),
        array('id' => 'auth', 'caption' => NETCAT_SECURITY_SETTINGS_AUTH, 'location' => 'security.auth.captcha'),
    );

    protected $toolbar_data = array(
        'filter' => array(
            array('id' => 'filter_settings', 'caption' => NETCAT_SECURITY_SETTINGS_INPUT_FILTER, 'location' => 'security.filter.settings(0)', 'group' => 'grp1'),
            array('id' => 'filter_log', 'caption' => NETCAT_SECURITY_LOG, 'location' => 'security.filter.log', 'group' => 'grp1'),
        ),
    );

    public $activeToolbarButtons = array('filter_settings');

    public $activeTab = 'filter_settings';

    public function __construct($config_array = array()) {
        parent::__construct($config_array);

        if (nc_core::get_object()->get_edition_name() !== 'Standard') {
            $this->toolbar_data['auth'] = array(
                array('id' => 'auth_captcha', 'caption' => NETCAT_SECURITY_SETTINGS_AUTH_CAPTCHA, 'location' => 'security.auth.captcha', 'group' => 'grp1'),
                array('id' => 'auth_2fa', 'caption' => NETCAT_SECURITY_SETTINGS_2FA, 'location' => 'security.auth.2fa', 'group' => 'grp1'),
            );
        }
    }


    /**
     * @param string $type первая часть пути после security → security.filter; они же в $tabs
     * @param string $action // вторая часть пути → security.filter.settings; данные в $toolbar_data
     * @param string $params
     * @return void
     */
    public function set_action($type, $action, $params = null) {
        if (!empty($params)) {
            $this->locationHash = "security.$type.$action($params)";
        } else {
            $this->locationHash = "security.$type.$action";
        }

        $this->activeTab = $type;

        if (isset($this->toolbar_data[$type])) {
            $this->toolbar = $this->toolbar_data[$type];
            $this->activeToolbarButtons = array($type . '_' . $action);
        }
    }
}