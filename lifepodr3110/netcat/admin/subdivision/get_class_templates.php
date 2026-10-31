<?php

/* $Id: get_class_templates.php 7609 2012-07-11 06:02:25Z alive $ */

$_POST["NC_HTTP_REQUEST"] = true;

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)).( strstr(__FILE__, "/") ? "/" : "\\" );
include_once ($NETCAT_FOLDER."vars.inc.php");
require_once ($ADMIN_FOLDER."function.inc.php");

if (!isset($class_id)) trigger_error("Wrong params", E_USER_ERROR);

$class_id += 0;
$selected_id += 0;
$catalogue_id += 0;
$is_mirror += 0;

if ($is_mirror) {
    $class_id = $nc_core->sub_class->get_by_id($class_id, 'Class_ID');
}

// Если selected_id не указан (создание нового блока), использовать Main_ClassTemplate_ID компонента
$main_class_template_id = 0;
if ($class_id) {
    $main_class_template_id = (int)$nc_core->component->get_by_id($class_id, 'Main_ClassTemplate_ID');
}
if (!$selected_id && $main_class_template_id) {
    $selected_id = $main_class_template_id;
}

if (false && $catalogue_id) $mobile = $nc_core->catalogue->get_by_id($catalogue_id, 'ncMobile');
$template_types = $mobile ? array('mobile') : array('useful', 'title', 'mobile');
$classTemplatesArr = $nc_core->component->get_component_templates($class_id, $template_types);

// Если Main_ClassTemplate_ID установлен, но его нет в списке (например, многоцелевой шаблон) - добавить его
if ($main_class_template_id) {
    $found = false;
    foreach ($classTemplatesArr as $tpl) {
        if ($tpl['Class_ID'] == $main_class_template_id) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        try {
            $main_template_data = $nc_core->component->get_by_id($main_class_template_id);
            if ($main_template_data) {
                array_unshift($classTemplatesArr, $main_template_data);
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

if (!empty($classTemplatesArr)) {
    echo "<br/><font color='gray'>".CONTROL_CLASS_CLASS_TEMPLATE_DEFAULT."</font>:<br/>";
    echo "<select name='Class_Template_ID'>";
    echo "<option value='0'>".CONTROL_CLASS_CLASS_DONT_USE_TEMPLATE."</option>";
    foreach ($classTemplatesArr as $classTemplate) {
        echo "<option value='".$classTemplate['Class_ID']."'".($selected_id == $classTemplate['Class_ID'] ? " selected" : "").">".$classTemplate['Class_Name']."</option>";
    }
    echo "</select>";
    echo "<p>".CONTROL_CLASS_CLASS_TEMPLATE_CHANGE_LATER."</p>";
}
?>