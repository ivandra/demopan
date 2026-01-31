<h1>Edit registrar account #<?= (int)$row['id'] ?></h1>

<?php if (!empty($error)): ?>
  <div style="color:#b00;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/accounts/edit?id=<?= (int)$row['id'] ?>">
  <div>
    Provider:
    <input name="provider" value="<?= htmlspecialchars((string)$row['provider']) ?>">
  </div>

  <div>
    Sandbox:
    <select name="is_sandbox">
      <option value="1" <?= ((int)$row['is_sandbox'] === 1) ? 'selected' : '' ?>>1</option>
      <option value="0" <?= ((int)$row['is_sandbox'] === 0) ? 'selected' : '' ?>>0</option>
    </select>
  </div>

  <div>
    Client IP:
    <input name="client_ip" value="<?= htmlspecialchars((string)$row['client_ip']) ?>">
  </div>

  <div>
    ApiUser:
    <input name="api_user" value="<?= htmlspecialchars((string)$row['api_user']) ?>">
  </div>

  <div>
    Username:
    <input name="username" value="<?= htmlspecialchars((string)$row['username']) ?>">
  </div>

  <div>
    ApiKey: <small>(оставь пустым, чтобы не менять)</small><br>
    <input name="api_key" value="">
  </div>

  <div style="margin-top:10px;">
    <button type="submit">Save</button>
    <a href="/registrar/accounts">Cancel</a>
  </div>
</form>
