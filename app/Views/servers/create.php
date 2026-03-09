<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$defaultVerifyTls = (int)($defaultVerifyTls ?? 0);
?>

<div class="page-head">
    <h1 class="page-title">Добавить сервер FastPanel</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/servers">Назад к серверам</a>
    </div>
</div>

<form method="post" action="/servers/create" class="panel-card system-form stack-gap-md">
    <div class="field-row">
        <label>Название</label>
        <input name="title" placeholder="VPS #1">
    </div>

    <div class="field-row">
        <label>Host панели</label>
        <input name="host" placeholder="95.129.234.20:8888">
        <div class="small muted">
            Вводите без <code>https://</code>. Если вставите с протоколом — контроллер всё равно нормализует.
        </div>
    </div>

    <div class="field-row">
        <label>Логин</label>
        <input name="username">
    </div>

    <div class="field-row">
        <label>Пароль</label>
        <input type="password" name="password">
    </div>

    <div class="field-row">
        <label>Проверять TLS сертификат</label>
        <select name="verify_tls">
            <option value="0" <?= ($defaultVerifyTls === 0 ? 'selected' : '') ?>>0 — self-signed / не проверять</option>
            <option value="1" <?= ($defaultVerifyTls === 1 ? 'selected' : '') ?>>1 — проверять</option>
        </select>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-secondary" href="/servers">Отмена</a>
    </div>
</form>