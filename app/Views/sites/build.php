<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$site = $site ?? [];
$report = $report ?? [];

$siteId = (int)($site['id'] ?? 0);
$domain = (string)($site['domain'] ?? '');
$configFileForFiles = 'config.default.php';
?>

<div class="page-head">
    <h1 class="page-title">Результат build</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">Файлы build</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Контент и SEO</a>
    </div>
    <div class="page-subtitle">
        Сайт: <code><?= h($domain) ?></code>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Конфиг</div>
    <div class="site-context__title"><code><?= h($configTargetPath ?? '') ?></code></div>
    <div class="site-context__meta">
        <a href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($configFileForFiles) ?>">Открыть config в Files</a>
    </div>
</div>

<div class="panel-card mt-16">
    <?php if (!empty($report['ok'])): ?>
        <span class="badge badge-success">Build выполнен успешно</span>
    <?php else: ?>
        <span class="badge badge-danger">Build завершился с ошибкой</span>
    <?php endif; ?>
</div>

<div class="report-grid mt-16">
    <?php if (!empty($report['errors'])): ?>
        <div class="panel-card">
            <h2 class="section-title">Ошибки</h2>
            <ul class="list-clean">
                <?php foreach ($report['errors'] as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['warnings'])): ?>
        <div class="panel-card">
            <h2 class="section-title">Предупреждения</h2>
            <ul class="list-clean">
                <?php foreach ($report['warnings'] as $w): ?>
                    <li><?= h($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['created_texts'])): ?>
        <div class="panel-card">
            <h2 class="section-title">Созданные texts</h2>
            <ul class="list-clean">
                <?php foreach ($report['created_texts'] as $f): ?>
                    <li><code><?= h($f) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['unused_texts'])): ?>
        <div class="panel-card">
            <h2 class="section-title">Неиспользуемые texts</h2>
            <ul class="list-clean">
                <?php foreach ($report['unused_texts'] as $f): ?>
                    <li><code><?= h($f) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($report['errors']) && empty($report['warnings']) && empty($report['created_texts']) && empty($report['unused_texts'])): ?>
        <div class="panel-card">
            <div class="small muted">Подробностей по build нет.</div>
        </div>
    <?php endif; ?>
</div>