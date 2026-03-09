<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$isMulty = (($site['template'] ?? '') === 'template-multy');
$siteId  = (int)($site['id'] ?? 0);
$label   = isset($label) ? (string)$label : '_default';

$configFileForLink = $isMulty ? 'config.default.php' : 'config.php';
$entityTitle = ($label === '_default') ? 'Основной домен (_default)' : ('Поддомен: ' . $label);
$entityHost  = ($label === '_default')
    ? (string)($site['domain'] ?? '')
    : ($label . '.' . (string)($site['domain'] ?? ''));
?>

<div class="page-head">
    <h1 class="page-title">Тексты: <?= h($site['domain'] ?? '') ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Контент и SEO</a>
        <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Страницы</a>
        <a class="btn btn-ai" href="/sites/ai?id=<?= $siteId ?>">AI для сайта</a>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Сейчас редактируется</div>
    <div class="site-context__title"><?= h($entityTitle) ?></div>
    <div class="site-context__meta">
        Хост: <code><?= h($entityHost) ?></code>
        <br>
        Конфиг генерируется в: <code><?= h($configTargetPath) ?></code>
        |
        <a href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($configFileForLink) ?>">Открыть config в Files</a>
    </div>
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Создать новый файл</h2>

        <form method="post" action="/sites/texts/new?id=<?= $siteId ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>" class="inline-form">
            <?php if ($isMulty): ?>
                <input type="hidden" name="label" value="<?= h($label) ?>">
            <?php endif; ?>

            <input type="text" name="new_file" placeholder="new.php">
            <button type="submit" class="btn btn-primary">Создать</button>
        </form>

        <div class="small muted">
            Обычно это PHP-фрагменты, которые подключаются страницами из массива <code>pages</code>.
        </div>
    </div>

    <div class="panel-card">
        <h2 class="section-title">Подсказка</h2>
        <div class="note">
            На этом экране редактируются именно файлы в <code>texts/</code> для выбранного label.
            Привязка файла к странице задаётся на экране <b>Страницы</b>.
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Список файлов</h2>
        <div class="small muted">Всего файлов: <b><?= count($files) ?></b></div>
    </div>

    <?php if (!$files): ?>
        <div class="alert alert-warning">Файлов в texts пока нет.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Файл</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td><code><?= h($f) ?></code></td>
                        <td>
                            <div class="inline-actions">
                                <a class="btn btn-sm btn-secondary"
                                   href="/sites/texts/edit?id=<?= $siteId ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>&file=<?= rawurlencode($f) ?>">
                                    Открыть
                                </a>

                                <form method="post"
                                      action="/sites/texts/delete?id=<?= $siteId ?><?= $isMulty ? '&label=' . urlencode($label) : '' ?>"
                                      data-confirm="Удалить файл <?= h($f) ?>?">
                                    <input type="hidden" name="file" value="<?= h($f) ?>">
                                    <?php if ($isMulty): ?>
                                        <input type="hidden" name="label" value="<?= h($label) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>