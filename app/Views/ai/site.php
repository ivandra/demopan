<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

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
    return $lb === '_default' ? 'root / _default' : $lb;
}
?>
<style>
.ai-wrap{
    max-width: 1280px;
}

.ai-page-head{
    margin-bottom: 18px;
}
.ai-page-head h2{
    margin: 0 0 8px 0;
    font-size: 28px;
    line-height: 1.2;
}
.ai-page-head .ai-domain{
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef4ff;
    border: 1px solid #d7e3ff;
    color: #2457b2;
    font-size: 13px;
    font-weight: 600;
}

.ai-top-links{
    margin: 0 0 20px 0;
    font-size: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.ai-top-links a{
    text-decoration: none;
}

.ai-section{
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.ai-section h3{
    margin: 0 0 8px 0;
    font-size: 20px;
    line-height: 1.25;
}
.ai-section-head{
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.ai-section-note{
    color: #666;
    font-size: 13px;
    max-width: 900px;
}

.ai-form-grid{
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.ai-field{
    min-width: 0;
}
.ai-field-full{
    grid-column: 1 / -1;
}
.ai-field label{
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    color: #555;
    font-weight: 600;
}
.ai-field input,
.ai-field select,
.ai-field textarea{
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #d8dde6;
    border-radius: 10px;
    background: #fff;
    font-size: 14px;
    font-family: inherit;
}
.ai-field textarea{
    min-height: 100px;
    resize: vertical;
}

.ai-actions{
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.ai-btn,
.ai-btn:visited{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid #d0d7e2;
    background: #f7f9fc;
    color: #1f2937;
    font-size: 14px;
    line-height: 1.2;
    white-space: nowrap;
    box-sizing: border-box;
    cursor: pointer;
}
.ai-btn:hover{
    filter: brightness(0.98);
}
.ai-btn-primary,
.ai-btn-primary:visited{
    background: #2f80ed;
    border-color: #2f80ed;
    color: #fff;
}
.ai-btn-violet,
.ai-btn-violet:visited{
    background: #6f42c1;
    border-color: #6f42c1;
    color: #fff;
}
.ai-btn-green,
.ai-btn-green:visited{
    background: #27ae60;
    border-color: #27ae60;
    color: #fff;
}
.ai-btn-danger,
.ai-btn-danger:visited{
    background: #fff1f1;
    border-color: #efb8b8;
    color: #9b1c1c;
}

.ai-muted{
    color: #666;
    font-size: 13px;
    margin-top: 10px;
}

.ai-cards{
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.ai-card{
    border: 1px solid #e3e7ee;
    border-radius: 14px;
    padding: 16px;
    background: #fafbfd;
}
.ai-card h4{
    margin: 0 0 8px 0;
    font-size: 17px;
    line-height: 1.3;
}
.ai-card-note{
    color: #666;
    font-size: 13px;
    margin-top: 10px;
    line-height: 1.45;
}

.ai-label-grid{
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 14px;
}
.ai-label-card{
    border: 1px solid #e4e7ec;
    background: #fafbfc;
    border-radius: 14px;
    padding: 14px;
}
.ai-label-head{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}
.ai-pill{
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef2f7;
    border: 1px solid #dde5ef;
    font-size: 12px;
    font-weight: 700;
}
.ai-label-actions{
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.ai-subtext{
    margin-top: 8px;
    color: #777;
    font-size: 12px;
}

.ai-run-grid{
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 18px;
    align-items: start;
}
.ai-run-side{
    border: 1px dashed #d7deea;
    border-radius: 14px;
    padding: 14px;
    background: #fbfcfe;
}
.ai-run-side h4{
    margin: 0 0 10px 0;
    font-size: 15px;
}
.ai-run-side ul{
    margin: 0;
    padding-left: 18px;
    color: #555;
    font-size: 13px;
    line-height: 1.5;
}

.ai-bulk-grid{
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 18px;
}
.ai-check-grid{
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.ai-check-item{
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #e3e7ef;
    border-radius: 10px;
    background: #fafbfd;
    font-size: 14px;
}
.ai-check-item input{
    margin: 0;
}
.ai-bulk-actions{
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.ai-bulk-actions form{
    margin: 0;
}

@media (max-width: 1100px){
    .ai-cards,
    .ai-label-grid,
    .ai-run-grid,
    .ai-form-grid,
    .ai-bulk-grid{
        grid-template-columns: 1fr;
    }

    .ai-check-grid{
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px){
    .ai-check-grid{
        grid-template-columns: 1fr;
    }
}
</style>

<div class="ai-wrap">
    <div class="ai-page-head">
        <h2>AI-фабрика сайта</h2>
        <div class="ai-domain"><?= h($domain) ?></div>
    </div>

    <div class="ai-top-links">
        <a href="/sites">← К сайтам</a>
        <a href="/ai/settings">AI settings</a>
        <a href="/sites/subcfg?id=<?= $siteId ?>">SubCfg</a>
        <a href="/sites/pages?id=<?= $siteId ?>">Pages</a>
        <a href="/sites/texts?id=<?= $siteId ?>">Texts</a>
    </div>

    <div class="ai-section">
        <div class="ai-section-head">
            <div>
                <h3>Разовые параметры генерации</h3>
                <div class="ai-section-note">
                    Эти параметры применяются только к текущему сайту. Они не заменяют глобальные промпты в AI settings,
                    а дополняют их при генерации меты и текстов.
                </div>
            </div>
        </div>

        <div class="ai-run-grid">
            <div>
                <form method="post" action="/ai/options/save?id=<?= $siteId ?>">
                    <div class="ai-form-grid">
                        <div class="ai-field">
                            <label>Объём текста</label>
                            <select name="text_length">
                                <option value="short" <?= (($runOptions['text_length'] ?? '') === 'short') ? 'selected' : '' ?>>Короткий</option>
                                <option value="medium" <?= (($runOptions['text_length'] ?? 'medium') === 'medium') ? 'selected' : '' ?>>Средний</option>
                                <option value="long" <?= (($runOptions['text_length'] ?? '') === 'long') ? 'selected' : '' ?>>Большой</option>
                            </select>
                        </div>

                        <div class="ai-field">
                            <label>Режим перезаписи</label>
                            <select name="overwrite_mode">
                                <option value="fill_empty" <?= (($runOptions['overwrite_mode'] ?? 'fill_empty') === 'fill_empty') ? 'selected' : '' ?>>
                                    Только пустые / inherit
                                </option>
                                <option value="overwrite_all" <?= (($runOptions['overwrite_mode'] ?? '') === 'overwrite_all') ? 'selected' : '' ?>>
                                    Перезаписывать всё
                                </option>
                            </select>
                        </div>

                        <div class="ai-field">
                            <label>Сквозная ссылка URL</label>
                            <input type="text" name="sitewide_link_url" value="<?= h($runOptions['sitewide_link_url'] ?? '') ?>" placeholder="https://example.com/page">
                        </div>

                        <div class="ai-field">
                            <label>Анкор ссылки</label>
                            <input type="text" name="sitewide_link_anchor" value="<?= h($runOptions['sitewide_link_anchor'] ?? '') ?>" placeholder="Перейти на сайт">
                        </div>

                        <div class="ai-field">
                            <label>CTA</label>
                            <input type="text" name="cta_text" value="<?= h($runOptions['cta_text'] ?? '') ?>" placeholder="Зарегистрируйтесь и начните игру">
                        </div>

                        <div class="ai-field">
                            <label>Запрещённые слова / фразы</label>
                            <input type="text" name="forbidden_phrases" value="<?= h($runOptions['forbidden_phrases'] ?? '') ?>" placeholder="Например: бесплатно, лучший, топ">
                        </div>

                        <div class="ai-field ai-field-full">
                            <label>Обязательные вхождения / фразы</label>
                            <textarea name="required_phrases" placeholder="Каждую фразу можно с новой строки"><?= h($runOptions['required_phrases'] ?? '') ?></textarea>
                        </div>

                        <div class="ai-field ai-field-full">
                            <label>Доп. инструкция</label>
                            <textarea name="extra_instruction" placeholder="Любые свободные требования к генерации"><?= h($runOptions['extra_instruction'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="ai-actions" style="margin-top:14px;">
                        <button class="ai-btn ai-btn-primary" type="submit">Сохранить параметры</button>
                    </div>
                </form>

                <form method="post" action="/ai/options/reset?id=<?= $siteId ?>" style="margin-top:10px;">
                    <button class="ai-btn ai-btn-danger" type="submit"
                        onclick="return confirm('Сбросить разовые параметры генерации?');">
                        Сбросить параметры
                    </button>
                </form>
            </div>

            <div class="ai-run-side">
                <h4>Что сюда удобно задавать</h4>
                <ul>
                    <li>нужный объём текста</li>
                    <li>обязательные вхождения и словоформы</li>
                    <li>запрещённые слова</li>
                    <li>одну сквозную ссылку с анкором</li>
                    <li>мягкий CTA в конце текста</li>
                    <li>свободные инструкции под текущую пачку генерации</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="ai-section">
        <div class="ai-section-head">
            <div>
                <h3>Быстрые действия</h3>
                <div class="ai-section-note">
                    Генерация root-меты, root-текста, меты поддоменов и текстов поддоменов без перехода по разным экранам.
                </div>
            </div>
        </div>

        <div class="ai-cards">
            <div class="ai-card">
                <h4>Основной домен</h4>

                <div class="ai-actions">
                    <form method="post" action="/ai/generate-meta?id=<?= $siteId ?>" style="display:inline;">
                        <button class="ai-btn ai-btn-primary" type="submit"
                            onclick="return confirm('Сгенерировать meta для основного домена?');">
                            Сгенерировать meta root
                        </button>
                    </form>

                    <a class="ai-btn ai-btn-violet"
                       href="/ai/generate-root-text?id=<?= $siteId ?>"
                       onclick="return confirm('Сгенерировать текст главной страницы для root?');">
                        Сгенерировать текст root
                    </a>

                    <a class="ai-btn"
                       href="/sites/pages?id=<?= $siteId ?>&label=_default">
                        Открыть pages root
                    </a>
                </div>

                <div class="ai-card-note">
                    Генерация <code>title</code>, <code>h1</code>, <code>description</code>, <code>keywords</code>
                    и текста главной страницы основного домена.
                </div>
            </div>

            <div class="ai-card">
                <h4>Поддомены</h4>

                <div class="ai-actions">
                    <form method="post" action="/ai/generate-subdomains?id=<?= $siteId ?>" style="display:inline;">
                        <button class="ai-btn ai-btn-primary" type="submit"
                            onclick="return confirm('Сгенерировать meta для всех поддоменов?');">
                            Сгенерировать meta для всех сабов
                        </button>
                    </form>

                    <a class="ai-btn ai-btn-violet"
                       href="/ai/generate-all-sub-texts?id=<?= $siteId ?>"
                       onclick="return confirm('Сгенерировать тексты для всех enabled поддоменов?');">
                        Сгенерировать тексты для всех сабов
                    </a>

                    <a class="ai-btn"
                       href="/sites/subdomains?id=<?= $siteId ?>">
                        Управление сабами
                    </a>
                </div>

                <div class="ai-card-note">
                    Массовая генерация SEO-меты и текстов главной страницы для всех поддоменов.
                </div>
            </div>
        </div>
    </div>

    <div class="ai-section">
        <div class="ai-section-head">
            <div>
                <h3>Пакетно по выбранным label</h3>
                <div class="ai-section-note">
                    Отметь нужные label и запусти только нужный тип генерации: meta, тексты главной или все pages.
                </div>
            </div>
        </div>

        <div class="ai-bulk-grid">
            <div>
                <form id="ai-selected-meta-form" method="post" action="/ai/generate-selected-meta?id=<?= $siteId ?>">
                    <div class="ai-check-grid">
                        <?php foreach ($labels as $lb): ?>
                            <label class="ai-check-item">
                                <input type="checkbox" name="labels[]" value="<?= h($lb) ?>">
                                <span><?= h(labelTitle($lb)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <div class="ai-bulk-actions">
                <button class="ai-btn ai-btn-primary"
                        type="submit"
                        form="ai-selected-meta-form"
                        onclick="return confirm('Сгенерировать meta для выбранных label?');">
                    Сгенерировать meta
                </button>

                <button class="ai-btn ai-btn-violet"
                        type="submit"
                        formaction="/ai/generate-selected-texts?id=<?= $siteId ?>"
                        formmethod="post"
                        form="ai-selected-meta-form"
                        onclick="return confirm('Сгенерировать тексты главной для выбранных label?');">
                    Сгенерировать тексты
                </button>

                <button class="ai-btn ai-btn-green"
                        type="submit"
                        formaction="/ai/generate-selected-pages?id=<?= $siteId ?>"
                        formmethod="post"
                        form="ai-selected-meta-form"
                        onclick="return confirm('Сгенерировать все pages для выбранных label?');">
                    Сгенерировать все pages
                </button>
            </div>
        </div>
    </div>

    <div class="ai-section">
        <div class="ai-section-head">
            <div>
                <h3>Внутренние страницы по label</h3>
                <div class="ai-section-note">
                    Для каждой метки можно перейти в экран страниц или массово сгенерировать все pages по массиву
                    <code>pages</code>.
                </div>
            </div>
        </div>

        <div class="ai-label-grid">
            <?php foreach ($labels as $lb): ?>
                <div class="ai-label-card">
                    <div class="ai-label-head">
                        <span class="ai-pill"><?= h(labelTitle($lb)) ?></span>
                    </div>

                    <div class="ai-label-actions">
                        <a class="ai-btn"
                           href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">
                            Открыть pages
                        </a>

                        <a class="ai-btn ai-btn-green"
                           href="/ai/generate-all-pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>"
                           onclick="return confirm('Сгенерировать meta и тексты для всех страниц: <?= h(labelTitle($lb)) ?> ?');">
                            Генерировать все pages
                        </a>

                        <a class="ai-btn"
                           href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">
                            Открыть SubCfg
                        </a>
                    </div>

                    <div class="ai-subtext">
                        Метка: <code><?= h($lb) ?></code>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>