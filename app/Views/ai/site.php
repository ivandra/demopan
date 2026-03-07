<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$site = is_array($site ?? null) ? $site : [];
$ai   = is_array($ai ?? null) ? $ai : [];

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
    max-width:1200px;
}
.ai-top-links{
    margin:0 0 18px 0;
    font-size:14px;
}
.ai-top-links a{
    text-decoration:none;
}
.ai-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:18px;
    margin-bottom:18px;
}
.ai-card{
    background:#fff;
    border:1px solid #ddd;
    border-radius:14px;
    padding:18px;
}
.ai-card h3{
    margin:0 0 14px 0;
    font-size:18px;
}
.ai-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}
.ai-btn,
.ai-btn:visited{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:0 16px;
    border-radius:10px;
    text-decoration:none;
    border:1px solid #d0d7e2;
    background:#f7f9fc;
    color:#1f2937;
    font-size:14px;
    line-height:1.2;
    white-space:nowrap;
    box-sizing:border-box;
}
.ai-btn:hover{
    opacity:.95;
}
.ai-btn-primary,
.ai-btn-primary:visited{
    background:#2f80ed;
    border-color:#2f80ed;
    color:#fff;
}
.ai-btn-violet,
.ai-btn-violet:visited{
    background:#6f42c1;
    border-color:#6f42c1;
    color:#fff;
}
.ai-btn-green,
.ai-btn-green:visited{
    background:#27ae60;
    border-color:#27ae60;
    color:#fff;
}
.ai-btn-gray,
.ai-btn-gray:visited{
    background:#f3f4f6;
    border-color:#d7dbe2;
    color:#222;
}
.ai-muted{
    color:#666;
    font-size:13px;
    margin-top:12px;
}
.ai-label-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
    margin-top:14px;
}
.ai-label-card{
    border:1px solid #e4e7ec;
    background:#fafbfc;
    border-radius:12px;
    padding:14px;
}
.ai-label-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:12px;
}
.ai-pill{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    background:#eef2f7;
    border:1px solid #dde5ef;
    font-size:12px;
    font-weight:600;
}
.ai-label-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}
.ai-subtext{
    margin-top:8px;
    color:#777;
    font-size:12px;
}
@media (max-width: 900px){
    .ai-grid,
    .ai-label-grid{
        grid-template-columns:1fr;
    }
}
</style>

<div class="ai-wrap">
    <h2>AI-фабрика: <?= h($domain) ?></h2>

    <div class="ai-top-links">
        <a href="/sites">← К сайтам</a> |
        <a href="/ai/settings">AI settings</a> |
        <a href="/sites/subcfg?id=<?= $siteId ?>">SubCfg</a> |
        <a href="/sites/pages?id=<?= $siteId ?>">Pages</a> |
        <a href="/sites/texts?id=<?= $siteId ?>">Texts</a>
    </div>

    <div class="ai-grid">
        <div class="ai-card">
            <h3>Основной домен</h3>

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

                <a class="ai-btn ai-btn-gray"
                   href="/sites/pages?id=<?= $siteId ?>&label=_default">
                    Открыть pages root
                </a>
            </div>

            <div class="ai-muted">
                Генерация title, h1, description, keywords и текста главной страницы основного домена.
            </div>
        </div>

        <div class="ai-card">
            <h3>Поддомены</h3>

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

                <a class="ai-btn ai-btn-gray"
                   href="/sites/subdomains?id=<?= $siteId ?>">
                    Управление сабами
                </a>
            </div>

            <div class="ai-muted">
                Массовая генерация SEO-меты и текстов главной страницы для всех поддоменов.
            </div>
        </div>
    </div>

    <div class="ai-card">
        <h3>Внутренние страницы</h3>

        <div class="ai-muted" style="margin-top:0;">
            Для каждой метки можно массово сгенерировать тексты и meta по страницам из config <code>pages</code>.
        </div>

        <div class="ai-label-grid">
            <?php foreach ($labels as $lb): ?>
                <div class="ai-label-card">
                    <div class="ai-label-head">
                        <span class="ai-pill"><?= h(labelTitle($lb)) ?></span>
                    </div>

                    <div class="ai-label-actions">
                        <a class="ai-btn ai-btn-gray"
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