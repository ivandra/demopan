<?php
/**
 * CRM Integration - Send Log View
 */

if (!class_exists('nc_core')) { die; }

global $site_id;

$nc_core = nc_core::get_object();
$db = $nc_core->db;
$input = $nc_core->input;

if (!$site_id) {
    echo $nc_core->ui->alert->error('Site not selected');
    return;
}

// Filters
$filter_provider = (int)$input->fetch_get('filter_provider');
$filter_status = $input->fetch_get('filter_status');
$page = max(1, (int)$input->fetch_get('page'));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$filters = [];
if ($filter_provider) {
    $filters['provider_id'] = $filter_provider;
}
if ($filter_status !== '' && $filter_status !== null) {
    $filters['status'] = (int)$filter_status;
}

// Get logs
$logs = nc_crm_integration_logger::get_logs($site_id, $filters, $per_page, $offset);
$total = nc_crm_integration_logger::get_logs_count($site_id, $filters);
$total_pages = ceil($total / $per_page);

// Get providers for filter
$providers = nc_crm_integration_factory::get_all_providers();

// Status labels
$status_labels = [
    nc_crm_integration_provider::STATUS_SUCCESS => ['label' => NETCAT_MODULE_CRM_INTEGRATION_STATUS_SUCCESS, 'color' => 'green'],
    nc_crm_integration_provider::STATUS_ERROR => ['label' => NETCAT_MODULE_CRM_INTEGRATION_STATUS_ERROR, 'color' => 'red'],
    nc_crm_integration_provider::STATUS_PENDING => ['label' => NETCAT_MODULE_CRM_INTEGRATION_STATUS_PENDING, 'color' => '#999'],
    nc_crm_integration_provider::STATUS_RETRY => ['label' => NETCAT_MODULE_CRM_INTEGRATION_STATUS_RETRY, 'color' => 'orange'],
];
?>

<form method="get" style="margin-bottom: 20px;">
    <input type="hidden" name="phase" value="log">
    <input type="hidden" name="site_id" value="<?= $site_id ?>">

    <label style="margin-right: 15px;">
        <?= NETCAT_MODULE_CRM_INTEGRATION_LOG_PROVIDER ?>:
        <select name="filter_provider" class="nc-select" onchange="this.form.submit()">
            <option value="">Все</option>
            <?php foreach ($providers as $p): ?>
                <option value="<?= $p['Provider_ID'] ?>" <?= $filter_provider == $p['Provider_ID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['Provider_Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <?= NETCAT_MODULE_CRM_INTEGRATION_LOG_STATUS ?>:
        <select name="filter_status" class="nc-select" onchange="this.form.submit()">
            <option value="">Все</option>
            <?php foreach ($status_labels as $status => $info): ?>
                <option value="<?= $status ?>" <?= $filter_status !== '' && (int)$filter_status === $status ? 'selected' : '' ?>>
                    <?= $info['label'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if (empty($logs)): ?>
    <?= $nc_core->ui->alert->info(NETCAT_MODULE_CRM_INTEGRATION_LOG_EMPTY) ?>
<?php else: ?>

<table class="nc-table nc--striped nc--hovered" width="100%">
    <thead>
        <tr>
            <th width="140"><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_DATE ?></th>
            <th width="120"><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_PROVIDER ?></th>
            <th><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_SOURCE ?></th>
            <th width="80"><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_STATUS ?></th>
            <th width="100"><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_EXTERNAL_ID ?></th>
            <th><?= NETCAT_MODULE_CRM_INTEGRATION_LOG_ERROR ?></th>
            <th width="80"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <?php
            $status_info = $status_labels[$log['Status']] ?? ['label' => '?', 'color' => '#999'];
            ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($log['Created'])) ?></td>
                <td><?= htmlspecialchars($log['Provider_Name'] ?? '-') ?></td>
                <td>
                    <?= htmlspecialchars($log['Source_Type']) ?>
                    #<?= $log['Source_Object_ID'] ?>
                    <?php if ($log['Component_ID']): ?>
                        <small style="color: #999;">(comp: <?= $log['Component_ID'] ?>)</small>
                    <?php endif; ?>
                </td>
                <td style="color: <?= $status_info['color'] ?>;">
                    <?= $status_info['label'] ?>
                    <?php if ($log['Retry_Count'] > 0): ?>
                        <small>(<?= $log['Retry_Count'] ?>)</small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($log['External_ID'] ?: '-') ?></td>
                <td>
                    <?php if ($log['Error_Message']): ?>
                        <span title="<?= htmlspecialchars($log['Error_Message']) ?>" style="color: red; cursor: help;">
                            <?= htmlspecialchars(mb_substr($log['Error_Message'], 0, 50)) ?>...
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($log['Status'] == nc_crm_integration_provider::STATUS_ERROR): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="retry_send">
                            <input type="hidden" name="log_id" value="<?= $log['Log_ID'] ?>">
                            <input type="hidden" name="phase" value="log">
                            <?= $nc_core->token->get_input() ?>
                            <button type="submit" class="nc-btn nc--small">
                                <?= NETCAT_MODULE_CRM_INTEGRATION_RETRY ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div style="margin-top: 20px; text-align: center;">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <?php if ($p == $page): ?>
            <strong style="margin: 0 5px;"><?= $p ?></strong>
        <?php else: ?>
            <a href="?phase=log&site_id=<?= $site_id ?>&page=<?= $p ?>&filter_provider=<?= $filter_provider ?>&filter_status=<?= $filter_status ?>"
               style="margin: 0 5px;"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<div style="margin-top: 20px; color: #999; font-size: 12px;">
    Всего записей: <?= $total ?>
</div>
