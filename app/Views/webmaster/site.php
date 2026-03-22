<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$log = $_SESSION['wm_log'] ?? [];
unset($_SESSION['wm_log']);

$siteId = (int)($site['id'] ?? 0);

// unique labels from $desired
$labels = ['' => '(основной домен)'];
$seen = [];
foreach (($desired ?? []) as $hrow) {
    $lbl = (string)($hrow['label'] ?? '');
    if ($lbl === '') continue;
    if (isset($seen[$lbl])) continue;
    $seen[$lbl] = true;
    $labels[$lbl] = $lbl;
}

$wmDeployState = $wmDeployState ?? [];
$indexStatusMap = is_array($indexStatusMap ?? null) ? $indexStatusMap : [];
$cronState = is_array($cronState ?? null) ? $cronState : [];
$indexWatchLogTail = is_array($indexWatchLogTail ?? null) ? $indexWatchLogTail : [];
$searchApiStatusMap = is_array($searchApiStatusMap ?? null) ? $searchApiStatusMap : [];
$searchApiCronState = is_array($searchApiCronState ?? null) ? $searchApiCronState : [];
?>

<div class="page-head">
    <h1 class="page-title">Вебмастер</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/webmaster">К общему списку</a>
        <a class="btn btn-secondary" href="/deploy?id=<?= $siteId ?>">Публикация</a>
        <a class="btn btn-secondary" href="/webmaster/index-tech?id=<?= $siteId ?>">Техстраница индекса</a>
        <a class="btn btn-secondary" href="/webmaster/search-api?id=<?= $siteId ?>">XMLStock Search API</a>
    </div>
    <div class="page-subtitle">
        Сайт #<?= $siteId ?> — <code><?= h($site['domain'] ?? '') ?></code>
    </div>
</div>

<?php if (!empty($log)): ?>
    <div class="panel-card">
        <h2 class="section-title">Лог Вебмастера</h2>
        <pre class="log-console"><?= h(implode("\n", $log)) ?></pre>
    </div>
<?php endif; ?>

<div class="panel-card mt-16">
    <h2 class="section-title">Статус cron вебмастера</h2>
    <?php if (!empty($cronState)): ?>
        <div class="small muted">Последний запуск: <b><?= h($cronState['last_run_at'] ?? '') ?></b></div>
        <div class="small muted mt-8">OK: <b><?= !empty($cronState['last_ok']) ? 'да' : 'нет' ?></b></div>
        <div class="small muted">Проверено хостов: <b><?= (int)($cronState['last_checked'] ?? 0) ?></b></div>
        <div class="small muted">Уведомлений: <b><?= (int)($cronState['last_notified'] ?? 0) ?></b></div>
        <div class="small muted">Ошибок: <b><?= (int)($cronState['last_errors'] ?? 0) ?></b></div>
        <?php if (!empty($cronState['last_error'])): ?>
            <pre class="log-console mt-8"><?= h((string)$cronState['last_error']) ?></pre>
        <?php endif; ?>
    <?php else: ?>
        <div class="small muted">Состояние cron еще не записывалось.</div>
    <?php endif; ?>
</div>

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

        <?php $verifyBlocked = !empty($wmDeployState['needs_deploy']); ?>
        <form method="post" action="/webmaster/verify?id=<?= $siteId ?>" data-confirm="Проверить верификацию в Яндексе?">
            <button
                type="submit"
                class="btn btn-primary"
                <?= $verifyBlocked ? 'disabled title="Сначала выполните публикацию на VPS"' : '' ?>
            >
                Проверить верификацию
            </button>
        </form>
    </div>
</div>

<?php if (!empty($wmDeployState['written_cnt'])): ?>
    <?php if (!empty($wmDeployState['needs_deploy'])): ?>
        <div class="alert alert-warning mt-16">
            <b><?= h($wmDeployState['title'] ?? '') ?></b><br>
            <?= h($wmDeployState['message'] ?? '') ?>
            <div class="page-actions mt-8">
                <a class="btn btn-primary" href="/deploy?id=<?= $siteId ?>">Открыть публикацию</a>
            </div>
        </div>
    <?php elseif (!empty($wmDeployState['ok'])): ?>
        <div class="alert alert-success mt-16">
            <b><?= h($wmDeployState['title'] ?? '') ?></b><br>
            <?= h($wmDeployState['message'] ?? '') ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-16">
            После шага 1 verify-файлы записываются в локальный build сайта.
            Затем нужно выполнить публикацию на VPS, и только после этого запускать проверку верификации.
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-info mt-16">
        После шага 1 verify-файлы записываются в локальный build сайта.
        Затем нужно выполнить публикацию на VPS, и только после этого запускать проверку верификации.
    </div>
<?php endif; ?>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Переобход страниц</h2>

        <form method="post" action="/webmaster/recrawl?id=<?= $siteId ?>" id="recrawlForm" class="stack-gap-md">
            <div class="inline-form">
                <label class="flex-grow">
                    Метка хоста
                    <select name="label" id="recrawlLabel">
                        <option value="ALL">ALL (все хосты)</option>
                        <?php foreach ($labels as $val => $title): ?>
                            <option value="<?= h($val) ?>"><?= h($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="button" class="btn btn-secondary" onclick="fillFromPages()">Вставить из страниц</button>
                <button type="submit" class="btn btn-primary" data-confirm="Отправить переобход для выбранной метки?">Отправить переобход</button>
                <button type="button" class="btn btn-secondary" onclick="submitRecrawlFromPagesAll()">Массово из страниц (ALL)</button>
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
                    Метка хоста
                    <select name="label" id="sitemapLabel">
                        <option value="ALL">ALL (все хосты)</option>
                        <?php foreach ($labels as $val => $title): ?>
                            <option value="<?= h($val) ?>"><?= h($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" class="btn btn-primary" data-confirm="Добавить sitemap.xml для выбранной метки?">Добавить sitemap.xml</button>
                <button type="button" class="btn btn-secondary" onclick="submitSitemapGet()">Получить sitemap</button>
            </div>

            <div class="small muted">
                По умолчанию отправляется <code>https://HOST/sitemap.xml</code>.
            </div>

            <div class="field-row">
                <label>Переопределить sitemap URL</label>
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
                Метка хоста
                <select name="label" id="robotsLabel">
                    <option value="ALL">ALL (все хосты)</option>
                    <?php foreach ($labels as $val => $title): ?>
                        <option value="<?= h($val) ?>"><?= h($title) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit" class="btn btn-primary" data-confirm="Подтвердить robots.txt для выбранной метки?">Подтвердить robots.txt</button>
            <button type="button" class="btn btn-secondary" onclick="submitRobotsGet()">Получить robots</button>
        </div>

        <div class="small muted">
            Подтверждение robots — это подтверждение текущего <code>https://HOST/robots.txt</code>.
        </div>

        <div class="field-row">
            <label>Переопределить robots URL</label>
            <input type="text" name="robots_url" id="robotsUrlInput" class="mono-input" placeholder="https://example.com/robots.txt">
            <div class="small muted">
                Если поле пустое — в БД будет сохранён <code>host_url + /robots.txt</code>.
            </div>
        </div>
    </form>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Yandex Search API fallback</h2>
        <div class="small muted">Этот сервис нужен, когда Вебмастер еще не отдает summary/history или cron Вебмастера пока не дал ясный статус. По умолчанию cron Search API опрашивает только хосты без подтвержденного статуса в Вебмастере, чтобы не жечь платные запросы.</div>
    </div>

    <div class="inline-form">
        <a class="btn btn-secondary" href="/webmaster/search-api?id=<?= $siteId ?>">Открыть страницу сервиса</a>
    </div>

    <?php if (!empty($searchApiCronState)): ?>
        <div class="small muted mt-8">
            Последний cron Search API: <b><?= h($searchApiCronState['last_run_at'] ?? '') ?></b>,
            checked_hosts=<b><?= (int)($searchApiCronState['last_checked_hosts'] ?? 0) ?></b>,
            detected=<b><?= (int)($searchApiCronState['last_detected_hosts'] ?? 0) ?></b>,
            skipped=<b><?= (int)($searchApiCronState['last_skipped_hosts'] ?? 0) ?></b>
        </div>
    <?php endif; ?>

    <div class="wm-table-wrap">
        <table class="wm-table">
            <thead>
            <tr>
                <th>Метка</th>
                <th>Хост</th>
                <th>Search API статус</th>
                <th>Последняя проверка</th>
                <th>Когда найдено</th>
                <th>URL в ответе</th>
                <th>Следующая проверка</th>
                <th>Ошибка</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($desired as $hrow): ?>
                <?php
                $label = (string)($hrow['label'] ?? '');
                $hostUrl = (string)($hrow['host_url'] ?? '');
                $srow = $searchApiStatusMap[$label] ?? [];
                ?>
                <tr>
                    <td><?= h($label === '' ? '(основной домен)' : $label) ?></td>
                    <td><?= h($hostUrl) ?></td>
                    <td><?= h((string)($srow['search_api_status'] ?? 'idle')) ?></td>
                    <td><?= h((string)($srow['search_api_last_checked_at'] ?? '')) ?></td>
                    <td><?= h((string)($srow['search_api_indexed_at'] ?? '')) ?></td>
                    <td><?= (int)($srow['search_api_result_count'] ?? 0) ?></td>
                    <td><?= h((string)($srow['search_api_next_check_at'] ?? '')) ?></td>
                    <td><?= h((string)($srow['search_api_error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Индекс Яндекса и авто-редирект</h2>
        <div class="small muted">
            Статусы ведутся отдельно по основному домену и по каждому поддомену текущего сайта.
            Redirect включается автоматически только тогда, когда хост найден в индексе и число
            "Страниц в поиске" равно числу "Страниц добавлено" по данным API Яндекс Вебмастера.
        </div>
    </div>

    <div class="inline-form">
        <form method="post" action="/webmaster/check-index?id=<?= $siteId ?>" data-confirm="Проверить индекс Яндекса сейчас для основного домена и всех поддоменов сайта?">
            <input type="hidden" name="label" value="ALL">
            <button type="submit" class="btn btn-primary">Проверить индекс сейчас</button>
        </form>

        <form method="post" action="/webmaster/manual-sync-configs?id=<?= $siteId ?>" data-confirm="Вручную выгрузить root и все config.php текущего сайта на VPS? Проверка индекса и redirect_enabled не изменяются.">
            <input type="hidden" name="label" value="ALL">
            <button type="submit" class="btn btn-secondary">Вручную выгрузить config на VPS</button>
        </form>
    </div>

    <div class="small muted mt-8">
        Ручная выгрузка не проверяет индекс и не меняет redirect_enabled. Она только повторно отправляет уже собранные config-файлы текущего сайта на VPS.
    </div>
    <div class="small muted mt-8">
        Авто-редирект включается только когда "Страниц в поиске" равно "Страниц добавлено" и хост найден в индексе.
    </div>

    <div class="wm-table-wrap">
        <table class="wm-table">
            <thead>
            <tr>
                <th>Метка</th>
                <th>Хост</th>
                <th>Статус индекса</th>
                <th>Страниц в поиске</th>
                <th>Страниц добавлено</th>
                <th>Последняя проверка</th>
                <th>Когда найдено</th>
                <th>Автовключение redirect</th>
                <th>Выгрузка config</th>
                <th>Последняя выгрузка</th>
                <th>Ошибка выгрузки</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($desired as $hrow): ?>
                <?php
                $label   = (string)($hrow['label'] ?? '');
                $hostUrl = (string)($hrow['host_url'] ?? '');
                $idx     = $indexStatusMap[$label] ?? [];
                $idxStatus = (string)($idx['yandex_index_status'] ?? 'unknown');
                $idxLast = (string)($idx['yandex_index_last_checked_at'] ?? '');
                $idxDetected = (string)($idx['yandex_index_detected_at'] ?? '');
                $autoAt = (string)($idx['redirect_auto_enabled_at'] ?? '');
                $syncStatus = (string)($idx['config_sync_status'] ?? 'idle');
                $syncLast = (string)($idx['config_sync_last_at'] ?? '');
                $syncErr = (string)($idx['config_sync_error'] ?? '');
                $pagesInSearch = (int)($idx['yandex_pages_in_search'] ?? 0);
                $pagesAdded = (int)($idx['yandex_pages_added'] ?? 0);
                ?>
                <tr>
                    <td><?= h($label === '' ? '(основной домен)' : $label) ?></td>
                    <td style="word-break:break-word;min-width:190px"><?= h($hostUrl) ?></td>
                    <td><?= h($idxStatus) ?></td>
                    <td><?= h((string)$pagesInSearch) ?></td>
                    <td><?= h((string)$pagesAdded) ?></td>
                    <td><?= h($idxLast) ?></td>
                    <td><?= h($idxDetected) ?></td>
                    <td><?= h($autoAt) ?></td>
                    <td><?= h($syncStatus) ?></td>
                    <td><?= h($syncLast) ?></td>
                    <td><?= h($syncErr) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Хосты и статусы</h2>
        <div class="small muted">Статусы по основному домену и поддоменам текущего сайта</div>
    </div>

    <div class="wm-table-wrap">
        <table class="wm-table">
            <thead>
            <tr>
                <th>Метка</th>
                <th>Хост</th>
                <th>ID хоста</th>
                <th>Файл верификации</th>
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
                    <td><?= h($label === '' ? '(основной домен)' : $label) ?></td>
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

<div class="panel-card mt-16">
    <details>
        <summary style="cursor:pointer;font-weight:600;">Техническая диагностика индекса</summary>

        <div class="mt-16">
            <div class="small muted mb-8">
                Здесь выводится хвост файла <code>storage/logs/yandex_index_watch.log</code> по текущему сайту.
            </div>

            <?php if (!empty($indexWatchLogTail)): ?>
                <pre class="log-console" style="max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;"><?= h(implode("\n", $indexWatchLogTail)) ?></pre>
            <?php else: ?>
                <div class="small muted">Лог пока пуст.</div>
            <?php endif; ?>
        </div>
    </details>
</div>

<script>
async function fillFromPages() {
    const siteId = <?= (int)$siteId ?>;
    let label = document.getElementById('recrawlLabel').value;

    if (label === '') label = '_default';

    if (label === 'ALL') {
        alert('Для ALL используйте кнопку «Массово». Для вставки выберите конкретную метку.');
        return;
    }

    const url = '/webmaster/pages-urls?id=' + encodeURIComponent(siteId) + '&label=' + encodeURIComponent(label);
    const r = await fetch(url, { credentials: 'same-origin' });
    const txt = await r.text();

    if (!r.ok) {
        alert('Ошибка получения URL страниц: ' + txt);
        return;
    }

    document.getElementById('recrawlUrls').value = (txt || '').trim();
}

function submitRecrawlFromPagesAll() {
    const siteId = <?= (int)$siteId ?>;
    if (!confirm('Отправить переобход по страницам сразу для всех хостов этого сайта?')) return;

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