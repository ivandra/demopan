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

<div class="page-head">
    <h1 class="page-title">Webmaster / Настройки</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/webmaster">Назад в Webmaster</a>
    </div>
</div>

<?php if ($saved): ?>
    <div class="alert alert-success">Сохранено</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><b>Ошибка:</b> <?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="/webmaster/connect" class="panel-card system-form stack-gap-md">
    <div class="field-row">
        <label>OAuth ClientID</label>
        <input type="text" name="oauth_client_id" value="<?= h($clientId) ?>">
    </div>

    <div class="field-row">
        <label>Access token</label>
        <textarea name="access_token" rows="6"><?= h($token) ?></textarea>
        <div class="small muted">
            Токен обычно действует около 6 месяцев. При необходимости обновляйте его вручную и вставляйте сюда.
        </div>
    </div>

    <div class="field-row">
        <label>Token expires at</label>
        <input type="text" name="token_expires_at" value="<?= h($expires) ?>" placeholder="YYYY-MM-DD HH:MM:SS (опционально)">
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>

<div class="panel-card mt-16">
    <h2 class="section-title">Как получить токен</h2>

    <ol class="status-list">
        <li>Создайте приложение в Яндекс OAuth для веб-сервисов.</li>
        <li>Скопируйте ClientID и вставьте его в поле выше.</li>
        <li>
            Откройте ссылку авторизации:
            <div class="mt-8"><code class="system-code">https://oauth.yandex.ru/authorize?response_type=token&amp;client_id=CLIENT_ID</code></div>
        </li>
        <li>После авторизации скопируйте token из адресной строки и вставьте его в поле Access token.</li>
    </ol>
</div>