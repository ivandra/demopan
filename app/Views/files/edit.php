<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$siteId = (int)($site['id'] ?? 0);
$scope = (string)($scope ?? 'root');
$label = (string)($label ?? '_default');
$isBinary = !empty($isBinary);
$previewDataUri = (string)($previewDataUri ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Редактирование файла build</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>&scope=<?= rawurlencode($scope) ?>&label=<?= rawurlencode($label) ?>">К списку файлов</a>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Текущий файл</div>
    <div class="site-context__title"><code><?= h($safeFile) ?></code></div>
    <div class="site-context__meta">
        Сайт: <?= h($site['domain'] ?? '') ?>
        <?php if ($scope === 'assets'): ?>
            · Label: <code><?= h($label) ?></code>
            · Папка: <code>subs/<?= h($label) ?>/assets</code>
        <?php else: ?>
            · Корневой build
        <?php endif; ?>
    </div>
</div>

<div class="panel-card mt-16">
    <form method="post" action="/sites/files/save?id=<?= $siteId ?>" class="stack-gap-md" enctype="multipart/form-data">
        <input type="hidden" name="file" value="<?= h($safeFile) ?>">
        <input type="hidden" name="scope" value="<?= h($scope) ?>">
        <input type="hidden" name="label" value="<?= h($label) ?>">

        <?php if ($isBinary): ?>
            <div class="alert alert-info">
                Для favicon и logo используется замена через загрузку файла. Текущая версия будет сохранена в backup.
            </div>

            <?php if ($previewDataUri !== ''): ?>
                <div>
                    <div class="small muted mb-8">Текущее изображение</div>
                    <img src="<?= h($previewDataUri) ?>" alt="preview" style="max-width:220px;max-height:220px;border:1px solid #d8dee6;border-radius:12px;padding:8px;background:#fff;">
                </div>
            <?php endif; ?>

            <label>
                Новый файл
                <input type="file" name="upload" required>
            </label>
        <?php else: ?>
            <textarea name="content" class="editor-textarea"><?= h($content) ?></textarea>
        <?php endif; ?>

        <div class="page-actions">
            <button type="submit" class="btn btn-primary"><?= $isBinary ? 'Заменить файл (с бэкапом)' : 'Сохранить (с бэкапом)' ?></button>
            <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>&scope=<?= rawurlencode($scope) ?>&label=<?= rawurlencode($label) ?>">Назад к списку</a>
        </div>
    </form>
</div>

<?php if (!empty($backups)): ?>
    <div class="panel-card mt-16">
        <h2 class="section-title">Бэкапы</h2>

        <form method="post"
              action="/sites/files/restore?id=<?= $siteId ?>"
              data-confirm="Восстановить выбранный бэкап? Текущий файл будет сохранён как новый бэкап."
              class="inline-form">
            <input type="hidden" name="file" value="<?= h($safeFile) ?>">
            <input type="hidden" name="scope" value="<?= h($scope) ?>">
            <input type="hidden" name="label" value="<?= h($label) ?>">

            <select name="backup">
                <?php foreach ($backups as $b): ?>
                    <option value="<?= h($b) ?>"><?= h($b) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-danger">Восстановить</button>
        </form>
    </div>
<?php endif; ?>
