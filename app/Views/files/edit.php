<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$siteId = (int)($site['id'] ?? 0);
?>

<div class="page-head">
    <h1 class="page-title">Редактирование файла build</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">К списку файлов</a>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Текущий файл</div>
    <div class="site-context__title"><code><?= h($safeFile) ?></code></div>
    <div class="site-context__meta">
        Сайт: <?= h($site['domain'] ?? '') ?>
    </div>
</div>

<div class="panel-card mt-16">
    <form method="post" action="/sites/files/save?id=<?= $siteId ?>" class="stack-gap-md">
        <input type="hidden" name="file" value="<?= h($safeFile) ?>">

        <textarea name="content" class="editor-textarea"><?= h($content) ?></textarea>

        <div class="page-actions">
            <button type="submit" class="btn btn-primary">Сохранить (с бэкапом)</button>
            <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">Назад к списку</a>
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

            <select name="backup">
                <?php foreach ($backups as $b): ?>
                    <option value="<?= h($b) ?>"><?= h($b) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-danger">Восстановить</button>
        </form>
    </div>
<?php endif; ?>