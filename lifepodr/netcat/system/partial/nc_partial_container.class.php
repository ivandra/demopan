<?php

/**
 * Контейнер для блоков (инфоблок с Class_ID = 0).
 * Выводит блоки с Parent_Sub_Class_ID, равным идентификатору контейнера.
 */
class nc_partial_container extends nc_partial {

    /** @var string префикс комментария (должен быть определён в классе-наследнике */
    protected $partial_comment_id_prefix = 'c';
    /** @var int счётчик вложенных фрагментов с отложенной загрузкой (используется в ID комментария) */
    static protected $partial_last_sequence_number = 0;
    /** @var int счётчик вложенных вызовов (фрагмент внутри фрагмента) */
    static protected $partial_nesting_level = 0;

    /** @var int */
    protected $container_id;

    /**
     * nc_partial_container constructor.
     *
     * @param int $container_id
     * @param array $data
     */
    public function __construct($container_id, array $data = array()) {
        $this->container_id = (int)$container_id;
        $this->set_data($data);
    }

    /**
     * @return string
     */
    public function get_content() {
        $nc_core = nc_core::get_object();
        $is_edit_mode = $nc_core->admin_mode && !$nc_core->inside_admin;

        $container_id = $this->container_id;
        $container_properties = $nc_core->sub_class->get_by_id($container_id);
        $container_has_mixin_settings = $this->has_mixin_settings($container_properties);
        $sub = $nc_core->subdivision->get_current('Subdivision_ID');
        $area = $container_properties['AreaKeyword'];

        $can_edit = $can_add_blocks_outside = false;
        if ($is_edit_mode) {
            /** @var Permission $perm */
            global $perm;
            $can_edit = $perm->isSubClassAdmin($container_id);
            if ($container_properties['Parent_Sub_Class_ID']) {
                $can_add_blocks_outside = $perm->isSubClassAdmin($container_properties['Parent_Sub_Class_ID']);
            } else if ($container_properties['Subdivision_ID']) {
                $can_add_blocks_outside = $perm->isSubdivisionAdmin($container_properties['Subdivision_ID']);
            } else {
                $can_add_blocks_outside = $perm->isCatalogueAllBlocksAdmin($container_properties['Catalogue_ID']);
            }
        }

        $blocks = $nc_core->sub_class->get_by_container_id($container_id);
        if (!$nc_core->admin_mode) {
            $blocks = array_filter($blocks, function($block) { return $block['Checked']; });
        }

        if (!$blocks && !$can_edit && !$container_has_mixin_settings) {
            return '';
        }

        $container_breakpoints = '';

        $mixins_css_class = 'tpl-container-' . $container_id;
        $mixins_list_css_class = $mixins_css_class . '-list';
        if ($container_has_mixin_settings) {
            $nc_core->page->has_mixins(true);
            nc_tpl_mixin_cache::load_block_data($container_properties['Catalogue_ID'], $container_id);
            $container_breakpoints = $nc_core->sub_class->get_meta($container_id, 'Index_Breakpoints');
        }

        $code_settings = nc_code_settings::parse($container_properties['Code_Settings'] ?? null);
        $container_tag = $code_settings['tag'];

        // --- Корневой тег ---
        $root_classes = ($is_edit_mode ? 'nc-container ' : '')
            . 'tpl-container ' . $mixins_css_class
            . ($blocks ? '' : ' nc--empty')
            . $code_settings['class_attr'];

        $result = '<' . $container_tag
            . ' class="' . $root_classes . '"'
            . ' id="' . ($code_settings['id'] ?? $container_properties['EnglishName']) . '"';

        // data-object-name (для вложенных контейнеров с expose_children_object_names)
        if ($container_properties['Parent_Sub_Class_ID'] &&
            $nc_core->sub_class->get_meta($container_properties['Parent_Sub_Class_ID'], 'expose_children_object_names')
        ) {
            $result .= ' data-object-name="' . htmlspecialchars(
                $container_properties['Sub_Class_Name'] ?: $container_properties['Class_Name']
            ) . '"';
        }

        if ($container_breakpoints) {
            $result .= ' data-nc-b="' . $container_breakpoints . '"';
        }

        // Edit mode: data-nc атрибуты
        if ($is_edit_mode) {
            $nc_properties = json_encode(array(
                'type' => 'container',
                'cc' => $container_id,
                'sub' => $sub,
                'area' => $area,
                'can_edit' => $can_edit,
                'can_add_outside' => $can_add_blocks_outside,
            ), JSON_UNESCAPED_UNICODE);
            $result .= ' data-nc-type="container"';
            $result .= " data-nc-properties='" . htmlspecialchars($nc_properties, ENT_QUOTES) . "'";
        }

        $result .= $code_settings['style_attr'] . '>';

        // Серверные тулбары, border и insert toolbar — только в edit mode
        if ($can_add_blocks_outside) {
            $result .= nc_admin_infoblock_insert_toolbar($sub, $area, $container_properties['Parent_Sub_Class_ID'], 'before', $container_id);
        }

        if ($is_edit_mode) {
            $result .= '<div class="nc-container-border"></div>';
            if ($can_edit) {
                $result .= nc_AdminCommonMultiBlock($container_id, $sub, false);
            }
        }

        // --- tpl-block-list div ---
        // Всегда присутствует: миксины (padding, background, layout) привязаны к .tpl-block-list
        $result .= '<div class="tpl-block-list' . $code_settings['inner_class_attr'] . '">';

        // --- tpl-block-list-objects div ---
        if ($blocks) {
            $list_classes = 'tpl-block-list-objects ' . trim($mixins_list_css_class . $code_settings['list_class_attr']);
            $result .= '<div class="' . $list_classes . '" data-object-count="' . count($blocks) . '">';

            foreach ($blocks as $block) {
                $result .= nc_objects_list(0, $block['Sub_Class_ID'], $this->data);
            }

            $result .= '</div>';
        } else if ($is_edit_mode && $can_edit) {
            $result .= '<div class="tpl-block-list-objects ' . trim($mixins_list_css_class) . '">';
            $result .= nc_admin_infoblock_insert_toolbar($sub, $area, $container_id, 'first', 0);
            $result .= '</div>';
        }

        $result .= '</div>'; // .tpl-block-list

        if ($can_add_blocks_outside) {
            $result .= nc_admin_infoblock_insert_toolbar($sub, $area, $container_properties['Parent_Sub_Class_ID'], 'after', $container_id);
        }

        $result .= '</' . $container_tag . '>';

        return $result;
    }

    /**
     * @return int
     */
    protected function get_src() {
        return $this->container_id;
    }

}