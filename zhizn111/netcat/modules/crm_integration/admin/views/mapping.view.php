<?php
/**
 * CRM Integration - Field Mapping View
 */

if (!class_exists('nc_core')) { die; }

global $site_id, $provider_id;

$nc_core = nc_core::get_object();
$db = $nc_core->db;

if (!$site_id || !$provider_id) {
    echo $nc_core->ui->alert->error('Invalid parameters');
    return;
}

$provider = nc_crm_integration_factory::create($provider_id, $site_id);

if (!$provider) {
    echo $nc_core->ui->alert->error('Provider not found');
    return;
}

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
    'Name' => 'Имя',
    'Surname' => 'Фамилия',
    'Patronymic' => 'Отчество',
    'Phone' => 'Телефон',
    'Email' => 'E-mail',
    'Company' => 'Компания',
    'Comment' => 'Комментарий',
    'FormType' => 'Тип формы',
];

// Transform options
$transforms = [
    '' => 'Без преобразования',
    'trim' => 'Trim (удалить пробелы)',
    'phone_format' => 'Формат телефона',
    'email_format' => 'Формат email',
    'uppercase' => 'Верхний регистр',
    'lowercase' => 'Нижний регистр',
    'strip_tags' => 'Удалить HTML теги',
];
?>

<h3><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING ?>: <?= htmlspecialchars($provider->get_name()) ?></h3>

<p style="color: #666; margin-bottom: 20px;">
    Настройте соответствие полей Netcat полям CRM. Если маппинг не задан, поля передаются с оригинальными именами.
</p>

<form method="post" action="<?= nc_module_folder('crm_integration') ?>admin.php">
    <input type="hidden" name="action" value="save_mapping">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">
    <input type="hidden" name="phase" value="mapping">
    <?= $nc_core->token->get_input() ?>

    <table class="nc-table nc--striped" width="100%">
        <thead>
            <tr>
                <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_NETCAT_FIELD ?></th>
                <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_CRM_FIELD ?></th>
                <th><?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_TRANSFORM ?></th>
                <th width="50"></th>
            </tr>
        </thead>
        <tbody id="mappings_tbody">
            <?php if (empty($mappings)): ?>
                <tr class="mapping-row">
                    <td>
                        <select name="mapping[0][netcat_field]" class="nc-select">
                            <option value="">-</option>
                            <?php foreach ($netcat_fields as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="mapping[0][crm_field]" class="nc-select">
                            <option value="">-</option>
                            <?php foreach ($crm_fields as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?> (<?= $key ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="mapping[0][transform]" class="nc-select">
                            <?php foreach ($transforms as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="nc-text-center">
                        <a href="javascript:void(0)" onclick="removeRow(this)"><i class="nc-icon nc--remove"></i></a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($mappings as $i => $mapping): ?>
                    <tr class="mapping-row">
                        <td>
                            <select name="mapping[<?= $i ?>][netcat_field]" class="nc-select">
                                <option value="">-</option>
                                <?php foreach ($netcat_fields as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $mapping['Netcat_Field'] === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?> (<?= $key ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mapping[<?= $i ?>][crm_field]" class="nc-select">
                                <option value="">-</option>
                                <?php foreach ($crm_fields as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $mapping['Crm_Field'] === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?> (<?= $key ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mapping[<?= $i ?>][transform]" class="nc-select">
                                <?php foreach ($transforms as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($mapping['Transform'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="nc-text-center">
                            <a href="javascript:void(0)" onclick="removeRow(this)"><i class="nc-icon nc--remove"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 10px;">
        <button type="button" class="nc-btn" onclick="addRow()">
            + <?= NETCAT_MODULE_CRM_INTEGRATION_MAPPING_ADD ?>
        </button>
    </div>
</form>

<script>
var rowIndex = <?= count($mappings) ?: 1 ?>;

function addRow() {
    var tbody = document.getElementById('mappings_tbody');
    var row = tbody.querySelector('.mapping-row').cloneNode(true);

    // Update names
    row.querySelectorAll('select').forEach(function(select) {
        select.name = select.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
        select.value = '';
    });

    tbody.appendChild(row);
    rowIndex++;
}

function removeRow(btn) {
    var row = btn.closest('tr');
    var tbody = document.getElementById('mappings_tbody');
    if (tbody.querySelectorAll('.mapping-row').length > 1) {
        row.remove();
    } else {
        // Clear values instead of removing last row
        row.querySelectorAll('select').forEach(function(select) {
            select.value = '';
        });
    }
}
</script>
