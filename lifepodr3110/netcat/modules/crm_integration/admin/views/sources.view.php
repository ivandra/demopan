<?php
/**
 * CRM Integration - Sources View
 */

if (!class_exists('nc_core')) { die; }

global $site_id, $message, $error;

$nc_core = nc_core::get_object();
$db = $nc_core->db;
$ui = nc_core('ui');

// Site selector
echo $ui->controls->site_select($site_id);

if (!$site_id) {
    echo $ui->alert->info(NETCAT_MODULE_CRM_INTEGRATION_SELECT_SITE);
    return;
}

// Get sources
$sources = $db->get_results(
    "SELECT s.*, p.Provider_Name
     FROM `" . NC_CRM_INTEGRATION_SOURCE_TABLE . "` s
     LEFT JOIN `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "` p ON s.Provider_ID = p.Provider_ID
     WHERE s.Catalogue_ID = $site_id
     ORDER BY s.Source_ID DESC",
    ARRAY_A
) ?: [];

// Get enabled providers for this site
$providers = $db->get_results(
    "SELECT p.Provider_ID, p.Provider_Name
     FROM `" . NC_CRM_INTEGRATION_PROVIDER_TABLE . "` p
     JOIN `" . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . "` pc ON p.Provider_ID = pc.Provider_ID
     WHERE pc.Catalogue_ID = $site_id AND pc.Enabled = 1 AND p.Enabled = 1
     ORDER BY p.Priority",
    ARRAY_A
) ?: [];

// Get components for selector
$components = $db->get_results(
    "SELECT `Class_ID`, `Class_Name` FROM `Class` WHERE `ClassTemplate` = 0 ORDER BY `Class_Name`",
    ARRAY_A
) ?: [];

if (empty($providers)):
    echo $ui->alert->warning(NETCAT_MODULE_CRM_INTEGRATION_NO_PROVIDERS . '. ' . NETCAT_MODULE_CRM_INTEGRATION_ENABLE . ' ' . NETCAT_MODULE_CRM_INTEGRATION_PROVIDERS . '.');
    return;
endif;
?>

<table class="nc-table nc--bordered nc--wide nc-margin-bottom-medium">
    <tr>
        <th class="nc--compact"></th>
        <th><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_TYPE ?></th>
        <th style="white-space: nowrap;"><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_PROVIDER ?></th>
        <th class="nc--compact"></th>
    </tr>
    <?php if (empty($sources)): ?>
        <tr>
            <td colspan="4" class="nc-text-center nc-text-grey" style="padding: 20px;">
                <?= NETCAT_MODULE_CRM_INTEGRATION_SOURCES_EMPTY ?>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($sources as $source): ?>
            <?php
            $source_id = $source['Source_ID'];
            $is_enabled = !empty($source['Enabled']);

            // Format source name
            $source_name = $source['Source_Type'];
            if ($source['Source_Type'] === 'requests') {
                $source_name = NETCAT_MODULE_CRM_INTEGRATION_SOURCE_REQUESTS;
            } elseif ($source['Source_Type'] === 'component') {
                $comp = $db->get_var("SELECT Class_Name FROM `Class` WHERE Class_ID = " . (int)$source['Source_Object_ID']);
                $source_name = NETCAT_MODULE_CRM_INTEGRATION_SOURCE_COMPONENT . ': ' . ($comp ?: '#' . $source['Source_Object_ID']);
            } elseif ($source['Source_Type'] === 'infoblock') {
                $source_name = NETCAT_MODULE_CRM_INTEGRATION_SOURCE_INFOBLOCK . ' #' . $source['Source_Object_ID'];
            }
            ?>
            <tr>
                <td>
                    <form method="post" action="?" style="display:inline;">
                        <input type="hidden" name="phase" value="sources">
                        <input type="hidden" name="site_id" value="<?= $site_id ?>">
                        <input type="hidden" name="action" value="toggle_source">
                        <input type="hidden" name="source_id" value="<?= $source_id ?>">
                        <input type="hidden" name="enable" value="<?= $is_enabled ? 0 : 1 ?>">
                        <?= $nc_core->token->get_input() ?>
                        <span class="nc-label nc--<?= $is_enabled ? 'green' : 'red' ?>" onclick="this.parentNode.submit();" style="cursor:pointer;">
                            <?= $is_enabled ? NETCAT_MODERATION_OBJ_ON : NETCAT_MODERATION_OBJ_OFF ?>
                        </span>
                    </form>
                </td>
                <td><?= htmlspecialchars($source_name) ?></td>
                <td><?= htmlspecialchars($source['Provider_Name']) ?></td>
                <td class="nc-text-center">
                    <form method="post" action="?" style="display:inline;" onsubmit="return confirm('<?= CONTROL_FIELD_CONFIRM_REMOVAL ?>');">
                        <input type="hidden" name="phase" value="sources">
                        <input type="hidden" name="site_id" value="<?= $site_id ?>">
                        <input type="hidden" name="action" value="delete_source">
                        <input type="hidden" name="source_id" value="<?= $source_id ?>">
                        <?= $nc_core->token->get_input() ?>
                        <a href="javascript:void(0)" onclick="this.parentNode.submit();"><i class="nc-icon nc--remove"></i></a>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<h3 class="nc-margin-top-medium"><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_ADD ?></h3>

<form method="post" action="?" class="nc-form nc-margin-top-small" id="add_source_form">
    <input type="hidden" name="phase" value="sources">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">
    <input type="hidden" name="action" value="save_source">
    <?= $nc_core->token->get_input() ?>

    <div class="nc-form-row">
        <label><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_TYPE ?></label>
        <div>
            <div class="nc-select" style="width: 300px; display: inline-block;">
                <select name="source_type" id="source_type" onchange="toggleObjectId()" required>
                    <option value=""><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_SELECT ?></option>
                    <option value="requests"><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_REQUESTS ?></option>
                    <option value="component"><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_COMPONENT ?></option>
                </select>
                <i class="nc-caret"></i>
            </div>
        </div>
    </div>

    <div class="nc-form-row" id="object_id_row" style="display: none;">
        <label><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_COMPONENT ?></label>
        <div>
            <div class="nc-select" style="width: 400px; display: inline-block;">
                <select name="source_object_id">
                    <option value="">—</option>
                    <?php foreach ($components as $comp): ?>
                        <option value="<?= $comp['Class_ID'] ?>"><?= htmlspecialchars("$comp[Class_ID]. $comp[Class_Name]" ) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="nc-caret"></i>
            </div>
        </div>
    </div>

    <div class="nc-form-row">
        <label><?= NETCAT_MODULE_CRM_INTEGRATION_SOURCE_PROVIDER ?></label>
        <div>
            <div class="nc-select" style="width: 300px; display: inline-block;">
                <select name="source_provider_id" required>
                    <option value="">—</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['Provider_ID'] ?>"><?= htmlspecialchars($p['Provider_Name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="nc-caret"></i>
            </div>
        </div>
    </div>

    <div class="nc-form-row">
        <label></label>
        <div>
            <button type="submit" class="nc-btn"><?= NETCAT_MODULE_CRM_INTEGRATION_ADD ?></button>
        </div>
    </div>
</form>

<script>
function toggleObjectId() {
    var type = document.getElementById('source_type').value;
    document.getElementById('object_id_row').style.display = (type === 'component') ? '' : 'none';
}
</script>
