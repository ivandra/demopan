<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$site = is_array($site ?? null) ? $site : [];
$ai   = is_array($ai ?? null) ? $ai : [];
$runOptions = is_array($runOptions ?? null) ? $runOptions : [];

$siteId = (int)($site['id'] ?? 0);
$domain = (string)($site['domain'] ?? '');

$pdo = DB::pdo();

$labels = ['_default'];

try {
    $st = $pdo->prepare("
        SELECT label
        FROM site_subdomains
        WHERE site_id = ?
        ORDER BY label ASC
    ");
    $st->execute([$siteId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $r) {
        $lb = trim((string)($r['label'] ?? ''));
        if ($lb === '') continue;
        if (!in_array($lb, $labels, true)) {
            $labels[] = $lb;
        }
    }
} catch (Throwable $e) {
}

function labelTitle(string $lb): string {
    return $lb === '_default' ? 'Основной домен (_default)' : $lb;
}
?>

<div class="page-head">
    <h1 class="page-title">AI для сайта</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
        <a class="btn btn-secondary" href="/ai/settings">AI-настройки</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Контент и SEO</a>
    </div>
    <div class="page-subtitle">
        Сайт: <code><?= h($domain) ?></code>
    </div>
</div>

<div class="panel-card">
    <h2 class="section-title">Разовые параметры генерации</h2>
    <div class="small muted mb-14">
        Эти параметры применяются только к текущему сайту и живут как runtime-настройки.
        Они не заменяют глобальные промпты, а дополняют их.
    </div>

    <div class="panel-grid panel-grid--2">
        <div>
            <form method="post" action="/ai/options/save?id=<?= $siteId ?>" class="stack-gap-md">
                <div class="field-row">
                    <label>Объём текста</label>
                    <select name="text_length">
                        <option value="short" <?= (($runOptions['text_length'] ?? '') === 'short') ? 'selected' : '' ?>>Короткий</option>
                        <option value="medium" <?= (($runOptions['text_length'] ?? 'medium') === 'medium') ? 'selected' : '' ?>>Средний</option>
                        <option value="long" <?= (($runOptions['text_length'] ?? '') === 'long') ? 'selected' : '' ?>>Большой</option>
                    </select>
                </div>

                <div class="field-row">
                    <label>Режим перезаписи</label>
                    <select name="overwrite_mode">
                        <option value="fill_empty" <?= (($runOptions['overwrite_mode'] ?? 'fill_empty') === 'fill_empty') ? 'selected' : '' ?>>Только пустые / inherit</option>
                        <option value="overwrite_all" <?= (($runOptions['overwrite_mode'] ?? '') === 'overwrite_all') ? 'selected' : '' ?>>Перезаписывать всё</option>
                    </select>
                </div>

                <div class="field-row">
                    <label>Сквозная ссылка URL</label>
                    <input type="text" name="sitewide_link_url" value="<?= h($runOptions['sitewide_link_url'] ?? '') ?>" placeholder="https://example.com/page">
                </div>

                <div class="field-row">
                    <label>Анкор ссылки</label>
                    <input type="text" name="sitewide_link_anchor" value="<?= h($runOptions['sitewide_link_anchor'] ?? '') ?>" placeholder="Перейти на сайт">
                </div>

                <div class="field-row">
                    <label>CTA</label>
                    <input type="text" name="cta_text" value="<?= h($runOptions['cta_text'] ?? '') ?>" placeholder="Зарегистрируйтесь и начните игру">
                </div>

                <div class="field-row">
                    <label>Запрещённые слова / фразы</label>
                    <input type="text" name="forbidden_phrases" value="<?= h($runOptions['forbidden_phrases'] ?? '') ?>" placeholder="например: лучший, №1, бесплатно">
                </div>

                <div class="field-row">
                    <label>Обязательные вхождения / фразы</label>
                    <textarea name="required_phrases"><?= h($runOptions['required_phrases'] ?? '') ?></textarea>
                </div>

                <div class="field-row">
                    <label>Доп. инструкция</label>
                    <textarea name="extra_instruction"><?= h($runOptions['extra_instruction'] ?? '') ?></textarea>
                </div>

                <div class="page-actions">
                    <button class="btn btn-primary" type="submit">Сохранить параметры</button>
                </div>
            </form>

            <form method="post" action="/ai/options/reset?id=<?= $siteId ?>" class="mt-12" data-confirm="Сбросить разовые параметры генерации?">
                <button class="btn btn-danger" type="submit">Сбросить параметры</button>
            </form>
        </div>

        <div class="panel-card">
            <h3 class="section-title">Что сюда удобно задавать</h3>
            <ul class="status-list small">
                <li>нужный объём текста;</li>
                <li>обязательные фразы и словоформы;</li>
                <li>запрещённые слова;</li>
                <li>одну сквозную ссылку с анкором;</li>
                <li>мягкий CTA в конце текста;</li>
                <li>свободные инструкции под текущую пачку генерации.</li>
            </ul>
        </div>
    </div>
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Основной домен</h2>

        <div class="page-actions">
            <form method="post" action="/ai/generate-meta?id=<?= $siteId ?>" data-confirm="Сгенерировать meta для основного домена?">
                <button class="btn btn-primary" type="submit">Сгенерировать meta root</button>
            </form>

            <a class="btn btn-ai"
               href="/ai/generate-root-text?id=<?= $siteId ?>"
               data-confirm="Сгенерировать текст главной страницы для root?">
                Сгенерировать текст root
            </a>

            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=_default">Открыть pages root</a>
        </div>

        <div class="small muted">
            Генерация <code>title</code>, <code>h1</code>, <code>description</code>, <code>keywords</code>
            и текста главной страницы основного домена.
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Поддомены</h2>

        <div class="page-actions">
            <form method="post" action="/ai/generate-subdomains?id=<?= $siteId ?>" data-confirm="Сгенерировать meta для всех поддоменов?">
                <button class="btn btn-primary" type="submit">Сгенерировать meta для всех сабов</button>
            </form>

            <a class="btn btn-ai"
               href="/ai/generate-all-sub-texts?id=<?= $siteId ?>"
               data-confirm="Сгенерировать тексты для всех enabled поддоменов?">
                Сгенерировать тексты для всех сабов
            </a>

            <a class="btn btn-secondary" href="/sites/subdomains?id=<?= $siteId ?>">Управление сабами</a>
        </div>

        <div class="small muted">
            Массовая генерация SEO-меты и текстов главной страницы для всех поддоменов.
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Пакетно по выбранным label</h2>
        <div class="small muted">
            Отметьте нужные label и запустите только нужный тип генерации.
        </div>
    </div>

    <div class="panel-grid panel-grid--2">
        <div>
            <form id="ai-selected-meta-form" method="post" action="/ai/generate-selected-meta?id=<?= $siteId ?>">
                <div class="checklist-box">
                    <?php foreach ($labels as $lb): ?>
                        <label class="checklist-item">
                            <input type="checkbox" class="label-batch-check" name="labels[]" value="<?= h($lb) ?>">
                            <span><?= h(labelTitle($lb)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>

            <div class="page-actions mt-12">
                <button type="button" class="btn btn-secondary" data-check-all=".label-batch-check">Выбрать все</button>
                <button type="button" class="btn btn-secondary" data-check-none=".label-batch-check">Снять все</button>
            </div>
        </div>

        <div class="stack-gap-md">
            <button class="btn btn-primary"
                    type="submit"
                    form="ai-selected-meta-form"
                    data-require-checked=".label-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы один label."
                    data-confirm="Сгенерировать meta для выбранных label?">
                Сгенерировать meta
            </button>

            <button class="btn btn-ai"
                    type="submit"
                    formaction="/ai/generate-selected-texts?id=<?= $siteId ?>"
                    formmethod="post"
                    form="ai-selected-meta-form"
                    data-require-checked=".label-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы один label."
                    data-confirm="Сгенерировать тексты главной для выбранных label?">
                Сгенерировать тексты
            </button>

            <button class="btn btn-secondary"
                    type="submit"
                    formaction="/ai/generate-selected-pages?id=<?= $siteId ?>"
                    formmethod="post"
                    form="ai-selected-meta-form"
                    data-require-checked=".label-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы один label."
                    data-confirm="Сгенерировать все pages для выбранных label?">
                Сгенерировать все pages
            </button>
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Внутренние страницы по label</h2>
        <div class="small muted">
            Для каждой сущности можно быстро открыть pages / texts / subcfg или массово сгенерировать все pages.
        </div>
    </div>

    <div class="check-grid check-grid--2">
        <?php foreach ($labels as $lb): ?>
            <div class="panel-card stack-gap-sm">
                <div>
                    <span class="badge badge-muted"><?= h(labelTitle($lb)) ?></span>
                </div>

                <div class="small muted">
                    Метка: <code><?= h($lb) ?></code>
                </div>

                <div class="page-actions">
                    <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Контент и SEO</a>
                    <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Страницы</a>
                    <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Тексты</a>
                    <a class="btn btn-ai"
                       href="/ai/generate-all-pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>"
                       data-confirm="Сгенерировать meta и тексты для всех страниц: <?= h(labelTitle($lb)) ?>?">
                        Генерировать все pages
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>