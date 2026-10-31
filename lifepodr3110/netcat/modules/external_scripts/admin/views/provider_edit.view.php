<?php
/**
 * Форма добавления/редактирования провайдера (карты/видео) для
 * авто-блокировки до cookie-согласия.
 */

global $UI_CONFIG, $provider_id;

$nc_core = nc_core::get_object();

$label = '';
$pattern = '';
$is_regex = 1;
$category = 'functional';
$priority = 100;
$enabled = 1;

if ($provider_id) {
    $provider = nc_external_scripts_provider::get_by_id($provider_id);
    if ($provider) {
        $label = $provider['Label'];
        $pattern = $provider['Pattern'];
        $is_regex = (int)$provider['IsRegex'];
        $category = $provider['Category'];
        $priority = (int)$provider['Priority'];
        $enabled = (int)$provider['Enabled'];
    }
}

$category_options = [
    'functional' => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_FUNCTIONAL,
    'analytics'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_ANALYTICS,
    'marketing'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_MARKETING,
];
?>

<form method="post" action="admin.php" class="nc-form">
    <?= $nc_core->token->get_input() ?>
    <input type="hidden" name="action" value="provider_save">
    <input type="hidden" name="provider_id" value="<?= (int)$provider_id ?>">
    <input type="submit" class="hidden">

    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td>
                <?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_LABEL ?>:<br>
                <?= nc_admin_input_simple('label', $label, 70, '', "maxlength='255' required") ?>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td>
                <?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN ?>:<br>
                <?= nc_admin_input_simple('pattern', $pattern, 70, '', "maxlength='255' required") ?>
                <div style="opacity:.65;font-size:12px;margin-top:4px;">
                    <?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN_HINT ?>
                </div>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td>
                <?= nc_admin_checkbox_simple('is_regex', 1, NETCAT_MODULE_EXTSCRIPTS_PROVIDER_IS_REGEX, $is_regex) ?>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td>
                <?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CATEGORY ?>:<br>
                <?= nc_admin_select_simple('', 'category', $category_options, $category) ?>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td>
                <?= NETCAT_MODULE_EXTSCRIPTS_PRIORITY ?>:<br>
                <?= nc_admin_input_simple('priority', $priority, 10, '', "maxlength='6'") ?>
            </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
            <td>
                <?= nc_admin_checkbox_simple('enabled', 1, NETCAT_MODULE_EXTSCRIPTS_ENABLED, $enabled) ?>
            </td>
        </tr>
    </table>
</form>
