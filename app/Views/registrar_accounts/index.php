<h1>Registrar accounts</h1>

<p>
  <a href="/registrar/accounts/create">+ Add account</a>
</p>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Provider</th>
    <th>Sandbox</th>
    <th>Client IP</th>
    <th>ApiUser</th>
    <th>Username</th>
    <th>Created</th>
    <th>Actions</th>
  </tr>
  <?php foreach (($rows ?? []) as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars((string)$r['provider']) ?></td>
      <td><?= (int)$r['is_sandbox'] ?></td>
      <td><?= htmlspecialchars((string)$r['client_ip']) ?></td>
      <td><?= htmlspecialchars((string)$r['api_user']) ?></td>
      <td><?= htmlspecialchars((string)$r['username']) ?></td>
      <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
      <td>
        <a href="/registrar/accounts/edit?id=<?= (int)$r['id'] ?>">edit</a>
        <form method="post" action="/registrar/accounts/delete?id=<?= (int)$r['id'] ?>" style="display:inline" onsubmit="return confirm('Delete?');">
          <button type="submit">delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
