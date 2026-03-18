<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$siteId = (int)($site['id'] ?? 0);
$scope = (string)($scope ?? 'root');
$label = (string)($label ?? '_default');
$labelsForFiles = is_array($labelsForFiles ?? null) ? $labelsForFiles : ['_default'];
$isAssets = ($scope === 'assets');
?>

<div class="page-head">
    <h1 class="page-title">Файлы сборки: <?= h($site['domain'] ?? '') ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= rawurlencode($label) ?>">Контент и SEO</a>
        <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= rawurlencode($label) ?>">Страницы</a>
        <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= rawurlencode($label) ?>">Тексты</a>
    </div>
</div>

<div class="panel-card stack-gap-md">
    <h2 class="section-title">Область редактирования</h2>

    <form method="get" action="/sites/files" class="inline-form">
        <input type="hidden" name="id" value="<?= $siteId ?>">

        <label>
            Раздел
            <select name="scope" onchange="this.form.submit()">
                <option value="root" <?= $scope === 'root' ? 'selected' : '' ?>>Корневые build-файлы</option>
                <option value="assets" <?= $scope === 'assets' ? 'selected' : '' ?>>Assets поддомена</option>
            </select>
        </label>

        <label>
            Label
            <select name="label" onchange="this.form.submit()" <?= $scope === 'assets' ? '' : 'disabled' ?>>
                <?php foreach ($labelsForFiles as $lb): ?>
                    <option value="<?= h($lb) ?>" <?= $lb === $label ? 'selected' : '' ?>><?= h($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <noscript><button type="submit" class="btn btn-secondary">Открыть</button></noscript>
    </form>

    <?php if ($isAssets): ?>
        <div class="alert alert-info">
            Здесь можно заменять бинарные assets текущего label: логотип и favicon в папке
            <code>subs/<?= h($label) ?>/assets</code>.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Здесь редактируются только корневые build-файлы сайта.
            Для multy-шаблона это общий корень build, а не папка отдельного поддомена.
        </div>
    <?php endif; ?>
</div>

<div class="panel-card">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Список файлов</h2>
        <div class="small muted">Всего файлов: <b><?= count($files) ?></b></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Файл</th>
                <th>Статус</th>
                <th>Размер</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><code><?= h($f['name']) ?></code></td>
                    <td>
                        <?php if (!empty($f['exists'])): ?>
                            <span class="badge badge-success">Есть</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$f['size'] ?> байт</td>
                    <td>
                        <a class="btn btn-sm btn-secondary"
                           href="/sites/files/edit?id=<?= $siteId ?>&scope=<?= rawurlencode($scope) ?>&label=<?= rawurlencode($label) ?>&file=<?= rawurlencode($f['name']) ?>">
                            Открыть
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$files): ?>
                <tr>
                    <td colspan="4" class="muted">Файлы не найдены.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
