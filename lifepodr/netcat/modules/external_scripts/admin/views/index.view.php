<?php
/**
 * Список скриптов для сайта
 */

global $UI_CONFIG, $site_id;

$nc_core = nc_core::get_object();
$module_path = nc_module_path('external_scripts');

// Селектор сайта
$ui = nc_core('ui');
echo $ui->controls->site_select($site_id);

if (!$site_id) {
    echo $ui->alert->info(NETCAT_MODULE_EXTSCRIPTS_MSG_SELECT_SITE);
    return;
}

// Режим окна cookie-согласия для сайта (read-only; настраивается в настройках сайта)
$consent_mode = nc_consent::get_mode($site_id);
$consent_labels = array(
    'notice'     => CONTROL_CONTENT_CATALOGUE_FUNCS_CONSENT_MODE_NOTICE,
    'accept-all' => CONTROL_CONTENT_CATALOGUE_FUNCS_CONSENT_MODE_ACCEPTALL,
    'categories' => CONTROL_CONTENT_CATALOGUE_FUNCS_CONSENT_MODE_CATEGORIES,
);
$consent_label = $consent_labels[$consent_mode] ?? $consent_mode;
$consent_settings_url = $nc_core->ADMIN_PATH . '#catalogue.edit(' . (int)$site_id . ')';
echo "<p style='margin:8px 0;opacity:.85;'>"
   . NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_LINE . ': <b>' . $consent_label . '</b> '
   . "(<a href='" . $consent_settings_url . "' target='_top'>" . NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_CONFIGURE . "</a>)"
   . "</p>";

// Получаем список скриптов
$snippets = nc_external_scripts_snippet::get_list($site_id);

// Названия типов для отображения
$type_names = [
    'yandex_metrika' => NETCAT_MODULE_EXTSCRIPTS_TYPE_YANDEX_METRIKA,
    'google_tag_manager' => NETCAT_MODULE_EXTSCRIPTS_TYPE_GTM,
    'google_analytics' => NETCAT_MODULE_EXTSCRIPTS_TYPE_GA4,
    'jivo' => NETCAT_MODULE_EXTSCRIPTS_TYPE_JIVO,
    'carrot_quest' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CARROT,
    'vk_pixel' => NETCAT_MODULE_EXTSCRIPTS_TYPE_VK_PIXEL,
    'calltouch' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CALLTOUCH,
    'custom' => NETCAT_MODULE_EXTSCRIPTS_TYPE_CUSTOM,
];

?>

<div class="nc-external-scripts-list">

<?php if (empty($snippets)): ?>
    <?= $ui->alert->info(NETCAT_MODULE_EXTSCRIPTS_MSG_NO_SCRIPTS) ?>
<?php else: ?>

<?php
// Добавляем кнопку сохранения приоритетов (как в управлении полями)
$UI_CONFIG->actionButtons[] = [
    'id' => 'submit',
    'caption' => NETCAT_MODULE_EXTSCRIPTS_SAVE,
    'action' => 'mainView.submitIframeForm()',
    'align' => 'right',
];
?>

    <form method="post" action="admin.php" id="priorityForm">
        <input type="hidden" name="action" value="reorder">
        <input type="hidden" name="site_id" value="<?= $site_id ?>">
        <?= $nc_core->token->get_input() ?>
        <input type="submit" class="hidden">

        <table class="nc-table nc--striped nc--small nc--hovered" width="100%">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th><?= NETCAT_MODULE_EXTSCRIPTS_NAME ?></th>
                    <th width="180"><?= NETCAT_MODULE_EXTSCRIPTS_TYPE ?></th>
                    <th width="150"><?= NETCAT_MODULE_EXTSCRIPTS_CONDITIONS ?></th>
                    <th width="100" class="nc-text-center"><?= NETCAT_MODULE_EXTSCRIPTS_STATUS ?></th>
                    <th width="80" class="nc-text-center">
                        <div class="icons icon_prior" title="<?= NETCAT_MODULE_EXTSCRIPTS_PRIORITY ?>"></div>
                    </th>
                    <th width="50" class="nc-text-center">
                        <div class="icons icon_delete" title="<?= NETCAT_MODULE_EXTSCRIPTS_DELETE ?>"></div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snippets as $snippet): ?>
                    <?php
                    $snippet_id = $snippet['Snippet_ID'];
                    $enabled = (bool)$snippet['Enabled'];
                    $type_name = $type_names[$snippet['SnippetType_Name']] ?? $snippet['SnippetType_Name'] ?? NETCAT_MODULE_EXTSCRIPTS_TYPE_CUSTOM;

                    // Определяем область видимости
                    $conditions = $snippet['Conditions'];
                    if (is_string($conditions)) {
                        $conditions = json_decode($conditions, true);
                    }
                    $condition_type = $conditions['type'] ?? 'all';
                    $scope_text = ($condition_type === 'all')
                        ? NETCAT_MODULE_EXTSCRIPTS_CONDITION_ALL
                        : NETCAT_MODULE_EXTSCRIPTS_SCOPE_SOME;
                    ?>
                    <tr class="<?= !$enabled ? 'nc-disabled' : '' ?>">
                        <td><?= $snippet_id ?></td>
                        <td>
                            <a href="#" onclick="parent.urlDispatcher.load('module.external_scripts.edit(<?= $site_id ?>,<?= $snippet_id ?>)'); return false;">
                                <?= htmlspecialchars($snippet['Name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($type_name) ?></td>
                        <td><?= $scope_text ?></td>
                        <td class="nc-text-center">
                            <?php
                            $toggle_params = [
                                'action' => 'toggle',
                                'site_id' => $site_id,
                                'snippet_id' => $snippet_id,
                            ];
                            echo nc_ui_controls::get()->toggle_button($enabled, $toggle_params);
                            ?>
                        </td>
                        <td class="nc-text-center">
                            <input type="text" name="priority[<?= $snippet_id ?>]"
                                   value="<?= (int)$snippet['Priority'] ?>"
                                   class="s" style="width: 50px; text-align: center;" maxlength="5">
                        </td>
                        <td class="nc-text-center">
                            <?php
                            $delete_params = [
                                'action' => 'delete',
                                'site_id' => $site_id,
                                'snippet_id' => $snippet_id,
                            ];
                            echo nc_ui_controls::get()->delete_button(NETCAT_MODULE_EXTSCRIPTS_CONFIRM_DELETE, $delete_params);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

<?php endif; ?>

<?php if (!empty($snippets)): ?>
    <?php
    // Предпросмотр кода
    $processor = new nc_external_scripts_processor($site_id);
    $preview = $processor->get_preview();
    $has_preview = !empty($preview['head_start']) || !empty($preview['head_end']) ||
                   !empty($preview['body_start']) || !empty($preview['body_end']);
    ?>

    <?php if ($has_preview): ?>
    <div class="nc-preview-section">
        <input type="button" class="nc-btn" value="<?= NETCAT_MODULE_EXTSCRIPTS_PREVIEW ?>" onclick="$nc('#previewContent').toggle();">
        <div id="previewContent" style="display: none; margin-top: 10px;">
            <?php if (!empty($preview['head_start'])): ?>
                <div class="nc-preview-block">
                    <h5><?= NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_START ?></h5>
                    <pre><code><?= htmlspecialchars($preview['head_start']) ?></code></pre>
                </div>
            <?php endif; ?>

            <?php if (!empty($preview['head_end'])): ?>
                <div class="nc-preview-block">
                    <h5><?= NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_END ?></h5>
                    <pre><code><?= htmlspecialchars($preview['head_end']) ?></code></pre>
                </div>
            <?php endif; ?>

            <?php if (!empty($preview['body_start'])): ?>
                <div class="nc-preview-block">
                    <h5><?= NETCAT_MODULE_EXTSCRIPTS_POS_BODY_START ?></h5>
                    <pre><code><?= htmlspecialchars($preview['body_start']) ?></code></pre>
                </div>
            <?php endif; ?>

            <?php if (!empty($preview['body_end'])): ?>
                <div class="nc-preview-block">
                    <h5><?= NETCAT_MODULE_EXTSCRIPTS_POS_BODY_END ?></h5>
                    <pre><code><?= htmlspecialchars($preview['body_end']) ?></code></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div>

<style>
.nc-external-scripts-list .nc-disabled td {
    opacity: 0.5;
}
.nc-external-scripts-list input.s {
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 2px 4px;
}
.nc-preview-section {
    margin-top: 30px;
    border-top: 1px solid #ddd;
    padding-top: 20px;
}
.nc-preview-section input[type="button"]:focus {
    outline: none;
    background-color: #7a7a7a;
}
.nc-preview-block {
    margin-bottom: 20px;
}
.nc-preview-block h5 {
    margin: 0 0 10px;
    padding: 5px 10px;
    background: #f5f5f5;
    border-left: 3px solid #007bff;
}
.nc-preview-block pre {
    margin: 0;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 300px;
}
.nc-preview-block code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 12px;
    color: #333;
}
</style>
