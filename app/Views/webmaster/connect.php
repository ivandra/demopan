<?php
declare(strict_types=1);

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$settings = $settings ?? [];
$saved = (bool)($saved ?? false);
$error = (string)($error ?? '');

$clientId = (string)($settings['oauth_client_id'] ?? '');
$token    = (string)($settings['access_token'] ?? '');
$expires  = (string)($settings['token_expires_at'] ?? '');
?>
<h2>Webmaster / Настройки</h2>

<p>
  <a href="/webmaster">← Назад</a>
</p>

<?php if ($saved): ?>
  <p style="color:green;font-weight:bold;">Сохранено</p>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <p style="color:#b00;"><b>Ошибка:</b> <?= h($error) ?></p>
<?php endif; ?>

<form method="post" action="/webmaster/connect" style="max-width:900px;">
  <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%;">
    <tr>
      <th style="width:220px;">OAuth ClientID</th>
      <td><input type="text" name="oauth_client_id" value="<?= h($clientId) ?>" style="width:100%;"></td>
    </tr>
    <tr>
      <th>Access token</th>
      <td>
        <textarea name="access_token" rows="5" style="width:100%;"><?= h($token) ?></textarea>
        <div style="opacity:.8;margin-top:6px;">
          Токен действует ~6 месяцев. Обновляй вручную и вставляй сюда.
        </div>
      </td>
    </tr>
    <tr>
      <th>Token expires at</th>
      <td>
        <input type="text" name="token_expires_at" value="<?= h($expires) ?>" style="width:100%;" placeholder="YYYY-MM-DD HH:MM:SS (опционально)">
      </td>
    </tr>
  </table>

  <p style="margin-top:12px;">
    <button type="submit">Сохранить</button>
  </p>
</form>

<hr>

<h3>Как получить токен</h3>

<ol>
  <li>В Яндекс OAuth создай приложение (платформа: веб-сервисы).</li>
  <li>Скопируй ClientID и вставь сюда.</li>
  <li>Перейди по ссылке авторизации вида:<br>
    <code>https://oauth.yandex.ru/authorize?response_type=token&client_id=CLIENT_ID</code>
  </li>
  <li>Скопируй token из адресной строки и вставь сюда.</li>
</ol>
