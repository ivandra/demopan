<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$diag = is_array($diag ?? null) ? $diag : [];
$results = is_array($diag['results'] ?? null) ? $diag['results'] : [];
$apiHosts = is_array($diag['api_hosts'] ?? null) ? $diag['api_hosts'] : [];
$desiredHosts = is_array($diag['desiredHosts'] ?? null) ? $diag['desiredHosts'] : [];
?>
<div class="page-head">
    <h1 class="page-title">Техстраница проверки индекса</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/webmaster/site?id=<?= (int)($site['id'] ?? 0) ?>">Назад к сайту</a>
        <a class="btn btn-secondary" href="/webmaster">К вебмастеру</a>
    </div>
    <div class="page-subtitle">Сайт #<?= (int)($site['id'] ?? 0) ?> — <code><?= h($site['domain'] ?? '') ?></code></div>
</div>
<div class="panel-card stack-gap-md">
    <form method="get" action="/webmaster/index-tech" class="inline-form">
        <input type="hidden" name="id" value="<?= (int)($site['id'] ?? 0) ?>">
        <label>Метка
            <select name="label">
                <option value="ALL"<?= ($targetLabel === 'ALL' ? ' selected' : '') ?>>ALL</option>
                <option value=""<?= ($targetLabel === '' ? ' selected' : '') ?>>root</option>
                <?php foreach ($desiredHosts as $row): $lbl=(string)($row['label'] ?? ''); if ($lbl==='') continue; ?>
                    <option value="<?= h($lbl) ?>"<?= ($targetLabel === $lbl ? ' selected' : '') ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary">Запустить техпроверку</button>
    </form>
    <div class="small muted">Страница не меняет redirect_enabled и не трогает основной cron. Она только показывает ответы разных методов API и HTML fallback.</div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger mt-16"><?= h($error) ?></div><?php endif; ?>
<div class="panel-card mt-16"><h2 class="section-title">API host list</h2><pre class="log-console"><?= h(json_encode($apiHosts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre></div>
<?php foreach ($results as $item): ?>
<div class="panel-card mt-16">
    <h2 class="section-title"><?= h(($item['debug']['label'] ?? '') === '' ? 'root' : (string)($item['debug']['label'] ?? '')) ?> — <?= h($item['debug']['host'] ?? '') ?></h2>
    <div class="small muted">host_url: <code><?= h($item['debug']['host_url'] ?? '') ?></code></div>
    <div class="small muted">host_id: <code><?= h($item['debug']['host_id'] ?? '') ?></code></div>
    <?php foreach ((array)($item['checks'] ?? []) as $name => $payload): ?>
        <h3 class="section-title mt-16" style="font-size:16px;">Метод: <?= h($name) ?></h3>
        <pre class="log-console"><?= h(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
