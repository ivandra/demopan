<?php
$site = is_array($site ?? null) ? $site : [];
$catalog = is_array($catalog ?? null) ? $catalog : [];
$rows = is_array($rows ?? null) ? $rows : [];
$registrarAccounts = is_array($registrarAccounts ?? null) ? $registrarAccounts : [];
$availableIps = is_array($availableIps ?? null) ? $availableIps : [];

$siteId = (int)($site['id'] ?? 0);

// домен для отображения
$siteDomainRaw = (string)($site['domain'] ?? '');
$siteDomainView = $siteDomainRaw !== '' ? $siteDomainRaw : '(no domain)';

// текущий выбранный IP сайта (то, что сохранено в БД)
$currentIp = trim((string)($site['vps_ip'] ?? ''));
if ($currentIp === '' && !empty($availableIps)) {
    // если в БД пусто — подставим первый из доступных чисто для UI
    $currentIp = (string)$availableIps[0];
}

// registrar account id
$rid = (int)($site['registrar_account_id'] ?? 0);

// сформируем читабельный текст текущего аккаунта
$currentText = 'Не выбран (будет использован default аккаунт Namecheap)';
if ($rid > 0) {
    foreach ($registrarAccounts as $a) {
        if ((int)($a['id'] ?? 0) === $rid) {
            $mode = ((int)($a['is_sandbox'] ?? 0) === 1) ? 'SANDBOX' : 'PROD';
            $username = (string)($a['username'] ?? '');
            $apiUser  = (string)($a['api_user'] ?? '');
            $clientIp = (string)($a['client_ip'] ?? '');
            $currentText = $mode . ' | ' . $username . ' | api_user=' . $apiUser . ($clientIp !== '' ? ' | client_ip=' . $clientIp : '');
            break;
        }
    }
    if ($currentText === 'Не выбран (будет использован default аккаунт Namecheap)') {
        $currentText = 'Выбран ID=' . $rid . ' (аккаунт не найден в списке registrar_accounts)';
    }
}
?>

<h2>Поддомены сайта #<?= $siteId ?>: <?= htmlspecialchars($siteDomainView, ENT_QUOTES) ?></h2>

<p>
  <a href="/">← к сайтам</a>
  | <a href="/subdomains">Каталог поддоменов</a>
</p>

<!-- Действия -->
<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin:10px 0;">

  <!-- APPLY: создаем/обновляем поддомены. ВАЖНО: передаем ip -->
  <form method="post"
        action="/sites/subdomains/apply?id=<?= $siteId ?>"
        onsubmit="return confirm('Создать/обновить поддомены по каталогу?');">
      <input type="hidden" name="ip" id="apply_ip" value="<?= htmlspecialchars($currentIp, ENT_QUOTES) ?>">
      <button type="submit">Создать/обновить поддомены по каталогу</button>
  </form>

  <!-- DELETE CATALOG: удалить записи поддоменов из БД -->
  <form method="post"
        action="/sites/subdomains/delete-catalog?id=<?= $siteId ?>"
        onsubmit="return confirm('Удалить все поддомены, добавленные из каталога?');">
      <button type="submit">Удалить поддомены каталога</button>
  </form>

  <!-- DELETE DNS ONLY: удалить DNS-записи поддоменов каталога (без удаления из БД) -->
  <form method="post"
        action="/sites/subdomains/delete-catalog-dns?id=<?= $siteId ?>"
        onsubmit="return confirm('Удалить DNS (только) для поддоменов каталога?');">
      <input type="hidden" name="ip" id="delete_dns_ip" value="<?= htmlspecialchars($currentIp, ENT_QUOTES) ?>">
      <button type="submit">Удалить DNS (только)</button>
  </form>

  <!-- UPDATE IP: обновить IP в DNS для поддоменов каталога -->
  <form method="post"
        action="/sites/subdomains/update-ip?id=<?= $siteId ?>"
        onsubmit="return confirm('Обновить IP в DNS для поддоменов каталога?');">
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <div>
        <label style="font-size:12px;display:block;">DNS IP</label>

        <select name="ip" id="dns_ip_select" style="width:170px;">
          <?php if ($currentIp !== '' && !in_array($currentIp, $availableIps, true)): ?>
            <option value="<?= htmlspecialchars($currentIp, ENT_QUOTES) ?>" selected>
              <?= htmlspecialchars($currentIp, ENT_QUOTES) ?> (текущий)
            </option>
          <?php endif; ?>

          <?php foreach ($availableIps as $ip): ?>
            <option value="<?= htmlspecialchars($ip, ENT_QUOTES) ?>" <?= ($ip === $currentIp ? 'selected' : '') ?>>
              <?= htmlspecialchars($ip, ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <label style="font-size:12px;display:flex;gap:6px;align-items:center;margin-top:18px;">
        <input type="checkbox" name="update_root" value="1">
        обновить @ тоже
      </label>

      <button type="submit" style="margin-top:18px;">Обновить DNS IP</button>
    </div>
  </form>

</div>

<script>
(function() {
  // Синхронизируем выбранный IP из селекта в скрытые поля форм apply/delete-dns
  var sel = document.getElementById('dns_ip_select');
  var a1  = document.getElementById('apply_ip');
  var a2  = document.getElementById('delete_dns_ip');

  function sync() {
    if (!sel) return;
    var v = sel.value || '';
    if (a1) a1.value = v;
    if (a2) a2.value = v;
  }
  if (sel) {
    sel.addEventListener('change', sync);
    sync();
  }
})();
</script>

<!-- Namecheap аккаунт -->
<div style="margin:12px 0; padding:10px; border:1px solid #ddd;">
  <div style="margin-bottom:6px;">
    <b>Namecheap аккаунт для DNS:</b>
    <span><?= htmlspecialchars($currentText, ENT_QUOTES) ?></span>
    <div style="font-size:12px; opacity:.75; margin-top:4px;">
      Если выбрать неверный аккаунт — будет ошибка “domain not found / not associated”.
    </div>
  </div>

  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
    <form method="post"
          action="/sites/subdomains/set-registrar?id=<?= $siteId ?>"
          style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">

      <select name="registrar_account_id">
        <option value="0" <?= $rid === 0 ? 'selected' : '' ?>>(использовать default)</option>

        <?php foreach ($registrarAccounts as $a): ?>
          <?php
            $id = (int)($a['id'] ?? 0);
            $mode = ((int)($a['is_sandbox'] ?? 0) === 1) ? 'SANDBOX' : 'PROD';
            $label = $mode . ' | ' . (string)($a['username'] ?? '') . ' | api_user=' . (string)($a['api_user'] ?? '');
            if ((int)($a['is_default'] ?? 0) === 1) $label .= ' | default';
            $sel = ($id === $rid) ? 'selected' : '';
          ?>
          <option value="<?= $id ?>" <?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Сохранить</button>
    </form>

    <form method="post"
          action="/sites/subdomains/detect-registrar?id=<?= $siteId ?>"
          onsubmit="return confirm('Попробовать найти Namecheap аккаунт, где лежит домен?');">
      <button type="submit">Автоопределить</button>
    </form>
  </div>
</div>

<h3 style="margin-top:20px;">Каталог (активный)</h3>
<div style="font-family:monospace; white-space:pre-wrap;">
<?php foreach ($catalog as $c): ?>
<?= htmlspecialchars((string)($c['label'] ?? ''), ENT_QUOTES) . "\n" ?>
<?php endforeach; ?>
</div>

<h3 style="margin-top:20px;">Поддомены, привязанные к сайту</h3>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
  <tr>
    <th>ID</th>
    <th>Label</th>
    <th>FQDN</th>
    <th>Enabled</th>
    <th>DNS</th>
    <th>SSL</th>
    <th>Last error</th>
    <th>Actions</th>
  </tr>

  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)($r['id'] ?? 0) ?></td>
      <td><?= htmlspecialchars((string)($r['label'] ?? ''), ENT_QUOTES) ?></td>
      <td><?= htmlspecialchars((string)($r['fqdn'] ?? ''), ENT_QUOTES) ?></td>
      <td><?= ((int)($r['enabled'] ?? 0) === 1) ? '✅' : '❌' ?></td>
      <td><?= htmlspecialchars((string)($r['dns_status'] ?? ''), ENT_QUOTES) ?></td>
      <td><?= htmlspecialchars((string)($r['ssl_status'] ?? ''), ENT_QUOTES) ?></td>
      <td style="max-width:520px; word-break:break-word;">
        <?= htmlspecialchars((string)($r['last_error'] ?? ''), ENT_QUOTES) ?>
      </td>
      <td style="white-space:nowrap;">
        <form method="post"
              action="/sites/subdomains/toggle?id=<?= $siteId ?>&sub_id=<?= (int)($r['id'] ?? 0) ?>"
              style="display:inline">
          <button type="submit">Toggle</button>
        </form>
        |
        <form method="post"
              action="/sites/subdomains/delete?id=<?= $siteId ?>&sub_id=<?= (int)($r['id'] ?? 0) ?>"
              style="display:inline"
              onsubmit="return confirm('Удалить поддомен?');">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
