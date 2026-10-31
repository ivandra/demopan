<?php

class nc_content_changer_site extends nc_content_changer {

    /**
     * @var nc_db_table
     */
    private static $site_table;

    /**
     * @inheritDoc
     */
    public function __construct(nc_content_changer_instruction $instruction) {
        parent::__construct($instruction);
        self::$site_table = nc_db_table::make("Catalogue");
    }


    /**
     * @inheritDoc
     */
    protected function validate() {
        $dataset = $this->instruction->get_dataset();

        return !empty($dataset);
    }

    /**
     * @return bool
     */
    protected function do_update() {
        $dataset = $this->instruction->get_dataset();

        try {
            if (isset($dataset["mixins"])) {
                $this->update_mixins();
            }

            if (isset($dataset['ai_context'])) {
                $this->update_ai_context();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function update_mixins() {
        $dataset = $this->instruction->get_dataset();
        $site_data = $this->get_site_table()->get_row();

        if (!$site_data) {
            return false;
        }

        $current_index_mixins = json_decode($site_data["MainArea_Mixin_Settings"] ?: "[]", true);

        if (empty($current_index_mixins)) {
            $current_index_mixins[""] = array();
        }

        if (isset($dataset["mixins"]["text"])) {
            $this->prepare_text_mixin_to_update($dataset);
        }

        $index_mixin_settings = array_replace_recursive($current_index_mixins, array("" => $dataset["mixins"]));
        $this->get_site_table()->update(array("MainArea_Mixin_Settings" => json_encode($index_mixin_settings, JSON_UNESCAPED_SLASHES)));

        nc_core::get_object()->catalogue->load_all();
        nc_tpl_mixin_cache::generate_site_files($site_data["Catalogue_ID"], false);

        return true;
    }

    /**
     * @return nc_db_table
     */
    private function get_site_table() {
        $site_keyword = $this->instruction->get_site_keyword();

        if ($site_keyword == null) {
            return self::$site_table->where("Catalogue_ID", (int)nc_core::get_object()->catalogue->get_current("Catalogue_ID"));
        }

        return self::$site_table->where(is_numeric($site_keyword) ? "Catalogue_ID" : "Domain", $site_keyword);
    }

    /**
     * @return void
     */
    private function prepare_text_mixin_to_update(array &$dataset) {
        $fonts_map = array();
        $fonts = nc_tpl_font::get_available_fonts();

        foreach ($fonts as $font) {
            $fonts_map[$font["name"]] = $font;
        }

        foreach ($dataset["mixins"]["text"] as $breakpoint => $data) {
            foreach ($data["settings"] as $font_type => $parameter) {
                if (!isset($fonts_map[$parameter["font_family"]])) {
                    continue;
                }

                $dataset["mixins"]["text"][$breakpoint]["settings"][$font_type]["asset"] = nc_array_value(
                    $fonts_map[$parameter["font_family"]],
                    "asset",
                    ""
                );

                if (!isset($dataset["mixins"]["text"][$breakpoint]["settings"][$font_type]["font_family_fallback"])) {
                    $dataset["mixins"]["text"][$breakpoint]["settings"][$font_type]["font_family_fallback"] = nc_array_value(
                        $fonts_map[$parameter["font_family"]],
                        "fallback",
                        ""
                    );
                }
            }
        }
    }

    private function update_ai_context() {
        $this->get_site_table()->update(array("AiContextData" => json_encode($this->instruction->get_dataset()['ai_context'])));
    }

    /**
     * Назначение макета (Template) сайту (Catalogue).
     *
     * Аналог nc_content_changer_subdivision::do_assign_template(), но обновляет
     * Catalogue.Template_ID — макет по умолчанию для всех разделов сайта.
     *
     * Параметры dataset:
     * - template_id (int) — ID макета
     * - template_path (string) — путь макета (например, 'netcat_default/free_landing')
     *
     * @return bool
     */
    protected function do_assign_template() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        $site_data = $this->get_site_table()->get_row();
        if (!$site_data) {
            throw new Exception("Site not found");
        }
        $catalogue_id = (int)$site_data['Catalogue_ID'];

        // Определяем template_id
        $template_id = null;

        if (!empty($dataset['template_id'])) {
            $template_id = (int)$dataset['template_id'];
        } elseif (!empty($dataset['template_path'])) {
            $file_path = '/' . trim($dataset['template_path'], '/') . '/';
            $template_id = nc_db()->get_var(
                "SELECT Template_ID FROM Template
                 WHERE File_Path = '" . nc_db()->escape($file_path) . "'"
            );
        }

        if (!$template_id) {
            throw new Exception("Template not found. Provide template_id or valid template_path");
        }

        $this->get_site_table()->update([
            'Template_ID' => $template_id,
        ]);

        $nc_core->catalogue->load_all();
        $nc_core->template->clear_cache();

        $this->log_operation_result('assign_template', [
            'catalogue_id' => $catalogue_id,
            'template_id' => $template_id,
        ]);

        return true;
    }

    /**
     * Обновление визуальных настроек макета (TemplateSettings) для сайта (Catalogue).
     *
     * Аналог nc_content_changer_subdivision::do_update_template_settings(), но пишет
     * настройки на уровне сайта (Catalogue.TemplateSettings) — они автоматически
     * наследуются всеми разделами через nc_subdivision::get_template_settings().
     * Используется free_multipage-сценарием для записи общесайтового CSS одной инструкцией.
     *
     * Параметры dataset:
     * - settings (array, обязательный) — значения настроек (ключ => значение)
     * - ensure_settings_exist (array) — гарантировать наличие настроек в Template.CustomSettings
     *   Формат: ['имя_настройки' => ['type' => 'textarea', 'caption' => 'Описание']]
     *
     * Пример использования:
     * <pre>
     *   new nc_content_changer_site_instruction('update_template_settings', null, [
     *       'settings' => [
     *           'css' => ':root { --primary-color: #2563eb; }',
     *       ],
     *       'ensure_settings_exist' => [
     *           'css' => ['type' => 'textarea', 'caption' => 'CSS-стили сайта'],
     *       ],
     *   ]);
     * </pre>
     *
     * @return bool
     * @throws Exception
     */
    protected function do_update_template_settings() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        if (empty($dataset['settings']) || !is_array($dataset['settings'])) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: settings (must be non-empty array)");
            }
            return false;
        }

        // Резолвим Catalogue
        $site_data = $this->get_site_table()->get_row();
        if (!$site_data) {
            throw new Exception("Site not found");
        }
        $catalogue_id = (int)$site_data['Catalogue_ID'];
        $template_id = (int)$site_data['Template_ID'];

        if (!$template_id) {
            throw new Exception("Template not found for catalogue: {$catalogue_id}");
        }

        // Гарантируем наличие настроек в CustomSettings шаблона
        if (!empty($dataset['ensure_settings_exist'])) {
            $this->ensure_template_settings_exist($template_id, $dataset['ensure_settings_exist']);
        }

        // Готовим a2f
        $custom_settings = $nc_core->template->get_custom_settings($template_id);
        $a2f = new nc_a2f($custom_settings, 'TemplateSettings');
        $a2f->set_initial_values();

        // Текущие значения из Catalogue
        $current_settings = $site_data['TemplateSettings'] ?? '';
        if ($current_settings) {
            $this->assert_stored_settings_not_broken($current_settings, "Catalogue.TemplateSettings #{$catalogue_id}");
            $a2f->set_value($current_settings, "Catalogue.TemplateSettings #{$catalogue_id}");
        }

        // Применяем новые значения
        $a2f->set_values($dataset['settings']);

        $template_settings = $a2f->get_values_as_string();
        $this->assert_value_fits_column('Catalogue', 'TemplateSettings', $template_settings);

        $this->get_site_table()->update([
            'TemplateSettings' => $template_settings
        ]);

        // Сброс кэша — паттерн из update_mixins()
        $nc_core->catalogue->load_all();
        $nc_core->template->clear_cache();

        $this->log_operation_result('update_template_settings', [
            'catalogue_id' => $catalogue_id,
            'template_id' => $template_id,
            'settings_keys' => array_keys($dataset['settings']),
        ]);

        return true;
    }

    /**
     * Добавляет отсутствующие настройки в Template.CustomSettings.
     * Идентичен методу nc_content_changer_subdivision::ensure_template_settings_exist();
     * дублирование принято осознанно (короткий хелпер, разные сущности-потребители,
     * выделение в общую базу избыточно для двух сайтов использования).
     *
     * @param int $template_id
     * @param array $required_settings
     * @return void
     */
    protected function ensure_template_settings_exist($template_id, array $required_settings) {
        $nc_core = nc_core::get_object();

        $current = $nc_core->template->get_by_id($template_id, 'CustomSettings');
        $current_array = nc_a2f::evaluate($current) ?: [];

        $changed = false;
        foreach ($required_settings as $name => $definition) {
            if (!isset($current_array[$name])) {
                $current_array[$name] = $definition;
                $changed = true;
            }
        }

        if ($changed) {
            $nc_core->template->update($template_id, [
                'CustomSettings' => var_export($current_array, true)
            ]);
            $nc_core->template->clear_cache();
        }
    }

}
