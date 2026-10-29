<?php

if (!class_exists('nc_core')) {
    die;
}

/** @var nc_core $nc_core */
/** @var array $infoblock_data */
/** @var array $visibility_conditions */
/** @var array $infoblock_data */
/** @var array $custom_settings */
/** @var bool $show_visibility_tab */
/** @var bool $show_conditions_tab */

global $perm;

$show_mixins_tab =
    !$infoblock_data['Class_ID'] ||
    $nc_core->component->can_add_block_list_markup($infoblock_data['Class_Template_ID'] ?: $infoblock_data['Class_ID']);

// Загружаем слоты компонента для вкладки оформления
$component_slots = nc_tpl_component_slots::get_all_for_block($infoblock_data);

// Парсим настройки слотов из JSON и добавляем в $infoblock_data для mixin_editor
$slots_mixin_settings = json_decode($infoblock_data['Slots_Mixin_Settings'] ?? '{}', true) ?: array();
foreach ($slots_mixin_settings as $slot_keyword => $slot_settings) {
    $scope = 'Slot_' . $slot_keyword;
    if (isset($slot_settings['Mixin_Settings'])) {
        $infoblock_data[$scope . '_Mixin_Settings'] = $slot_settings['Mixin_Settings'];
    }
    if (isset($slot_settings['Mixin_Preset_ID'])) {
        $infoblock_data[$scope . '_Mixin_Preset_ID'] = $slot_settings['Mixin_Preset_ID'];
    }
    if (isset($slot_settings['Mixin_BreakpointType'])) {
        $infoblock_data[$scope . '_Mixin_BreakpointType'] = $slot_settings['Mixin_BreakpointType'];
    }
}

?>
<div class="nc-modal-dialog nc-infoblock-settings-dialog" data-focus="false">
    <div class="nc-modal-dialog-header">
        <h2><?= $infoblock_data['Class_ID'] ? $infoblock_data['Sub_Class_Name'] : NETCAT_INFOBLOCK_SETTINGS_TITLE_CONTAINER ?></h2>
    </div>
    <div class="nc-modal-dialog-body">
        <form action="<?= $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH ?>action.php" method="post" class="nc-form">

            <input type="hidden" name="ctrl" value="admin.infoblock">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="infoblock_id" value="<?= $infoblock_data['Sub_Class_ID'] ?>">

            <?php if ($custom_settings): ?>
            <div data-tab-id="settings" data-tab-caption="<?= NETCAT_INFOBLOCK_SETTINGS_TAB_CUSTOM ?>" class="custom_settings">
                <?= $custom_settings ?>
            </div>
            <?php endif; ?>

            <?php if ($show_mixins_tab): ?>
            <?php $show_index_item_mixins = $infoblock_data['Class_ID'] && $infoblock_data['MaxRecordsInInfoblock'] !== '0'; ?>
            <?php $has_multiple_tabs = $show_index_item_mixins || !empty($component_slots); ?>
            <div data-tab-id="mixins" data-tab-caption="<?= NETCAT_MIXIN_TITLE ?>">
                <?php if ($has_multiple_tabs): ?>
                <ul class="nc-tabs nc--small nc-margin-bottom-medium nc-infoblock-settings-dialog-mixin-tabs-labels">
                    <li class="nc--active" data-mixin-tab-id="index">
                        <a><?= NETCAT_MIXIN_INDEX ?></a>
                    </li>
                    <?php if ($show_index_item_mixins): ?>
                    <li data-mixin-tab-id="indexitem">
                        <a><?= NETCAT_MIXIN_INDEX_ITEM ?></a>
                    </li>
                    <?php endif; ?>
                    <?php foreach ($component_slots as $slot_keyword => $slot_config): ?>
                    <li data-mixin-tab-id="slot_<?= htmlspecialchars($slot_keyword) ?>">
                        <a><?= htmlspecialchars($slot_config['name']) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="nc-infoblock-settings-dialog-mixin-tabs">
                    <div data-mixin-tab-id="index">
                        <?= $this->include_view('../mixin/mixin_editor', array(
                                'show_preset_select' => true,
                                'show_breakpoint_type_select' => true,
                                'field_name_prefix' => 'Index',
                                'data' => $infoblock_data,
                        )) ?>
                    </div>
                    <?php if ($show_index_item_mixins): ?>
                    <div data-mixin-tab-id="indexitem" style="display: none">
                        <?= $this->include_view('../mixin/mixin_editor', array(
                                'show_preset_select' => true,
                                'show_breakpoint_type_select' => true,
                                'field_name_prefix' => 'IndexItem',
                                'data' => $infoblock_data,
                                'lazy' => true,
                        )) ?>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($component_slots as $slot_keyword => $slot_config): ?>
                    <div data-mixin-tab-id="slot_<?= htmlspecialchars($slot_keyword) ?>" style="display: none">
                        <?= $this->include_view('../mixin/mixin_editor', array(
                                'show_preset_select' => false,
                                'show_breakpoint_type_select' => true,
                                'field_name_prefix' => 'Slot_' . $slot_keyword,
                                'scope' => 'Slot_' . $slot_keyword,
                                'data' => $infoblock_data,
                                'slot_data' => $slot_config,
                                'lazy' => true,
                        )) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                (function() {
                    var dialog = nc.ui.modal_dialog.get_current_dialog(),
                        labels = dialog.find('.nc-infoblock-settings-dialog-mixin-tabs-labels > li'),
                        tabs = dialog.find('.nc-infoblock-settings-dialog-mixin-tabs > div');
                    labels.click(function() {
                        var clicked_label = $nc(this);
                        labels.removeClass('nc--active');
                        clicked_label.addClass('nc--active');
                        var tab = tabs.filter('[data-mixin-tab-id="' + clicked_label.data('mixin-tab-id') + '"]');
                        tabs.hide();
                        tab.show();
                        // Инициализация редактора миксинов, если ещё не был инициализирован
                        // (на случай, если фоновая инициализация не успела)
                        nc_init_lazy_mixin_editors(tab);
                        return false;
                    });

                    // Фоновая инициализация скрытых редакторов миксинов после открытия модалки
                    var lazy_editors = dialog.find('[data-lazy-mixin-editor]').toArray();
                    (function init_next() {
                        var el = lazy_editors.shift();
                        if (!el) return;
                        setTimeout(function() {
                            // Проверяем, что ещё не был инициализирован (например, по клику на вкладку)
                            if (el._nc_init_mixin_editor) {
                                el._nc_init_mixin_editor();
                                delete el._nc_init_mixin_editor;
                                el.removeAttribute('data-lazy-mixin-editor');
                            }
                            init_next();
                        }, 50);
                    })();
                })();
            </script>
            <?php endif; ?>

            <?php if (!$infoblock_data['Subdivision_ID'] && $show_visibility_tab): ?>
            <div data-tab-id="visibility" data-tab-caption="<?= NETCAT_INFOBLOCK_SETTINGS_TAB_VISIBILITY ?>">
                <?= $this->include_view('settings_dialog/visibility_conditions', array(
                        'infoblock_data' => $infoblock_data,
                        'visibility_conditions' => $visibility_conditions,
                )) ?>
            </div>
            <?php endif; ?>

            <div data-tab-id="others" data-tab-caption="<?= NETCAT_INFOBLOCK_SETTINGS_TAB_OTHERS ?>">
                <?= $this->include_view('settings_dialog/others', array(
                    'infoblock_data' => $infoblock_data,
                    'isSupervisor' => $perm->isSupervisor(),
                )) ?>
            </div>

            <div data-tab-id="code_settings" data-tab-caption="<?= NETCAT_CODE_SETTINGS_TITLE ?>">
                <?= $this->include_view('settings_dialog/code_settings', array(
                    'infoblock_data' => $infoblock_data,
                )) ?>
            </div>

            <?php if ($show_conditions_tab): ?>
            <div data-tab-id="conditions" data-tab-caption="<?= NETCAT_CONDITION_FIELD ?>">
                <?= $this->include_view('settings_dialog/conditions', array(
                    'infoblock_data' => $infoblock_data,
                )) ?>
            </div>
            <?php endif; ?>

<!--            <div data-tab-id="loading" data-tab-caption="--><?//= NETCAT_INFOBLOCK_SETTINGS_TAB_LOADING ?><!--">-->
<!--            </div>-->

        </form>
    </div>
    <div class="nc-modal-dialog-footer">
        <button data-action="submit"><?= NETCAT_REMIND_SAVE_SAVE ?></button>
        <button data-action="close"><?= CONTROL_BUTTON_CANCEL ?></button>
    </div>
</div>
