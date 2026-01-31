<h2>Deploy: <?= htmlspecialchars($site['domain']) ?></h2>
<p><a href="/">← к сайтам</a></p>

<?php if (!empty($ips_error)): ?>
    <p style="color:#b00;"><b>Ошибка получения IP:</b> <?= htmlspecialchars($ips_error) ?></p>
<?php endif; ?>

<!-- 1) Общий блок выбора сервера + IP -->
<div style="padding:12px; border:1px solid #ddd; margin-bottom:12px;">
    <p>
        <label>Сервер FASTPANEL</label><br>
        <select id="server_id" name="server_id"
                onchange="location.href='/deploy?id=<?= (int)$site['id'] ?>&server_id='+this.value;">
            <?php foreach ($servers as $srv): ?>
                <option value="<?= (int)$srv['id'] ?>" <?= ((int)$srv['id'] === (int)$serverId ? 'selected' : '') ?>>
                    <?= htmlspecialchars($srv['host']) ?> (<?= htmlspecialchars($srv['username']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <label>IP для сайта</label><br>

    <?php if (!empty($ips)): ?>
        <select id="ip" name="ip" required>
            <?php foreach ($ips as $oneIp): ?>
                <option value="<?= htmlspecialchars($oneIp) ?>">
                    <?= htmlspecialchars($oneIp) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input id="ip" type="text" name="ip" placeholder="Например: 95.129.234.93" required>
        <div style="color:#777; font-size:12px; margin-top:6px;">
            Список IP не получен — введи IP вручную.
        </div>
    <?php endif; ?>
</div>

<!-- 2) Кнопка Create site (GET) -->
<form method="get" action="/deploy/create-site" style="margin-bottom:12px;">
    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
    <input type="hidden" name="server_id" id="server_id_create" value="<?= (int)$serverId ?>">
    <input type="hidden" name="ip" id="ip_create" value="">
    <button type="submit" onclick="return fillCreateHidden();">
        1) Create site (Fastpanel)
    </button>
</form>

<!-- 3) Кнопка Update files (POST) -->
<form method="post" action="/deploy/update-files?id=<?= (int)$site['id'] ?>">
    <input type="hidden" name="server_id" id="server_id_update" value="<?= (int)$serverId ?>">
    <button type="submit">
        2) Update files (upload + unpack)
    </button>
</form>
<form method="post" action="/deploy/issue-ssl?id=<?= (int)$site['id'] ?>">
  <button type="submit">Выпустить SSL (self-signed)</button>
</form>

<!-- 4) Reset (опционально) -->
<form method="post" action="/deploy/reset?id=<?= (int)$site['id'] ?>" style="margin-top:16px;">
    <button type="submit" onclick="return confirm('Сбросить привязку Fastpanel/FTP для сайта?');" style="background:#fff; border:1px solid #b00; color:#b00;">
        Reset deploy state
    </button>
</form>

<script>
function fillCreateHidden() {
    var srv = document.getElementById('server_id');
    var ip  = document.getElementById('ip');

    if (!srv || !ip) return false;

    document.getElementById('server_id_create').value = srv.value;
    document.getElementById('server_id_update').value = srv.value;

    document.getElementById('ip_create').value = ip.value;

    if (!ip.value) {
        alert('IP обязателен для Create site');
        return false;
    }
    return true;
}
</script>
