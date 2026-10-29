<?php

/**
 * Handler инструкции external_script: создаёт/обновляет/удаляет сниппет модуля
 * «Внешние скрипты» через штатный API nc_external_scripts_snippet.
 *
 * apply() базового класса уже вызвал validate() и выставил текущий сайт из
 * meta['site_id'] (check_site_id()) до do_*.
 *
 * Идемпотентность: dataset['snippet_key'] кладётся в Settings._preserve_key;
 * find_existing() ищет по нему уже созданный сниппет и делает UPDATE вместо INSERT
 * (повтор деплоя не плодит дубли).
 */
class nc_content_changer_external_script extends nc_content_changer {

    /**
     * Служебный ключ идемпотентности внутри Settings JSON.
     * ВАЖНО: не для показа в UI — по нему повторный деплой находит и обновляет сниппет.
     */
    const PRESERVE_KEY = "_preserve_key";

    /**
     * create/update требуют type; delete проверит цель сам.
     * @return bool
     */
    protected function validate() {
        if (in_array($this->action, array("create", "update"), true)) {
            $dataset = $this->instruction->get_dataset();
            return !empty($dataset["type"]);
        }
        return true;
    }

    /**
     * @return int|false Snippet_ID при успехе
     */
    protected function do_create() {
        return $this->upsert();
    }

    /**
     * @return int|false Snippet_ID при успехе
     */
    protected function do_update() {
        return $this->upsert();
    }

    /**
     * Удаление по snippet_key (иначе по Name) в рамках текущего сайта.
     * Идемпотентно: цели нет — считаем успехом (повторный delete не ошибка).
     * @return bool
     */
    protected function do_delete() {
        $site_id     = $this->get_current_site_id();
        $existing_id = $this->find_existing($site_id, $this->instruction->get_dataset());

        if (!$existing_id) {
            return true;
        }
        return nc_external_scripts_snippet::delete($existing_id);
    }

    /**
     * Создать или (при найденном snippet_key/Name) обновить сниппет.
     * @return int|false Snippet_ID
     */
    private function upsert() {
        $dataset = $this->instruction->get_dataset();
        $site_id = $this->get_current_site_id();

        $snippet_id = nc_external_scripts_snippet::save(array(
            "id"         => $this->find_existing($site_id, $dataset),
            "site_id"    => $site_id,
            "type_id"    => $this->resolve_type_id($dataset["type"] ?? "custom"),
            "name"       => $dataset["name"] ?? ($dataset["type"] ?? "custom"),
            "settings"   => $this->build_settings($dataset),
            "conditions" => $dataset["conditions"] ?? array(),
            "priority"   => $dataset["priority"] ?? 500,
            "enabled"    => $dataset["enabled"] ?? 1,
        ));

        return $snippet_id ?: false;
    }

    /**
     * settings из dataset как есть + служебный ключ идемпотентности из snippet_key.
     * @param array $dataset
     * @return array
     */
    private function build_settings(array $dataset) {
        $settings = isset($dataset["settings"]) && is_array($dataset["settings"])
            ? $dataset["settings"]
            : array();

        if (!empty($dataset["snippet_key"])) {
            $settings[self::PRESERVE_KEY] = $dataset["snippet_key"];
        }
        return $settings;
    }

    /**
     * Ключ типа → SnippetType_ID классификатора ExternalScripts_Type по
     * SnippetType_Class = "nc_external_scripts_snippet_<type>".
     * NULL, если типа нет: save() тогда пишет SnippetType_ID=NULL, и на рендере
     * factory() отдаёт custom-сниппет (сырой код из settings).
     * @param string $type
     * @return int|null
     */
    private function resolve_type_id($type) {
        $suffix = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$type));
        if ($suffix === "") {
            return null;
        }

        $db = nc_db();
        $type_id = $db->get_var(
            "SELECT `SnippetType_ID` FROM `ExternalScripts_Type`
             WHERE `SnippetType_Class` = '" . $db->escape("nc_external_scripts_snippet_" . $suffix) . "'"
        );

        return $type_id !== null ? (int)$type_id : null;
    }

    /**
     * Существующий Snippet_ID этого сайта: сперва по snippet_key (Settings._preserve_key),
     * иначе по точному совпадению Name. 0, если не найдено.
     * @param int   $site_id
     * @param array $dataset
     * @return int
     */
    private function find_existing($site_id, array $dataset) {
        $key  = isset($dataset["snippet_key"]) && $dataset["snippet_key"] !== "" ? $dataset["snippet_key"] : null;
        $name = isset($dataset["name"]) ? $dataset["name"] : null;

        foreach (nc_external_scripts_snippet::get_list($site_id) as $row) {
            if ($key !== null) {
                $settings = json_decode(isset($row["Settings"]) ? $row["Settings"] : "", true);
                if (is_array($settings)
                    && isset($settings[self::PRESERVE_KEY])
                    && $settings[self::PRESERVE_KEY] === $key
                ) {
                    return (int)$row["Snippet_ID"];
                }
            } elseif ($name !== null && isset($row["Name"]) && $row["Name"] === $name) {
                return (int)$row["Snippet_ID"];
            }
        }

        return 0;
    }

    /**
     * Текущий сайт — уже выставлен базовым check_site_id() из meta['site_id'].
     * @return int
     */
    private function get_current_site_id() {
        return (int)nc_core::get_object()->catalogue->get_current("Catalogue_ID");
    }
}
