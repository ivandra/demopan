<?php

/**
 * Конфигурация UI для модуля внешних скриптов
 */
class ui_config_module_external_scripts extends ui_config_module {

    public function __construct($phase = 'index', $site_id = 0, $snippet_id = 0, $type_id = 0, $type_name = '', $provider_id = 0) {
        $nc_core = nc_core::get_object();

        $this->treeMode = 'modules';

        $module_settings = $nc_core->modules->get_by_keyword('external_scripts');
        $this->treeSelectedNode = 'module-' . $module_settings['Module_ID'];

        // Основной заголовок всегда - название модуля
        $this->headerText = NETCAT_MODULE_EXTSCRIPTS;

        $provider_phases = ['providers', 'provider_add', 'provider_edit'];
        $is_provider_phase = in_array($phase, $provider_phases, true);

        // Вкладки: «Сниппеты» (по сайтам) / «Авто-блокировка карт и видео»
        // (общий для всех сайтов реестр доменов, см. nc_consent::providers()).
        $this->tabs[] = [
            'id' => 'snippets',
            'caption' => NETCAT_MODULE_EXTSCRIPTS_TAB_SNIPPETS,
            'location' => "module.external_scripts($site_id)",
        ];
        $this->tabs[] = [
            'id' => 'providers',
            'caption' => NETCAT_MODULE_EXTSCRIPTS_TAB_PROVIDERS,
            'location' => 'module.external_scripts.providers',
        ];
        $this->activeTab = $is_provider_phase ? 'providers' : 'snippets';

        // Подзаголовок зависит от фазы
        if ($phase === 'index') {
            if ($site_id) {
                $site_name = $nc_core->catalogue->get_by_id($site_id, 'Catalogue_Name');
                $this->subheaderText = $site_name;
                $this->locationHash = "module.external_scripts.site($site_id)";
            } else {
                $this->locationHash = "module.external_scripts";
            }
        } elseif ($phase === 'add') {
            $this->subheaderText = NETCAT_MODULE_EXTSCRIPTS_ADMIN_ADD_TITLE;
            if ($type_name) {
                $this->subheaderText .= ': ' . $type_name;
            }
            $this->locationHash = $type_id
                ? "module.external_scripts.add.type($site_id,$type_id)"
                : "module.external_scripts.add($site_id)";
        } elseif ($phase === 'edit') {
            $this->subheaderText = NETCAT_MODULE_EXTSCRIPTS_ADMIN_EDIT_TITLE;
            if ($type_name) {
                $this->subheaderText .= ': ' . $type_name;
            }
            $this->locationHash = "module.external_scripts.edit($site_id,$snippet_id)";
        } elseif ($phase === 'providers') {
            $this->subheaderText = NETCAT_MODULE_EXTSCRIPTS_TAB_PROVIDERS;
            $this->locationHash = 'module.external_scripts.providers';
        } elseif ($phase === 'provider_add') {
            $this->subheaderText = NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD_TITLE;
            $this->locationHash = 'module.external_scripts.providers.add';
        } elseif ($phase === 'provider_edit') {
            $this->subheaderText = NETCAT_MODULE_EXTSCRIPTS_PROVIDER_EDIT_TITLE;
            $this->locationHash = "module.external_scripts.providers.edit($provider_id)";
        }

        // Кнопки в зависимости от фазы
        if ($phase === 'index' && $site_id) {
            // Кнопка добавления слева
            $this->actionButtons[] = [
                'id' => 'add_script',
                'caption' => NETCAT_MODULE_EXTSCRIPTS_ADD,
                'location' => "#module.external_scripts.add($site_id)",
                'align' => 'left',
            ];
        }

        if ($phase === 'providers') {
            $this->actionButtons[] = [
                'id' => 'add_provider',
                'caption' => NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD,
                'location' => '#module.external_scripts.providers.add',
                'align' => 'left',
            ];
        }

        if ($phase === 'add' || $phase === 'edit' || $phase === 'provider_add' || $phase === 'provider_edit') {
            // Кнопка назад слева
            $this->actionButtons[] = [
                'id' => 'history_back',
                'caption' => TOOLS_REDIRECT_BACK,
                'action' => 'history.back(1)',
                'align' => 'left',
            ];

            // Кнопка сохранения справа - только если тип выбран (есть форма)
            if ($phase === 'edit' || ($phase === 'add' && $type_id) || $phase === 'provider_add' || $phase === 'provider_edit') {
                $this->actionButtons[] = [
                    'id' => 'submit',
                    'caption' => NETCAT_MODULE_EXTSCRIPTS_SAVE,
                    'action' => 'mainView.submitIframeForm()',
                    'align' => 'right',
                ];
            }
        }
    }
}
