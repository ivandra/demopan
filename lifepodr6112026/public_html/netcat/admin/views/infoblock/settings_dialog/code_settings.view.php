<?php

if (!class_exists('nc_core')) {
    die;
}

$code_settings = json_decode($infoblock_data['Code_Settings'] ?? '', true) ?: [];
$current_tag = $code_settings['tag'] ?? '';
$current_class = $code_settings['class'] ?? '';
$current_style = $code_settings['style'] ?? '';
$current_id = $code_settings['id'] ?? '';

$allowed_tags = ['div', 'section', 'article', 'aside', 'header', 'footer', 'nav', 'main', 'figure'];

?>
<div>
    <div><?= NETCAT_CODE_SETTINGS_TAG ?>:</div>
    <div>
        <select name="data[Code_Settings_Tag]">
            <option value=""><?= NETCAT_CODE_SETTINGS_TAG_DEFAULT ?></option>
            <?php foreach ($allowed_tags as $t): ?>
                <option value="<?= $t ?>"<?= $current_tag === $t ? ' selected' : '' ?>>&lt;<?= $t ?>&gt;</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<br>
<div>
    <div><?= NETCAT_CODE_SETTINGS_CLASS ?>:</div>
    <div><?= nc_admin_input_simple('data[Code_Settings_Class]', $current_class, 50, '', "maxlength='500'") ?></div>
</div>
<br>
<div>
    <div><?= NETCAT_CODE_SETTINGS_STYLE ?>:</div>
    <div><textarea name="data[Code_Settings_Style]" rows="3" class="no_cm" style="width: 100%; font-family: monospace"><?= htmlspecialchars($current_style) ?></textarea></div>
</div>
<br>
<div>
    <div><?= NETCAT_CODE_SETTINGS_ID ?>:</div>
    <div><?= nc_admin_input_simple('data[Code_Settings_ID]', $current_id, 50, '', "maxlength='255'") ?></div>
</div>
