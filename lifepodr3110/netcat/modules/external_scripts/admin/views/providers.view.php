<?php
/**
 * Список провайдеров (карты/видео) для авто-блокировки до cookie-согласия.
 * Общий для всех сайтов установки — см. nc_consent::providers().
 */

global $UI_CONFIG;

$ui = nc_core('ui');
$providers = nc_external_scripts_provider::get_list();
$category_labels = [
    'functional' => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_FUNCTIONAL,
    'analytics'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_ANALYTICS,
    'marketing'  => NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_MARKETING,
];
?>

<p style="margin:8px 0;opacity:.85;"><?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_INTRO ?></p>

<div class="nc-external-scripts-providers-list">

<?php if (empty($providers)): ?>
    <?= $ui->alert->info(NETCAT_MODULE_EXTSCRIPTS_PROVIDER_MSG_EMPTY) ?>
<?php else: ?>

    <table class="nc-table nc--striped nc--small nc--hovered" width="100%">
        <thead>
            <tr>
                <th width="50">ID</th>
                <th><?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_LABEL ?></th>
                <th><?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN ?></th>
                <th width="140"><?= NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CATEGORY ?></th>
                <th width="100" class="nc-text-center"><?= NETCAT_MODULE_EXTSCRIPTS_STATUS ?></th>
                <th width="50" class="nc-text-center">
                    <div class="icons icon_delete" title="<?= NETCAT_MODULE_EXTSCRIPTS_DELETE ?>"></div>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($providers as $provider): ?>
                <?php
                $provider_id = (int)$provider['Provider_ID'];
                $enabled = (bool)$provider['Enabled'];
                $category_label = $category_labels[$provider['Category']] ?? $provider['Category'];
                ?>
                <tr class="<?= !$enabled ? 'nc-disabled' : '' ?>">
                    <td><?= $provider_id ?></td>
                    <td>
                        <a href="#" onclick="parent.urlDispatcher.load('module.external_scripts.providers.edit(<?= $provider_id ?>)'); return false;">
                            <?= htmlspecialchars($provider['Label']) ?>
                        </a>
                    </td>
                    <td><code><?= htmlspecialchars($provider['Pattern']) ?></code><?= $provider['IsRegex'] ? ' <span style="opacity:.6;">(regex)</span>' : '' ?></td>
                    <td><?= htmlspecialchars($category_label) ?></td>
                    <td class="nc-text-center">
                        <?php
                        $toggle_params = [
                            'action' => 'provider_toggle',
                            'provider_id' => $provider_id,
                        ];
                        echo nc_ui_controls::get()->toggle_button($enabled, $toggle_params);
                        ?>
                    </td>
                    <td class="nc-text-center">
                        <?php
                        $delete_params = [
                            'action' => 'provider_delete',
                            'provider_id' => $provider_id,
                        ];
                        echo nc_ui_controls::get()->delete_button(NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CONFIRM_DELETE, $delete_params);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

</div>

<style>
.nc-external-scripts-providers-list .nc-disabled td {
    opacity: 0.5;
}
</style>
