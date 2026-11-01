<?php

/**
 * Класс для работы с палитрой цветов сайта
 *
 * Палитра хранится в Catalogue.ncColorList в формате JSON:
 * {"xxxx": "#FFFFFF", "yyyy": "#000000", ...}
 * где ключи — 4-символьные уникальные идентификаторы цветов
 */
class nc_site_color_list {

    /** @var string Символы для генерации ключей */
    protected static $key_chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** @var int Длина ключа */
    protected static $key_length = 4;

    /**
     * Получить палитру сайта
     *
     * @param int $site_id ID сайта
     * @return array Ассоциативный массив {key => color}
     */
    public static function get($site_id) {
        $site_id = (int)$site_id;
        if ($site_id <= 0) {
            return array();
        }

        $nc_core = nc_core::get_object();
        $palette_json = $nc_core->catalogue->get_by_id($site_id, 'ncColorList');

        if (empty($palette_json)) {
            return array();
        }

        $palette = json_decode($palette_json, true);
        return is_array($palette) ? $palette : array();
    }

    /**
     * Сохранить палитру сайта
     *
     * @param int $site_id ID сайта
     * @param array $palette Ассоциативный массив {key => color}
     * @return bool Успешность операции
     */
    public static function save($site_id, array $palette) {
        $site_id = (int)$site_id;
        if ($site_id <= 0) {
            return false;
        }

        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        // Нормализуем цвета
        $normalized = array();
        foreach ($palette as $key => $color) {
            if (!empty($color)) {
                $normalized[$key] = self::normalize_color($color);
            }
        }

        $palette_json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $result = $db->query(
            "UPDATE `Catalogue` SET `ncColorList` = '" . $db->escape($palette_json) . "'
             WHERE `Catalogue_ID` = $site_id"
        );

        if ($result) {
            // Сбрасываем кеш каталога
            $nc_core->catalogue->get_by_id($site_id, '', true);
            // Регенерируем CSS
            nc_tpl_mixin_cache::generate_site_files($site_id, false);
        }

        return (bool)$result;
    }

    /**
     * Добавить цвет в палитру
     *
     * @param int $site_id ID сайта
     * @param string $hex Цвет в формате hex
     * @return string|false Ключ добавленного цвета или false при ошибке
     */
    public static function add_color($site_id, $hex) {
        $palette = self::get($site_id);
        $hex = self::normalize_color($hex);

        // Проверяем, нет ли уже такого цвета
        foreach ($palette as $key => $color) {
            if (strtoupper($color) === strtoupper($hex)) {
                return $key;
            }
        }

        // Генерируем уникальный ключ
        $key = self::generate_key(array_keys($palette));
        $palette[$key] = $hex;

        if (self::save($site_id, $palette)) {
            return $key;
        }

        return false;
    }

    /**
     * Генерировать уникальный ключ
     *
     * @param array $existing Массив существующих ключей
     * @return string 4-символьный ключ
     */
    public static function generate_key(array $existing = array()) {
        $chars = self::$key_chars;
        $len = self::$key_length;
        $chars_count = strlen($chars);
        $max_attempts = 100;

        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            $key = '';
            for ($i = 0; $i < $len; $i++) {
                $key .= $chars[mt_rand(0, $chars_count - 1)];
            }
            if (!in_array($key, $existing)) {
                return $key;
            }
        }

        // Fallback: используем uniqid
        return substr(uniqid(), -$len);
    }

    /**
     * Нормализовать цвет в формат HEX uppercase
     *
     * @param string $color Цвет (hex, rgb, rgba)
     * @return string Цвет в формате #XXXXXX или #XXXXXXXX
     */
    public static function normalize_color($color) {
        if (empty($color)) {
            return '';
        }

        $color = trim($color);

        // Уже hex
        if ($color[0] === '#') {
            // Короткий hex -> полный
            if (strlen($color) === 4) {
                $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
            } elseif (strlen($color) === 5) {
                $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3] . $color[4] . $color[4];
            }
            return strtoupper($color);
        }

        // rgb(r, g, b)
        if (preg_match('/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i', $color, $m)) {
            return sprintf('#%02X%02X%02X', $m[1], $m[2], $m[3]);
        }

        // rgba(r, g, b, a)
        if (preg_match('/^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/i', $color, $m)) {
            $alpha = round(floatval($m[4]) * 255);
            if ($alpha === 255) {
                return sprintf('#%02X%02X%02X', $m[1], $m[2], $m[3]);
            }
            return sprintf('#%02X%02X%02X%02X', $m[1], $m[2], $m[3], $alpha);
        }

        return $color;
    }

    /**
     * Генерировать CSS-переменные для палитры
     *
     * @param array $palette Палитра {key => color}
     * @return string CSS-код
     */
    public static function generate_css_variables(array $palette) {
        $css = '';
        foreach ($palette as $key => $color) {
            if (!empty($color)) {
                $css .= "--tpl-color-list-$key: $color;\n";
            }
        }
        return $css;
    }

    /**
     * Замена удалённых цветов палитры на HEX-значения во всех миксинах сайта
     *
     * При удалении цвета из палитры сайта все ссылки var(--tpl-color-list-KEY)
     * в настройках миксинов инфоблоков, разделов и пресетов должны быть
     * заменены на конкретные HEX-значения.
     *
     * @param int $site_id ID сайта
     * @param array $deleted_colors Массив удалённых цветов [{key: 'xxxx', hex: '#FFFFFF'}, ...]
     * @return int Количество обновлённых записей
     *
     * @todo
     */
    public static function replace_deleted_colors($site_id, $deleted_colors) {
        if (empty($deleted_colors) || !is_array($deleted_colors)) {
            return 0;
        }

        $site_id = (int)$site_id;
        if ($site_id <= 0) {
            return 0;
        }

        $db = nc_db();
        $updated_count = 0;

        // Подготавливаем замены: var(--tpl-color-list-KEY) => HEX
        $replacements = array();
        foreach ($deleted_colors as $color) {
            if (!empty($color['key']) && !empty($color['hex'])) {
                $key = preg_replace('/[^a-z0-9]/i', '', $color['key']);
                $hex = $color['hex'];
                if (strpos($hex, '#') !== 0) {
                    $hex = '#' . $hex;
                }
                $replacements['var(--tpl-color-list-' . $key . ')'] = $hex;
            }
        }

        if (empty($replacements)) {
            return 0;
        }

        /**
         * Рекурсивно заменяет значения в массиве
         */
        $replace_in_array = function (&$arr) use ($replacements, &$replace_in_array) {
            foreach ($arr as $key => &$value) {
                if (is_array($value)) {
                    $replace_in_array($value);
                } elseif (is_string($value)) {
                    $new_value = str_replace(array_keys($replacements), array_values($replacements), $value);
                    if ($new_value !== $value) {
                        $arr[$key] = $new_value;
                    }
                }
            }
        };

        /**
         * Обновляет JSON-настройки в таблице
         */
        $update_settings = function ($table, $id_field, $settings_field, $id, $json_str) use ($db, $replacements, $replace_in_array) {
            $decoded = json_decode($json_str, true);
            if ($decoded === null) {
                return false;
            }

            $original = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            $replace_in_array($decoded);
            $new_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            if ($new_json !== $original) {
                // Используем prepare() вместо escape(), т.к. escape() вызывает stripslashes()
                $db->query(
                    "UPDATE `$table` SET `$settings_field` = '" . $db->prepare(
                        $new_json
                    ) . "' WHERE `$id_field` = " . (int)$id
                );
                return true;
            }
            return false;
        };

        // 1. Обновляем инфоблоки (Sub_Class)
        $infoblocks = $db->get_results(
            "SELECT sc.Sub_Class_ID, sc.Index_Mixin_Settings
         FROM Sub_Class sc
         JOIN Subdivision s ON sc.Subdivision_ID = s.Subdivision_ID
         WHERE s.Catalogue_ID = {$site_id}
         AND sc.Index_Mixin_Settings IS NOT NULL
         AND sc.Index_Mixin_Settings != ''
         AND sc.Index_Mixin_Settings != '{}'",
            ARRAY_A
        );
        if ($infoblocks) {
            foreach ($infoblocks as $ib) {
                if ($update_settings(
                    'Sub_Class', 'Sub_Class_ID', 'Index_Mixin_Settings', $ib['Sub_Class_ID'],
                    $ib['Index_Mixin_Settings']
                )) {
                    $updated_count++;
                }
            }
        }

        // 2. Обновляем разделы (Subdivision)
        $subdivisions = $db->get_results(
            "SELECT Subdivision_ID, Mixin_Settings
         FROM Subdivision
         WHERE Catalogue_ID = {$site_id}
         AND Mixin_Settings IS NOT NULL
         AND Mixin_Settings != ''
         AND Mixin_Settings != '{}'",
            ARRAY_A
        );
        if ($subdivisions) {
            foreach ($subdivisions as $sub) {
                if ($update_settings(
                    'Subdivision', 'Subdivision_ID', 'Mixin_Settings', $sub['Subdivision_ID'], $sub['Mixin_Settings']
                )) {
                    $updated_count++;
                }
            }
        }

        // 3. Обновляем пресеты миксинов (Mixin_Preset)
        $presets = $db->get_results(
            "SELECT Mixin_Preset_ID, Mixin_Settings
         FROM Mixin_Preset
         WHERE Mixin_Settings IS NOT NULL
         AND Mixin_Settings != ''
         AND Mixin_Settings != '{}'",
            ARRAY_A
        );
        if ($presets) {
            foreach ($presets as $preset) {
                if ($update_settings(
                    'Mixin_Preset', 'Mixin_Preset_ID', 'Mixin_Settings', $preset['Mixin_Preset_ID'],
                    $preset['Mixin_Settings']
                )) {
                    $updated_count++;
                }
            }
        }

        // 4. Обновляем настройки сайта (Catalogue.MainArea_Mixin_Settings)
        $site_settings = $db->get_var(
            "SELECT MainArea_Mixin_Settings FROM Catalogue
         WHERE Catalogue_ID = {$site_id}
         AND MainArea_Mixin_Settings IS NOT NULL
         AND MainArea_Mixin_Settings != ''
         AND MainArea_Mixin_Settings != '{}'"
        );
        if ($site_settings) {
            if ($update_settings('Catalogue', 'Catalogue_ID', 'MainArea_Mixin_Settings', $site_id, $site_settings)) {
                $updated_count++;
                // Сбрасываем кэш каталога
                nc_core::get_object()->catalogue->get_by_id($site_id, '', true);
            }
        }

        // Регенерируем CSS после замены цветов
        if ($updated_count > 0) {
            nc_tpl_mixin_cache::generate_site_files($site_id, true);
        }

        return $updated_count;
    }

}
