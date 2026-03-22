<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteId = (int)($site['id'] ?? 0);
$settings = is_array($settings ?? null) ? $settings : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$cronState = is_array($cronState ?? null) ? $cronState : [];
$logTail = is_array($logTail ?? null) ? $logTail : [];
$diag = is_array($diag ?? null) ? $diag : [];
$desiredHosts = is_array($desiredHosts ?? null) ? $desiredHosts : [];
?>
<div class="page-head">
    <h1 class="page-title">XMLStock Search API</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/webmaster/site?id=<?= $siteId ?>">Назад к сайту</a>
        <a class="btn btn-secondary" href="/webmaster">К вебмастеру</a>
    </div>
    <div class="page-subtitle">Сайт #<?= $siteId ?> — <code><?= h($site['domain'] ?? '') ?></code></div>
</div>

<?php if (!empty($_SESSION['wm_log'])): ?>
    <div class="panel-card mt-16">
        <h2 class="section-title">Лог действия</h2>
        <pre class="log-console"><?= h(implode("\n", (array)$_SESSION['wm_log'])) ?></pre>
    </div>
    <?php unset($_SESSION['wm_log']); ?>
<?php endif; ?>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Настройки XMLStock</h2>
        <form method="post" action="/webmaster/search-api/run?id=<?= $siteId ?>">
            <input type="hidden" name="mode" value="save_settings">

            <div class="field-row">
                <label><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> Включить сервис</label>
            </div>

            <div class="field-row">
                <label>XML endpoint</label>
                <input class="mono-input" type="text" name="endpoint_xml" value="<?= h($settings['endpoint_xml'] ?? 'https://xmlstock.com/yandexlive/xml/') ?>">
            </div>

            <div class="field-row">
                <label>JSON endpoint</label>
                <input class="mono-input" type="text" name="endpoint_json" value="<?= h($settings['endpoint_json'] ?? 'https://xmlstock.com/yandexlive/json/') ?>">
            </div>

            <div class="field-row">
                <label>User</label>
                <input class="mono-input" type="text" name="user" value="<?= h($settings['user'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>Key</label>
                <input class="mono-input" type="text" name="key" value="<?= h($settings['key'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>Интервал проверки, минут</label>
                <input class="mono-input" type="number" name="query_interval_minutes" value="<?= (int)($settings['query_interval_minutes'] ?? 30) ?>">
            </div>

            <div class="field-row">
                <label>После нахождения не дергать, минут</label>
                <input class="mono-input" type="number" name="recheck_after_detect_minutes" value="<?= (int)($settings['recheck_after_detect_minutes'] ?? 1440) ?>">
            </div>

            <div class="field-row">
                <label>Страниц за один прогон</label>
                <input class="mono-input" type="number" name="max_pages_per_run" value="<?= (int)($settings['max_pages_per_run'] ?? 1) ?>">
            </div>

            <button type="submit" class="btn btn-primary">Сохранить настройки</button>
        </form>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Cron Search API</h2>

        <?php if (!empty($cronState)): ?>
            <div class="small muted">Последний запуск: <b><?= h($cronState['last_run_at'] ?? '') ?></b></div>
            <div class="small muted mt-8">OK: <b><?= !empty($cronState['last_ok']) ? 'да' : 'нет' ?></b></div>
            <div class="small muted">Сайтов проверено: <b><?= (int)($cronState['last_checked_sites'] ?? 0) ?></b></div>
            <div class="small muted">Хостов проверено: <b><?= (int)($cronState['last_checked_hosts'] ?? 0) ?></b></div>
            <div class="small muted">Найдено: <b><?= (int)($cronState['last_detected_hosts'] ?? 0) ?></b></div>
            <div class="small muted">Пропущено: <b><?= (int)($cronState['last_skipped_hosts'] ?? 0) ?></b></div>
            <div class="small muted">Ошибок: <b><?= (int)($cronState['last_errors'] ?? 0) ?></b></div>
            <?php if (!empty($cronState['last_error'])): ?>
                <pre class="log-console mt-8"><?= h($cronState['last_error']) ?></pre>
            <?php endif; ?>
        <?php else: ?>
            <div class="small muted">Состояние cron еще не записывалось.</div>
        <?php endif; ?>

        <form method="post" action="/webmaster/search-api/run?id=<?= $siteId ?>" class="mt-16">
            <input type="hidden" name="mode" value="run_manual">
            <label>Метка
                <select name="label">
                    <option value="ALL">ALL</option>
                    <option value="">root</option>
                    <?php foreach ($desiredHosts as $row): $label=(string)($row['label'] ?? ''); if ($label==='') continue; ?>
                        <option value="<?= h($label) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary">Запустить проверку сейчас</button>
        </form>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Статусы хостов</h2>
    <div class="small muted">Сервис работает как fallback, если Вебмастер еще не дал ясный статус. Хосты со статусом Webmaster=indexed или уже найденные через Search API больше не опрашиваются.</div>

    <div class="wm-table-wrap">
        <table class="wm-table">
            <thead>
            <tr>
                <th>Метка</th>
                <th>Хост</th>
                <th>Search API статус</th>
                <th>Последняя проверка</th>
                <th>Когда найдено</th>
                <th>Найдено URL</th>
                <th>Следующая проверка</th>
                <th>Последний запрос</th>
                <th>Ошибка</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($desiredHosts as $row): $label=(string)($row['label'] ?? ''); $hostUrl=(string)($row['host_url'] ?? ''); $s=$statuses[$label] ?? []; ?>
                <tr>
                    <td><?= h($label === '' ? '(основной домен)' : $label) ?></td>
                    <td><?= h($hostUrl) ?></td>
                    <td><?= h((string)($s['search_api_status'] ?? 'idle')) ?></td>
                    <td><?= h((string)($s['search_api_last_checked_at'] ?? '')) ?></td>
                    <td><?= h((string)($s['search_api_indexed_at'] ?? '')) ?></td>
                    <td><?= (int)($s['search_api_result_count'] ?? 0) ?></td>
                    <td><?= h((string)($s['search_api_next_check_at'] ?? '')) ?></td>
                    <td><?= h((string)($s['search_api_last_query'] ?? '')) ?></td>
                    <td><?= h((string)($s['search_api_error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($diag['results'])): ?>
<div class="panel-card mt-16">
    <h2 class="section-title">Диагностика последнего ручного запуска</h2>
    <?php foreach ((array)$diag['results'] as $item): ?>
        <div class="mt-16">
            <div><b><?= h(($item['label'] ?? '') === '' ? 'root' : (string)($item['label'] ?? '')) ?></b> — <code><?= h($item['host_url'] ?? '') ?></code></div>
            <?php if (!empty($item['error'])): ?>
                <pre class="log-console"><?= h((string)$item['error']) ?></pre>
            <?php else: ?>
                <pre class="log-console"><?= h(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel-card mt-16">
    <h2 class="section-title">Хвост лога Search API</h2>
    <?php if (!empty($logTail)): ?>
        <pre class="log-console"><?= h(implode("\n", $logTail)) ?></pre>
    <?php else: ?>
        <div class="small muted">Лог пока пуст.</div>
    <?php endif; ?>
</div>
