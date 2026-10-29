<?php
/**
 * Форма редактирования/добавления скрипта
 */

global $UI_CONFIG, $site_id, $snippet_id, $type_id, $phase, $type_names;

$nc_core = nc_core::get_object();
$input = $nc_core->input;

// Получаем все доступные типы
$available_types = nc_external_scripts_snippet::get_available_types();

// Загружаем данные сниппета для редактирования
$snippet = null;
$settings = [];
$conditions = [];
$name = '';
$enabled = 1;

if ($snippet_id) {
    $snippet = nc_external_scripts_snippet::get_by_id($snippet_id);
    if ($snippet) {
        $name = $snippet->get_name();
        $settings = $snippet->get_settings();
        $enabled = $snippet->is_enabled() ? 1 : 0;
        $type_info_row = nc_db()->get_row(
            "SELECT * FROM `ExternalScripts_Snippet` WHERE `Snippet_ID` = " . $snippet_id,
            ARRAY_A
        );
        if ($type_info_row) {
            $type_id = (int)$type_info_row['SnippetType_ID'];
            $conditions = json_decode($type_info_row['Conditions'] ?: '[]', true);
        }
    }
}

// Получаем информацию о типе
$type_info = null;
$snippet_instance = null;
$settings_fields = [];

if ($type_id) {
    $type_info = nc_external_scripts_snippet::get_type_info($type_id);
    if ($type_info && class_exists($type_info['SnippetType_Class'])) {
        $class_name = $type_info['SnippetType_Class'];
        $snippet_instance = new $class_name();
        $settings_fields = $snippet_instance->get_settings_fields();
    }

    // Предзаполнение названия для новых скриптов
    if (!$snippet_id && $type_info) {
        $name = $type_names[$type_info['SnippetType_Name']] ?? $type_info['SnippetType_Name'];
    }
}

// Если добавление и тип не выбран — показываем выбор типа
if ($phase === 'add' && !$type_id):
?>
<div class="nc-script-types">
    <?php foreach ($available_types as $type): ?>
        <?php
        $tid = $type['SnippetType_ID'];
        $tname = $type_names[$type['SnippetType_Name']] ?? $type['SnippetType_Name'];
        ?>
        <a href="#" onclick="parent.urlDispatcher.load('module.external_scripts.add.type(<?= $site_id ?>,<?= $tid ?>)'); return false;" class="nc-script-type-card">
            <span class="nc-script-type-name"><?= htmlspecialchars($tname) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<style>
.nc-script-types {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}
.nc-script-type-card {
    display: block;
    padding: 20px 30px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    text-align: center;
    min-width: 180px;
    transition: all 0.2s;
}
.nc-script-type-card:hover {
    background: #e9ecef;
    border-color: #007bff;
    color: #007bff;
}
.nc-script-type-name {
    font-weight: 500;
}
</style>

<?php else: ?>

<form method="post" action="admin.php" class="nc-form">
    <?= $nc_core->token->get_input() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">
    <input type="hidden" name="snippet_id" value="<?= $snippet_id ?>">
    <input type="hidden" name="type_id" value="<?= $type_id ?>">
    <input type="submit" class="hidden">

    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <?= NETCAT_MODULE_EXTSCRIPTS_NAME ?>:<br>
                <?= nc_admin_input_simple('name', $name, 70, '', "maxlength='255' required") ?>
            </td>
        </tr>
        <tr>
            <td>
                <?= nc_admin_checkbox_simple('enabled', 1, NETCAT_MODULE_EXTSCRIPTS_ENABLED, $enabled) ?>
            </td>
        </tr>
    </table>

    <br>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <?php
                // Текущая категория: сохранённое значение либо дефолт по типу
                // сниппета (для произвольного кода — «не собирает данные»).
                // Псевдо-пункта «по умолчанию для типа» нет — сразу конкретная категория.
                $cookie_category = (isset($settings['cookie_category']) && $settings['cookie_category'] !== '')
                    ? $settings['cookie_category']
                    : ($snippet_instance ? $snippet_instance->get_cookie_category() : nc_consent::CATEGORY_NONE);
                ?>
                <?= NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY ?>:<br>
                <select name="settings[cookie_category]" id="settings_cookie_category">
                    <?php foreach (array(
                        'none'       => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_NONE,
                        'functional' => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_FUNCTIONAL,
                        'analytics'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_ANALYTICS,
                        'marketing'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_MARKETING,
                    ) as $cat_value => $cat_label): ?>
                        <option value="<?= $cat_value ?>" <?= $cookie_category === $cat_value ? 'selected' : '' ?>><?= htmlspecialchars($cat_label) ?></option>
                    <?php endforeach; ?>
                </select>
                <br><small style="color: #666;"><?= NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY_HINT ?></small>
            </td>
        </tr>
    </table>

    <?php if (!empty($settings_fields)): ?>
    <br>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <?php foreach ($settings_fields as $field_name => $field): ?>
            <?php
            $field_id = 'settings_' . $field_name;
            $field_value = $settings[$field_name] ?? ($field['default'] ?? '');
            $required = !empty($field['required']) ? 'required' : '';
            $depends_on = !empty($field['depends_on']) ? 'data-depends-on="settings_' . $field['depends_on'] . '"' : '';
            ?>
            <tr <?= $depends_on ?>>
                <td>
                    <?php if ($field['type'] === 'text'): ?>
                        <?= $field['label'] ?>:<br>
                        <?= nc_admin_input_simple(
                            "settings[{$field_name}]",
                            $field_value,
                            70,
                            '',
                            "id='{$field_id}' placeholder='" . htmlspecialchars($field['placeholder'] ?? '') . "' {$required}"
                        ) ?>
                        <?php if (!empty($field['description'])): ?>
                            <br><small style="color: #666;"><?= $field['description'] ?></small>
                        <?php endif; ?>

                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <?= $field['label'] ?>:<br>
                        <textarea name="settings[<?= $field_name ?>]" id="<?= $field_id ?>"
                                  rows="<?= $field['rows'] ?? 5 ?>" cols="70"
                                  style="font-family: monospace;" <?= $required ?>><?= htmlspecialchars($field_value) ?></textarea>

                    <?php elseif ($field['type'] === 'checkbox'): ?>
                        <input type="hidden" name="settings[<?= $field_name ?>]" value="0">
                        <?= nc_admin_checkbox_simple(
                            "settings[{$field_name}]",
                            1,
                            $field['label'],
                            $field_value,
                            $field_id
                        ) ?>

                    <?php elseif ($field['type'] === 'select'): ?>
                        <?= $field['label'] ?>:<br>
                        <select name="settings[<?= $field_name ?>]" id="<?= $field_id ?>" <?= $required ?>>
                            <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                <option value="<?= htmlspecialchars($opt_value) ?>"
                                    <?= $field_value == $opt_value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt_label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <br><br>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <br>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <?php
                $condition_type = $conditions['type'] ?? 'all';
                $condition_subdivisions = $conditions['subdivision_ids'] ?? [];
                $condition_children = $conditions['include_children'] ?? true;
                ?>
                <label>
                    <input type="radio" name="conditions[type]" value="all"
                        <?= $condition_type === 'all' ? 'checked' : '' ?>>
                    <?= NETCAT_MODULE_EXTSCRIPTS_CONDITION_ALL ?>
                </label><br>
                <label>
                    <input type="radio" name="conditions[type]" value="include"
                        <?= $condition_type === 'include' ? 'checked' : '' ?>>
                    <?= NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE ?>
                </label><br>
                <label>
                    <input type="radio" name="conditions[type]" value="exclude"
                        <?= $condition_type === 'exclude' ? 'checked' : '' ?>>
                    <?= NETCAT_MODULE_EXTSCRIPTS_CONDITION_EXCLUDE ?>
                </label>
            </td>
        </tr>
        <tr id="subdivisions_row" style="<?= $condition_type === 'all' ? 'display:none' : '' ?>">
            <td>
                <br>
                <?= NETCAT_MODULE_EXTSCRIPTS_CONDITION_SELECT ?>:<br>
                <?php
                // Получаем все разделы сайта в плоский список
                $all_subdivisions = nc_db()->get_results(
                    "SELECT Subdivision_ID, Subdivision_Name, Parent_Sub_ID
                     FROM Subdivision
                     WHERE Catalogue_ID = " . (int)$site_id . "
                     ORDER BY Priority, Subdivision_ID",
                    ARRAY_A
                );

                // Строим дерево для отображения
                function build_subdivision_tree($subdivisions, $parent_id = 0) {
                    $tree = [];
                    foreach ($subdivisions as $sub) {
                        if ((int)$sub['Parent_Sub_ID'] === $parent_id) {
                            $children = build_subdivision_tree($subdivisions, (int)$sub['Subdivision_ID']);
                            $sub['children'] = $children;
                            $tree[] = $sub;
                        }
                    }
                    return $tree;
                }

                function render_subdivision_options_flat($tree, $selected, $level = 0) {
                    $html = '';
                    foreach ($tree as $sub) {
                        $prefix = str_repeat('— ', $level);
                        $is_selected = in_array($sub['Subdivision_ID'], $selected) ? 'selected' : '';
                        $html .= "<option value='{$sub['Subdivision_ID']}' {$is_selected}>";
                        $html .= htmlspecialchars($prefix . $sub['Subdivision_ID'] . '. ' . $sub['Subdivision_Name']);
                        $html .= "</option>";
                        if (!empty($sub['children'])) {
                            $html .= render_subdivision_options_flat($sub['children'], $selected, $level + 1);
                        }
                    }
                    return $html;
                }

                $subdivision_tree = build_subdivision_tree($all_subdivisions ?: []);
                ?>
                <select name="conditions[subdivision_ids][]" id="subdivision_ids" multiple
                        size="8" style="width: 400px;">
                    <?= render_subdivision_options_flat($subdivision_tree, $condition_subdivisions) ?>
                </select>
                <br>
                <input type="hidden" name="conditions[include_children]" value="0">
                <?= nc_admin_checkbox_simple(
                    'conditions[include_children]',
                    1,
                    NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE_CHILDREN,
                    $condition_children
                ) ?>
            </td>
        </tr>
    </table>

    <br>
    <input type="button" id="preview_btn" class="nc-btn" value="<?= NETCAT_MODULE_EXTSCRIPTS_PREVIEW ?>" onclick="togglePreview();">

    <div id="preview_container" style="display: none; margin-top: 15px;">
        <div id="preview_content"></div>
    </div>
</form>

<script>
function togglePreview() {
    var container = document.getElementById('preview_container');
    var content = document.getElementById('preview_content');
    var btn = document.getElementById('preview_btn');

    if (container.style.display === 'none') {
        // Собираем текущие значения настроек из формы
        var settings = {};
        var form = document.querySelector('form.nc-form');
        var inputs = form.querySelectorAll('[name^="settings["]');
        inputs.forEach(function(input) {
            var match = input.name.match(/settings\[([^\]]+)\]/);
            if (match) {
                var key = match[1];
                if (input.type === 'checkbox') {
                    settings[key] = input.checked ? '1' : '0';
                } else {
                    settings[key] = input.value;
                }
            }
        });

        // AJAX-запрос для генерации превью
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'admin.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        content.innerHTML = response.html;
                    } else {
                        content.innerHTML = '<p style="color: red;">' + (response.error || 'Error') + '</p>';
                    }
                } catch (e) {
                    content.innerHTML = '<p style="color: red;">Error parsing response</p>';
                }
            }
        };

        var params = 'action=preview&type_id=<?= $type_id ?>&' + encodeSettings(settings);
        xhr.send(params);

        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

function encodeSettings(settings) {
    var parts = [];
    for (var key in settings) {
        parts.push('settings[' + encodeURIComponent(key) + ']=' + encodeURIComponent(settings[key]));
    }
    return parts.join('&');
}

(function() {
    // Показ/скрытие выбора разделов
    var radios = document.querySelectorAll('input[name="conditions[type]"]');
    var subdivRow = document.getElementById('subdivisions_row');

    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            subdivRow.style.display = (this.value === 'all') ? 'none' : '';
        });
    });

    // Зависимые поля
    var dependentRows = document.querySelectorAll('[data-depends-on]');
    dependentRows.forEach(function(row) {
        var dependsOn = row.getAttribute('data-depends-on');
        var parentCheckbox = document.getElementById(dependsOn);
        if (parentCheckbox) {
            function updateVisibility() {
                row.style.display = parentCheckbox.checked ? '' : 'none';
            }
            parentCheckbox.addEventListener('change', updateVisibility);
            updateVisibility();
        }
    });
})();
</script>

<style>
#preview_btn:focus {
    outline: none;
    background-color: #7a7a7a;
}
</style>

<?php endif; ?>
