<?php
// app/Views/sites/subcfg.php
// Ожидает: $site, $siteId, $label, $labels, $cfg, $unused

$siteId = (int)($siteId ?? 0);
$label  = (string)($label ?? '_default');
$labels = is_array($labels ?? null) ? $labels : ['_default'];
$cfg    = is_array($cfg ?? null) ? $cfg : [];
$unused = is_array($unused ?? null) ? $unused : [];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$entityTitle = ($label === '_default') ? 'Основной домен (_default)' : ('Поддомен: ' . $label);
$entityHost  = ($label === '_default')
    ? (string)($site['domain'] ?? '')
    : ($label . '.' . (string)($site['domain'] ?? ''));
?>

<div class="subcfg-wrap">
    <div class="page-head">
        <h1 class="page-title">Контент и SEO: <?= e($site['domain'] ?? '') ?></h1>
        <div class="page-actions">
            <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
            <a class="btn btn-secondary" href="/sites/edit?id=<?= $siteId ?>">Настройки сайта</a>
        </div>
    </div>

    <div class="site-context panel-card">
        <div class="site-context__eyebrow">Сейчас редактируется</div>
        <div class="site-context__title"><?= e($entityTitle) ?></div>
        <div class="site-context__meta">Хост: <code><?= e($entityHost) ?></code></div>

        <div class="subcfg-actions mt-12">
            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Страницы</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Тексты</a>
            <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Файлы</a>
            <a class="btn btn-ai" href="/sites/ai?id=<?= $siteId ?>">AI для сайта</a>
            <a class="btn btn-secondary" href="/sites/subdomains?id=<?= $siteId ?>">Поддомены</a>
        </div>
    </div>

    <div class="subcfg-top mt-16">
        <div class="panel-card">
            <label>Поиск сущности</label>
            <input id="subSearch" type="text" placeholder="например: 1win, pinup, _default" data-filter-options="#subSelect">
            <div class="small muted">Фильтрует список. Enter не нужен.</div>
        </div>

        <div class="panel-card">
            <label>Выбор root / поддомена</label>
            <select id="subSelect" data-set-query-param="label">
                <?php foreach ($labels as $lb): ?>
                    <option value="<?= e($lb) ?>" <?= $lb === $label ? 'selected' : '' ?>>
                        <?= e($lb === '_default' ? 'Основной домен (_default)' : $lb) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="small muted">Открывает экран редактирования для выбранной сущности.</div>
        </div>

        <div class="panel-card subcfg-top__wide">
            <label>Быстрые действия</label>
            <div class="subcfg-actions">
                <form method="post" action="/sites/subcfg/regenAll" data-confirm="Перегенерировать config.php для всех сабов?">
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                    <button type="submit" class="btn btn-secondary">Пересобрать config.php для всех</button>
                </form>

                <form method="post" action="/sites/subcfg/create" data-confirm="Создать саб + папки + config.php?">
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                    <input type="text" name="new_label" placeholder="new-sub" class="input-sm">
                    <button type="submit" class="btn btn-primary">Создать поддомен</button>
                </form>

                <?php if ($label !== '_default'): ?>
                    <form method="post" action="/sites/subcfg/delete" data-confirm="Удалить конфиг саба <?= e($label) ?> из БД?">
                        <input type="hidden" name="site_id" value="<?= $siteId ?>">
                        <input type="hidden" name="label" value="<?= e($label) ?>">
                        <label class="checkbox-inline small">
                            <input type="checkbox" name="delete_folder" value="1"> удалить папку subs/<?= e($label) ?>
                        </label>
                        <button type="submit" class="btn btn-danger">Удалить конфиг</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-16">
        <b>Важно:</b> при открытии экрана панель вызывает provisioner и гарантирует наличие
        <code>subs/&lt;label&gt;/</code> (texts + assets + config.php).
        Для основного домена используется <code>subs/_default</code>.
    </div>

    <form method="post" action="/sites/subcfg/save" class="mt-16 stack-gap-lg">
        <input type="hidden" name="site_id" value="<?= $siteId ?>">
        <input type="hidden" name="label" value="<?= e($label) ?>">

        <div class="subcfg-grid">
            <div class="panel-card">
                <h3 class="section-title">SEO-поля и мета (для <?= e($label) ?>)</h3>

                <div class="subcfg-actions mb-14">
                    <a
                        class="btn btn-ai"
                        href="/ai/generate-sub-meta?id=<?= $siteId ?>&label=<?= urlencode($label) ?>"
                        data-confirm="Сгенерировать AI meta для саба <?= e($label) ?>? Текущие title/h1/description/keywords будут перезаписаны."
                    >
                        AI: сгенерировать meta
                    </a>

                    <?php if ($label === '_default'): ?>
                        <a
                            class="btn btn-secondary"
                            href="/ai/generate-meta?id=<?= $siteId ?>"
                            data-confirm="Сгенерировать AI meta для основного домена?"
                        >
                            AI: meta root
                        </a>
                    <?php endif; ?>
                </div>

                <div class="ai-note small">
                    Для текущего саба AI может автоматически заполнить:
                    <b>title</b>, <b>h1</b>, <b>description</b>, <b>keywords</b>.
                </div>

                <div class="subcfg-actions mt-14 mb-14">
                    <a
                        class="btn btn-ai"
                        href="/ai/generate-sub-text?id=<?= $siteId ?>&label=<?= urlencode($label) ?>"
                        data-confirm="Сгенерировать AI-текст для саба <?= e($label) ?>? Будет перезаписан файл текста главной страницы."
                    >
                        AI: сгенерировать текст
                    </a>

                    <?php if ($label === '_default'): ?>
                        <a
                            class="btn btn-ai"
                            href="/ai/generate-root-text?id=<?= $siteId ?>"
                            data-confirm="Сгенерировать AI-текст для основного домена? Будет перезаписан файл главной страницы."
                        >
                            AI: текст root
                        </a>
                    <?php endif; ?>
                </div>

                <div class="row mt-12">
                    <label>title</label>
                    <input type="text" name="title" value="<?= e($cfg['title'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>h1</label>
                    <input type="text" name="h1" value="<?= e($cfg['h1'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>description</label>
                    <input type="text" name="description" value="<?= e($cfg['description'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>keywords</label>
                    <input type="text" name="keywords" value="<?= e($cfg['keywords'] ?? '') ?>">
                </div>
            </div>

            <div class="panel-card">
                <h3 class="section-title">Редиректы, партнёрские ссылки и assets</h3>

                <div class="row">
                    <label>promolink</label>
                    <input type="text" name="promolink" value="<?= e($cfg['promolink'] ?? '/reg') ?>">
                </div>

                <div class="row">
                    <label>internal_reg_url</label>
                    <input type="text" name="internal_reg_url" value="<?= e($cfg['internal_reg_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>partner_override_url</label>
                    <input type="text" name="partner_override_url" value="<?= e($cfg['partner_override_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="redirect_enabled" value="1" <?= !empty($cfg['redirect_enabled']) ? 'checked' : '' ?>>
                        redirect_enabled
                    </label>
                </div>

                <div class="row">
                    <label>base_new_url</label>
                    <input type="text" name="base_new_url" value="<?= e($cfg['base_new_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>base_second_url</label>
                    <input type="text" name="base_second_url" value="<?= e($cfg['base_second_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>logo (path)</label>
                    <input type="text" name="logo" value="<?= e($cfg['logo'] ?? 'assets/logo.png') ?>">
                    <div class="small muted">Обычно: <code>assets/logo.png</code></div>
                </div>

                <div class="row">
                    <label>favicon (path)</label>
                    <input type="text" name="favicon" value="<?= e($cfg['favicon'] ?? 'assets/favicon.png') ?>">
                    <div class="small muted">Обычно: <code>assets/favicon.png</code></div>
                </div>
            </div>
        </div>

        <div class="subcfg-actions">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Открыть страницы</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Открыть тексты</a>
        </div>
    </form>

    <?php if (!empty($unused)): ?>
        <div class="panel-card mt-16">
            <h3 class="section-title">Неиспользуемые texts (<?= e($label) ?>)</h3>
            <ul class="unused">
                <?php foreach ($unused as $f): ?>
                    <li><code><?= e($f) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <div class="small muted">Это просто список. Удаление можно добавить отдельным action, если надо.</div>
        </div>
    <?php endif; ?>
</div>