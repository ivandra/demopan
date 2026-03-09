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