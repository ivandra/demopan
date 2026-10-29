<?php

/**
 * Единый источник текста галочек согласия (обработка ПД и правила сайта) для
 * форм регистрации (модуль auth), оформления заказа (netshop) и произвольных
 * форм (requests).
 *
 * Каскад текста (одно место настройки — уровень сайта):
 *   1) текст модуля (если задан) →
 *   2) дефолт сайта (Settings, модуль system: ConsentPDText / ConsentRulesText) →
 *   3) встроенная константа.
 *
 * Ссылки на юр-документы внутри текста задаются макросами
 * {privacy_policy_url} / {consent_url} / {rules_url} и подставляются из настроек
 * сайта (Catalogue: PrivacyPolicy_Sub_ID / Consent_Sub_ID / Rules_Sub_ID). Так
 * и текст, и ссылки настраиваются централизованно, а модули переопределяют их
 * только при необходимости.
 *
 * Это НЕ про cookie-согласие — см. отдельный класс nc_consent.
 */
class nc_consent_text {

    /** Слот «согласие на обработку персональных данных». */
    const SLOT_PD = 'pd';

    /** Слот «согласие с правилами использования сайта». */
    const SLOT_RULES = 'rules';

    /** Ключи сайтового дефолта в Settings (модуль system). */
    const SETTING_PD    = 'ConsentPDText';
    const SETTING_RULES = 'ConsentRulesText';

    /**
     * Встроенный текст по умолчанию (с макросами ссылок) — используется, когда
     * ни на уровне модуля, ни на уровне сайта текст не задан.
     *
     * @param string $slot
     * @return string
     */
    public static function fallback_text($slot) {
        if ($slot === self::SLOT_RULES) {
            return 'соглашаюсь с <a href="{rules_url}" target="_blank">правилами использования сайта</a>';
        }

        return 'даю <a href="{consent_url}" target="_blank">согласие на обработку персональных данных</a>'
             . ' в соответствии с <a href="{privacy_policy_url}" target="_blank">Политикой</a>';
    }

    /**
     * Сайтовый дефолт текста галочки, заданный в настройках сайта (Settings).
     * Без подстановки макросов. Пустая строка, если в настройках ничего нет.
     *
     * @param string   $slot
     * @param int|null $catalogue_id  null → текущий сайт
     * @return string
     */
    public static function site_text_raw($slot, $catalogue_id = null) {
        $nc_core = nc_core::get_object();
        $key  = ($slot === self::SLOT_RULES) ? self::SETTING_RULES : self::SETTING_PD;
        $text = $nc_core->get_settings($key, 'system', false, $catalogue_id);

        return ($text !== false && $text !== null) ? (string)$text : '';
    }

    /**
     * Сайтовый дефолт текста галочки (без подстановки макросов). Пустое значение
     * в настройках сайта → встроенная константа.
     *
     * @param string   $slot
     * @param int|null $catalogue_id  null → текущий сайт
     * @return string
     */
    public static function site_text($slot, $catalogue_id = null) {
        $text = self::site_text_raw($slot, $catalogue_id);

        return ($text !== '') ? $text : self::fallback_text($slot);
    }

    /**
     * Итоговый текст галочки с подставленными макросами ссылок. Каскад источников:
     *   1) текст модуля → 2) дефолт сайта (Settings) →
     *   3) кастомный фолбэк модуля (если передан) → 4) встроенная константа.
     *
     * @param string      $slot
     * @param string|null $module_text      текст, заданный в настройках модуля
     * @param int|null    $catalogue_id     null → текущий сайт
     * @param string|null $custom_fallback  собственный дефолт модуля (напр. auth %USER_AGR)
     * @return string
     */
    public static function resolve($slot, $module_text = null, $catalogue_id = null, $custom_fallback = null) {
        $candidates = array(
            $module_text,
            self::site_text_raw($slot, $catalogue_id),
            $custom_fallback,
            self::fallback_text($slot),
        );

        $text = '';
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                $text = $candidate;
                break;
            }
        }

        return self::replace_macros($text, $catalogue_id);
    }

    /**
     * Подстановка макросов ссылок на юр-документы из настроек сайта.
     * Тексты без макросов возвращаются как есть (обратная совместимость).
     *
     * @param string|null         $text
     * @param int|array|null      $catalogue  id сайта, массив строки Catalogue или null (текущий)
     * @return string|null
     */
    public static function replace_macros($text, $catalogue = null) {
        if ($text === null || $text === '' || strpos($text, '{') === false) {
            return $text;
        }

        $nc_core = nc_core::get_object();

        if (is_array($catalogue)) {
            $cat = $catalogue;
        } elseif ($catalogue) {
            $cat = $nc_core->catalogue->get_by_id((int)$catalogue);
        } else {
            $cat = $nc_core->catalogue ? $nc_core->catalogue->get_current() : array();
        }
        $cat = (array)$cat;

        $replacements = array(
            '{privacy_policy_url}' => !empty($cat['PrivacyPolicy_Sub_ID']) ? nc_folder_url($cat['PrivacyPolicy_Sub_ID']) : '/policy/',
            '{consent_url}'        => !empty($cat['Consent_Sub_ID'])       ? nc_folder_url($cat['Consent_Sub_ID'])       : '/consent/',
            '{rules_url}'          => !empty($cat['Rules_Sub_ID'])         ? nc_folder_url($cat['Rules_Sub_ID'])         : '/terms/',
        );

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
