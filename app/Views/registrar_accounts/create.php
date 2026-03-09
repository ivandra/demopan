<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$error = (string)($error ?? '');
?>

<div class="page-head">
    <h1 class="page-title">Добавить аккаунт регистратора</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/registrar/accounts">Назад к аккаунтам</a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/accounts/create" class="panel-card system-form--narrow stack-gap-md">
    <div class="field-row">
        <label>Provider</label>
        <input name="provider" value="namecheap">
    </div>

    <div class="field-row">
        <label>Sandbox</label>
        <select name="is_sandbox">
            <option value="1" selected>1 — sandbox</option>
            <option value="0">0 — prod</option>
        </select>
    </div>

    <div class="field-row">
        <label>Client IP</label>
        <input name="client_ip" value="">
    </div>

    <div class="field-row">
        <label>ApiUser</label>
        <input name="api_user" value="">
    </div>

    <div class="field-row">
        <label>Username</label>
        <input name="username" value="">
    </div>

    <div class="field-row">
        <label>ApiKey</label>
        <input name="api_key" value="">
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-secondary" href="/registrar/accounts">Отмена</a>
    </div>
</form>