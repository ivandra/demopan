<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$deploy = is_array($deploy ?? null) ? $deploy : [];
?>

<div class="page-head">
    <h1 class="page-title">Deploy report #<?= (int)($deploy['id'] ?? 0) ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
        <?php if (!empty($deploy['site_id'])): ?>
            <a class="btn btn-secondary" href="/sites/overview?id=<?= (int)$deploy['site_id'] ?>">Обзор сайта</a>
            <a class="btn btn-secondary" href="/deploy?id=<?= (int)$deploy['site_id'] ?>">Публикация</a>
        <?php endif; ?>
    </div>
</div>

<div class="panel-grid panel-grid--3">
    <div class="panel-card">
        <div class="small muted">Status</div>
        <div class="kpi"><?= h((string)($deploy['status'] ?? '—')) ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Site ID</div>
        <div class="kpi"><?= (int)($deploy['site_id'] ?? 0) ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Created</div>
        <div class="kpi" style="font-size:18px;"><?= h((string)($deploy['created_at'] ?? '—')) ?></div>
    </div>
</div>

<?php if (!empty($deploy['last_error'])): ?>
    <div class="panel-card mt-16">
        <h2 class="section-title">Ошибка</h2>
        <pre class="report-code"><?= h((string)$deploy['last_error']) ?></pre>
    </div>
<?php endif; ?>

<div class="panel-card mt-16">
    <h2 class="section-title">Payload</h2>
    <pre class="report-code"><?= h((string)($deploy['payload'] ?? '')) ?></pre>
</div>

<div class="panel-card mt-16">
    <h2 class="section-title">Response</h2>
    <pre class="report-code"><?= h((string)($deploy['response'] ?? '')) ?></pre>
</div>