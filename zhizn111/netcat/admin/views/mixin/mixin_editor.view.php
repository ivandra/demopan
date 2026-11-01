<?php

if (!class_exists('nc_core')) {
    die;
}

/**
 * @var string $field_name_template  шаблон имён полей (по умолчанию: 'data[%s]')
 * @var string $field_name_prefix    префикс имени свойств (например, 'MainArea'; по умолчанию: '')
 * @var string $scope                тип области применения миксинов (по умолчанию равен $field_name_prefix)
 * @var bool $show_preset_select     показывать выбор пресета? (редактирование пресета инфоблока)
 * @var bool $show_breakpoint_type_select показывать выбор типа брейкпоинта? (инфоблок)
 * @var string $container_id         ID div’а, где будет размещён редактор
 *   (по умолчанию генерируется уникальный ID блока — рекомендуется не менять)
 *
 * @var array $data параметры:
 *      — (Prefix_)Mixin_Settings — настройки в виде JSON-строки
 *      Для инфоблока:
 *      — Class_Template_ID || Class_ID
 *      — Sub_Class_ID
 *      — (Prefix_)Mixin_Preset_ID
 *      — (Prefix_)Mixin_BreakpointType
 */


// Структура массива с настройками (пресет):
// селектор → тип (группа) миксинов → ширина «до» : { mixin => ключевое слово миксина, settings => настройки }
// $mixin_settings = array(
//    '' => array(
//        'visibility' => array(
//            600 => array(
//                'mixin' => 'netcat_visibility_hide',
//            ),
//            1200 => array(
//                'mixin' => 'netcat_visibility_hide',
//            ),
//        ),
//        'layout' => array(
//            // ----0----
//            300 => array(
//                'mixin' => '',
//            ),
//            9999 => array(
//                'mixin' => 'netcat_layout_tiles',
//                'settings' => array(
//                    'min_tile_width' => '500',
//                    'objects' => array(
//                        '1' => array('width' => 'MIXIN_SETTINGS_WIDTH')
//                    ),
//                ),
//            ),
//        )
//    ),
//    // АЛЬТ КЛАСС
//    '.tile-wide' => array(),
//);

$nc_core = nc_core::get_object();

// Получаем палитру сайта для передачи в JavaScript
$_nc_site_palette = null;
$_nc_catalogue_id = $nc_core->catalogue->id();
if (!$_nc_catalogue_id) {
    $_nc_catalogue_id = isset($GLOBALS['catalogue']) ? (int)$GLOBALS['catalogue'] : 0;
}
if (!$_nc_catalogue_id) {
    $_nc_catalogue_id = isset($GLOBALS['current_catalogue']) ? (int)$GLOBALS['current_catalogue'] : 0;
}
if (!$_nc_catalogue_id) {
    $_nc_catalogue_id = (int)$nc_core->input->fetch('catalogue', 'ig');
}
if (!$_nc_catalogue_id) {
    $_nc_catalogue_id = (int)$nc_core->input->fetch('site', 'ig');
}
// Пытаемся получить Catalogue_ID из данных инфоблока или раздела
if (!$_nc_catalogue_id && !empty($data)) {
    if (!empty($data['Catalogue_ID'])) {
        $_nc_catalogue_id = (int)$data['Catalogue_ID'];
    } elseif (!empty($data['Subdivision_ID'])) {
        $_nc_catalogue_id = (int)$nc_core->subdivision->get_by_id($data['Subdivision_ID'], 'Catalogue_ID');
    } elseif (!empty($data['Sub_Class_ID'])) {
        $sub_id = $nc_core->sub_class->get_by_id($data['Sub_Class_ID'], 'Subdivision_ID');
        if ($sub_id) {
            $_nc_catalogue_id = (int)$nc_core->subdivision->get_by_id($sub_id, 'Catalogue_ID');
        }
    }
}
// Проверяем infoblock_id из входных данных
if (!$_nc_catalogue_id) {
    $_nc_infoblock_id = (int)$nc_core->input->fetch('infoblock', 'ig');
    if (!$_nc_infoblock_id) {
        $_nc_infoblock_id = (int)$nc_core->input->fetch('sub_class_id', 'ig');
    }
    if ($_nc_infoblock_id) {
        $sub_id = $nc_core->sub_class->get_by_id($_nc_infoblock_id, 'Subdivision_ID');
        if ($sub_id) {
            $_nc_catalogue_id = (int)$nc_core->subdivision->get_by_id($sub_id, 'Catalogue_ID');
        }
    }
}
// Проверяем subdivision из входных данных
if (!$_nc_catalogue_id) {
    $_nc_sub_input = (int)$nc_core->input->fetch('sub', 'ig');
    if (!$_nc_sub_input) {
        $_nc_sub_input = (int)$nc_core->input->fetch('subdivision', 'ig');
    }
    if ($_nc_sub_input) {
        $_nc_catalogue_id = (int)$nc_core->subdivision->get_by_id($_nc_sub_input, 'Catalogue_ID');
    }
}
// Устанавливаем в GLOBALS для доступа из BlockSettings.html миксинов
if ($_nc_catalogue_id > 0 && !isset($GLOBALS['catalogue'])) {
    $GLOBALS['catalogue'] = $_nc_catalogue_id;
}
if ($_nc_catalogue_id > 0) {
    // Получаем палитру из Catalogue.ncColorList (новое место хранения)
    $_nc_palette_json = $nc_core->catalogue->get_by_id($_nc_catalogue_id, 'ncColorList');
    if ($_nc_palette_json) {
        $_nc_palette_decoded = json_decode($_nc_palette_json, true);
        // Если результат - строка, значит было двойное кодирование, парсим ещё раз
        if (is_string($_nc_palette_decoded)) {
            $_nc_palette_decoded = json_decode($_nc_palette_decoded, true);
        }
        $_nc_site_palette = array(
            'palette' => $_nc_palette_decoded ?: array(),
            'site_id' => $_nc_catalogue_id
        );
    }

    // Получаем роли из миксина netcat_color_palette (они хранятся там)
    $_nc_site_mixin_settings = $nc_core->catalogue->get_by_id($_nc_catalogue_id, 'MainArea_Mixin_Settings');
    if ($_nc_site_mixin_settings) {
        $_nc_site_mixin_settings = json_decode($_nc_site_mixin_settings, true);
        if (is_array($_nc_site_mixin_settings)) {
            foreach ($_nc_site_mixin_settings as $_selector => $_selectorData) {
                if (!is_array($_selectorData) || !isset($_selectorData['color'])) continue;
                foreach ($_selectorData['color'] as $_bp => $_bpData) {
                    if (isset($_bpData['mixin']) && $_bpData['mixin'] === 'netcat_color_palette' && isset($_bpData['settings'])) {
                        // Если палитра ещё не загружена из ncColorList, берём из миксина
                        if (empty($_nc_site_palette['palette']) && !empty($_bpData['settings']['palette'])) {
                            $_nc_site_palette = $_bpData['settings'];
                            $_nc_site_palette['site_id'] = $_nc_catalogue_id;
                            // Если palette - строка, парсим как JSON
                            if (is_string($_nc_site_palette['palette'])) {
                                $_nc_site_palette['palette'] = json_decode($_nc_site_palette['palette'], true) ?: array();
                            }
                        }
                        // Добавляем роли если они есть
                        if (!empty($_bpData['settings']['roles'])) {
                            if (!$_nc_site_palette) {
                                $_nc_site_palette = array('site_id' => $_nc_catalogue_id);
                            }
                            $_nc_site_palette['roles'] = $_bpData['settings']['roles'];
                            // Если roles - строка, парсим как JSON
                            if (is_string($_nc_site_palette['roles'])) {
                                $_nc_site_palette['roles'] = json_decode($_nc_site_palette['roles'], true) ?: array();
                            }
                        }
                        break 2;
                    }
                }
            }
        }
    }

    // Гарантируем наличие site_id в site_palette
    if ($_nc_site_palette && !isset($_nc_site_palette['site_id'])) {
        $_nc_site_palette['site_id'] = $_nc_catalogue_id;
    }
}

$container_id = isset($container_id)
    ? htmlspecialchars($container_id)
    : "nc_mixins_editor_container_{$_SERVER['REQUEST_TIME']}_" . rand(0, PHP_INT_MAX);

$field_name_template = isset($field_name_template) ? $field_name_template : 'data[%s]';
$field_name_prefix = isset($field_name_prefix) ? $field_name_prefix : '';
$settings_prefix = $field_name_prefix ? $field_name_prefix . '_' : '';

$scope = isset($scope) ? $scope : $field_name_prefix;

if (!empty($show_preset_select) && !empty($data['Sub_Class_ID']) && $field_name_prefix) {
    $mixin_presets = $nc_core->sub_class->get_mixin_preset_options($data['Sub_Class_ID'], $field_name_prefix);
} else {
    $mixin_presets = array();
}

// шаблон подключается на странице только один раз (при необходимости используется повторно)
require_once(__DIR__ . '/mixin_editor_template.view.php');

// Глобальная функция для инициализации lazy-редакторов внутри контейнера (определяется один раз)
static $_nc_lazy_mixin_helper_defined;
if (!$_nc_lazy_mixin_helper_defined):
    $_nc_lazy_mixin_helper_defined = true;
?>
<script>
function nc_init_lazy_mixin_editors(container) {
    $nc(container).find('[data-lazy-mixin-editor]').each(function() {
        if (this._nc_init_mixin_editor) {
            this._nc_init_mixin_editor();
            delete this._nc_init_mixin_editor;
            this.removeAttribute('data-lazy-mixin-editor');
        }
    });
}
</script>
<?php endif;

?>

<?php
    $lazy = !empty($lazy);

    // Вычисляем subdivision_id заранее, чтобы использовать и в lazy, и в обычном режиме
    $_nc_sub_id = nc_array_value($data, 'Subdivision_ID');
    if (!$_nc_sub_id && isset($GLOBALS['SubdivisionID']) && $GLOBALS['SubdivisionID'] > 0) {
        $_nc_sub_id = (int)$GLOBALS['SubdivisionID'];
    }
    if (!$_nc_sub_id && isset($GLOBALS['sub']) && $GLOBALS['sub'] > 0) {
        $_nc_sub_id = (int)$GLOBALS['sub'];
    }
?>

<div id="<?= $container_id ?>"<?php if ($lazy): ?> data-lazy-mixin-editor="1"<?php endif; ?>></div>

<script>
(function() {
    // Устанавливаем глобальный кэш палитры для ncPalettePicker
    <?php if ($_nc_site_palette): ?>
    if (!window.ncSiteColorList) {
        window.ncSiteColorList = <?= json_encode($_nc_site_palette, JSON_UNESCAPED_UNICODE) ?>;
    }
    <?php endif; ?>

    function init() {
        new nc_mixin_settings_editor({
            target: '#<?= $container_id ?>',
            field_name_template: <?= json_encode($field_name_template) ?>,
            field_name_prefix: <?= json_encode($field_name_prefix) ?>,
            scope: <?= json_encode($scope ?: 'Index') ?>,
            component_template_id: '<?= nc_array_value($data, 'Class_Template_ID') ?: nc_array_value($data, 'Class_ID') ?>',
            infoblock_id: <?= nc_array_value($data, 'Sub_Class_ID', 'null') ?>,
            own_settings: <?= nc_array_value($data, $settings_prefix . 'Mixin_Settings') ?: '{}' ?>,
            breakpoint_type: '<?= nc_array_value($data, $settings_prefix . 'Mixin_BreakpointType', 'block') ?>',
            mixin_presets: <?= nc_array_json($mixin_presets) ?>,
            show_preset_select: <?= !empty($show_preset_select) ? 'true' : 'false' ?>,
            show_breakpoint_type_select: <?= !empty($show_breakpoint_type_select) ? 'true' : 'false' ?>,
            slot_allowed_types: <?= !empty($slot_data['scopes']) ? nc_array_json($slot_data['scopes']) : '[]' ?>,
            site_color_list: <?= $_nc_site_palette ? json_encode($_nc_site_palette, JSON_UNESCAPED_UNICODE) : 'null' ?>,
            site_id: <?= $_nc_catalogue_id > 0 ? $_nc_catalogue_id : 'null' ?>,
            subdivision_id: <?= $_nc_sub_id ? $_nc_sub_id : 'null' ?>
        });
    }

    <?php if ($lazy): ?>
    document.getElementById('<?= $container_id ?>')._nc_init_mixin_editor = init;
    <?php else: ?>
    init();
    <?php endif; ?>
})();
</script>
