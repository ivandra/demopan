<?php
$siteId = (int)($siteId ?? 0);
$label  = (string)($label ?? '_default');
$labels = is_array($labels ?? null) ? $labels : ['_default'];
$cfg    = is_array($cfg ?? null) ? $cfg : [];
$unused = is_array($unused ?? null) ? $unused : [];
$partnerSubId = (string)($partnerSubId ?? '');

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$entityTitle = ($label === '_default') ? 'Основной домен (_default)' : ('Поддомен: ' . $label);
$entityHost  = ($label === '_default')
    ? (string)($site['domain'] ?? '')
    : ($label . '.' . (string)($site['domain'] ?? ''));

$isRoot = ($label === '_default');
?>

<div class="subcfg-wrap">
    <div class="page-head">
        <h1 class="page-title">Контент и SEO: <?= e($site['domain'] ?? '') ?></h1>
        <div class="page-actions">
            <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
            <a class="btn btn-secondary" href="/sites/edit?id=<?= $siteId ?>">Настройки сайта</a>
            <a class="btn btn-secondary" href="/sites/subdomains?id=<?= $siteId ?>">Поддомены</a>
        </div>
        <div class="page-subtitle">
            Здесь редактируются фактические SEO-поля и технические ссылки текущего root / поддомена.
        </div>
    </div>

    <div class="site-context panel-card">
        <div class="site-context__eyebrow">Сейчас редактируется</div>
        <div class="site-context__title"><?= e($entityTitle) ?></div>
        <div class="site-context__meta">Хост: <code><?= e($entityHost) ?></code></div>

        <div class="subcfg-actions mt-12">
            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Страницы</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Тексты</a>
            <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">Файлы build</a>
            <a class="btn btn-ai" href="/sites/ai?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">AI для текущего label</a>
        </div>
    </div>

    <div class="subcfg-top mt-16">
<div class="panel-card">
            <label>Выбор root / поддомена</label>
            <select id="subSelect" data-set-query-param="label">
                <?php foreach ($labels as $lb): ?>
                    <option value="<?= e($lb) ?>" <?= $lb === $label ? 'selected' : '' ?>>
                        <?= e($lb === '_default' ? 'Основной домен (_default)' : $lb) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="small muted">Переключает экран редактирования на выбранную сущность.</div>
        </div>

        <div class="panel-card subcfg-top__wide">
            <label>Быстрые действия</label>
            <div class="subcfg-actions">
                <div class="small muted">На этом экране можно отредактировать настройки текущего label и при сохранении сразу скопировать их на все поддомены.</div>
            </div>
        </div>
    </div>
    </div>
<form method="post" action="/sites/subcfg/save" class="mt-16 stack-gap-lg">
        <input type="hidden" name="site_id" value="<?= $siteId ?>">
        <input type="hidden" name="label" value="<?= e($label) ?>">

        <div class="subcfg-grid">
            <div class="panel-card">
                <h3 class="section-title">SEO-поля и мета</h3>

                <div class="subcfg-actions mb-14">
                    <?php if ($isRoot): ?>
                        <a
                            class="btn btn-ai"
                            href="/ai/generate-meta?id=<?= $siteId ?>"
                            data-confirm="Сгенерировать AI meta для основного домена? Текущие title/h1/description/keywords будут перезаписаны."
                        >
                            AI: сгенерировать meta root
                        </a>
                    <?php else: ?>
                        <a
                            class="btn btn-ai"
                            href="/ai/generate-sub-meta?id=<?= $siteId ?>&label=<?= urlencode($label) ?>"
                            data-confirm="Сгенерировать AI meta для поддомена <?= e($label) ?>? Текущие title/h1/description/keywords будут перезаписаны."
                        >
                            AI: сгенерировать meta
                        </a>
                    <?php endif; ?>
                </div>

                <div class="ai-note small">
                    Здесь хранятся <b>фактические</b> SEO-поля текущей сущности.
                    AI-кнопки ниже только помогают заполнить их автоматически.
                </div>

                <div class="subcfg-actions mt-14 mb-14">
                    <?php if ($isRoot): ?>
                        <a
                            class="btn btn-ai"
                            href="/ai/generate-root-text?id=<?= $siteId ?>"
                            data-confirm="Сгенерировать AI-текст для основного домена? Будет перезаписан файл главной страницы."
                        >
                            AI: сгенерировать текст root
                        </a>
                    <?php else: ?>
                        <a
                            class="btn btn-ai"
                            href="/ai/generate-sub-text?id=<?= $siteId ?>&label=<?= urlencode($label) ?>"
                            data-confirm="Сгенерировать AI-текст для поддомена <?= e($label) ?>? Будет перезаписан файл текста главной страницы."
                        >
                            AI: сгенерировать текст
                        </a>
                    <?php endif; ?>
                </div>

                <div class="row mt-12">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= e($cfg['title'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>H1</label>
                    <input type="text" name="h1" value="<?= e($cfg['h1'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>Description</label>
                    <input type="text" name="description" value="<?= e($cfg['description'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>Keywords</label>
                    <input type="text" name="keywords" value="<?= e($cfg['keywords'] ?? '') ?>">
                </div>
            </div>

            <div class="panel-card">
                <h3 class="section-title">Ссылки, redirect и assets</h3>

                <div class="row">
                    <label>Promolink</label>
                    <input type="text" name="promolink" value="<?= e($cfg['promolink'] ?? '/reg') ?>">
                </div>

                <div class="row">
                    <label>internal_reg_url</label>
                    <input type="text" name="internal_reg_url" value="<?= e($cfg['internal_reg_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>Сгенерированный sub_id</label>
                    <input type="text" value="<?= e($partnerSubId) ?>" readonly>
                    <div class="small muted">Для текущего label будет использован именно этот sub_id.</div>
                </div>

                <div class="row">
                    <label>partner_override_url</label>
                    <input type="text" name="partner_override_url" value="<?= e($cfg['partner_override_url'] ?? '') ?>">
                    <div class="small muted">Если ссылка внешняя, панель добавит или заменит параметр <code>sub_id=<?= e($partnerSubId) ?></code>. Пустые URL автоматически берутся из root-конфига этого сайта.</div>
                </div>

                <div class="row">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="redirect_enabled" value="1" <?= !empty($cfg['redirect_enabled']) ? 'checked' : '' ?>>
                        Включить redirect_enabled
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
                    <div class="small muted">Обычно: <code>assets/logo.png</code> или <code>assets/logo.webp</code></div>
                </div>

                <div class="row">
                    <label>favicon (path)</label>
                    <input type="text" name="favicon" value="<?= e($cfg['favicon'] ?? 'assets/favicon.png') ?>">
                    <div class="small muted">Обычно: <code>assets/favicon.png</code></div>
                </div>
            </div>
        </div>

        <div class="subcfg-actions">
            <label class="checkbox-inline small"><input type="checkbox" name="copy_to_all_labels" value="1"> Сохранить настройки для всех поддоменов</label>
            <button type="submit" class="btn btn-primary">Сохранить настройки</button>
            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Открыть страницы</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Открыть тексты</a>
        </div>
    </form>

    <?php if (!empty($unused)): ?>
        <div class="panel-card mt-16">
            <h3 class="section-title">Неиспользуемые texts</h3>
            <ul class="unused">
                <?php foreach ($unused as $f): ?>
                    <li><code><?= e($f) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <div class="small muted">
                Это просто список для контроля. Удаление можно добавить отдельным действием позже.
            </div>
        </div>
    <?php endif; ?>
</div>