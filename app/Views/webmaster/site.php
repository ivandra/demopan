<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$log = $_SESSION['wm_log'] ?? [];
unset($_SESSION['wm_log']);
?>

<h2>Webmaster: сайт #<?= (int)$site['id'] ?> — <?= h($site['domain']) ?></h2>

<p>
    <a href="/webmaster">← Назад</a>
</p>

<?php if (!empty($log)): ?>
    <pre style="background:#111;color:#0f0;padding:12px;border-radius:8px;white-space:pre-wrap;"><?= h(implode("\n", $log)) ?></pre>
<?php endif; ?>

<form method="post" action="/webmaster/sync?id=<?= (int)$site['id'] ?>" style="display:inline"
      onsubmit="return confirm('Синхронизировать хосты + получить HTML verify + записать файлы в build?');">
    <button type="submit">1) Синхронизировать + получить HTML verify + записать файлы</button>
</form>

<form method="post" action="/webmaster/verify?id=<?= (int)$site['id'] ?>" style="display:inline;margin-left:10px"
      onsubmit="return confirm('Проверить в Яндексе (verifyHost)? Перед этим файлы должны быть задеплоены на домены.');">
    <button type="submit">2) Проверить верификацию в Яндексе (verifyHost)</button>
</form>

<p style="margin-top:12px;color:#555">
    Важно: после шага 1 файлы окажутся в build папке сайта. Чтобы Яндекс реально увидел их по URL — сделай Deploy → update-files (или твой обычный пайплайн выкладки).
</p>

<h3>Хосты (актуальные по сайту)</h3>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
    <tr>
        <th>label</th>
        <th>host_url</th>
        <th>host_id</th>
        <th>verify file</th>
        <th>file_written</th>
        <th>verified_at</th>
        <th>last_sync_at</th>
    </tr>

    <?php foreach ($desired as $hrow): ?>
        <?php
        $label = (string)$hrow['label'];
        $hostUrl = (string)$hrow['host_url'];
        $r = $rowMap[$label] ?? null;
        ?>
        <tr>
            <td><?= h($label === '' ? '(root)' : $label) ?></td>
            <td><?= h($hostUrl) ?></td>
            <td><?= $r ? h($r['host_id'] ?? '') : '' ?></td>
            <td><?= $r ? h($r['verification_file'] ?? '') : '' ?></td>
            <td><?= $r ? ((int)($r['file_written'] ?? 0) === 1 ? 'YES' : 'NO') : '' ?></td>
            <td><?= $r ? h($r['verified_at'] ?? '') : '' ?></td>
            <td><?= $r ? h($r['last_sync_at'] ?? '') : '' ?></td>
        </tr>
    <?php endforeach; ?>
</table>
