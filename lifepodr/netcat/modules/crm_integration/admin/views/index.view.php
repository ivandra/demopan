<?php
/**
 * CRM Integration - Providers List View
 */

if (!class_exists('nc_core')) {
    die;
}

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

// Get all providers
$providers = $db->get_results(
    "SELECT p.*,
            COALESCE(pc.Enabled, 0) as Site_Enabled
     FROM " . NC_CRM_INTEGRATION_PROVIDER_TABLE . " p
     LEFT JOIN " . NC_CRM_INTEGRATION_PROVIDER_CATALOGUE_TABLE . " pc
          ON p.Provider_ID = pc.Provider_ID AND pc.Catalogue_ID = " . (int)$site_id . "
     WHERE p.Enabled = 1
     ORDER BY p.Priority",
    ARRAY_A
);

if (empty($providers)) {
    echo $ui->alert->info(NETCAT_MODULE_CRM_INTEGRATION_NO_PROVIDERS);
    return;
}
?>

<table class="nc-table nc--bordered nc--wide nc-margin-bottom-medium">
    <tr>
        <th class="nc--compact"></th>
        <th class="nc--wide"><?= NETCAT_MODULE_CRM_INTEGRATION_PROVIDERS ?></th>
        <th style="white-space: nowrap;"><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_STATUS ?></th>
        <th style="white-space: nowrap;"><?= NETCAT_MODULE_CRM_INTEGRATION_SENT_COUNT ?></th>
        <th><?= NETCAT_MODULE_CRM_INTEGRATION_SETTINGS ?></th>
    </tr>
    <?php foreach ($providers as $provider): ?>
        <?php
        $id = $provider['Provider_ID'];
        $is_enabled = !empty($provider['Site_Enabled']);
        $name = htmlspecialchars($provider['Provider_Name']);

        // Get statistics for this provider
        $provider_stats = nc_crm_integration_logger::get_stats($site_id, $id);

        // Check if configured
        $provider_obj = nc_crm_integration_factory::create($id, $site_id);
        $is_configured = $provider_obj && $provider_obj->validate_settings();

        $edit_url = "?phase=provider_settings&site_id={$site_id}&provider_id={$id}";
        ?>
        <tr>
            <td>
                <form method="post" action="?" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_provider">
                    <input type="hidden" name="provider_id" value="<?= $id ?>">
                    <input type="hidden" name="site_id" value="<?= $site_id ?>">
                    <input type="hidden" name="enable" value="<?= $is_enabled ? 0 : 1 ?>">
                    <?= $nc_core->token->get_input() ?>
                    <span class="nc-label nc--<?= $is_enabled ? 'green' : 'red' ?>" onclick="this.parentNode.submit();" style="cursor:pointer;">
                        <?= $is_enabled ? NETCAT_MODERATION_OBJ_ON : NETCAT_MODERATION_OBJ_OFF ?>
                    </span>
                </form>
            </td>
            <td>
                <?php if ($is_enabled): ?>
                    <a href="<?= $edit_url ?>"><?= $name ?></a>
                <?php else: ?>
                    <?= $name ?>
                <?php endif; ?>
            </td>
            <td style="white-space: nowrap;">
                <?php if ($is_enabled && $is_configured): ?>
                    <span class="nc-text-green"><?= NETCAT_MODULE_CRM_INTEGRATION_STATUS_CONNECTED ?></span>
                <?php elseif (!$is_configured): ?>
                    <span class="nc-text-grey"><?= NETCAT_MODULE_CRM_INTEGRATION_STATUS_NOT_CONFIGURED ?></span>
                <?php else: ?>
                    <span class="nc-text-grey"><?= NETCAT_MODULE_CRM_INTEGRATION_DISABLE ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?= $provider_stats['success'] ?>
                <?php if ($provider_stats['error'] > 0): ?>
                    / <span class="nc-text-red"><?= $provider_stats['error'] ?></span>
                <?php endif; ?>
            </td>
            <td class="nc-text-center">
                <a href="<?= $edit_url ?>">
                    <i class="nc-icon nc--settings<?= $is_enabled ? '' : ' nc--disabled' ?>"></i>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
