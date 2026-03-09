<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$log = $_SESSION['wm_log'] ?? [];
unset($_SESSION['wm_log']);

$siteId = (int)($site['id'] ?? 0);

// unique labels from $desired
$labels = ['' => '(root)'];
$seen = [];
foreach (($desired ?? []) as $hrow) {
    $lbl = (string)($hrow['label'] ?? '');
    if ($lbl === '') continue;
    if (isset($seen[$lbl])) continue;
    $seen[$lbl] = true;
    $labels[$lbl] = $lbl;
}
?>

<div class="page-head">
    <h1 class="page-title">Webmaster</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/webmaster">К общему списку</a>
        <a class="btn btn-secondary" href="/deploy?id=<?= $siteId ?>">Публикация</a>
    </div>
    <div class="page-subtitle">
        Сайт #<?= $siteId ?> — <code><?= h($site['domain'] ?? '') ?></code>
    </div>
</div>

<?php if (!empty($log)): ?>
    <div class="panel-card">
        <h2 class="section-title">Лог Webmaster</h2>
        <pre class="log-console"><?= h(implode("\n", $log)) ?></pre>
    </div>
<?php endif; ?>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Шаг 1</h2>
        <div class="small muted">
            Синхронизировать хосты, получить HTML verify и записать verify-файлы в build.
        </div>

        <form method="post" action="/webmaster/sync?id=<?= $siteId ?>" data-confirm="Синхронизировать хосты + получить HTML verify + записать файлы в build?">
            <button type="submit" class="btn btn-primary">Синхронизировать + записать verify-файлы</button>
        </form>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Шаг 2</h2>
        <div class="small muted">
            Проверить верификацию в Яндексе. Перед этим verify-файлы должны быть уже задеплоены на домены.
        </div>

        <form method="post" action="/webmaster/verify?id=<?= $siteId ?>" data-confirm="Проверить верификацию в Яндексе (verifyHost)?">
            <button type="submit" class="btn btn-primary">Проверить verifyHost</button>
        </form>
    </div>
</div>

<div class="alert alert-info mt-16">
    После шага 1 verify-файлы записываются только в build сайта.
    Чтобы Яндекс увидел их по URL, нужно сделать обычный deploy / update-files.
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Recrawl</h2>

        <form method="post" action="/webmaster/recrawl?id=<?= $siteId ?>" id="recrawlForm" class="stack-gap-md">
            <div class="inline-form">
                <label class="flex-grow">
                    Host label
                    <select name="label" id="recrawlLabel">
                        <option value="ALL">ALL (все хосты)</option>
                        <?php foreach ($labels as $val => $title): ?>
                            <option value="<?= h($val) ?>"><?= h($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="button" class="btn btn-secondary" onclick="fillFromPages()">Вставить из pages</button>
                <button type="submit" class="btn btn-primary" data-confirm="Отправить recrawl на выбранный label?">Отправить recrawl</button>
                <button type="button" class="btn btn-secondary" onclick="submitRecrawlFromPagesAll()">Массово из pages (ALL)</button>
            </div>

            <textarea name="urls" id="recrawlUrls" rows="8" class="big-textarea" placeholder="/&#10;/new&#10;/404"></textarea>

            <div class="small muted">
                Поддерживаются относительные (<code>/path</code>) и абсолютные (<code>https://...</code>) URL.
            </div>
        </form>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Sitemap</h2>

        <form method="post" action="/webmaster/sitemap/add?id=<?= $siteId ?>" class="stack-gap-md">
            <div class="inline-form">
                <label class="flex-grow">
                    Host label
                    <select name="label" id="sitemapLabel">
                        <option value="ALL">ALL (все хосты)</option>
                        <?php foreach ($labels as $val => $title): ?>
                            <option value="<?= h($val) ?>"><?= h($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" class="btn btn-primary" data-confirm="Добавить sitemap.xml для выбранного label?">Add sitemap.xml</button>
                <button type="button" class="btn btn-secondary" onclick="submitSitemapGet()">Get sitemaps</button>
            </div>

            <div class="small muted">
                По умолчанию отправляется <code>https://HOST/sitemap.xml</code>.
            </div>

            <div class="field-row">
                <label>Override sitemap URL</label>
                <input type="text" name="sitemap_url" id="sitemapUrlInput" class="mono-input" placeholder="https://example.com/sitemap.xml">
                <div class="small muted">
                    Если поле пустое — используется <code>host_url + /sitemap.xml</code>.
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Robots</h2>

    <form method="post" action="/webmaster/robots/confirm?id=<?= $siteId ?>" class="stack-gap-md">
        <div class="inline-form">
            <label class="flex-grow">
                Host label
                <select name="label" id="robotsLabel">
                    <option value="ALL">ALL (все хосты)</option>
                    <?php foreach ($labels as $val => $title): ?>
                        <option value="<?= h($val) ?>"><?= h($title) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="btn btn-primary" data-confirm="Подтвердить robots.txt для выбранного label?">Confirm robots.txt</button>
            <button type="button" class="btn btn-secondary" onclick="submitRobotsGet()">Get robots</button>
        </div>

        <div class="small muted">
            Confirm robots — это подтверждение текущего <code>https://HOST/robots.txt</code>.
        </div>

        <div class="field-row">
            <label>Override robots URL</label>
            <input type="text" name="robots_url" id="robotsUrlInput" class="mono-input" placeholder="https://example.com/robots.txt">
            <div class="small muted">
                Если поле пустое — в БД будет сохранён <code>host_url + /robots.txt</code>.
            </div>
        </div>
    </form>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Хосты и статусы</h2>
        <div class="small muted">Статусы по root и поддоменам текущего сайта</div>
    </div>

    <div class="wm-table-wrap">
        <table class="wm-table">
            <thead>
            <tr>
                <th>Метка</th>
                <th>Хост</th>
                <th>Host ID</th>
                <th>Verify файл</th>
                <th>Файл записан</th>
                <th>Верифицирован</th>
                <th>Robots добавлен</th>
                <th>Sitemap добавлен</th>
                <th>Страницы добавлены</th>
                <th>Последняя синхронизация</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($desired as $hrow): ?>
                <?php
                $label   = (string)($hrow['label'] ?? '');
                $hostUrl = (string)($hrow['host_url'] ?? '');
                $r       = $rowMap[$label] ?? null;

                $fileWritten = $r ? (int)($r['file_written'] ?? 0) : 0;

                $verifiedAt = $r ? (string)($r['verified_at'] ?? '') : '';
                $robotsAt   = $r ? (string)($r['robots_confirmed_at'] ?? '') : '';
                $robotsUrl  = $r ? (string)($r['robots_url'] ?? '') : '';
                $sitemapAt  = $r ? (string)($r['sitemap_added_at'] ?? '') : '';
                $sitemapUrl = $r ? (string)($r['sitemap_url'] ?? '') : '';

                $recrawlAt  = $r ? (string)($r['last_recrawl_at'] ?? '') : '';
                $recrawlCnt = $r ? (int)($r['last_recrawl_count'] ?? 0) : 0;

                $isVerified = ($verifiedAt !== '');
                $hasRobots  = ($robotsAt !== '' || $robotsUrl !== '');
                $hasSitemap = ($sitemapAt !== '' || $sitemapUrl !== '');
                $hasPages   = ($recrawlAt !== '' || $recrawlCnt > 0);

                $badge = function(bool $ok, string $yes='Да', string $no='Нет') {
                    return $ok
                        ? '<span class="wm-badge wm-ok">'.$yes.'</span>'
                        : '<span class="wm-badge wm-bad">'.$no.'</span>';
                };
                ?>
                <tr>
                    <td><?= h($label === '' ? '(root)' : $label) ?></td>
                    <td><?= h($hostUrl) ?></td>
                    <td><?= $r ? h($r['host_id'] ?? '') : '' ?></td>
                    <td><?= $r ? h($r['verification_file'] ?? '') : '' ?></td>

                    <td>
                        <?php if (!$r): ?>
                            <span class="wm-badge wm-na">—</span>
                        <?php else: ?>
                            <?= $fileWritten === 1
                                ? '<span class="wm-badge wm-ok">Да</span>'
                                : '<span class="wm-badge wm-bad">Нет</span>' ?>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= $badge($isVerified) ?>
                        <?php if ($isVerified): ?>
                            <div class="wm-small"><?= h($verifiedAt) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= $badge($hasRobots) ?>
                        <?php if ($robotsUrl !== ''): ?>
                            <div class="wm-small"><?= h($robotsUrl) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= $badge($hasSitemap) ?>
                        <?php if ($sitemapUrl !== ''): ?>
                            <div class="wm-small"><?= h($sitemapUrl) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($hasPages): ?>
                            <span class="wm-badge wm-ok">Да<?= $recrawlCnt > 0 ? ' ('.$recrawlCnt.')' : '' ?></span>
                            <?php if ($recrawlAt !== ''): ?>
                                <div class="wm-small"><?= h($recrawlAt) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="wm-badge wm-bad">Нет</span>
                        <?php endif; ?>
                    </td>

                    <td><?= $r ? h($r['last_sync_at'] ?? '') : '' ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($desired)): ?>
                <tr>
                    <td colspan="10" class="muted">Хостов пока нет.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function fillFromPages() {
    const siteId = <?= (int)$siteId ?>;
    let label = document.getElementById('recrawlLabel').value;

    if (label === '') label = '_default';

    if (label === 'ALL') {
        alert('Для ALL используйте кнопку "Массово". Для вставки выберите конкретный label.');
        return;
    }

    const url = '/webmaster/pages-urls?id=' + encodeURIComponent(siteId) + '&label=' + encodeURIComponent(label);
    const r = await fetch(url, { credentials: 'same-origin' });
    const txt = await r.text();

    if (!r.ok) {
        alert('Ошибка получения pages urls: ' + txt);
        return;
    }

    document.getElementById('recrawlUrls').value = (txt || '').trim();
}

function submitRecrawlFromPagesAll() {
    const siteId = <?= (int)$siteId ?>;
    if (!confirm('Отправить recrawl по pages сразу для всех хостов этого сайта?')) return;

    const f = document.createElement('form');
    f.method = 'post';
    f.action = '/webmaster/recrawl-from-pages?id=' + encodeURIComponent(siteId);

    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = 'label';
    i.value = 'ALL';
    f.appendChild(i);

    document.body.appendChild(f);
    f.submit();
}

function submitSitemapGet() {
    const siteId = <?= (int)$siteId ?>;
    const label = document.getElementById('sitemapLabel').value;

    const f = document.createElement('form');
    f.method = 'post';
    f.action = '/webmaster/sitemap/get?id=' + encodeURIComponent(siteId);

    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = 'label';
    i.value = label;
    f.appendChild(i);

    document.body.appendChild(f);
    f.submit();
}

function submitRobotsGet() {
    const siteId = <?= (int)$siteId ?>;
    const label = document.getElementById('robotsLabel').value;

    const f = document.createElement('form');
    f.method = 'post';
    f.action = '/webmaster/robots/get?id=' + encodeURIComponent(siteId);

    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = 'label';
    i.value = label;
    f.appendChild(i);

    document.body.appendChild(f);
    f.submit();
}
</script>