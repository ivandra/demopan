<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$token = (string)($row['tg_bot_token'] ?? '');
$chat  = (string)($row['tg_chat_id'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Telegram настройки</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/ssl">SSL monitor</a>
        <a class="btn btn-secondary" href="/sites">К сайтам</a>
    </div>
</div>

<form method="post" action="/ssl/settings" class="panel-card system-form--narrow stack-gap-md">
    <div class="field-row">
        <label>tg_bot_token</label>
        <input name="tg_bot_token" value="<?= h($token) ?>" class="system-code">
    </div>

    <div class="field-row">
        <label>tg_chat_id</label>
        <input name="tg_chat_id" value="<?= h($chat) ?>" class="system-code">
        <div class="small muted">
            Пример: <code>123456789</code> или <code>-1001234567890</code> для группы/канала.
        </div>
    </div>

    <div class="field-row">
        <label class="mb-8">Какие уведомления отправлять</label>
        <label><input type="checkbox" name="tg_notify_ssl_ok" value="1" <?= (int)($row['tg_notify_ssl_ok'] ?? 1) === 1 ? 'checked' : '' ?>> SSL OK</label><br>
        <label><input type="checkbox" name="tg_notify_xmlstock_detected" value="1" <?= (int)($row['tg_notify_xmlstock_detected'] ?? 1) === 1 ? 'checked' : '' ?>> XMLStock увидел хост в поиске</label><br>
        <label><input type="checkbox" name="tg_notify_redirect_enabled" value="1" <?= (int)($row['tg_notify_redirect_enabled'] ?? 1) === 1 ? 'checked' : '' ?>> Авто-включение redirect_enabled</label><br>
        <label><input type="checkbox" name="tg_notify_redirect_disabled" value="1" <?= (int)($row['tg_notify_redirect_disabled'] ?? 1) === 1 ? 'checked' : '' ?>> Авто-выключение redirect_enabled</label><br>
        <label><input type="checkbox" name="tg_notify_manual_sync" value="1" <?= (int)($row['tg_notify_manual_sync'] ?? 1) === 1 ? 'checked' : '' ?>> Ручная выгрузка config на VPS</label>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>

<div class="panel-card mt-16">
    <div class="small muted">
        Состояние чекбоксов применяется ко всем Telegram-уведомлениям панели. Если чекбокс выключен, событие логически продолжает выполняться, но сообщение в Telegram не отправляется.
    </div>
</div>
