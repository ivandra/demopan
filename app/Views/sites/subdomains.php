<?php
$siteId = (int)($siteId ?? 0);
$site   = $site ?? [];
$catalog = $catalog ?? [];
$siteSubs = $siteSubs ?? [];
$registrarAccounts = $registrarAccounts ?? [];

$serverIps = $serverIps ?? []; // array of strings
$dnsA = $dnsA ?? [];           // array of strings

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$attachedMap = [];
$enabledMap  = [];
foreach ($siteSubs as $r) {
    $lb = (string)($r['label'] ?? '');
    $attachedMap[$lb] = true;
    $enVal = $r['enabled'] ?? ($r['is_enabled'] ?? 0); // совместимость
    $enabledMap[$lb] = ((int)$enVal === 1);
}

$currentVpsIp = (string)($site['vps_ip'] ?? '');
?>
<h1>Поддомены сайта: <?= h($site['domain'] ?? '') ?></h1>

<div style="margin: 10px 0;">
    <a href="/sites">← К списку сайтов</a>
</div>

<?php if (($site['template'] ?? '') !== 'template-multy'): ?>
    <div style="padding:10px;border:1px solid #f00;color:#900;">
        Этот сайт не template-multy. Сабы и папки subs/* применимы только к template-multy.
    </div>
<?php endif; ?>

<hr>

<div style="padding:10px;border:1px solid #ddd;background:#fafafa;margin:10px 0;">
    <div><b>IP сохранен в панели (sites.vps_ip):</b> <?= $currentVpsIp !== '' ? h($currentVpsIp) : '—' ?></div>
    <div><b>DNS A сейчас у домена:</b> <?= !empty($dnsA) ? h(implode(', ', $dnsA)) : '— (A не найден)' ?></div>
</div>

<hr>

<h2>1) Применить список поддоменов (создать/удалить)</h2>

<form method="post" action="/sites/subdomains/apply?id=<?= $siteId ?>">
    <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">

        <div style="min-width:320px;">
            <div style="margin-bottom:8px;">
                <button type="button" id="btnAll">Выбрать все</button>
                <button type="button" id="btnNone">Снять все</button>
            </div>

            <div style="max-height:260px;overflow:auto;border:1px solid #ddd;padding:10px;">
                <?php foreach ($catalog as $row): ?>
                    <?php
                    $lb = (string)($row['label'] ?? '');
                    $isActive = (int)($row['is_active'] ?? 0) === 1;
                    $attached = isset($attachedMap[$lb]); // привязан к сайту
                    $enabled  = $enabledMap[$lb] ?? false;
                    ?>
                    <label style="display:block;margin:4px 0;opacity:<?= $isActive ? '1' : '0.5' ?>">
                        <input class="lbChk" type="checkbox" name="labels[]" value="<?= h($lb) ?>" <?= $attached ? 'checked' : '' ?>>
                        <?= h($lb) ?>
                        <?= $isActive ? '' : ' (неактивен)' ?>
                        <?= $attached && !$enabled ? ' (выключен)' : '' ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:10px;">
                <label>
                    <input type="checkbox" name="apply_dns" value="1">
                    Сразу применить DNS (A) после сохранения
                </label>
                <div style="color:#666;font-size:13px;margin-top:4px;">
                    Если IP не задан, панель попробует взять его из DNS A или с сервера.
                </div>
            </div>

            <div style="margin-top:10px;">
                <button type="submit">Применить выбранные</button>
            </div>
        </div>

        <div style="min-width:360px;">
            <div style="margin-bottom:6px;">Быстро добавить вручную (через запятую/пробел):</div>
            <textarea name="labels_text" rows="8" style="width:360px;"></textarea>

            <div style="margin-top:10px;color:#666;font-size:13px;">
                applyBatch = "привести список к выбранному": добавит недостающие и удалит лишние (кроме _default).
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('btnAll').addEventListener('click', function(){
  document.querySelectorAll('.lbChk').forEach(ch => ch.checked = true);
});
document.getElementById('btnNone').addEventListener('click', function(){
  document.querySelectorAll('.lbChk').forEach(ch => ch.checked = false);
});
</script>

<hr>

<h2>2) Текущие поддомены сайта</h2>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>label</th>
        <th>enabled</th>
        <th>действия</th>
    </tr>
    <?php foreach ($siteSubs as $r): ?>
        <?php
        $lb = (string)($r['label'] ?? '');
        $enVal = $r['enabled'] ?? ($r['is_enabled'] ?? 0);
        $en = (int)$enVal === 1;
        ?>
        <tr>
            <td><?= h($lb) ?></td>
            <td><?= $en ? '1' : '0' ?></td>
            <td>
                <form method="post" action="/sites/subdomains/toggle?id=<?= $siteId ?>" style="display:inline;">
                    <input type="hidden" name="label" value="<?= h($lb) ?>">
                    <button type="submit">Toggle</button>
                </form>

                <?php if ($lb !== '_default'): ?>
                    <form method="post" action="/sites/subdomains/delete?id=<?= $siteId ?>" style="display:inline;margin-left:6px;">
                        <input type="hidden" name="label" value="<?= h($lb) ?>">
                        <button type="submit" onclick="return confirm('Удалить поддомен <?= h($lb) ?>?')">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<div style="margin-top:10px;">
    <form method="post" action="/sites/subdomains/delete-catalog?id=<?= $siteId ?>" style="display:inline;">
        <button type="submit" onclick="return confirm('Удалить все сабы (кроме _default) и их папки?')">
            Удалить все сабы (кроме _default)
        </button>
    </form>
</div>

<hr>

<h2>3) Registrar + DNS (Namecheap)</h2>

<form method="post" action="/sites/subdomains/set-registrar?id=<?= $siteId ?>" style="margin-bottom:10px;">
    <label>Аккаунт Namecheap:
        <select name="registrar_account_id">
            <?php foreach ($registrarAccounts as $a): ?>
                <?php
                $id = (int)$a['id'];
                $sel = ((int)($site['registrar_account_id'] ?? 0) === $id) ? 'selected' : '';
                ?>
                <option value="<?= $id ?>" <?= $sel ?>>
                    #<?= $id ?> <?= h($a['title'] ?? '') ?><?= ((int)($a['is_default'] ?? 0) === 1 ? ' (default)' : '') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Сохранить</button>
</form>

<form method="post" action="/sites/subdomains/detect-registrar?id=<?= $siteId ?>" style="margin-bottom:10px;">
    <button type="submit">Авто-определить аккаунт по домену</button>
</form>

<form method="post" action="/sites/subdomains/update-ip?id=<?= $siteId ?>" style="margin-bottom:10px;">
    <div style="margin-bottom:6px;">
        IP для A-записей:

        <?php if (!empty($serverIps)): ?>
            <select id="ipSelect" style="width:220px;">
                <option value="">— выбрать из сервера —</option>
                <?php foreach ($serverIps as $ip): ?>
                    <option value="<?= h($ip) ?>" <?= ($currentVpsIp === $ip ? 'selected' : '') ?>><?= h($ip) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <input id="ipInput" type="text" name="ip"
               placeholder="например 1.2.3.4 (можно пусто, тогда попытаемся взять из DNS/сервера)"
               style="width:360px;"
               value="<?= h($currentVpsIp) ?>">
    </div>

    <label>
        <input type="checkbox" name="update_root" value="1">
        Также обновить корневой A (@)
    </label>

    <div style="margin-top:10px;">
        <button type="submit">Применить DNS (A) для enabled сабов</button>
    </div>
</form>

<?php if (!empty($serverIps)): ?>
<script>
const sel = document.getElementById('ipSelect');
const inp = document.getElementById('ipInput');
if (sel && inp) {
  sel.addEventListener('change', () => {
    if (sel.value) inp.value = sel.value;
  });
}
</script>
<?php endif; ?>

<form method="post" action="/sites/subdomains/delete-catalog-dns?id=<?= $siteId ?>">
    <button type="submit" onclick="return confirm('Удалить DNS записи для всех сабов этого сайта?')">
        Удалить DNS для сабов (без удаления папок)
    </button>
</form>
