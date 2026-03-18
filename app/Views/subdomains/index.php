<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="page-head">
    <h1 class="page-title">Каталог поддоменов</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
    <div class="page-subtitle">
        Общий каталог label и брендов по умолчанию. Значение из этого каталога используется как fallback,
        а на уровне конкретного сайта/label может быть переопределено в AI-настройках.
    </div>
</div>

<div class="panel-grid panel-grid--2">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Добавить пачкой</h2>

        <form method="post" action="/subdomains/bulk-add" class="stack-gap-md">
            <textarea name="labels" rows="8" placeholder="1win&#10;pinup&#10;betera"></textarea>
            <div class="small muted">
                Можно вставлять через пробел, запятую или перенос строки.
                Для `_default` отдельная строка уже должна быть в каталоге и редактируется ниже.
            </div>
            <div class="page-actions">
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>

    <div class="panel-card">
        <h2 class="section-title">Подсказка</h2>
        <div class="note">
            Здесь задается бренд по умолчанию для label. Например:
            <br><code>banda → Banda Casino</code>
            <br><code>gizbo → Gizbo Casino (Гизбо Казино)</code>
            <br><code>_default → общий root fallback</code>
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Текущие label</h2>
        <div class="small muted">Всего: <b><?= count($rows) ?></b></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Label</th>
                <th>Brand</th>
                <th>Active</th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><code><?= h((string)$r['label']) ?></code></td>
                    <td style="min-width:320px;">
                        <form method="post" action="/subdomains/save" class="inline-form" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input
                                type="text"
                                name="brand_name"
                                value="<?= h((string)($r['brand_name'] ?? '')) ?>"
                                placeholder="например: Gizbo Casino"
                                style="min-width:240px;"
                            >
                            <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                        </form>
                    </td>
                    <td>
                        <?php if ((int)$r['is_active'] === 1): ?>
                            <span class="badge badge-success">Да</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="system-actions">
                            <form method="post" action="/subdomains/toggle">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">
                                    <?= ((int)$r['is_active'] === 1) ? 'Выключить' : 'Включить' ?>
                                </button>
                            </form>

                            <form method="post"
                                  action="/subdomains/delete"
                                  data-confirm="Удалить label #<?= (int)$r['id'] ?> — <?= h((string)$r['label']) ?>?">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="5" class="muted">Каталог пуст.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>