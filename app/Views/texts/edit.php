<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteId  = (int)($site['id'] ?? 0);
$label   = isset($label) ? (string)$label : '_default';

$configFileForLink = 'config.default.php';
$entityTitle = ($label === '_default') ? 'Основной домен (_default)' : ('Поддомен: ' . $label);
$entityHost  = ($label === '_default')
    ? (string)($site['domain'] ?? '')
    : ($label . '.' . (string)($site['domain'] ?? ''));
?>

<div class="page-head">
    <h1 class="page-title">Редактирование текста</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">К списку текстов</a>
        <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Страницы</a>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Текущий файл</div>
    <div class="site-context__title"><code><?= h($safeFile) ?></code></div>
    <div class="site-context__meta">
        Сущность: <?= h($entityTitle) ?>
        <br>
        Хост: <code><?= h($entityHost) ?></code>
        <br>
        Конфиг генерируется в: <code><?= h($configTargetPath) ?></code>
        |
        <a href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($configFileForLink) ?>">Открыть config в файлах</a>
    </div>
</div>

<?php if (!empty($mentionStats) && is_array($mentionStats)): ?>
<div class="panel-card mt-16" style="border:2px solid #2563eb;background:#f8fbff;">
    <div style="font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#2563eb;margin-bottom:10px;">Контроль упоминаний бренда</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Бренд</div>
            <div style="font-size:22px;font-weight:700;"><?= h((string)($mentionStats['brand'] ?? '—')) ?></div>
        </div>
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Русский вариант</div>
            <div style="font-size:22px;font-weight:700;"><?= h((string)($mentionStats['brand_ru'] ?? '—')) ?></div>
        </div>
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Целевое число упоминаний</div>
            <div style="font-size:28px;font-weight:700;color:#1d4ed8;"><?= (int)($mentionStats['target_count'] ?? 0) ?></div>
        </div>
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Фактическое число упоминаний</div>
            <div style="font-size:28px;font-weight:700;color:<?= ((int)($mentionStats['actual_count'] ?? 0) === (int)($mentionStats['target_count'] ?? 0)) ? '#15803d' : '#b91c1c' ?>;"><?= (int)($mentionStats['actual_count'] ?? 0) ?></div>
        </div>
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Целевое число символов</div>
            <div style="font-size:28px;font-weight:700;color:#1d4ed8;"><?= (int)($mentionStats['target_symbols'] ?? 0) ?></div>
        </div>
        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;">Фактическое число символов</div>
            <div style="font-size:28px;font-weight:700;color:<?= ((int)($mentionStats['actual_symbols'] ?? 0) >= (int)($mentionStats['target_symbols'] ?? 0)) ? '#15803d' : '#b91c1c' ?>;"><?= (int)($mentionStats['actual_symbols'] ?? 0) ?></div>
        </div>
    </div>

    <?php if (!empty($mentionStats['variants']) && is_array($mentionStats['variants'])): ?>
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:#fff;border:1px solid #dbeafe;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:8px;">Разбивка по вариантам бренда</div>
            <?php foreach ($mentionStats['variants'] as $variantName => $variantCount): ?>
                <div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #eef2ff;">
                    <div><code><?= h((string)$variantName) ?></code></div>
                    <div style="font-weight:700;"><?= (int)$variantCount ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel-card mt-16">
    <div class="ai-note mb-14">
        В этот файл AI может записывать HTML-фрагменты для страницы:
        <code>home.php</code>, <code>game.php</code>, <code>demo.php</code> и другие.
    </div>

    <form method="post" action="/sites/texts/save?id=<?= $siteId ?>&label=<?= urlencode($label) ?>" class="stack-gap-md">
        <input type="hidden" name="file" value="<?= h($safeFile) ?>">
        <input type="hidden" name="label" value="<?= h($label) ?>">

        <textarea name="content" class="editor-textarea"><?= h($content) ?></textarea>

        <div class="page-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Назад к списку</a>
        </div>
    </form>
</div>