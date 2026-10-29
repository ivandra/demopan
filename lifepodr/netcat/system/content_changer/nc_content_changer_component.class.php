<?php

/**
 * Обработчик инструкции создания компонента Netcat
 *
 * Создаёт компонент (Class) с полями и шаблонами, либо шаблон компонента.
 * Для создания инфоблока и объекта используйте отдельные инструкции:
 * - nc_content_changer_infoblock_instruction (action: create)
 * - nc_content_changer_object_instruction (action: create)
 *
 * Формат dataset инструкции:
 * - keyword (string, обязательный) — уникальный keyword компонента/шаблона
 * - parent_component_keyword (string) — keyword родительского компонента для создания шаблона
 *   Если указан — создаётся шаблон компонента (ClassTemplate > 0), поля наследуются
 * - fields (array, обязательный если нет parent_component_keyword) — массив полей компонента
 *   - name (string) — имя поля
 *   - type (int) — TypeOfData_ID
 *   - description (string) — описание
 *   - format (string) — формат (для файловых полей)
 *   - not_null (int) — обязательность
 *   - searchable (int) — поиск по полю
 * - name (string) — название компонента
 * - group (string) — группа компонентов
 * - record_template (string) — PHP-шаблон RecordTemplate.html
 * - form_prefix (string) — PHP-шаблон FormPrefix.html (обычно <?= $f_AdminCommon ?>)
 * - form_suffix (string) — PHP-шаблон FormSuffix.html
 * - settings (string) — PHP-шаблон Settings.html (инициализация перед выводом, например $menu_tree)
 * - styles (string) — CSS-стили SiteStyles.css
 * - record_template_full (string) — PHP-шаблон RecordTemplateFull.html (детальная страница объекта)
 * - type (string) — Class.Type, только для шаблона компонента (с parent_component_keyword):
 *   useful (по умолчанию) | object_partial (вариант для блоков страницы полного вывода записи,
 *   печатает поля записи через $f_*) | responsive | mobile | multi_edit | title | xml | rss;
 *   неизвестное значение → useful. Базовый компонент всегда useful (nc_component::create()):
 *   type, отличный от useful, для него — ошибка инструкции
 * - title_template (string) — Class.TitleTemplate: шаблон <title> страницы объекта (напр. `$f_Name | Компания`);
 *   для шаблона компонента по умолчанию наследуется от родителя (иначе у варианта он пуст, и
 *   full.php оставляет title раздела)
 * - is_optimized_for_multiple_mode (int) — Class.IsOptimizedForMultipleMode (вариант подходит
 *   для вывода списком; для object_partial обычно 0)
 *
 * @see nc_content_changer_component_instruction
 */
class nc_content_changer_component extends nc_content_changer {

    /**
     * Валидация данных инструкции
     *
     * @return bool
     */
    protected function validate() {
        $dataset = $this->instruction->get_dataset();

        if (empty($dataset['keyword'])) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: keyword");
            }
            return false;
        }

        // Для update нужен только keyword
        if ($this->action === 'update') {
            return true;
        }

        // Для шаблона компонента поля не нужны (наследуются от родителя)
        $is_template = !empty($dataset['parent_component_keyword']);

        // nc_component::create() у базового компонента принудительно ставит Type = useful: молча
        // создать не тот тип хуже, чем отказать (object_partial — это вариант, а не компонент)
        if (!$is_template && $this->component_type($dataset) !== 'useful') {
            if ($this->logger) {
                $this->logger->set_error("Parameter type='{$dataset['type']}' is supported only for component templates (parent_component_keyword)");
            }
            return false;
        }

        if (!$is_template && (empty($dataset['fields']) || !is_array($dataset['fields']))) {
            if ($this->logger) {
                $this->logger->set_error("Missing required parameter: fields (must be non-empty array)");
            }
            return false;
        }

        return true;
    }

    /**
     * Создание компонента или шаблона компонента
     *
     * @return int|false ID компонента/шаблона или false при ошибке
     */
    protected function do_create() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        // Определяем, создаём ли шаблон компонента
        $is_template = !empty($dataset['parent_component_keyword']);

        if ($is_template) {
            return $this->create_component_template($dataset);
        }

        return $this->create_component($dataset);
    }

    /**
     * Обновление шаблонов существующего компонента (по keyword).
     *
     * Перезаписывает файлы шаблонов (RecordTemplate, RecordTemplateFull,
     * SiteStyles и т.д.) без изменения полей или свойств компонента.
     *
     * @return int ID компонента
     */
    protected function do_update() {
        $nc_core = nc_core::get_object();
        $dataset = $this->instruction->get_dataset();

        $component_id = nc_db_table::make('Class')
            ->where('Keyword', $dataset['keyword'])
            ->get_value('Class_ID');

        if (!$component_id) {
            throw new Exception("Component not found for update: " . $dataset['keyword']);
        }

        $this->write_template_files($component_id, $dataset);
        $nc_core->component->clear_cache();

        if ($this->logger) {
            $this->log_operation_result('component_update', [
                'component_id' => $component_id,
                'keyword' => $dataset['keyword'],
            ]);
        }

        return $component_id;
    }

    /**
     * Создание базового компонента (с полями)
     *
     * @param array $dataset
     * @return int ID компонента
     */
    protected function create_component(array $dataset) {
        $nc_core = nc_core::get_object();
        $component_id = null;

        try {
            // 1. Создание компонента через существующий API
            $component_properties = [
                'Keyword' => $dataset['keyword'],
                'Class_Name' => $dataset['name'] ?? $dataset['keyword'],
                'Class_Group' => $dataset['group'] ?? 'AI Generated',
                'File_Mode' => 1,  // шаблоны в файлах
                'Type' => 'useful',  // create() всё равно ставит useful; иной type отклоняет validate()
            ];

            if (!empty($dataset['DisableBlockListMarkup'])) {
                $component_properties['DisableBlockListMarkup'] = 1;
            }

            if (!empty($dataset['DisableBlockMarkup'])) {
                $component_properties['DisableBlockMarkup'] = 1;
            }

            if (isset($dataset['is_optimized_for_multiple_mode'])) {
                $component_properties['IsOptimizedForMultipleMode'] = (int)(bool)$dataset['is_optimized_for_multiple_mode'];
            }

            if (!empty($dataset['title_template']) && is_string($dataset['title_template'])) {
                $component_properties['TitleTemplate'] = $dataset['title_template'];
            }

            $component_id = $nc_core->component->create($component_properties);

            if (!$component_id) {
                throw new Exception("Failed to create component: " . $dataset['keyword']);
            }

            // 2. Добавление полей
            $this->add_component_fields($component_id, $dataset['fields']);

            // 3. Сброс кэша (ВАЖНО: до write_template_files, иначе get_by_id вернёт пустой File_Path)
            $nc_core->component->clear_cache();

            // 4. Запись шаблонов в файлы
            $this->write_template_files($component_id, $dataset);

            // Логирование
            if ($this->logger) {
                $this->log_operation_result('component_create', [
                    'component_id' => $component_id,
                    'keyword' => $dataset['keyword'],
                ]);
            }

            return $component_id;

        } catch (Exception $e) {
            // Откат при ошибке
            if ($component_id) {
                $this->cleanup_failed_component($component_id);
            }

            if ($this->logger) {
                $this->logger->set_error($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Создание шаблона компонента (наследует поля, имеет свой RecordTemplate)
     *
     * @param array $dataset
     * @return int ID шаблона
     */
    protected function create_component_template(array $dataset) {
        $nc_core = nc_core::get_object();

        try {
            // 1. Находим родительский компонент
            $parent_id = nc_db_table::make('Class')
                ->where('Keyword', $dataset['parent_component_keyword'])
                ->where('ClassTemplate', 0)  // только базовые компоненты
                ->get_value('Class_ID');

            if (!$parent_id) {
                throw new Exception("Parent component not found: " . $dataset['parent_component_keyword']);
            }

            // 2. Создаём шаблон через $nc_core->component->add()
            $template_properties = [
                'Keyword' => $dataset['keyword'],
                'File_Mode' => 1,
            ];

            if (!empty($dataset['DisableBlockListMarkup'])) {
                $template_properties['DisableBlockListMarkup'] = 1;
            }

            if (!empty($dataset['DisableBlockMarkup'])) {
                $template_properties['DisableBlockMarkup'] = 1;
            }

            if (isset($dataset['is_optimized_for_multiple_mode'])) {
                $template_properties['IsOptimizedForMultipleMode'] = (int)(bool)$dataset['is_optimized_for_multiple_mode'];
            }

            // TitleTemplate: явный из инструкции, иначе — родителя. Страница полного вывода
            // берёт его из ВАРИАНТА (Class_Template_ID инфоблока), а component->add() для
            // шаблонов дефолт `$f_Name` не ставит — title записи оставался title раздела.
            if (!empty($dataset['title_template']) && is_string($dataset['title_template'])) {
                $template_properties['TitleTemplate'] = $dataset['title_template'];
            } else {
                $parent_title_template = (string)nc_db_table::make('Class')
                    ->where('Class_ID', $parent_id)
                    ->get_value('TitleTemplate');
                if ($parent_title_template !== '') {
                    $template_properties['TitleTemplate'] = $parent_title_template;
                }
            }

            $template_id = $nc_core->component->add(
                $dataset['name'] ?? $dataset['keyword'],           // Class_Name
                $dataset['group'] ?? 'AI Generated Templates',     // Class_Group
                $template_properties,
                $parent_id,  // ClassTemplate — родительский компонент
                $this->component_type($dataset)  // Type: useful | object_partial | ...
            );

            if (!$template_id) {
                throw new Exception("Failed to create component template: " . $dataset['keyword']);
            }

            // 3. Сброс кэша (ВАЖНО: до write_template_files, иначе get_by_id вернёт пустой File_Path)
            $nc_core->component->clear_cache();

            // 4. Запись шаблонов в файлы
            $this->write_template_files($template_id, $dataset);

            // Логирование
            if ($this->logger) {
                $this->log_operation_result('component_template_create', [
                    'template_id' => $template_id,
                    'parent_id' => $parent_id,
                    'keyword' => $dataset['keyword'],
                    'parent_keyword' => $dataset['parent_component_keyword'],
                ]);
            }

            return $template_id;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->set_error($e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Тип компонента/варианта (Class.Type) из dataset; неизвестное значение → useful
     *
     * @param array $dataset
     * @return string
     */
    protected function component_type(array $dataset) {
        $allowed = array('useful', 'object_partial', 'responsive', 'mobile', 'multi_edit', 'title', 'xml', 'rss');
        $type = isset($dataset['type']) && is_string($dataset['type']) ? strtolower(trim($dataset['type'])) : '';
        return in_array($type, $allowed, true) ? $type : 'useful';
    }

    /**
     * Добавление полей компонента
     *
     * @param int $component_id
     * @param array $fields
     * @return void
     */
    protected function add_component_fields($component_id, array $fields) {
        $nc_core = nc_core::get_object();

        foreach ($fields as $field) {
            $nc_core->component->add_field($component_id, [
                'Field_Name' => $field['name'],
                'TypeOfData_ID' => (int)$field['type'],
                'Description' => $field['description'] ?? '',
                'Format' => $field['format'] ?? '',
                'NotNull' => (int)($field['not_null'] ?? 0),
                'DoSearch' => (int)($field['searchable'] ?? 0),
                'DefaultState' => '',
            ]);
        }
    }

    /**
     * Запись шаблонов в файловую систему
     *
     * @param int $component_id
     * @param array $dataset
     * @return void
     */
    protected function write_template_files($component_id, array $dataset) {
        $nc_core = nc_core::get_object();

        $file_path = $nc_core->CLASS_TEMPLATE_FOLDER .
                     trim($nc_core->component->get_by_id($component_id, 'File_Path'), '/') . '/';

        // RecordTemplate.html — основной шаблон вывода
        if (!empty($dataset['record_template'])) {
            file_put_contents($file_path . 'RecordTemplate.html', $dataset['record_template']);
        }

        // FormPrefix.html — префикс формы (кнопки администрирования на уровне инфоблока)
        if (!empty($dataset['form_prefix'])) {
            file_put_contents($file_path . 'FormPrefix.html', $dataset['form_prefix']);
        }

        // FormSuffix.html — суффикс формы (закрывающие теги обёртки списка)
        if (!empty($dataset['form_suffix'])) {
            file_put_contents($file_path . 'FormSuffix.html', $dataset['form_suffix']);
        }

        // Settings.html — инициализация компонента перед выводом (виджеты,
        // подготовка $menu_tree через nc_nav и т. п.)
        if (!empty($dataset['settings'])) {
            file_put_contents($file_path . 'Settings.html', $dataset['settings']);
        }

        // SiteStyles.css — стили компонента (append при update, overwrite при create)
        if (!empty($dataset['styles'])) {
            $styles_path = $file_path . 'SiteStyles.css';
            $existing = file_exists($styles_path) ? file_get_contents($styles_path) : '';
            if (!empty($existing) && !str_contains($existing, $dataset['styles'])) {
                file_put_contents($styles_path, $existing . "\n\n" . $dataset['styles']);
            } elseif (empty($existing)) {
                file_put_contents($styles_path, $dataset['styles']);
            }
        }

        // RecordTemplateFull.html — шаблон детального вывода объекта
        if (!empty($dataset['record_template_full'])) {
            file_put_contents($file_path . 'RecordTemplateFull.html', $dataset['record_template_full']);
        }

        // Обновление File_Hash для корректной работы кэширования
        $tpl = new nc_tpl(rtrim($nc_core->CLASS_TEMPLATE_FOLDER, '/'), $nc_core->db);
        $tpl->load($component_id, 'Class');
        nc_tpl_parser::parts2main($tpl);
    }

    /**
     * Очистка при неудачном создании компонента
     *
     * @param int $component_id
     * @return void
     */
    protected function cleanup_failed_component($component_id) {
        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        // Получаем File_Path ДО удаления записи
        try {
            $file_path = $nc_core->component->get_by_id($component_id, 'File_Path');
        } catch (Exception $e) {
            $file_path = null;
        }

        // Удаление записи компонента
        $db->query("DELETE FROM `Class` WHERE `Class_ID` = " . (int)$component_id);

        // Удаление полей
        $db->query("DELETE FROM `Field` WHERE `Class_ID` = " . (int)$component_id);

        // Удаление таблицы объектов
        $db->query("DROP TABLE IF EXISTS `Message{$component_id}`");

        // Удаление директории с файлами
        if ($file_path) {
            $full_path = $nc_core->CLASS_TEMPLATE_FOLDER . trim($file_path, '/');
            if (is_dir($full_path)) {
                nc_delete_dir($full_path);
            }
        }
    }

}
