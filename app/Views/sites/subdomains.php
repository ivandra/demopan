<?php
$siteId = (int)($siteId ?? 0);
$site   = $site ?? [];
$catalog = $catalog ?? [];
$siteSubs = $siteSubs ?? [];
$registrarAccounts = $registrarAccounts ?? [];

$serverIps = $serverIps ?? [];
$dnsA = $dnsA ?? [];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$attachedMap = [];
$enabledMap  = [];
foreach ($siteSubs as $r) {
    $lb = (string)($r['label'] ?? '');
    $attachedMap[$lb] = true;
    $enVal = $r['enabled'] ?? ($r['is_enabled'] ?? 0);
    $enabledMap[$lb] = ((int)$enVal === 1);
}

$currentVpsIp = (string)($site['vps_ip'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Поддомены и DNS: <?= h($site['domain'] ?? '') ?></h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">← К списку сайтов</a>
        <a class="btn btn-secondary" href="/sites/subcfg?id=<?= $siteId ?>&label=_default">Открыть root / _default</a>
    </div>
    <div class="page-subtitle">
        Здесь выбираются поддомены, включаются и выключаются DNS-записи и открывается редактирование контента.
        <b>_default</b> = основной домен / root-конфиг.
    </div>
</div>

<?php if (($site['template'] ?? '') !== 'template-multy'): ?>
    <div class="alert alert-warning">
        У текущего шаблона не используется multy-структура <code>subs/*</code>.
        Экран поддоменов актуален в первую очередь для multy-шаблонов.
    </div>
<?php endif; ?>

<div class="panel-card stack-gap-sm">
    <div><b>IP в панели:</b> <?= $currentVpsIp !== '' ? h($currentVpsIp) : '—' ?></div>
    <div><b>DNS A у домена:</b> <?= !empty($dnsA) ? h(implode(', ', $dnsA)) : '— (A не найден)' ?></div>
</div>

<div class="panel-grid panel-grid--2">
    <div class="panel-card">
        <h2 class="section-title">1) Применить список поддоменов</h2>

        <form method="post" action="/sites/subdomains/apply?id=<?= $siteId ?>" class="stack-gap-md">
            <div class="page-actions">
                <button type="button" class="btn btn-secondary" data-check-all="#subCatalog .lbChk">Выбрать все</button>
                <button type="button" class="btn btn-secondary" data-check-none="#subCatalog .lbChk">Снять все</button>
            </div>

            <div id="subCatalog" class="checklist-box">
                <?php foreach ($catalog as $row): ?>
                    <?php
                    $lb = (string)($row['label'] ?? '');
                    $isActive = (int)($row['is_active'] ?? 0) === 1;
                    $attached = isset($attachedMap[$lb]);
                    $enabled  = $enabledMap[$lb] ?? false;
                    ?>
                    <label class="checklist-item <?= $isActive ? '' : 'is-muted' ?>">
                        <input class="lbChk" type="checkbox" name="labels[]" value="<?= h($lb) ?>" <?= $attached ? 'checked' : '' ?>>
                        <span>
                            <?= h($lb) ?>
                            <?= $isActive ? '' : ' (неактивен)' ?>
                            <?= $attached && !$enabled ? ' (выключен)' : '' ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="checkbox-inline">
                <input type="checkbox" name="apply_dns" value="1">
                Сразу применить DNS (A) после сохранения
            </label>

            <div class="small muted">
                Если IP не задан, панель попробует взять его из DNS A или с сервера.
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Применить выбранные</button>
            </div>
        </form>
    </div>

    <div class="panel-card">
        <h2 class="section-title">2) Быстро добавить вручную</h2>
        <form method="post" action="/sites/subdomains/apply?id=<?= $siteId ?>" class="stack-gap-md">
            <div class="small muted">Можно указать label через запятую или пробел.</div>
            <textarea name="labels_text" rows="10" placeholder="например: banda, beef, betera"></textarea>
            <div class="small muted">
                Панель приведёт список к выбранному: добавит недостающие и удалит лишние (кроме <code>_default</code>).
            </div>
            <label class="checkbox-inline">
                <input type="checkbox" name="apply_dns" value="1">
                Сразу применить DNS после сохранения
            </label>
            <div>
                <button type="submit" class="btn btn-secondary">Применить список</button>
            </div>
        </form>
    </div>
</div>

<div class="panel-card">
    <div class="page-head page-head--compact">
        <h2 class="section-title">3) Текущие поддомены сайта</h2>
        <div class="page-actions">
            <a class="btn btn-ai" href="/ai/generate-all-sub-texts?id=<?= (int)$siteId ?>" data-confirm="Сгенерировать AI-тексты для всех enabled поддоменов?">AI: тексты для всех сабов</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Сущность</th>
                <th>Включён</th>
                <th>Статус папки</th>
                <th>Папка обновлена</th>
                <th>Ошибка папки</th>
                <th>Действия и переходы</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($siteSubs as $r): ?>
                <?php
                $lb = (string)($r['label'] ?? '');
                $lbTitle = ($lb === '_default') ? 'Основной домен (_default)' : $lb;
                $enVal = $r['enabled'] ?? ($r['is_enabled'] ?? 0);
                $en = (int)$enVal === 1;

                $fs = (string)($r['folder_status'] ?? '');
                $fe = (string)($r['folder_error'] ?? '');
                $fu = (string)($r['folder_updated_at'] ?? '');

                if ($fs === '') $fs = '—';
                if ($fe === '') $fe = '—';
                if ($fu === '') $fu = '—';
                ?>
                <tr>
                    <td><?= h($lbTitle) ?></td>
                    <td>
                        <span class="badge <?= $en ? 'badge-success' : 'badge-muted' ?>"><?= $en ? 'Да' : 'Нет' ?></span>
                    </td>
                    <td><?= h($fs) ?></td>
                    <td><?= h($fu) ?></td>
                    <td><?= h($fe) ?></td>
                    <td>
                        <div class="inline-actions">
                            <form method="post" action="/sites/subdomains/toggle?id=<?= $siteId ?>">
                                <input type="hidden" name="label" value="<?= h($lb) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= $en ? 'Выключить' : 'Включить' ?></button>
                            </form>

                            <?php if ($lb !== '_default'): ?>
                                <form method="post" action="/sites/subdomains/delete?id=<?= $siteId ?>" data-confirm="Удалить поддомен <?= h($lb) ?>?">
                                    <input type="hidden" name="label" value="<?= h($lb) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="inline-links mt-8">
                            <a href="/sites/subcfg?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Контент и SEO</a>
                            <a href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Страницы</a>
                            <a href="/sites/texts?id=<?= $siteId ?>&label=<?= urlencode($lb) ?>">Тексты</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="page-actions mt-12">
        <form method="post" action="/sites/subdomains/delete-catalog?id=<?= $siteId ?>" data-confirm="Удалить все сабы (кроме _default) и их папки?">
            <button type="submit" class="btn btn-danger">Удалить все сабы (кроме _default)</button>
        </form>
    </div>
</div>

<div class="panel-card stack-gap-md">
    <h2 class="section-title">4) Registrar + DNS (Namecheap)</h2>

    <form method="post" action="/sites/subdomains/set-registrar?id=<?= $siteId ?>" class="inline-form">
        <label>Аккаунт Namecheap</label>
        <select name="registrar_account_id">
            <?php foreach ($registrarAccounts as $a): ?>
                <?php
                $id = (int)($a['id'] ?? 0);
                $sel = ((int)($site['registrar_account_id'] ?? 0) === $id) ? 'selected' : '';
                $title = (string)($a['title'] ?? '');
                ?>
                <option value="<?= $id ?>" <?= $sel ?>>
                    #<?= $id ?> <?= h($title) ?><?= ((int)($a['is_default'] ?? 0) === 1 ? ' (default)' : '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>

    <form method="post" action="/sites/subdomains/detect-registrar?id=<?= $siteId ?>">
        <button type="submit" class="btn btn-secondary">Авто-определить аккаунт по домену</button>
    </form>

    <form method="post" action="/sites/subdomains/update-ip?id=<?= $siteId ?>" class="stack-gap-md">
        <div class="field-row">
            <label>IP для A-записей</label>
            <div class="field-row__controls">
                <?php if (!empty($serverIps)): ?>
                    <select data-fill-target="#ipInput">
                        <option value="">— выбрать из сервера —</option>
                        <?php foreach ($serverIps as $ip): ?>
                            <option value="<?= h($ip) ?>" <?= ($currentVpsIp === $ip ? 'selected' : '') ?>><?= h($ip) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <input id="ipInput" type="text" name="ip" placeholder="например 1.2.3.4" value="<?= h($currentVpsIp) ?>">
            </div>
        </div>

        <label class="checkbox-inline">
            <input type="checkbox" name="update_root" value="1">
            Также обновить корневой A (@)
        </label>

        <div>
            <button type="submit" class="btn btn-primary">Применить DNS (A) для enabled сабов</button>
        </div>
    </form>

    <form method="post" action="/sites/subdomains/delete-catalog-dns?id=<?= $siteId ?>" data-confirm="Удалить DNS записи для всех сабов этого сайта?">
        <button type="submit" class="btn btn-danger">Удалить DNS для сабов (без удаления папок)</button>
    </form>
</div>