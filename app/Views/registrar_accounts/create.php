<h1>Add registrar account</h1>

<?php if (!empty($error)): ?>
  <div style="color:#b00;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/registrar/accounts/create">
  <div>
    Provider:
    <input name="provider" value="namecheap">
  </div>

  <div>
    Sandbox:
    <select name="is_sandbox">
      <option value="1" selected>1</option>
      <option value="0">0</option>
    </select>
  </div>

  <div>
    Client IP:
    <input name="client_ip" value="">
  </div>

  <div>
    ApiUser:
    <input name="api_user" value="">
  </div>

  <div>
    Username:
    <input name="username" value="">
  </div>

  <div>
    ApiKey:
    <input name="api_key" value="">
  </div>

  <div style="margin-top:10px;">
    <button type="submit">Save</button>
    <a href="/registrar/accounts">Cancel</a>
  </div>
</form>
