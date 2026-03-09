<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$token = (string)($row['tg_bot_token'] ?? '');
$chat  = (string)($row['tg_chat_id'] ?? '');
?>

<div class="page-head">
    <h1 class="page-title">SSL monitor — Telegram настройки</h1>
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

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>

<div class="panel-card mt-16">
    <div class="small muted">
        Уведомления отправляются, когда домен стал <b>https_ok=1</b> и при этом <b>notified_at IS NULL</b>.
    </div>
</div>