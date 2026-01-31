<h1>Add registrar contact</h1>

<?php if (!empty($error)): ?>
  <div style="color:#b00; font-weight:bold; margin:8px 0;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php $d = $data ?? []; ?>

<form method="post" action="/registrar/contacts/create">
  <div>
    Label:
    <input name="label" value="<?= htmlspecialchars((string)($d['label'] ?? 'default')) ?>">
  </div>

  <hr>

  <div>First name*: <input name="first_name" value="<?= htmlspecialchars((string)($d['first_name'] ?? '')) ?>"></div>
  <div>Last name*: <input name="last_name" value="<?= htmlspecialchars((string)($d['last_name'] ?? '')) ?>"></div>
  <div>Organization: <input name="organization" value="<?= htmlspecialchars((string)($d['organization'] ?? '')) ?>"></div>

  <div>Address1*: <input name="address1" value="<?= htmlspecialchars((string)($d['address1'] ?? '')) ?>"></div>
  <div>Address2: <input name="address2" value="<?= htmlspecialchars((string)($d['address2'] ?? '')) ?>"></div>

  <div>City*: <input name="city" value="<?= htmlspecialchars((string)($d['city'] ?? '')) ?>"></div>

  <div>
    State/Province:
    <input name="state_province" value="<?= htmlspecialchars((string)($d['state_province'] ?? '')) ?>">
  </div>

  <div>
    Postal code*:
    <input name="postal_code" value="<?= htmlspecialchars((string)($d['postal_code'] ?? '')) ?>">
  </div>

  <div>
    Country* (2 letters):
    <input name="country" value="<?= htmlspecialchars((string)($d['country'] ?? 'US')) ?>">
  </div>

  <div>
    Phone* (пример +7 999 123-45-67):
    <input name="phone" value="<?= htmlspecialchars((string)($d['phone'] ?? '')) ?>">
  </div>

  <div>Email*: <input name="email" value="<?= htmlspecialchars((string)($d['email'] ?? '')) ?>"></div>

  <div style="margin-top:10px;">
    <button type="submit">Save</button>
    <a href="/registrar/contacts">Cancel</a>
  </div>
</form>
