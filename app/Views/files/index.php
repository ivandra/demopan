<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$siteId = (int)($site['id'] ?? 0);
?>

<div class="page-head">
    <h1 class="page-title">Файлы сборки: <?= h($site['domain'] ?? '') ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Контент и SEO</a>
        <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=_default">Страницы root</a>
        <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=_default">Тексты root</a>
    </div>
</div>

<div class="alert alert-info">
    Здесь редактируются только корневые файлы сборки сайта.
    Для multy-шаблона это общий корень build, а не экран отдельных поддоменов.
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
                           href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($f['name']) ?>">
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