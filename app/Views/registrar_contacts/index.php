<h1>Registrar contacts</h1>

<p>
  <a href="/registrar/contacts/create">+ Add contact</a>
</p>

<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Label</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Country</th>
    <th>City</th>
    <th>Postal</th>
    <th>Actions</th>
  </tr>

  <?php foreach (($rows ?? []) as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars((string)$r['label']) ?></td>
      <td><?= htmlspecialchars((string)$r['first_name'] . ' ' . (string)$r['last_name']) ?></td>
      <td><?= htmlspecialchars((string)$r['email']) ?></td>
      <td><?= htmlspecialchars((string)$r['phone']) ?></td>
      <td><?= htmlspecialchars((string)$r['country']) ?></td>
      <td><?= htmlspecialchars((string)$r['city']) ?></td>
      <td><?= htmlspecialchars((string)$r['postal_code']) ?></td>
      <td>
        <a href="/registrar/contacts/edit?id=<?= (int)$r['id'] ?>">edit</a>

        <form method="post"
              action="/registrar/contacts/delete?id=<?= (int)$r['id'] ?>"
              style="display:inline"
              onsubmit="return confirm('Delete?');">
          <button type="submit">delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
