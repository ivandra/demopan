<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$server = is_array($server ?? null) ? $server : [];
?>

<div class="page-head">
    <h1 class="page-title">Редактировать сервер FastPanel</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/servers">Назад к серверам</a>
        <a class="btn btn-secondary" href="/servers/test?id=<?= (int)$server['id'] ?>" target="_blank" rel="noopener">Проверить соединение</a>
    </div>
    <div class="page-subtitle">
        Сервер #<?= (int)$server['id'] ?>
    </div>
</div>

<form method="post" action="/servers/edit?id=<?= (int)$server['id'] ?>" class="panel-card system-form stack-gap-md">
    <div class="field-row">
        <label>Название</label>
        <input name="title" value="<?= h($server['title'] ?? '') ?>">
    </div>

    <div class="field-row">
        <label>Host панели</label>
        <input name="host" value="<?= h($server['host'] ?? '') ?>">
        <div class="small muted">
            Без <code>https://</code>. Если указать с протоколом — контроллер нормализует значение.
        </div>
    </div>

    <div class="field-row">
        <label>Логин</label>
        <input name="username" value="<?= h($server['username'] ?? '') ?>">
    </div>

    <div class="field-row">
        <label>Пароль</label>
        <input type="password" name="password" value="">
        <div class="small muted">Оставьте пустым, если пароль менять не нужно.</div>
    </div>

    <div class="field-row">
        <label>Проверять TLS сертификат</label>
        <select name="verify_tls">
            <option value="0" <?= ((int)($server['verify_tls'] ?? 0) === 0 ? 'selected' : '') ?>>0 — self-signed / не проверять</option>
            <option value="1" <?= ((int)($server['verify_tls'] ?? 0) === 1 ? 'selected' : '') ?>>1 — проверять</option>
        </select>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-secondary" href="/servers">Отмена</a>
    </div>
</form>