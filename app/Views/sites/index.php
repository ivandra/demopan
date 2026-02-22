<h2>Сайты</h2>

<p>
    <a href="/sites/create">➕ Создать сайт</a>
</p>

<p>
  <a href="/servers">FASTPANEL серверы</a>
  | <a href="/subdomains">Поддомены (каталог)</a>
</p>


<?php if (empty($sites)): ?>
    <p>Сайтов пока нет</p>
<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
    <tr>
        <th>ID</th>
        <th>Домен</th>
        <th>Шаблон</th>
        <th>Статус</th>
		<th>VPS</th>
		<th>FTP</th>
		<th>Файлы</th>
		<th>SSL</th>
        <th>Действия</th>
		
    </tr>

   <?php foreach ($sites as $site): ?>
    <tr>
        <td><?= (int)($site['id'] ?? 0) ?></td>

        <td><?= htmlspecialchars((string)($site['domain'] ?? ''), ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars((string)($site['template'] ?? ''), ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars((string)($site['status'] ?? ''), ENT_QUOTES) ?></td>

        <td>
            <?php if ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0): ?>
                ✅ (#<?= (int)$site['fp_site_id'] ?>)
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>

        <td>
            <?php if ((int)($site['fp_ftp_ready'] ?? 0) === 1): ?>
                ✅
                <?php if (!empty($site['fp_ftp_last_ok'])): ?>
                    <small><?= htmlspecialchars(date('d.m H:i', strtotime((string)$site['fp_ftp_last_ok'])), ENT_QUOTES) ?></small>
                <?php endif; ?>
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>

        <td>
            <?php if ((int)($site['fp_files_ready'] ?? 0) === 1): ?>
                ✅
                <?php if (!empty($site['fp_files_last_ok'])): ?>
                    <small><?= htmlspecialchars(date('d.m H:i', strtotime((string)$site['fp_files_last_ok'])), ENT_QUOTES) ?></small>
                <?php endif; ?>
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>
		

	<td>
  <?php
    $vpsOk = ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0);
    $sslReady = (int)($site['ssl_ready'] ?? 0) === 1;
    $sslHasCert = (int)($site['ssl_has_cert'] ?? 0) === 1;
    $sslCertId = (int)($site['ssl_cert_id'] ?? 0);
    $sslErr = (string)($site['ssl_error'] ?? '');

    if (!$vpsOk) {
        echo '<span style="color:#666">—</span>';
    } elseif ($sslErr !== '') {
        echo '<span style="color:#b00">Ошибка</span>';
    } elseif ($sslReady) {
        echo '<span style="color:#0a0">Готов</span>';
        if ($sslCertId > 0) echo ' <small style="opacity:.7">#' . $sslCertId . '</small>';
    } elseif ($sslHasCert) {
        echo '<span style="color:#b80">Не применен</span>';
        if ($sslCertId > 0) echo ' <small style="opacity:.7">#' . $sslCertId . '</small>';
    } else {
        echo '<span style="color:#666">Нет</span>';
    }
  ?>
</td>


        <td>
    <form method="post"
          action="/sites/build?id=<?= (int)($site['id'] ?? 0) ?>"
          style="display:inline"
          onsubmit="return confirm('Запустить сборку и проверку?');">
        <button type="submit">Build</button>
    </form>

    | <a href="/deploy?id=<?= (int)($site['id'] ?? 0) ?>">Deploy</a>
    | <a href="/domains?id=<?= (int)($site['id'] ?? 0) ?>">Domains</a>
| <a href="/sites/subdomains?id=<?= (int)($site['id'] ?? 0) ?>">Subs</a>
	 | <a href="/sites/subcfg?id=<?= (int)$site['id'] ?>">SubCfg</a>

    | <a href="/sites/resetFastpanelState?id=<?= (int)($site['id'] ?? 0) ?>"
         onclick="return confirm('Сбросить статусы VPS/FTP/Files?')">Reset</a>

    | <a href="/sites/edit?id=<?= (int)($site['id'] ?? 0) ?>">Редактировать</a>
    | <a href="/sites/pages?id=<?= (int)($site['id'] ?? 0) ?>">Pages</a>
    | <a href="/sites/texts?id=<?= (int)($site['id'] ?? 0) ?>">Texts</a>
    | <a href="/sites/files?id=<?= (int)($site['id'] ?? 0) ?>">Files</a>
	| <a href="/webmaster/site?id=<?= (int)($site['id'] ?? 0) ?>">Webmaster</a>
    <?php if (!empty($site['build_path'])): ?>
        | <a href="/sites/export?id=<?= (int)($site['id'] ?? 0) ?>">ZIP</a>
    <?php endif; ?>

    | <form method="post"
            action="/sites/delete?id=<?= (int)($site['id'] ?? 0) ?>"
            style="display:inline"
            onsubmit="return confirm('Удалить сайт #<?= (int)($site['id'] ?? 0) ?>?');">
        <button type="submit">Удалить</button>
    </form>
</td>

		
    </tr>
<?php endforeach; ?>

</table>

<?php endif; ?>
