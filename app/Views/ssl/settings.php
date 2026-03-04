<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
$token = (string)($row['tg_bot_token'] ?? '');
$chat  = (string)($row['tg_chat_id'] ?? '');
?>

<h2>SSL monitor — Telegram настройки</h2>

<p>
  <a href="/sites">← К сайтам</a>
  | <a href="/ssl">SSL monitor (общий)</a>
</p>

<form method="post" action="/ssl/settings" style="max-width:720px;border:1px solid #ddd;padding:14px;border-radius:10px;">
  <div style="margin-bottom:10px;">
    <label><b>tg_bot_token</b></label><br>
    <input name="tg_bot_token" value="<?= h($token) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-family:monospace;">
  </div>

  <div style="margin-bottom:10px;">
    <label><b>tg_chat_id</b></label><br>
    <input name="tg_chat_id" value="<?= h($chat) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-family:monospace;">
    <div style="margin-top:6px;color:#666;font-size:12px;">
      Пример: <code>123456789</code> или <code>-1001234567890</code> (для групп/каналов).
    </div>
  </div>

  <button type="submit" style="padding:10px 14px;border-radius:8px;border:0;background:#2f80ed;color:#fff;font-weight:600;">
    Сохранить
  </button>
</form>

<div style="margin-top:12px;color:#666;font-size:12px;">
  Примечание: уведомления отправляются, когда домен стал <b>https_ok=1</b> и <b>notified_at IS NULL</b>.
</div>