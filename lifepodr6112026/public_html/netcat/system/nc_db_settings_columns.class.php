<?php

/**
 * Колонки с исполняемым PHP-кодом настроек: text (64 КБ) → mediumtext (16 МБ).
 *
 * - Catalogue.TemplateSettings, Subdivision.TemplateSettings — настройки макета;
 * - Sub_Class.CustomSettings — пользовательские настройки инфоблоков;
 * - Classificator_*.CustomSettings — пользовательские настройки элементов списков.
 *
 * В этих полях лежит var_export(), который читается через eval() (nc_a2f).
 * При превышении 65535 байт MySQL молча обрезал значение (ядро отключает
 * strict-режим) → Parse error. См. docs/proposals/templatesettings-text-overflow.md.
 *
 * Используется патчем обновления и импортом (старые дампы создают таблицы с text).
 * Класс отдельный (а не метод nc_db): патч выполняется в процессе, где старый
 * nc_db уже загружен, а новый класс подгружается автозагрузчиком из обновлённых файлов.
 */
class nc_db_settings_columns {

    /**
     * Расширяет до mediumtext те колонки настроек, которые ещё text.
     * Идемпотентно; кодировка, сопоставление, NULL/NOT NULL и комментарий колонки сохраняются.
     *
     * @param string[]|null $tables  ограничить списком таблиц (null — все подходящие)
     * @return array  ['modified' => ['Table.Column', ...], 'errors' => [['table', 'column', 'sql', 'error'], ...]]
     */
    public static function upgrade(?array $tables = null) {
        $result = array('modified' => array(), 'errors' => array());

        if ($tables !== null) {
            $tables = array_values(array_filter(array_map('strval', $tables), 'strlen'));
            if (!$tables) {
                return $result;
            }
        }

        $db = nc_core::get_object()->db;
        $columns = (array)$db->get_results(self::get_select_query($tables), ARRAY_A);

        foreach ($columns as $column) {
            $sql = self::get_modify_query($column);
            $db->query($sql);
            if ($db->is_error) {
                $result['errors'][] = array(
                    'table' => $column['TABLE_NAME'],
                    'column' => $column['COLUMN_NAME'],
                    'sql' => $sql,
                    'error' => $db->last_error,
                );
            } else {
                $result['modified'][] = $column['TABLE_NAME'] . '.' . $column['COLUMN_NAME'];
            }
        }

        return $result;
    }

    /**
     * upgrade() для указанных таблиц с предупреждением (E_USER_WARNING) об ошибках —
     * для импорта, где старый дамп мог создать таблицу с колонками text.
     *
     * @param string[] $tables
     * @return array  см. upgrade()
     */
    public static function upgrade_tables(array $tables) {
        $result = self::upgrade($tables);
        foreach ($result['errors'] as $error) {
            trigger_error(
                "nc_db_settings_columns: cannot convert {$error['table']}.{$error['column']} to MEDIUMTEXT " .
                "({$error['error']}); run manually: {$error['sql']}",
                E_USER_WARNING
            );
        }
        return $result;
    }

    /**
     * Запрос к information_schema: колонки настроек, которые ещё text.
     *
     * @param string[]|null $tables
     * @return string
     */
    public static function get_select_query(?array $tables = null) {
        $query =
            "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME,
                    IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND DATA_TYPE = 'text'
                AND (
                      (TABLE_NAME IN ('Catalogue', 'Subdivision') AND COLUMN_NAME = 'TemplateSettings')
                   OR (TABLE_NAME = 'Sub_Class' AND COLUMN_NAME = 'CustomSettings')
                   OR (TABLE_NAME LIKE 'Classificator\\_%' AND COLUMN_NAME = 'CustomSettings')
                )";

        if ($tables !== null) {
            $query .= "\n                AND TABLE_NAME IN ('" . join("', '", array_map(array(__CLASS__, 'escape_string'), $tables)) . "')";
        }

        return $query;
    }

    /**
     * ALTER TABLE … MODIFY … MEDIUMTEXT для строки из information_schema.COLUMNS.
     *
     * MODIFY без CHARACTER SET/COLLATE сбросил бы кодировку колонки к умолчанию таблицы
     * (например, utf8mb4 → utf8 с порчей 4-байтных символов), а без NOT NULL/COMMENT —
     * потерял бы их, поэтому всё берётся из текущего описания колонки.
     * Значение по умолчанию: DEFAULT NULL только у nullable-колонки; у NOT NULL TEXT-колонки
     * DEFAULT не указывается (MySQL до 8.0.13 не допускает DEFAULT у TEXT).
     *
     * @param array $column  TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME, IS_NULLABLE, COLUMN_COMMENT
     * @return string
     */
    public static function get_modify_query(array $column) {
        $sql = "ALTER TABLE " . self::quote_identifier($column['TABLE_NAME']) .
               " MODIFY " . self::quote_identifier($column['COLUMN_NAME']) . " MEDIUMTEXT";

        // имена кодировок и сопоставлений — только [A-Za-z0-9_]
        if (!empty($column['CHARACTER_SET_NAME']) && preg_match('/^\w+$/', $column['CHARACTER_SET_NAME'])) {
            $sql .= " CHARACTER SET " . $column['CHARACTER_SET_NAME'];
        }
        if (!empty($column['COLLATION_NAME']) && preg_match('/^\w+$/', $column['COLLATION_NAME'])) {
            $sql .= " COLLATE " . $column['COLLATION_NAME'];
        }

        $sql .= isset($column['IS_NULLABLE']) && strtoupper($column['IS_NULLABLE']) === 'NO'
            ? " NOT NULL"
            : " NULL DEFAULT NULL";

        if (isset($column['COLUMN_COMMENT']) && $column['COLUMN_COMMENT'] !== '') {
            $sql .= " COMMENT '" . self::escape_string($column['COLUMN_COMMENT']) . "'";
        }

        return $sql;
    }

    /**
     * @param string $identifier
     * @return string
     */
    protected static function quote_identifier($identifier) {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Экранирование строкового литерала без обращения к соединению с БД
     * (значения берутся из information_schema).
     *
     * @param string $string
     * @return string
     */
    protected static function escape_string($string) {
        return strtr((string)$string, array(
            "\\" => "\\\\",
            "'" => "\\'",
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "\x1a" => "\\Z",
        ));
    }

}
