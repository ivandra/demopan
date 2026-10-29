<?php
/**
 * CRM Integration - Provider Settings View (merged with mapping)
 */

if (!class_exists('nc_core')) {
    die;
}

global $site_id, $provider_id, $message, $error;

$nc_core = nc_core::get_object();
$db = $nc_core->db;
$ui = nc_core('ui');

if (!$site_id || !$provider_id) {
    echo $ui->alert->error(NETCAT_MODULE_CRM_INTEGRATION_ERROR_INVALID_SETTINGS);
    return;
}

$provider = nc_crm_integration_factory::create($provider_id, $site_id);

if (!$provider) {
    echo $ui->alert->error(NETCAT_MODULE_CRM_INTEGRATION_NO_PROVIDERS);
    return;
}

$is_enabled = nc_crm_integration_factory::is_provider_enabled($provider_id, $site_id);
$settings_list = $provider->get_settings_list();

// Get existing mappings
$mappings = $db->get_results(
    "SELECT * FROM `" . NC_CRM_INTEGRATION_FIELD_MAPPING_TABLE . "`
     WHERE `Catalogue_ID` = $site_id AND `Provider_ID` = $provider_id
     ORDER BY `Mapping_ID`",
    ARRAY_A
) ?: [];

// Available CRM fields
$crm_fields = $provider->get_available_fields();

// Common Netcat fields
$netcat_fields = [
    'Name' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_NAME,
    'Surname' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_SURNAME,
    'Patronymic' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_PATRONYMIC,
    'Phone' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_PHONE,
    'Email' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_EMAIL,
    'Company' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_COMPANY,
    'Comment' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_COMMENT,
    'FormType' => NETCAT_MODULE_CRM_INTEGRATION_FIELD_FORM_TYPE,
];

// Transform options
$transforms = [
    '' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_NONE,
    'trim' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_TRIM,
    'phone_format' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_PHONE,
    'email_format' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_EMAIL,
    'uppercase' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_UPPERCASE,
    'lowercase' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_LOWERCASE,
    'strip_tags' => NETCAT_MODULE_CRM_INTEGRATION_TRANSFORM_STRIP_TAGS,
];
?>

<form method="post" action="?" class="nc-form nc--vertical" id="provider_settings_form" autocomplete="off">
    <input type="hidden" name="phase" value="provider_settings">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">
    <input type="hidden" name="action" value="save_provider_settings">
    <?= $nc_core->token->get_input() ?>
    <!-- Hidden fields to prevent browser autofill -->
    <input type="text" name="fake_user" style="display:none;" autocomplete="username">
    <input type="password" name="fake_pass" style="display:none;" autocomplete="current-password">

    <div class="nc-form-row">
        <input type="hidden" name="provider_enabled" value="0">
        <label>
            <input type="checkbox" name="provider_enabled" value="1" <?= $is_enabled ? 'checked' : '' ?>>
            <?= NETCAT_MODULE_CRM_INTEGRATION_PROVIDER_ENABLED ?>
        </label>
    </div>

    <?php foreach ($settings_list as $name => $config):
        $value = $provider->get_setting($name);
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? $name;
        $hint = $config['hint'] ?? '';
        $required = !empty($config['required']);
        $options = $config['options'] ?? [];
    ?>
    <?php if ($type === 'checkbox'): ?>
    <div class="nc-form-row">
        <input type="hidden" name="settings[<?= $name ?>]" value="0">
        <label>
            <input type="checkbox" name="settings[<?= $name ?>]" value="1" <?= $value ? 'checked' : '' ?>>
            <?= $label ?>
        </label>
        <?php if ($hint): ?>
            <div class="nc-text-grey" style="margin-top: 5px;"><?= $hint ?></div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="nc-form-row">
        <label><?= $label ?><?= $required ? ' <span class="nc-text-red">*</span>' : '' ?></label>
        <?php if ($type === 'text'): ?>
            <input type="text" name="settings[<?= $name ?>]" value="<?= htmlspecialchars($value) ?>" style="width: 400px;" autocomplete="off">
        <?php elseif ($type === 'password'): ?>
            <input type="password" name="settings[<?= $name ?>]" value="<?= htmlspecialchars($value) ?>" style="width: 400px;" autocomplete="new-password">
        <?php elseif ($type === 'textarea'): ?>
            <textarea name="settings[<?= $name ?>]" rows="4" style="width: 400px;"><?= htmlspecialchars($value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select name="settings[<?= $name ?>]" style="width: 400px;">
                <?php foreach ($options as $k => $v): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $value == $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($hint): ?>
            <div class="nc-text-grey" style="margin-top: 5px;"><?= $hint ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</form>

<div class="nc-margin-top-medium nc-margin-bottom-medium">
    <button type="button" class="nc-btn" onclick="testConnection()"><?= NETCAT_MODULE_CRM_INTEGRATION_TEST_CONNECTION ?></button>
    <?php if ($provider instanceof nc_crm_integration_provider_amocrm && method_exists($provider, 'is_authorized')): ?>
        <?php if (!$provider->is_authorized()): ?>
            <button type="button" class="nc-btn" onclick="window.open('<?= htmlspecialchars($provider->get_authorization_url($site_id . '_' . $provider_id), ENT_QUOTES) ?>', '_blank')"><?= NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_AUTHORIZE ?></button>
        <?php else: ?>
            <span class="nc-text-green nc-margin-left-medium"><?= NETCAT_MODULE_CRM_INTEGRATION_AMOCRM_AUTHORIZED ?></span>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Field Mapping Section -->
<h3><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING ?></h3>
<p class="nc-text-grey"><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_HINT ?></p>

<table class="nc-table nc--striped nc--wide" id="mapping_table">
    <thead>
        <tr>
            <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_NETCAT_FIELD ?></th>
            <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_CRM_FIELD ?></th>
            <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_TRANSFORM ?></th>
            <th style="width: 50px;"></th>
        </tr>
    </thead>
    <tbody id="mappings_tbody">
        <?php if (empty($mappings)): ?>
            <tr class="mapping-row">
                <td>
                    <div class="nc-select">
                        <select name="mapping[0][netcat_field]" form="provider_settings_form">
                            <option value="">—</option>
                            <?php foreach ($netcat_fields as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <i class="nc-caret"></i>
                    </div>
                </td>
                <td>
                    <div class="nc-select">
                        <select name="mapping[0][crm_field]" form="provider_settings_form">
                            <option value="">—</option>
                            <?php foreach ($crm_fields as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <i class="nc-caret"></i>
                    </div>
                </td>
                <td>
                    <div class="nc-select">
                        <select name="mapping[0][transform]" form="provider_settings_form">
                            <?php foreach ($transforms as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="nc-caret"></i>
                    </div>
                </td>
                <td class="nc-text-center">
                    <a href="javascript:void(0)" onclick="removeRow(this)"><i class="nc-icon nc--remove"></i></a>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($mappings as $i => $mapping): ?>
                <tr class="mapping-row">
                    <td>
                        <div class="nc-select">
                            <select name="mapping[<?= $i ?>][netcat_field]" form="provider_settings_form">
                                <option value="">—</option>
                                <?php foreach ($netcat_fields as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $mapping['Netcat_Field'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <i class="nc-caret"></i>
                        </div>
                    </td>
                    <td>
                        <div class="nc-select">
                            <select name="mapping[<?= $i ?>][crm_field]" form="provider_settings_form">
                                <option value="">—</option>
                                <?php foreach ($crm_fields as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $mapping['Crm_Field'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <i class="nc-caret"></i>
                        </div>
                    </td>
                    <td>
                        <div class="nc-select">
                            <select name="mapping[<?= $i ?>][transform]" form="provider_settings_form">
                                <?php foreach ($transforms as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($mapping['Transform'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="nc-caret"></i>
                        </div>
                    </td>
                    <td class="nc-text-center">
                        <a href="javascript:void(0)" onclick="removeRow(this)"><i class="nc-icon nc--remove"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="nc-margin-top-small">
    <button type="button" class="nc-btn" id="add_mapping_btn"><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_ADD ?></button>
</div>

<form id="test_connection_form" method="post" action="?" style="display:none;">
    <input type="hidden" name="action" value="test_connection">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">
    <input type="hidden" name="phase" value="provider_settings">
    <?= $nc_core->token->get_input() ?>
</form>

<p>&nbsp;</p>

<script>
(function() {
    var rowIndex = <?= max(count($mappings), 1) ?>;
    var formId = 'provider_settings_form';

    document.getElementById('add_mapping_btn').addEventListener('click', function() {
        var tbody = document.getElementById('mappings_tbody');
        var firstRow = tbody.querySelector('.mapping-row');
        var newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('select').forEach(function(select) {
            select.name = select.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
            select.selectedIndex = 0;
            select.setAttribute('form', formId);
        });

        tbody.appendChild(newRow);
        rowIndex++;
    });

    window.removeRow = function(el) {
        var row = el.closest('tr');
        var tbody = document.getElementById('mappings_tbody');
        if (tbody.querySelectorAll('.mapping-row').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('select').forEach(function(select) {
                select.selectedIndex = 0;
            });
        }
    };

    window.testConnection = function() {
        document.getElementById('test_connection_form').submit();
    };
})();
</script>
