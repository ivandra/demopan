<?php
$site = is_array($site ?? null) ? $site : [];
$cfg  = is_array($cfg ?? null) ? $cfg : [];
$configTargetPath = (string)($configTargetPath ?? '');
?>


<h2>Редактирование: <?= htmlspecialchars($site['domain'] ?? '') ?></h2>
<p style="font-size:13px;opacity:.85;">
    config.php генерируется в: <code><?= htmlspecialchars($configTargetPath) ?></code>
    | <a href="/sites/files/edit?id=<?= (int)$site['id'] ?>&file=config.php">открыть в Files</a>
</p>

<p>
    <a href="/">← назад</a> |
    <a href="/sites/pages?id=<?= (int)$site['id'] ?>">Pages</a>
	<a href="/sites/texts?id=<?= (int)$site['id'] ?>">Texts</a>
	<a href="/sites/files?id=<?= (int)$site['id'] ?>">Files</a>
</p>

<form method="post" action="/sites/edit?id=<?= (int)$site['id'] ?>">
    <h3>Основное</h3>
    <p>
        <label>Домен</label><br>
        <input style="width:100%" name="domain" value="<?= htmlspecialchars($cfg['domain'] ?? '') ?>">
    </p>
	<?php
$registrarAccounts = is_array($registrarAccounts ?? null) ? $registrarAccounts : [];
$currentAccId = (int)($site['registrar_account_id'] ?? 0);
?>

<h3>Домен / регистратор</h3>

<p>
    <label>Namecheap аккаунт для DNS (обязательно тот, где лежит домен)</label><br>
    <select name="registrar_account_id" style="width:100%">
        <option value="">— не выбран —</option>

        <?php foreach ($registrarAccounts as $a): ?>
            <?php
                $id = (int)($a['id'] ?? 0);
                $isSandbox = ((int)($a['is_sandbox'] ?? 0) === 1);

                $text =
                    (string)($a['username'] ?? '') .
                    ' / ' . (string)($a['api_user'] ?? '') .
                    ' — ' . ($isSandbox ? 'SANDBOX' : 'PROD') .
                    (!empty($a['client_ip']) ? ' (IP ' . (string)$a['client_ip'] . ')' : '') .
                    (((int)($a['is_default'] ?? 0) === 1) ? ' [default]' : '');

                $sel = ($id === $currentAccId) ? 'selected' : '';
            ?>
            <option value="<?= $id ?>" <?= $sel ?>>
                <?= htmlspecialchars($text, ENT_QUOTES) ?>
            </option>
        <?php endforeach; ?>
    </select>
</p>

    <p>
        <label>Promo link</label><br>
        <input style="width:100%" name="promolink" value="<?= htmlspecialchars($cfg['promolink'] ?? '/play') ?>">
    </p>

    <h3>Сервисы</h3>
    <p>
        <label>Yandex verification</label><br>
        <input style="width:100%" name="yandex_verification" value="<?= htmlspecialchars($cfg['yandex_verification'] ?? '') ?>">
    </p>
    <p>
        <label>Yandex metrika</label><br>
        <input style="width:100%" name="yandex_metrika" value="<?= htmlspecialchars($cfg['yandex_metrika'] ?? '') ?>">
    </p>

    <h3>SEO</h3>
    <p><label>Title</label><br><input style="width:100%" name="title" value="<?= htmlspecialchars($cfg['title'] ?? '') ?>"></p>
    <p><label>Description</label><br><textarea style="width:100%;height:120px" name="description"><?= htmlspecialchars($cfg['description'] ?? '') ?></textarea></p>
    <p><label>Keywords</label><br><input style="width:100%" name="keywords" value="<?= htmlspecialchars($cfg['keywords'] ?? '') ?>"></p>
    <p><label>H1</label><br><input style="width:100%" name="h1" value="<?= htmlspecialchars($cfg['h1'] ?? '') ?>"></p>

    <h3>Redirect / партнерка</h3>
    <p>
        <label>Redirect enabled (0/1)</label><br>
        <input name="redirect_enabled" value="<?= (int)($cfg['redirect_enabled'] ?? 0) ?>">
    </p>
    <p><label>partner_override_url</label><br><input style="width:100%" name="partner_override_url" value="<?= htmlspecialchars($cfg['partner_override_url'] ?? '') ?>"></p>
    <p><label>internal_reg_url</label><br><input style="width:100%" name="internal_reg_url" value="<?= htmlspecialchars($cfg['internal_reg_url'] ?? '') ?>"></p>
    <p><label>base_new_url</label><br><input style="width:100%" name="base_new_url" value="<?= htmlspecialchars($cfg['base_new_url'] ?? '') ?>"></p>
    <p><label>base_second_url</label><br><input style="width:100%" name="base_second_url" value="<?= htmlspecialchars($cfg['base_second_url'] ?? '') ?>"></p>

    <button type="submit">Сохранить и перегенерировать config.php</button>
</form>

<hr>
