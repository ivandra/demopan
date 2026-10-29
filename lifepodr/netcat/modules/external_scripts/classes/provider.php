<?php

/**
 * Реестр доменных провайдеров (карты/видео и т.п.) для авто-блокировки до
 * cookie-согласия — хранится в БД, редактируется в админке модуля «Внешние
 * скрипты». Источник для nc_consent::providers() (см. nc_consent.class.php),
 * который читает таблицу напрямую (мягкая зависимость — без этого класса).
 */
class nc_external_scripts_provider {

    const TABLE = 'ExternalScripts_Provider';

    /**
     * События изменения записи реестра — их пишет в журнал глобальный логгер
     * (модуль «Журнал изменений»). Имя события обязано иметь вид
     * <действие><Сущность>, см. nc_logging::logging_event().
     */
    const EVENT_AFTER_CREATED  = 'addExternalScriptProvider';
    const EVENT_AFTER_UPDATED  = 'updateExternalScriptProvider';
    const EVENT_AFTER_DELETED  = 'dropExternalScriptProvider';
    const EVENT_AFTER_ENABLED  = 'checkExternalScriptProvider';
    const EVENT_AFTER_DISABLED = 'uncheckExternalScriptProvider';

    /** Категории, доступные для выбора (необязательные — блокируемые). */
    public static function categories() {
        return array(
            nc_consent::CATEGORY_FUNCTIONAL => 'functional',
            nc_consent::CATEGORY_ANALYTICS  => 'analytics',
            nc_consent::CATEGORY_MARKETING  => 'marketing',
        );
    }

    /**
     * @return array[] все записи реестра (включая выключенные) — для списка в админке
     */
    public static function get_list() {
        return nc_db()->get_results(
            'SELECT * FROM `' . self::TABLE . '` ORDER BY `Priority` ASC, `Provider_ID` ASC',
            ARRAY_A
        ) ?: array();
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function get_by_id($id) {
        return nc_db()->get_row(
            'SELECT * FROM `' . self::TABLE . '` WHERE `Provider_ID` = ' . (int)$id,
            ARRAY_A
        );
    }

    /**
     * Создание/обновление записи.
     *
     * @param array $data id?, label, pattern, is_regex, category, priority, enabled
     * @return int Provider_ID
     */
    public static function save(array $data) {
        $db = nc_db();

        // prepare(), а НЕ escape(): escape() делает stripslashes() (наследие
        // magic_quotes) и срежет легитимные обратные слэши паттерна (regex
        // вида `\.yandex\.ru` потеряет экранирование точки). Значения из
        // fetch_post() сырые (без добавленных слэшей) — prepare() корректен.
        // См. тот же приём в classes/snippet.php::save().
        $fields = array(
            'Label'    => $db->prepare((string)$data['label']),
            'Pattern'  => $db->prepare((string)$data['pattern']),
            'IsRegex'  => empty($data['is_regex']) ? 0 : 1,
            'Category' => $db->prepare((string)$data['category']),
            'Priority' => (int)$data['priority'],
            'Enabled'  => empty($data['enabled']) ? 0 : 1,
        );

        $id = !empty($data['id']) ? (int)$data['id'] : 0;

        if ($id) {
            $set = array();
            foreach ($fields as $col => $val) {
                $set[] = is_int($val) ? "`$col` = $val" : "`$col` = '$val'";
            }
            $db->query('UPDATE `' . self::TABLE . '` SET ' . implode(', ', $set) . ' WHERE `Provider_ID` = ' . $id);
            self::fire_event(self::EVENT_AFTER_UPDATED, $id, $data['label']);

            return $id;
        }

        $fields['Created'] = date('Y-m-d H:i:s');
        $cols = array();
        $vals = array();
        foreach ($fields as $col => $val) {
            $cols[] = "`$col`";
            $vals[] = is_int($val) ? $val : "'$val'";
        }
        $db->query('INSERT INTO `' . self::TABLE . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');

        $new_id = (int)$db->insert_id;
        self::fire_event(self::EVENT_AFTER_CREATED, $new_id, $data['label']);

        return $new_id;
    }

    /**
     * @param int $id
     */
    public static function delete($id) {
        // Название нужно журналу изменений — читаем до удаления строки
        $label = nc_db()->get_var('SELECT `Label` FROM `' . self::TABLE . '` WHERE `Provider_ID` = ' . (int)$id);

        nc_db()->query('DELETE FROM `' . self::TABLE . '` WHERE `Provider_ID` = ' . (int)$id);

        if (nc_db()->rows_affected > 0) {
            self::fire_event(self::EVENT_AFTER_DELETED, (int)$id, $label);
        }
    }

    /**
     * Запись остаётся в базе — просто перестаёт участвовать в гейтинге
     * (вызов провайдера больше не блокируется), как и попросили явно.
     *
     * @param int $id
     * @param bool $enabled
     */
    public static function toggle($id, $enabled) {
        // Название нужно журналу изменений
        $label = nc_db()->get_var('SELECT `Label` FROM `' . self::TABLE . '` WHERE `Provider_ID` = ' . (int)$id);

        nc_db()->query('UPDATE `' . self::TABLE . '` SET `Enabled` = ' . ($enabled ? 1 : 0) . ' WHERE `Provider_ID` = ' . (int)$id);

        if ($label !== null) {
            self::fire_event(
                $enabled ? self::EVENT_AFTER_ENABLED : self::EVENT_AFTER_DISABLED,
                (int)$id,
                $label
            );
        }
    }

    /**
     * Отправка события в шину nc_event для журнала изменений (модуль logging
     * слушает все события глобально). Своих слушателей у модуля нет.
     *
     * @param string $event одна из констант EVENT_AFTER_*
     * @param int $id
     * @param string $label название записи реестра
     */
    protected static function fire_event($event, $id, $label) {
        nc_core::get_object()->event->execute($event, $id, $label);
    }
}
