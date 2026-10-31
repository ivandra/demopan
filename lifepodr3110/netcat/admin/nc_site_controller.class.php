<?php

class nc_site_controller extends nc_ui_controller {

    protected $use_layout = true;

    /**
     * @param $result
     *
     * @return string
     */
    protected function after_action($result) {
        if (!$this->use_layout) {
            return $result;
        }

        BeginHtml('', '', '');
        echo $result;
        EndHtml();
        return '';
    }

    /**
     * @return nc_ui_view
     */
    protected function action_show_add_form() {
        $this->check_permissions(NC_PERM_ITEM_SITE, NC_PERM_ACTION_ADD, 0, false);
        $nc_core = nc_core::get_object();

        if (!$nc_core->catalogue->can_create()) {
            $this->ui_config = new ui_config(
                array(
                    'treeMode' => 'sitemap',
                    'headerText' => CONTROL_CONTENT_CATALOUGE_FUNCS_SHOWCATALOGUELIST_ADDSITE,
                    'actionButtons' => array(),
                )
            );

            return $this->view('error_message')->with('message', CONTROL_CONTENT_CATALOUGE_ERROR_CANNOT_CREATE_MORE);
        }

        $this->ui_config = new ui_config(
            array(
                'treeMode' => 'sitemap',
                'headerText' => CONTROL_CONTENT_CATALOUGE_FUNCS_SHOWCATALOGUELIST_ADDSITE,
                'actionButtons' => array(
                    array(
                        "id" => "submit",
                        "caption" => CONTROL_CONTENT_CATALOUGE_FUNCS_SHOWCATALOGUELIST_ADDSITE,
                        "action" => "mainView.submitIframeForm()",
                        "align" => "right",
                    ),
                ),
            )
        );

        // Данные о сайтах с netcat.ru
        try {
            $data = nc_store_site::get_list();
        } catch (nc_store_exception $e) {
            $data = array('error_text' => $e->getMessage());
        }

        return $this->view(
            'site/add',
            array(
                'domain' => $nc_core->catalogue->get_current() ? '' : $_SERVER['HTTP_HOST'],
                'data' => $data,
            )
        );
    }

    /**
     * @param $site_id
     */
    protected function redirect_to_site_properties($site_id) {
        $nc_core = nc_core::get_object();
        $redirect_url = "{$nc_core->ADMIN_PATH}catalogue/index.php?phase=2&CatalogueID=$site_id&action=edit";
        echo "<script>window.location = '$redirect_url';</script>";
    }

    /**
     * @return nc_ui_view
     * @throws Exception
     */
    protected function action_create() {
        $this->check_permissions(NC_PERM_ITEM_SITE, NC_PERM_ACTION_ADD, 0, true);
        $nc_core = nc_core::get_object();

        /** @TODO: проверка доменного имени */
        $site_type = $nc_core->input->fetch_post('site_type');

        if ($site_type === 'blank') {
            $properties = (array)$nc_core->input->fetch_post('data');
            $created_site_id = $nc_core->catalogue->create($properties);
            $site_properties = $nc_core->catalogue->get_by_id($created_site_id);

            $this->print_new_site_ui_config($site_properties);
            $this->redirect_to_site_properties($created_site_id);
        } else if ($site_type == 'store_site') {
            if (!$this->input->fetch_post("site_id")) {
                $this->ui_config = new ui_config(
                    array(
                        'treeMode' => 'sitemap',
                        'headerText' => CONTROL_CONTENT_CATALOUGE_FUNCS_SHOWCATALOGUELIST_ADDSITE,
                        'actionButtons' => array(
                            array(
                                "id" => "history_back",
                                "caption" => TOOLS_REDIRECT_BACK,
                                "action" => "history.back(1)",
                                "align" => "left",
                                "red_border" => true,
                            ),
                        ),
                    )
                );

                return $this->view('error_message')
                            ->with('message', CONTROL_CONTENT_SITE_ADD_SITE_ID_IS_EMPTY_ERROR);
            }

            $hidden_inputs = array();

            foreach ($nc_core->input->fetch_post() as $k => $v) {
                if (is_array($v)) {
                    continue;
                }

                $hidden_inputs[$k] = $v;
            }

            foreach ((array)$nc_core->input->fetch_post('data') as $k => $v) {
                $hidden_inputs["data[$k]"] = $v;
            }

            $hidden_inputs['action'] = 'download_and_import';

            return $this->view(
                'site/add_wait',
                array(
                    'hidden_inputs' => $hidden_inputs,
                )
            );
        } else if ($site_type == 'ai_site') {
            $this->ui_config = new ui_config();

            return $this->view(
                'site/add_ai_site',
                array(
                    'payload' => $this->input->fetch_post_get(),
                )
            );
        } else {
            return $this->action_show_add_form();
        }

        return $this->action_show_add_form();
    }

    /**
     *
     * @throws Exception
     */
    protected function action_download_and_import() {
        $this->check_permissions(NC_PERM_ITEM_SITE, NC_PERM_ACTION_ADD, 0, true);
        $nc_core = nc_core::get_object();

        session_write_close();
        @set_time_limit(0);

        if ($this->input->fetch_post("site_type") !== "ai_site") {
            @ignore_user_abort(true);
        }

        $site_id = (int)$nc_core->input->fetch_post('site_id');
        $is_ai_site_generation = $nc_core->input->fetch_post('site_type') == "ai_site";

        if ($is_ai_site_generation) {
            $this->use_layout = false;
            $site_id = AI_GENERATION_SITE_TEMPLATE_ID;
        }

        try {
            $tmp_file_name = nc_store_site::download_by_id($site_id);
        } catch (nc_store_exception $e) {
            return $this->view('error_message.view.php')->with('message', $e->getMessage());
        }

        if ($is_ai_site_generation) {
            try {
                $site_id = $this->import_site_and_update_site_fields($tmp_file_name);
                $site = nc_core::get_object()->catalogue->get_by_id($site_id);

                echo $this->json_response(
                    array(
                        'status' => 'success',
                        'data' => array(
                            'site_id' => $site_id,
                            'domain' => $site['Domain'],
                        ),
                    )
                );
            } catch (Exception $e) {
                echo $this->json_error($e->getMessage());
            }

            exit();
        } else {
            $created_site_id = $this->import_site_and_update_site_fields($tmp_file_name);
        }

        $site_properties = $nc_core->catalogue->get_by_id($created_site_id);
        $this->print_new_site_ui_config($site_properties);
        $this->redirect_to_site_properties($created_site_id);

        return '';
    }

    /**
     * @throws Exception
     */
    protected function action_complete_ai_site_importing() {
        $this->check_permissions(NC_PERM_ITEM_SITE, NC_PERM_ACTION_ADD, 0, true);

        $created_site_id = $this->input->fetch_get('site_id');
        $site_properties = nc_core::get_object()->catalogue->get_by_id($created_site_id);

        $this->use_layout = false;

        $this->print_new_site_ui_config($site_properties);
        $this->redirect_to_site_properties($created_site_id);

        return '';
    }

    /**
     * @param string $tmp_file_name
     *
     * @return int
     * @throws Exception
     */
    private function import_site_and_update_site_fields($tmp_file_name) {
        $nc_core = nc_core::get_object();

        /** @var nc_backup_result $result */
        $result = $nc_core->backup->import(
            $tmp_file_name,
            array(
                'save_ids' => false,
            )
        );

        $created_site_id = $result->get_new_id();
        unlink($tmp_file_name);

        // Установка названия сайта, домена, если они были заданы
        $data = (array)$nc_core->input->fetch_post('data');
        $data = array_filter($data, 'strlen');

        if ($data) {
            nc_db_table::make('Catalogue')->where_id($created_site_id)->update($data);
        }

        return $created_site_id;
    }

    /**
     * @param array $site_properties
     */
    protected function print_new_site_ui_config(array $site_properties) {
        $site_name = $site_properties['Catalogue_Name'];
        $site_href = "#site.map($site_properties[Catalogue_ID])";

        $site_scheme = nc_get_scheme();
        $site_domain = $_SERVER['HTTP_HOST'];

        if ($site_properties['Domain']) {
            $site_scheme = nc_Core::get_object()->catalogue->get_scheme_by_id($site_properties['Catalogue_ID']);
            $site_domain = $site_properties['Domain'];
        }

        $UI_CONFIG = new ui_config(
            array(
                'treeChanges' => array(
                    'addNode' => array(
                        array(
                            'nodeId' => "site-$site_properties[Catalogue_ID]",
                            'sprite' => 'nc-icon nc--site',
                            'name' => "$site_properties[Catalogue_ID]. $site_name",
                            'href' => $site_href,
                            'hasChildren' => true,
                            'acceptDropFn' => 'treeSitemapAcceptDrop',
                            'onDropFn' => 'treeSitemapOnDrop',
                            'dragEnabled' => true,
                            'buttons' => array(
                                array(
                                    "label" => CONTROL_CONTENT_CATALOUGE_FUNCS_SHOWMENU_A_VIEW,
                                    "action" => "window.open('{$site_scheme}://{$site_domain}');",
                                    "icon" => "arrow-right",
                                    "sprite" => true,
                                ),
                                array(
                                    "label" => CONTROL_CONTENT_SUBDIVISION_FUNCS_MAINDATA_A_ADDSUBSECTION,
                                    "action" => "parent.location.hash = 'subdivision.add(0,$site_properties[Catalogue_ID])'",
                                    "icon" => "folder-add",
                                    "sprite" => true,
                                ),
                            ),
                        ),
                    ),
                ),
                'addNavBarCatalogue' => array(
                    'name' => $site_name,
                    'href' => $site_href,
                ),
            )
        );

        echo $UI_CONFIG->to_json();
    }

}
