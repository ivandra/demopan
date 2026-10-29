<?php

/**
 * Базовый класс сниппета внешнего скрипта
 */
abstract class nc_external_scripts_snippet {

    /**
     * События изменения сниппета. Имя события обязано иметь вид
     * <действие><Сущность> (действия: add|update|drop|check|uncheck) — по нему
     * модуль logging находит метод nc_logging::get_externalscript_info() и
     * фильтр по сущности в журнале. См. nc_logging::logging_event().
     */
    const EVENT_AFTER_CREATED  = 'addExternalScript';
    const EVENT_AFTER_UPDATED  = 'updateExternalScript';
    const EVENT_AFTER_DELETED  = 'dropExternalScript';
    const EVENT_AFTER_ENABLED  = 'checkExternalScript';
    const EVENT_AFTER_DISABLED = 'uncheckExternalScript';

    /** @var int */
    protected $id;

    /** @var int */
    protected $site_id;

    /** @var string */
    protected $name;

    /** @var string */
    protected $type;

    /** @var bool */
    protected $enabled = true;

    /** @var int */
    protected $priority = 500;

    /** @var array */
    protected $settings = [];

    /** @var nc_external_scripts_condition|null */
    protected $condition;

    /** @var array Настройки по умолчанию для переопределения в подклассах */
    protected static $default_settings = [];

    /**
     * Фабричный метод для создания сниппета по данным из БД
     *
     * @param array $row Данные из таблицы ExternalScripts_Snippet
     * @return nc_external_scripts_snippet|null
     */
    public static function factory(array $row): ?nc_external_scripts_snippet {
        $type_class = null;

        // Если есть SnippetType_ID, получаем класс из классификатора
        if (!empty($row['SnippetType_ID'])) {
            $type_class = nc_db()->get_var(
                "SELECT `SnippetType_Class`
                 FROM `ExternalScripts_Type`
                 WHERE `SnippetType_ID` = " . (int)$row['SnippetType_ID']
            );
        }

        // Если тип не найден, используем custom
        if (!$type_class) {
            $type_class = 'nc_external_scripts_snippet_custom';
        }

        if (!class_exists($type_class)) {
            return null;
        }

        $snippet = new $type_class();
        $snippet->load_from_row($row);

        return $snippet;
    }

    /**
     * Получение списка доступных типов сниппетов
     *
     * @return array
     */
    public static function get_available_types(): array {
        return nc_db()->get_results(
            "SELECT * FROM `ExternalScripts_Type`
             WHERE `Enabled` = 1
             ORDER BY `Priority` ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Получение информации о типе по ID
     *
     * @param int $type_id
     * @return array|null
     */
    public static function get_type_info(int $type_id): ?array {
        $row = nc_db()->get_row(
            "SELECT * FROM `ExternalScripts_Type`
             WHERE `SnippetType_ID` = " . $type_id,
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Загрузка данных из строки БД
     *
     * @param array $row
     */
    protected function load_from_row(array $row): void {
        $this->id = (int)$row['Snippet_ID'];
        $this->site_id = (int)$row['Catalogue_ID'];
        $this->name = $row['Name'];
        $this->enabled = (bool)$row['Enabled'];
        $this->priority = (int)$row['Priority'];

        // Декодирование JSON-настроек
        $settings = $row['Settings'];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        $this->settings = array_merge(static::$default_settings, $settings ?: []);

        // Загрузка условий
        $conditions = $row['Conditions'];
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true);
        }
        $this->condition = nc_external_scripts_condition::from_array($conditions ?: []);
    }

    /**
     * Получение ID сниппета
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Получение ID сайта
     *
     * @return int
     */
    public function get_site_id(): int {
        return $this->site_id;
    }

    /**
     * Получение названия
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Получение типа
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Проверка включённости
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Получение приоритета
     *
     * @return int
     */
    public function get_priority(): int {
        return $this->priority;
    }

    /**
     * Получение настройки
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_setting(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Получение всех настроек
     *
     * @return array
     */
    public function get_settings(): array {
        return $this->settings;
    }

    /**
     * Установка настроек (для предпросмотра)
     *
     * @param array $settings
     */
    public function set_settings(array $settings): void {
        $this->settings = array_merge(static::$default_settings, $settings);
    }

    /**
     * Категория cookie сниппета (для гейтинга согласия).
     * Приоритет: явная настройка администратора > дефолт типа.
     *
     * @return string одна из nc_consent::CATEGORY_*
     */
    public function get_cookie_category(): string {
        $category = $this->get_setting('cookie_category');
        $known = array_merge(
            nc_consent::gated_categories(),
            array(nc_consent::CATEGORY_NONE, nc_consent::CATEGORY_NECESSARY)
        );
        if ($category && in_array($category, $known, true)) {
            return $category;
        }
        return $this->default_cookie_category();
    }

    /**
     * Категория cookie по умолчанию для типа сниппета.
     * Переопределяется в подклассах для известных провайдеров; базовое значение —
     * «не собирает данные» (none): произвольный скрипт не блокируется, пока
     * администратор явно не назначит ему блокируемую категорию.
     *
     * @return string
     */
    protected function default_cookie_category(): string {
        return nc_consent::CATEGORY_NONE;
    }

    /**
     * Код для вставки после <head>
     *
     * @return string
     */
    public function get_code_head_start(): string {
        return '';
    }

    /**
     * Код для вставки перед </head>
     *
     * @return string
     */
    public function get_code_head_end(): string {
        return '';
    }

    /**
     * Код для вставки после <body>
     *
     * @return string
     */
    public function get_code_body_start(): string {
        return '';
    }

    /**
     * Код для вставки перед </body>
     *
     * @return string
     */
    public function get_code_body_end(): string {
        return '';
    }

    /**
     * Нужен ли скрипт отправки событий аналитики
     *
     * @return bool
     */
    public function needs_analytics_event_script(): bool {
        return false;
    }

    /**
     * Проверка условий показа
     *
     * @return bool
     */
    public function should_display(): bool {
        $nc_core = nc_core::get_object();

        // Не показывать в админке
        if ($nc_core->admin_mode) {
            return false;
        }

        // Не показывать при AJAX-запросах
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }

        // Проверка условий по разделам
        if ($this->condition) {
            return $this->condition->check();
        }

        return true;
    }

    /**
     * Описание полей настроек для формы в админке
     *
     * @return array
     */
    abstract public function get_settings_fields(): array;

    /**
     * Валидация настроек
     *
     * @return bool
     */
    public function validate_settings(): bool {
        return true;
    }

    /**
     * Получение ошибок валидации
     *
     * @return array
     */
    public function get_validation_errors(): array {
        return [];
    }

    /**
     * Сохранение сниппета в БД
     *
     * @param array $data
     * @return int ID сохранённого сниппета
     */
    public static function save(array $data): int {
        $db = nc_db();

        $id = (int)($data['id'] ?? 0);
        $site_id = (int)$data['site_id'];
        $type_id = !empty($data['type_id']) ? (int)$data['type_id'] : 'NULL';
        // ВАЖНО: для JSON используем prepare(), а НЕ escape(): escape() делает
        // stripslashes() (наследие magic_quotes) и срезает легитимные слэши JSON
        // (\r\n, \", \\), ломая переносы строк в коде (\r\n -> rn). prepare()
        // экранирует для SQL без stripslashes. Значения из fetch_post() сырые
        // (без добавленных слэшей), поэтому prepare() корректен и для name.
        $name = $db->prepare($data['name']);
        $settings = $db->prepare(json_encode($data['settings'] ?? [], JSON_UNESCAPED_UNICODE));
        $conditions = $db->prepare(json_encode($data['conditions'] ?? [], JSON_UNESCAPED_UNICODE));
        $priority = (int)($data['priority'] ?? 500);
        $enabled = (int)($data['enabled'] ?? 1);

        if ($id) {
            $db->query("
                UPDATE `ExternalScripts_Snippet` SET
                    `Name` = '{$name}',
                    `SnippetType_ID` = {$type_id},
                    `Settings` = '{$settings}',
                    `Conditions` = '{$conditions}',
                    `Priority` = {$priority},
                    `Enabled` = {$enabled}
                WHERE `Snippet_ID` = {$id}
            ");
            self::fire_event(self::EVENT_AFTER_UPDATED, $id, $site_id, $data['name']);
            return $id;
        } else {
            $db->query("
                INSERT INTO `ExternalScripts_Snippet`
                    (`Catalogue_ID`, `SnippetType_ID`, `Name`, `Settings`, `Conditions`, `Priority`, `Enabled`, `Created`)
                VALUES
                    ({$site_id}, {$type_id}, '{$name}', '{$settings}', '{$conditions}', {$priority}, {$enabled}, NOW())
            ");
            $new_id = (int)$db->insert_id;
            self::fire_event(self::EVENT_AFTER_CREATED, $new_id, $site_id, $data['name']);
            return $new_id;
        }
    }

    /**
     * Удаление сниппета
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        // Название и сайт нужны журналу изменений — читаем до удаления строки
        $row = nc_db()->get_row(
            "SELECT `Name`, `Catalogue_ID` FROM `ExternalScripts_Snippet` WHERE `Snippet_ID` = " . $id,
            ARRAY_A
        );

        nc_db()->query("DELETE FROM `ExternalScripts_Snippet` WHERE `Snippet_ID` = " . $id);
        $deleted = nc_db()->rows_affected > 0;

        if ($deleted && $row) {
            self::fire_event(self::EVENT_AFTER_DELETED, $id, (int)$row['Catalogue_ID'], $row['Name']);
        }

        return $deleted;
    }

    /**
     * Получение сниппета по ID
     *
     * @param int $id
     * @return nc_external_scripts_snippet|null
     */
    public static function get_by_id(int $id): ?nc_external_scripts_snippet {
        $row = nc_db()->get_row(
            "SELECT * FROM `ExternalScripts_Snippet` WHERE `Snippet_ID` = " . $id,
            ARRAY_A
        );
        return $row ? self::factory($row) : null;
    }

    /**
     * Получение списка сниппетов для сайта
     *
     * @param int $site_id
     * @param bool $only_enabled
     * @return array
     */
    public static function get_list(int $site_id, bool $only_enabled = false): array {
        $where = "`Catalogue_ID` = " . $site_id;
        if ($only_enabled) {
            $where .= " AND `Enabled` = 1";
        }

        $rows = nc_db()->get_results(
            "SELECT es.*, est.SnippetType_Name, est.SnippetType_Icon
             FROM `ExternalScripts_Snippet` es
             LEFT JOIN `ExternalScripts_Type` est
                 ON es.SnippetType_ID = est.SnippetType_ID
             WHERE {$where}
             ORDER BY `Priority` ASC",
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Обновление приоритетов сниппетов
     *
     * @param array $order Массив [snippet_id => priority]
     */
    public static function update_priorities(array $order): void {
        $db = nc_db();

        // Форма присылает порядок целиком, поэтому текущие значения читаем
        // заранее: в журнал изменений должны попасть только те сниппеты,
        // приоритет которых действительно поменялся
        $ids = array_filter(array_map('intval', array_keys($order)));
        if (!$ids) {
            return;
        }

        $current = array();
        $rows = $db->get_results(
            "SELECT `Snippet_ID`, `Name`, `Catalogue_ID`, `Priority`
             FROM `ExternalScripts_Snippet`
             WHERE `Snippet_ID` IN (" . implode(',', $ids) . ")",
            ARRAY_A
        ) ?: array();
        foreach ($rows as $row) {
            $current[(int)$row['Snippet_ID']] = $row;
        }

        $changed_ids = array();
        $changed_names = array();
        $site_id = 0;

        foreach ($order as $id => $priority) {
            $db->query("
                UPDATE `ExternalScripts_Snippet`
                SET `Priority` = " . (int)$priority . "
                WHERE `Snippet_ID` = " . (int)$id
            );

            $id = (int)$id;
            if (isset($current[$id]) && (int)$current[$id]['Priority'] !== (int)$priority) {
                $changed_ids[] = $id;
                $changed_names[$id] = $current[$id]['Name'];
                $site_id = (int)$current[$id]['Catalogue_ID'];
            }
        }

        if ($changed_ids) {
            self::fire_event(self::EVENT_AFTER_UPDATED, $changed_ids, $site_id, $changed_names);
        }
    }

    /**
     * Переключение состояния включённости
     *
     * @param int $id
     * @param bool $enabled
     */
    public static function toggle(int $id, bool $enabled): void {
        // Название и сайт нужны журналу изменений
        $row = nc_db()->get_row(
            "SELECT `Name`, `Catalogue_ID` FROM `ExternalScripts_Snippet` WHERE `Snippet_ID` = " . $id,
            ARRAY_A
        );

        nc_db()->query("
            UPDATE `ExternalScripts_Snippet`
            SET `Enabled` = " . (int)$enabled . "
            WHERE `Snippet_ID` = " . $id
        );

        if ($row) {
            self::fire_event(
                $enabled ? self::EVENT_AFTER_ENABLED : self::EVENT_AFTER_DISABLED,
                $id,
                (int)$row['Catalogue_ID'],
                $row['Name']
            );
        }
    }

    /**
     * Отправка события в шину nc_event. Своих слушателей у модуля нет: событие
     * нужно глобальному логгеру (модуль «Журнал изменений»), который пишет любое
     * событие в таблицу `Logging`. Так операции со сниппетами — включая код,
     * который хранится в `Settings`, — становятся отслеживаемыми.
     *
     * @param string $event одна из констант EVENT_AFTER_*
     * @param int|int[] $id ID сниппета либо список ID при массовой операции
     * @param int $site_id
     * @param string|string[] $name название сниппета либо карта [id => название]
     */
    protected static function fire_event(string $event, $id, int $site_id, $name): void {
        nc_core::get_object()->event->execute($event, $id, $site_id, $name);
    }
}
