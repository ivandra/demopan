<?php
// vars: $site, $siteId, $labels
?>
<h1>Clone site</h1>

<p>
    Source: <b><?php echo htmlspecialchars((string)($site['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></b>
</p>

<form method="post" action="/sites/clone?id=<?php echo (int)$siteId; ?>">
    <div style="margin-bottom:12px;">
        <label>New domain</label><br>
        <input type="text" name="new_domain" style="width:420px;" placeholder="example.com" required>
    </div>

    <div style="margin-bottom:12px;">
        <label>Subdomains to include</label><br>
        <?php foreach ($labels as $lb): ?>
            <label style="display:block;">
                <input type="checkbox" name="labels[]" value="<?php echo htmlspecialchars($lb, ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo ($lb === '_default') ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($lb, ENT_QUOTES, 'UTF-8'); ?>
            </label>
        <?php endforeach; ?>
        <small>Если оставить только _default, будет перенос только основного сайта.</small>
    </div>

    <button type="submit">Clone</button>
</form>
