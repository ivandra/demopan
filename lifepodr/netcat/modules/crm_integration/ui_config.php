<?php
/**
 * CRM Integration Module - UI Config
 *
 * @package NetcatCMS
 * @subpackage CrmIntegration
 */

class ui_config_module_crm_integration extends ui_config_module {

    public function __construct($phase = 'index', $site_id = 0, $provider_id = 0) {
        $nc_core = nc_core::get_object();

        parent::__construct('crm_integration', $phase);

        // Remove default tabs (module name and settings)
        $this->tabs = [];

        // Tree mode for left menu navigation
        $this->treeMode = 'modules';

        // Main header
        $this->headerText = NETCAT_MODULE_CRM_INTEGRATION;

        // Set tree node and subheader based on phase
        switch ($phase) {
            case 'index':
            case 'provider_settings':
                $this->treeSelectedNode = 'crm_integration-providers';
                $this->locationHash = 'module.crm_integration';
                if ($phase === 'provider_settings' && $provider_id) {
                    $provider = nc_crm_integration_factory::create($provider_id, $site_id);
                    $provider_name = $provider ? $provider->get_name() : '';
                    $this->subheaderText = NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_SETTINGS . ($provider_name ? ": $provider_name" : '');
                    $this->locationHash = "module.crm_integration.provider($site_id,$provider_id)";
                } else {
                    $this->subheaderText = NETCAT_MODULE_CRM_INTEGRATION_PROVIDERS;
                }
                break;

            case 'sources':
                $this->treeSelectedNode = 'crm_integration-sources';
                $this->subheaderText = NETCAT_MODULE_CRM_INTEGRATION_SOURCES;
                $this->locationHash = "module.crm_integration.sources($site_id)";
                break;

            case 'log':
                $this->treeSelectedNode = 'crm_integration-log';
                $this->subheaderText = NETCAT_MODULE_CRM_INTEGRATION_LOG;
                $this->locationHash = "module.crm_integration.log($site_id)";
                break;
        }

        // Back button for inner pages
        if ($phase === 'provider_settings') {
            $this->actionButtons[] = [
                'id' => 'back',
                'caption' => TOOLS_REDIRECT_BACK,
                'location' => "#module.crm_integration",
                'align' => 'left',
            ];
        }

        // Save button for settings pages
        if ($phase === 'provider_settings') {
            $this->actionButtons[] = [
                'id' => 'submit',
                'caption' => CONTROL_CONTENT_CATALOUGE_FUNCS_CATALOGUEFORM_SAVE,
                'action' => 'mainView.submitIframeForm()',
                'align' => 'right',
            ];
        }
    }
}
