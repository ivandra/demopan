<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$site = $site ?? [];
$labels = $labels ?? [];
?>

<div class="page-head">
    <h1 class="page-title">Клонирование сайта</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= (int)($site['id'] ?? 0) ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
    </div>
    <div class="page-subtitle">
        Источник: сайт #<?= (int)($site['id'] ?? 0) ?> — <code><?= h($site['domain'] ?? '') ?></code>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Источник</div>
    <div class="site-context__title"><?= h($site['domain'] ?? '') ?></div>
    <div class="site-context__meta">
        Шаблон: <b><?= h($site['template'] ?? '') ?></b>
        <br>
        VPS: <?= (int)($site['fastpanel_server_id'] ?? 0) > 0 ? 'ID ' . (int)$site['fastpanel_server_id'] : '—' ?>
        |
        IP: <?= h($site['vps_ip'] ?? '—') ?>
    </div>
</div>

<form method="post" action="/sites/clone?id=<?= (int)($site['id'] ?? 0) ?>" class="panel-card system-form mt-16 stack-gap-lg">
    <div class="field-row">
        <label>Новый основной домен</label>
        <input type="text" name="new_domain" value="<?= h($defaultNewDomain ?? '') ?>" placeholder="mynewdomain.com">
        <div class="small muted">
            Пример: <code>mynewdomain.com</code>
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Параметры клона</h2>

        <label class="checkbox-inline">
            <input type="checkbox" name="same_vps" value="1" <?= ((int)($defaultSameVps ?? 1) === 1 ? 'checked' : '') ?>>
            Оставить тот же VPS (FastPanel сервер и IP)
        </label>

        <label class="checkbox-inline">
            <input type="checkbox" name="reset_state" value="1" <?= ((int)($defaultResetState ?? 1) === 1 ? 'checked' : '') ?>>
            Сбросить статусы deploy / SSL / DNS / покупку домена
        </label>

        <div class="small muted">
            Рекомендуется оставлять включённым “сброс”, чтобы новый сайт проходил pipeline как новый.
        </div>
    </div>

    <div class="panel-card stack-gap-md">
        <div class="page-head page-head--compact">
            <h2 class="section-title">Какие поддомены копировать</h2>
            <div class="page-actions">
                <button type="button" class="btn btn-secondary" data-check-all=".lbcb">Выбрать все</button>
                <button type="button" class="btn btn-secondary" data-check-none=".lbcb">Снять все</button>
            </div>
        </div>

        <div class="checklist-box">
            <?php if (empty($labels)): ?>
                <div class="small muted">
                    Поддомены не найдены. <code>_default</code> копируется всегда как root-конфиг.
                </div>
            <?php else: ?>
                <?php foreach ($labels as $r): ?>
                    <label class="checklist-item">
                        <input class="lbcb" type="checkbox" name="labels[]" value="<?= h($r['label']) ?>" checked>
                        <span>
                            <b><?= h($r['label']) ?></b>
                            <span class="muted">(<?= h($r['fqdn']) ?>)</span>
                        </span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="small muted">
            <code>_default</code> копируется всегда — это базовая конфигурация основного домена.
        </div>
    </div>

    <div class="page-actions">
        <button type="submit"
                class="btn btn-primary"
                data-confirm="Создать клон сайта и применить выбранные параметры?">
            Создать копию сайта
        </button>
    </div>
</form>