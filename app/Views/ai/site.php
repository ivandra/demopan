<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$site = is_array($site ?? null) ? $site : [];
$ai   = is_array($ai ?? null) ? $ai : [];
$runOptions = is_array($runOptions ?? null) ? $runOptions : [];
$labels = is_array($labels ?? null) ? $labels : ['_default'];
$entityAi = is_array($entityAi ?? null) ? $entityAi : [];
$currentCfg = is_array($currentCfg ?? null) ? $currentCfg : [];
$pagePaths = is_array($pagePaths ?? null) ? $pagePaths : [];
$currentLabel = (string)($currentLabel ?? '_default');
$resolvedMirrorUrl = (string)($resolvedMirrorUrl ?? '');
$aiCron = is_array($aiCron ?? null) ? $aiCron : [];
$aiQueue = is_array($aiQueue ?? null) ? $aiQueue : [];
$aiQueueSummary = is_array($aiQueue['summary'] ?? null) ? $aiQueue['summary'] : [];
$aiQueueItems = is_array($aiQueue['items'] ?? null) ? $aiQueue['items'] : [];

$siteId = (int)($site['id'] ?? 0);
$domain = (string)($site['domain'] ?? '');

function labelTitleAi(string $lb): string {
    return $lb === '_default' ? 'Основной домен' : ('Поддомен: ' . $lb);
}


function aiKindTitle(string $kind): string {
    $map = [
        'sub_home_text' => 'Главная поддомена',
        'root_home_text' => 'Главная основного домена',
        'page_bundle' => 'Внутренняя страница',
    ];
    return $map[$kind] ?? $kind;
}

function aiStatusTitle(string $status): string {
    $map = [
        'queued' => 'В очереди',
        'running' => 'Генерируется',
        'done' => 'Готово',
        'error' => 'Ошибка',
        'not_queued' => 'Не поставлено',
    ];
    return $map[$status] ?? $status;
}
?>

<div class="page-head">
    <h1 class="page-title">AI: переменные и генерация</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
        <a class="btn btn-secondary" href="/ai/settings">Глобальные AI-настройки</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>">Контент и SEO</a>
    </div>
    <div class="page-subtitle">
        Сайт: <code><?= h($domain) ?></code>
    </div>
</div>

<div class="panel-card stack-gap-md">
    <h2 class="section-title">Текущая сущность</h2>

    <div class="field-row">
        <label>Label / сущность для настройки</label>
        <select id="aiEntitySelect" data-set-query-param="label">
            <?php foreach ($labels as $lb): ?>
                <option value="<?= h($lb) ?>" <?= $lb === $currentLabel ? 'selected' : '' ?>>
                    <?= h(labelTitleAi($lb)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="small muted">
        Все параметры ниже относятся только к текущему label.
        Для основного домена используется label <code>_default</code>.
    </div>
</div>


<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">AI cron и очередь AI-задач</h2>

    <div class="panel-grid panel-grid--2">
        <div class="stack-gap-sm">
            <div><b>Статус cron:</b>
                <?php if (!empty($aiCron['alive'])): ?>
                    <span class="badge badge-success">работает</span>
                <?php else: ?>
                    <span class="badge badge-danger">нет запусков</span>
                <?php endif; ?>
            </div>
            <div class="small muted">Последний запуск: <code><?= h((string)($aiCron['last_run_at'] ?? '—')) ?></code></div>
            <div class="small muted">Последняя задача: <code><?= h((string)($aiCron['last_job_kind'] ?? '')) ?></code> / <code><?= h((string)($aiCron['last_job_label'] ?? '')) ?></code></div>
            <?php if (!empty($aiCron['last_error'])): ?>
                <div class="small" style="color:#b42318;">Последняя ошибка cron: <?= h((string)$aiCron['last_error']) ?></div>
            <?php endif; ?>
            <div class="small muted">Cron URL: <code>/ai/cron</code></div>
        </div>

        <div class="stack-gap-sm">
            <div><b>Активных задач в очереди:</b> <?= (int)($aiQueueSummary['active_total'] ?? 0) ?></div>
            <div><b>Всего активных label:</b> <?= (int)($aiQueueSummary['labels_total'] ?? 0) ?></div>
            <div><b>Готово:</b> <?= (int)($aiQueueSummary['done'] ?? 0) ?></div>
            <div><b>В очереди:</b> <?= (int)($aiQueueSummary['queued'] ?? 0) ?></div>
            <div><b>Генерируется:</b> <?= (int)($aiQueueSummary['running'] ?? 0) ?></div>
            <div><b>Ошибок:</b> <?= (int)($aiQueueSummary['error'] ?? 0) ?></div>
            <div><b>Осталось:</b> <?= (int)($aiQueueSummary['remaining'] ?? 0) ?></div>
        </div>
    </div>

    <div class="page-actions">
        <button type="button" class="btn btn-secondary" id="aiQueueRefreshNow">Обновить сейчас</button>
        <label class="checkbox-inline small" style="margin-left:8px;">
            <input type="checkbox" id="aiQueueAutoRefresh" value="1">
            Автообновление каждые 15 секунд
        </label>
    </div>

    <div class="small muted">
        Автообновление можно включать и выключать вручную. Настройка сохраняется автоматически сразу после клика по чекбоксу.
    </div>

    <script>
        (function () {
            var checkbox = document.getElementById('aiQueueAutoRefresh');
            var refreshBtn = document.getElementById('aiQueueRefreshNow');
            var storageKey = 'aiQueueAutoRefresh';
            var timer = null;

            function applyState() {
                if (!checkbox) return;
                if (checkbox.checked && <?= !empty($aiQueue['has_active']) ? 'true' : 'false' ?>) {
                    timer = setTimeout(function () { window.location.reload(); }, 15000);
                }
            }

            if (checkbox) {
                checkbox.checked = localStorage.getItem(storageKey) === '1';
                checkbox.addEventListener('change', function () {
                    localStorage.setItem(storageKey, checkbox.checked ? '1' : '0');
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                    }
                    applyState();
                });
                applyState();
            }

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () {
                    window.location.reload();
                });
            }
        })();
    </script>

</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Последние AI-задачи очереди</h2>
        <div class="small muted">Здесь показываются последние задачи по сайту из очереди, независимо от того, из какого блока они были запущены.</div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Тип</th>
                <th>Label</th>
                <th>Путь</th>
                <th>Статус</th>
                <th>Попыток</th>
                <th>Обновлено</th>
                <th>Ошибка</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($aiQueueItems as $queueItem): ?>
                <?php $status = (string)($queueItem['status'] ?? 'not_queued'); ?>
                <tr>
                    <td><?= h(aiKindTitle((string)($queueItem['kind'] ?? ''))) ?></td>
                    <td><code><?= h((string)($queueItem['label'] ?? '')) ?></code></td>
                    <td><code><?= h((string)($queueItem['page_path'] ?? '')) ?></code></td>
                    <td>
                        <?php if ($status === 'done'): ?>
                            <span class="badge badge-success"><?= h(aiStatusTitle($status)) ?></span>
                        <?php elseif ($status === 'running'): ?>
                            <span class="badge badge-warning"><?= h(aiStatusTitle($status)) ?></span>
                        <?php elseif ($status === 'error'): ?>
                            <span class="badge badge-danger"><?= h(aiStatusTitle($status)) ?></span>
                        <?php else: ?>
                            <span class="badge badge-muted"><?= h(aiStatusTitle($status)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)($queueItem['tries'] ?? 0) ?></td>
                    <td><code><?= h((string)($queueItem['updated_at'] ?? '')) ?></code></td>
                    <td class="small" style="max-width:380px; word-break:break-word;"><?= h((string)($queueItem['error_text'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($site['publish_dirty'])): ?>
    <div class="alert alert-warning mt-16">
        <b>Есть локальные изменения, сайт нужно выгрузить на VPS.</b><br>
        <?= h((string)($site['publish_dirty_message'] ?? 'Есть локальные изменения.')) ?>
        <div class="page-actions mt-8">
            <a class="btn btn-primary" href="/deploy?id=<?= $siteId ?>">Открыть публикацию</a>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success mt-16">
        <b>Сейчас по этому сайту нет отложенных локальных изменений.</b><br>
        Если после сохранения AI, SEO, текстов или config появится необходимость публикации, здесь появится предупреждение.
    </div>
<?php endif; ?>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Как устроен раздел</h2>
    <div class="small">
        Здесь настраиваются только переменные текущего label: бренд, ссылки, объем текста и текстовые ограничения.
        Итоговые SEO-поля редактируются в разделе <b>Контент и SEO</b>, а глобальные prompts и общие шаблоны — в <b>Глобальных AI-настройках</b>.
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>">Открыть Контент и SEO</a>
        <a class="btn btn-secondary" href="/ai/settings">Открыть глобальные AI-настройки</a>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Шаблоны и переменные генерации для текущего label</h2>

    <form method="post" action="/ai/entity-settings/save?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>" class="stack-gap-md">
        <div class="field-row">
            <label>Название бренда ({BRAND})</label>
            <div class="small muted">Используется в мета и текстах как базовая подстановка для текущей сущности.</div>
            <input type="text" name="brand_name" value="<?= h($entityAi['brand_name'] ?? '') ?>" placeholder="например: Gizbo Casino (Гизбо Казино)">
        </div>

        <div class="panel-grid panel-grid--2">
            <div class="field-row">
                <label>Количество упоминаний бренда ({BRAND_COUNT})</label>
                <input type="number" name="brand_count" min="0" value="<?= (int)($entityAi['brand_count'] ?? 5) ?>">
            </div>

            <div class="field-row">
                <label>Объем текста ({SYMBOLS})</label>
                <input type="number" name="text_symbols" min="500" step="100" value="<?= (int)($entityAi['text_symbols'] ?? 4000) ?>">
            </div>
        </div>

        <div class="panel-grid panel-grid--2">
            <div class="field-row">
                <label>Страница для {LINK_REGISTRATION}</label>
                <select name="link_registration_path">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($pagePaths as $path): ?>
                        <option value="<?= h($path) ?>" <?= (($entityAi['link_registration_path'] ?? '') === $path) ? 'selected' : '' ?>>
                            <?= h($path) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <label>Страница для {LINK_SLOTS}</label>
                <select name="link_slots_path">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($pagePaths as $path): ?>
                        <option value="<?= h($path) ?>" <?= (($entityAi['link_slots_path'] ?? '') === $path) ? 'selected' : '' ?>>
                            <?= h($path) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="panel-grid panel-grid--2">
            <div class="field-row">
                <label>Страница для {LINK_BONUSES}</label>
                <select name="link_bonuses_path">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($pagePaths as $path): ?>
                        <option value="<?= h($path) ?>" <?= (($entityAi['link_bonuses_path'] ?? '') === $path) ? 'selected' : '' ?>>
                            <?= h($path) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <label>{LINK_MIRROR}</label>
                <input type="text" value="<?= h($resolvedMirrorUrl) ?>" readonly>
                <div class="small muted">Подставляется автоматически из promolink текущего label. Здесь всегда показывается относительный путь, например <code>/reg</code>.</div>
            </div>
        </div>

        <div class="field-row">
            <label>Обязательные вхождения / фразы</label>
            <textarea name="required_phrases" rows="5"><?= h($entityAi['required_phrases'] ?? '') ?></textarea>
        </div>

        <div class="field-row">
            <label>Запрещенные слова / фразы</label>
            <textarea name="forbidden_phrases" rows="4"><?= h($entityAi['forbidden_phrases'] ?? '') ?></textarea>
        </div>

        <div class="field-row">
            <label>Доп. инструкция</label>
            <textarea name="extra_instruction" rows="6"><?= h($entityAi['extra_instruction'] ?? '') ?></textarea>
        </div>

        <label class="checkbox-inline small" style="display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid #2563eb;border-radius:10px;background:#eef4ff;color:#123a8f;font-weight:700;"><input type="checkbox" name="copy_all_labels" value="1"> После сохранения скопировать эти настройки на все label сайта</label>

        <div class="page-actions">
            <button class="btn btn-primary" type="submit">Сохранить переменные текущего label</button>
        </div>
    </form>
</div>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Текущая сущность</h2>

        <div class="page-actions">
            <form method="post" action="/ai/generate-meta?id=<?= $siteId ?>" data-confirm="Сгенерировать мета для основного домена?">
                <?php if ($currentLabel === '_default'): ?>
                    <button class="btn btn-primary" type="submit">Сгенерировать мета для основного домена</button>
                <?php else: ?>
                    <a class="btn btn-primary" href="/ai/generate-sub-meta?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>" data-confirm="Сгенерировать мета для <?= h($currentLabel) ?>?">Сгенерировать мета для текущего label</a>
                <?php endif; ?>
            </form>

            <?php if ($currentLabel === '_default'): ?>
                <a class="btn btn-ai" href="/ai/generate-root-text?id=<?= $siteId ?>" data-confirm="Сгенерировать текст главной для основного домена?">Сгенерировать текст для основного домена</a>
            <?php else: ?>
                <a class="btn btn-ai" href="/ai/generate-sub-text?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>" data-confirm="Сгенерировать текст главной для <?= h($currentLabel) ?>?">Сгенерировать текст для текущего label</a>
            <?php endif; ?>

            <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>">Открыть страницы</a>
            <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($currentLabel) ?>">Открыть тексты</a>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Массово по всем сабам</h2>

        <div class="page-actions">
            <form method="post" action="/ai/generate-subdomains?id=<?= $siteId ?>" data-confirm="Сгенерировать мета для всех включенных сабов?">
                <button class="btn btn-primary" type="submit">Сгенерировать мета для всех сабов</button>
            </form>

            <a class="btn btn-ai"
               href="/ai/generate-all-sub-texts?id=<?= $siteId ?>"
               data-confirm="Поставить генерацию текстов главной в очередь для всех включенных сабов?">
                Поставить тексты для всех сабов в очередь
            </a>

            <a class="btn btn-secondary"
               href="/ai/generate-all-sub-pages?id=<?= $siteId ?>"
               data-confirm="Поставить в очередь все внутренние страницы для всех enabled label? 404 не будет включена.">
                Поставить все внутренние страницы в очередь
            </a>
        </div>

        <div class="small muted">
            Генерация текстов теперь идет не в одном длинном HTTP-запросе, а по очереди через <code>/ai/cron</code>.
            За один запуск cron обрабатывается одна задача, поэтому 504 больше не должен возникать даже на больших сетках.
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Пакетно по выбранным label</h2>
        <div class="small muted">Отметь нужные label и поставь в очередь только нужный тип генерации.</div>
    </div>

    
    <div class="panel-grid panel-grid--2">
        <div>
            <form id="ai-selected-meta-form" method="post" action="/ai/generate-selected-meta?id=<?= $siteId ?>">
                <div class="checklist-box">
                    <?php foreach ($labels as $lb): ?>
                        <label class="checklist-item">
                            <input type="checkbox" class="label-batch-check" name="labels[]" value="<?= h($lb) ?>">
                            <span><?= h(labelTitleAi($lb)) ?></span>
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
                    data-require-checked-message="Сначала выберите хотя бы одну метку."
                    data-confirm="Сгенерировать мета для выбранных меток?">
                Сгенерировать мета
            </button>

            <button class="btn btn-ai"
                    type="submit"
                    formaction="/ai/generate-selected-texts?id=<?= $siteId ?>"
                    formmethod="post"
                    form="ai-selected-meta-form"
                    data-require-checked=".label-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы одну метку."
                    data-confirm="Сгенерировать тексты главной для выбранных меток?">
                Сгенерировать тексты
            </button>

            <button class="btn btn-secondary"
                    type="submit"
                    formaction="/ai/generate-selected-pages?id=<?= $siteId ?>"
                    formmethod="post"
                    form="ai-selected-meta-form"
                    data-require-checked=".label-batch-check"
                    data-require-checked-message="Сначала выберите хотя бы одну метку."
                    data-confirm="Поставить внутренние страницы в очередь для выбранных меток?">
                Поставить внутренние страницы в очередь
            </button>
        </div>
    </div>
</div>

<div class="panel-card mt-16">
    <div class="page-head page-head--compact">
        <h2 class="section-title">Быстрый доступ по label</h2>
        <div class="small muted">Можно быстро переключиться в AI-настройки конкретной сущности.</div>
    </div>

    <div class="check-grid check-grid--2">
        <?php foreach ($labels as $lb): ?>
            <div class="panel-card stack-gap-sm">
                <div><span class="badge badge-muted"><?= h(labelTitleAi($lb)) ?></span></div>
                <div class="small muted">Метка: <code><?= h($lb) ?></code></div>
                <div class="page-actions">
                    <a class="btn btn-secondary" href="/sites/ai?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">AI-настройки</a>
                    <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Контент и SEO</a>
                    <a class="btn btn-secondary" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Страницы</a>
                    <a class="btn btn-secondary" href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Тексты</a>
                    <a class="btn btn-ai"
                       href="/ai/generate-all-pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>"
                       data-confirm="Сгенерировать мета и тексты для всех страниц: <?= h(labelTitleAi($lb)) ?>?">
                        Генерировать все страницы
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>