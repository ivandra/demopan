<h1>Edit registrar contact #<?= (int)$row['id'] ?></h1>

<?php if (!empty($error)): ?>
  <div style="color:#b00; font-weight:bold; margin:8px 0;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/contacts/edit?id=<?= (int)$row['id'] ?>">
  <div>
    Label:
    <input name="label" value="<?= htmlspecialchars((string)$row['label']) ?>">
  </div>

  <hr>

  <div>First name*: <input name="first_name" value="<?= htmlspecialchars((string)$row['first_name']) ?>"></div>
  <div>Last name*: <input name="last_name" value="<?= htmlspecialchars((string)$row['last_name']) ?>"></div>
  <div>Organization: <input name="organization" value="<?= htmlspecialchars((string)$row['organization']) ?>"></div>

  <div>Address1*: <input name="address1" value="<?= htmlspecialchars((string)$row['address1']) ?>"></div>
  <div>Address2: <input name="address2" value="<?= htmlspecialchars((string)$row['address2']) ?>"></div>

  <div>City*: <input name="city" value="<?= htmlspecialchars((string)$row['city']) ?>"></div>

  <div>
    State/Province:
    <input name="state_province" value="<?= htmlspecialchars((string)$row['state_province']) ?>">
  </div>

  <div>
    Postal code*:
    <input name="postal_code" value="<?= htmlspecialchars((string)$row['postal_code']) ?>">
  </div>

  <div>
    Country* (2 letters):
    <input name="country" value="<?= htmlspecialchars((string)$row['country']) ?>">
  </div>

  <div>
    Phone* (пример +7 999 123-45-67):
    <input name="phone" value="<?= htmlspecialchars((string)$row['phone']) ?>">
  </div>

  <div>Email*: <input name="email" value="<?= htmlspecialchars((string)$row['email']) ?>"></div>

  <div style="margin-top:10px;">
    <button type="submit">Save</button>
    <a href="/registrar/contacts">Cancel</a>
  </div>
</form>
