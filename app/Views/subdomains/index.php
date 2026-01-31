<h2>Поддомены (каталог)</h2>

<p><a href="/">← к сайтам</a></p>

<form method="post" action="/subdomains/bulk-add" style="margin:10px 0;">
  <p><b>Добавить пачкой</b> (через пробел/перенос/запятую):</p>
  <textarea name="labels" rows="6" style="width:100%"></textarea>
  <button type="submit">Добавить</button>
</form>



<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
  <tr>
    <th>ID</th>
    <th>Label</th>
    <th>Active</th>
    <th>Actions</th>
  </tr>

  <?php foreach (($rows ?? []) as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars((string)$r['label'], ENT_QUOTES) ?></td>
      <td><?= ((int)$r['is_active'] === 1) ? '✅' : '❌' ?></td>
      <td>
        <form method="post" action="/subdomains/toggle?id=<?= (int)$r['id'] ?>" style="display:inline">
          <button type="submit">Toggle</button>
        </form>
        |
        <form method="post" action="/subdomains/delete?id=<?= (int)$r['id'] ?>" style="display:inline"
              onsubmit="return confirm('Удалить #<?= (int)$r['id'] ?>?');">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
