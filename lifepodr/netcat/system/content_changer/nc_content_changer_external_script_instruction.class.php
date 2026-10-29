<?php

/**
 * Инструкция content_changer для модуля «Внешние скрипты» (external_scripts).
 *
 * Переносит сквозные (scope=global) сторонние скрипты — аналитику, чаты, пиксели —
 * на пересобранный сайт. Модель — nc_content_changer_site_instruction.
 *
 * dataset (кладёт сторона SaaS-генератора):
 *   type        — ключ типа сниппета (jivo|yandex_metrika|google_gtm|
 *                 google_analytics4|vk_pixel|…|custom); резолвится в SnippetType_ID
 *                 по ExternalScripts_Type.SnippetType_Class = "nc_external_scripts_snippet_<type>".
 *   name        — человекочитаемое имя сниппета.
 *   settings    — массив настроек под класс сниппета (для custom — code_body_end и т.п.),
 *                 передаётся в save() как есть.
 *   conditions  — массив условий показа (обычно пусто = на всех страницах).
 *   priority    — опц., дефолт 500.
 *   enabled     — опц., дефолт 1.
 *   snippet_key — опц., стабильный ключ идемпотентности; handler кладёт его в
 *                 Settings._preserve_key и по нему делает UPDATE вместо INSERT.
 *
 * site_id передаётся через meta (set_meta(['site_id' => ...])); текущий сайт выставит
 * базовый nc_content_changer::check_site_id() до вызова do_*.
 */
class nc_content_changer_external_script_instruction extends nc_content_changer_instruction {

    protected static $entity_type = "external_script";

    /**
     * @param string $action  create|update|delete
     * @param array  $dataset {type, name, settings, conditions, priority, enabled, snippet_key?}
     */
    public function __construct($action = "create", $dataset = array()) {
        parent::__construct($action, "", $dataset, array());
    }
}
