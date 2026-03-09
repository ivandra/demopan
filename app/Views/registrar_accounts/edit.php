<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$row = is_array($row ?? null) ? $row : [];
$error = (string)($error ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Редактировать аккаунт регистратора</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/registrar/accounts">Назад к аккаунтам</a>
    </div>
    <div class="page-subtitle">
        Аккаунт #<?= (int)($row['id'] ?? 0) ?>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/accounts/edit?id=<?= (int)$row['id'] ?>" class="panel-card system-form--narrow stack-gap-md">
    <div class="field-row">
        <label>Provider</label>
        <input name="provider" value="<?= h((string)$row['provider']) ?>">
    </div>

    <div class="field-row">
        <label>Sandbox</label>
        <select name="is_sandbox">
            <option value="1" <?= ((int)$row['is_sandbox'] === 1) ? 'selected' : '' ?>>1 — sandbox</option>
            <option value="0" <?= ((int)$row['is_sandbox'] === 0) ? 'selected' : '' ?>>0 — prod</option>
        </select>
    </div>

    <div class="field-row">
        <label>Client IP</label>
        <input name="client_ip" value="<?= h((string)$row['client_ip']) ?>">
    </div>

    <div class="field-row">
        <label>ApiUser</label>
        <input name="api_user" value="<?= h((string)$row['api_user']) ?>">
    </div>

    <div class="field-row">
        <label>Username</label>
        <input name="username" value="<?= h((string)$row['username']) ?>">
    </div>

    <div class="field-row">
        <label>ApiKey</label>
        <input name="api_key" value="">
        <div class="small muted">Оставьте пустым, чтобы не менять текущий ключ.</div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-secondary" href="/registrar/accounts">Отмена</a>
    </div>
</form>