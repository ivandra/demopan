<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$error = (string)($error ?? '');
$d = is_array($data ?? null) ? $data : [];
?>

<div class="page-head">
    <h1 class="page-title">Добавить контакт регистратора</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/registrar/contacts">Назад к контактам</a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/contacts/create" class="panel-card system-form stack-gap-md">
    <div class="field-row">
        <label>Label</label>
        <input name="label" value="<?= h((string)($d['label'] ?? 'default')) ?>">
    </div>

    <div class="system-sep"></div>

    <div class="panel-grid panel-grid--2">
        <div class="field-row">
            <label>First name*</label>
            <input name="first_name" value="<?= h((string)($d['first_name'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Last name*</label>
            <input name="last_name" value="<?= h((string)($d['last_name'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Organization</label>
            <input name="organization" value="<?= h((string)($d['organization'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Email*</label>
            <input name="email" value="<?= h((string)($d['email'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Address1*</label>
            <input name="address1" value="<?= h((string)($d['address1'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Address2</label>
            <input name="address2" value="<?= h((string)($d['address2'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>City*</label>
            <input name="city" value="<?= h((string)($d['city'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>State/Province</label>
            <input name="state_province" value="<?= h((string)($d['state_province'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Postal code*</label>
            <input name="postal_code" value="<?= h((string)($d['postal_code'] ?? '')) ?>">
        </div>

        <div class="field-row">
            <label>Country* (2 letters)</label>
            <input name="country" value="<?= h((string)($d['country'] ?? 'US')) ?>">
        </div>

        <div class="field-row">
            <label>Phone*</label>
            <input name="phone" value="<?= h((string)($d['phone'] ?? '')) ?>">
            <div class="small muted">Пример: <code>+7 999 123-45-67</code></div>
        </div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-secondary" href="/registrar/contacts">Отмена</a>
    </div>
</form>