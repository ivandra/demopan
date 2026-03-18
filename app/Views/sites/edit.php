<?php
$site = is_array($site ?? null) ? $site : [];
$cfg  = is_array($cfg ?? null) ? $cfg : [];
$configTargetPath = (string)($configTargetPath ?? '');

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$siteId = (int)($site['id'] ?? 0);
$registrarAccounts = is_array($registrarAccounts ?? null) ? $registrarAccounts : [];
$currentAccId = (int)($site['registrar_account_id'] ?? 0);
$partnerSubId = (string)($partnerSubId ?? '');

$configFileForFiles = 'config.default.php';
?>

<div class="page-head">
    <h1 class="page-title">Настройки сайта</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/domains?id=<?= $siteId ?>">Домен и DNS</a>
            </div>
    <div class="page-subtitle">
        Сайт #<?= $siteId ?> — <code><?= h($site['domain'] ?? '') ?></code>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Служебная информация</div>
    <div class="site-context__title"><?= h($site['domain'] ?? '') ?></div>
    <div class="site-context__meta">
        config генерируется в: <code><?= h($configTargetPath) ?></code>
        |
        <a href="/sites/files/edit?id=<?= $siteId ?>&file=<?= rawurlencode($configFileForFiles) ?>">Открыть config в Files</a>
    </div>
</div>

<form method="post" action="/sites/edit?id=<?= $siteId ?>" class="mt-16 stack-gap-lg">
    <div class="panel-grid panel-grid--2">
        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Основное</h2>

            <div class="field-row">
                <label>Домен</label>
                <input name="domain" value="<?= h($cfg['domain'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>Promo link</label>
                <input name="promolink" value="<?= h($cfg['promolink'] ?? '/play') ?>">
            </div>

            <div class="field-row">
                <label>Namecheap аккаунт для DNS</label>
                <select name="registrar_account_id">
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
                            <?= h($text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Сервисы</h2>

            <div class="field-row">
                <label>Yandex verification</label>
                <input name="yandex_verification" value="<?= h($cfg['yandex_verification'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>Yandex metrika</label>
                <input name="yandex_metrika" value="<?= h($cfg['yandex_metrika'] ?? '') ?>">
            </div>
        </div>

        <div class="panel-card stack-gap-md">
            <h2 class="section-title">Redirect / партнёрка</h2>

            <div class="field-row">
                <label>Redirect enabled</label>
                <select name="redirect_enabled">
                    <option value="0" <?= ((int)($cfg['redirect_enabled'] ?? 0) === 0) ? 'selected' : '' ?>>0 — выключен</option>
                    <option value="1" <?= ((int)($cfg['redirect_enabled'] ?? 0) === 1) ? 'selected' : '' ?>>1 — включён</option>
                </select>
                <div class="small muted">Для основного домена переключается вручную здесь. Авто-включение по индексу отображается и запускается только в разделе «Вебмастер».</div>
            </div>

            <div class="field-row">
                <label>Сгенерированный sub_id</label>
                <input value="<?= h($partnerSubId) ?>" readonly>
                <div class="small muted">Для root используется корень домена до точки. При сохранении этот sub_id будет автоматически подставлен в partner_override_url, internal_reg_url, base_new_url и base_second_url, а затем разнесён по конфигам всех поддоменов сайта с их собственными значениями.</div>
            </div>

            <div class="field-row">
                <label>partner_override_url</label>
                <input name="partner_override_url" value="<?= h($cfg['partner_override_url'] ?? '') ?>">
                <div class="small muted">Если ссылка внешняя, панель добавит или заменит параметр <code>sub_id=<?= h($partnerSubId) ?></code>.</div>
            </div>

            <div class="field-row">
                <label>internal_reg_url</label>
                <input name="internal_reg_url" value="<?= h($cfg['internal_reg_url'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>base_new_url</label>
                <input name="base_new_url" value="<?= h($cfg['base_new_url'] ?? '') ?>">
            </div>

            <div class="field-row">
                <label>base_second_url</label>
                <input name="base_second_url" value="<?= h($cfg['base_second_url'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="page-actions">
        <button type="submit" class="btn btn-primary">Сохранить и пересобрать config</button>
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Назад к обзору</a>
    </div>
</form>