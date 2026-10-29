<?php

/**
 * Класс для работы со слотами компонентов (Component Slots)
 *
 * Слот — это именованная область внутри компонента, доступная для стилизации через миксины.
 * Слоты объявляются в файле MixinSlots.html в папке компонента или шаблона компонента.
 *
 * Формат файла MixinSlots.html:
 * ```php
 * <?php
 * return array(
 *     'slot_keyword' => array(
 *         'name' => 'Название слота',           // обязательно
 *         'selector' => '.css-selector',        // обязательно
 *         'portal' => true,                     // опционально: элемент переносится в body
 *         'scopes' => array('border_radius'),   // опционально: фильтр применимых миксинов
 *     ),
 * );
 * ```
 *
 * @see /docs/proposals/component-slots-universal.md
 */
class nc_tpl_component_slots {

    const FILE_NAME = 'MixinSlots.html';

    /** @var array Кэш слотов по ID компонента/шаблона */
    protected static $cache = array();

    /** @var array Кэш путей к файлам слотов */
    protected static $path_cache = array();

    /**
     * Получить слоты компонента или шаблона компонента
     *
     * @param int $class_id ID компонента или шаблона компонента
     * @return array Массив слотов или пустой массив
     */
    public static function get($class_id) {
        $class_id = (int)$class_id;
        if (!$class_id) {
            return array();
        }

        if (!isset(self::$cache[$class_id])) {
            self::$cache[$class_id] = self::load($class_id);
        }

        return self::$cache[$class_id];
    }

    /**
     * Получить слоты для инфоблока (с учётом шаблона компонента)
     *
     * @param array $block_data Данные инфоблока из Sub_Class
     * @return array Массив слотов
     */
    public static function get_for_block(array $block_data) {
        // Сначала пробуем шаблон компонента, потом базовый компонент
        $template_id = !empty($block_data['Class_Template_ID']) ? (int)$block_data['Class_Template_ID'] : 0;
        $class_id = !empty($block_data['Class_ID']) ? (int)$block_data['Class_ID'] : 0;

        $slots = array();

        // Слоты из шаблона компонента (имеют приоритет)
        if ($template_id) {
            $slots = self::get($template_id);
        }

        // Если у шаблона нет слотов, берём из базового компонента
        if (empty($slots) && $class_id && $class_id !== $template_id) {
            $slots = self::get($class_id);
        }

        return $slots;
    }

    /**
     * Получить встроенные (системные) слоты для инфоблока
     *
     * @param array $block_data Данные инфоблока из Sub_Class
     * @return array Массив встроенных слотов
     */
    public static function get_builtin(array $block_data) {
        $slots = array();

        // Встроенный слот для полного вывода object_partial
        if (!empty($block_data['Sub_Class_Type']) && $block_data['Sub_Class_Type'] === 'object_partial') {
            $slots['_full'] = array(
                'name' => defined('NETCAT_MIXIN_SLOT_FULL')
                    ? NETCAT_MIXIN_SLOT_FULL
                    : 'Full view',
                'selector' => '.tpl-block-full',
                'builtin' => true,
            );
        }

        return $slots;
    }

    /**
     * Получить все слоты для инфоблока (встроенные + компонентные)
     *
     * @param array $block_data Данные инфоблока из Sub_Class
     * @return array Объединённый массив слотов
     */
    public static function get_all_for_block(array $block_data) {
        return array_merge(
            self::get_builtin($block_data),
            self::get_for_block($block_data)
        );
    }

    /**
     * Загрузить слоты из файла
     *
     * @param int $class_id ID компонента или шаблона
     * @return array Массив слотов или пустой массив
     */
    protected static function load($class_id) {
        $file_path = self::get_file_path($class_id);
        if (!$file_path || !file_exists($file_path)) {
            return array();
        }

        $slots = @include($file_path);

        if (!is_array($slots)) {
            return array();
        }

        // Валидация и нормализация слотов
        $validated_slots = array();
        foreach ($slots as $keyword => $config) {
            if (!self::validate_slot($keyword, $config)) {
                continue;
            }
            $validated_slots[$keyword] = self::normalize_slot($config);
        }

        return $validated_slots;
    }

    /**
     * Получить путь к файлу MixinSlots.html
     *
     * @param int $class_id ID компонента или шаблона
     * @return string|null Путь к файлу или null
     */
    public static function get_file_path($class_id) {
        $class_id = (int)$class_id;
        if (!$class_id) {
            return null;
        }

        if (isset(self::$path_cache[$class_id])) {
            return self::$path_cache[$class_id];
        }

        $nc_core = nc_core::get_object();

        try {
            $file_path = $nc_core->component->get_by_id($class_id, 'File_Path');
        } catch (Exception $e) {
            self::$path_cache[$class_id] = null;
            return null;
        }

        if (!$file_path) {
            self::$path_cache[$class_id] = null;
            return null;
        }

        $full_path = rtrim($nc_core->CLASS_TEMPLATE_FOLDER, '/') . $file_path . self::FILE_NAME;

        self::$path_cache[$class_id] = $full_path;
        return $full_path;
    }

    /**
     * Проверить существование файла слотов для компонента
     *
     * @param int $class_id ID компонента или шаблона
     * @return bool
     */
    public static function has_slots_file($class_id) {
        $file_path = self::get_file_path($class_id);
        return $file_path && file_exists($file_path);
    }

    /**
     * Валидация конфигурации слота
     *
     * @param string $keyword Ключевое слово слота
     * @param mixed $config Конфигурация слота
     * @return bool
     */
    protected static function validate_slot($keyword, $config) {
        // Ключ должен быть строкой
        if (!is_string($keyword) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $keyword)) {
            return false;
        }

        // Конфиг должен быть массивом
        if (!is_array($config)) {
            return false;
        }

        // Обязательные поля
        if (empty($config['name'])) {
            return false;
        }

        // Селектор должен быть строкой (может быть пустым или "&" для применения к блоку)
        if (!isset($config['selector']) || !is_string($config['selector'])) {
            return false;
        }

        return true;
    }

    /**
     * Нормализация конфигурации слота
     *
     * @param array $config Конфигурация слота
     * @return array Нормализованная конфигурация
     */
    protected static function normalize_slot(array $config) {
        return array(
            'name' => (string)$config['name'],
            'selector' => (string)$config['selector'],
            'portal' => !empty($config['portal']),
            'scopes' => isset($config['scopes']) && is_array($config['scopes']) ? $config['scopes'] : null,
            'builtin' => !empty($config['builtin']),
        );
    }

    /**
     * Очистить кэш слотов
     *
     * @param int|null $class_id ID компонента или null для полной очистки
     */
    public static function clear_cache($class_id = null) {
        if ($class_id === null) {
            self::$cache = array();
            self::$path_cache = array();
        } else {
            $class_id = (int)$class_id;
            unset(self::$cache[$class_id]);
            unset(self::$path_cache[$class_id]);
        }
    }

    /**
     * Проверить, разрешён ли тип миксина для слота
     *
     * @param array $slot_config Конфигурация слота
     * @param string $mixin_type Тип миксина
     * @return bool
     */
    public static function is_mixin_allowed(array $slot_config, $mixin_type) {
        // Если scopes не указаны, разрешены все миксины
        if (!isset($slot_config['scopes']) || $slot_config['scopes'] === null) {
            return true;
        }

        return in_array($mixin_type, $slot_config['scopes'], true);
    }

    /**
     * Сформировать полный CSS-селектор для слота
     *
     * @param int $block_id ID инфоблока
     * @param array $slot_config Конфигурация слота
     * @param string $slot_keyword Ключевое слово слота (для определения типа селектора)
     * @return string CSS-селектор
     */
    public static function get_full_selector($block_id, array $slot_config, $slot_keyword = '') {
        $block_id = (int)$block_id;
        $slot_selector = trim($slot_config['selector']);

        // Слоты с префиксом full_ используют селектор .tpl-block-full-XXX
        // для применения стилей только на странице полного вывода
        if ($slot_keyword && strpos($slot_keyword, 'full_') === 0) {
            $block_selector = ".tpl-block-full-$block_id";
        } else {
            $block_selector = ".tpl-block-$block_id";
        }

        // Пустой селектор или "&" — применить стили к самому блоку
        if ($slot_selector === '' || $slot_selector === '&') {
            return $block_selector;
        }

        return "$block_selector $slot_selector";
    }

}
