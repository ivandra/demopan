<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$deploy = is_array($deploy ?? null) ? $deploy : [];
$statusRaw = (string)($deploy['status'] ?? '');
$statusMap = [
    'done' => ['Успешно', 'badge-success', 'Операция завершена успешно.'],
    'error' => ['Ошибка', 'badge-danger', 'Операция завершилась с ошибкой.'],
    'creating_site' => ['Создание сайта', 'badge-warning', 'Панель создает сайт в FastPanel.'],
    'uploading_files' => ['Идет выгрузка', 'badge-warning', 'Идет выгрузка файлов на VPS.'],
];
$statusInfo = $statusMap[$statusRaw] ?? [($statusRaw !== '' ? $statusRaw : 'Неизвестно'), 'badge-muted', 'Статус операции не распознан.'];
?>

<div class="page-head">
    <h1 class="page-title">Отчёт deploy #<?= (int)($deploy['id'] ?? 0) ?></h1>
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
        <div class="small muted">Статус</div>
        <div class="kpi"><span class="badge <?= h($statusInfo[1]) ?>"><?= h($statusInfo[0]) ?></span></div><div class="small muted mt-12"><?= h($statusInfo[2]) ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">ID сайта</div>
        <div class="kpi"><?= (int)($deploy['site_id'] ?? 0) ?></div>
    </div>

    <div class="panel-card">
        <div class="small muted">Создан</div>
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
    <h2 class="section-title">Что отправлялось</h2>
    <pre class="report-code"><?= h((string)($deploy['payload'] ?? '')) ?></pre>
</div>

<div class="panel-card mt-16">
    <h2 class="section-title">Ответ сервера</h2>
    <pre class="report-code"><?= h((string)($deploy['response'] ?? '')) ?></pre>
</div>