<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteId = (int)($site['id'] ?? 0);
$ipValue = trim((string)($site['vps_ip'] ?? ''));

if ($ipValue === '' && !empty($servers)) {
    $sid = (int)($site['fastpanel_server_id'] ?? 0);
    foreach ($servers as $srv) {
        if ((int)($srv['id'] ?? 0) === $sid) {
            if (!empty($srv['ip'])) {
                $ipValue = (string)$srv['ip'];
            } elseif (!empty($srv['host'])) {
                $ipValue = (string)$srv['host'];
            }
            break;
        }
    }
}

$domainPurchaseStatus = (string)($site['domain_purchase_status'] ?? '');
$dnsStatus = (string)($site['dns_status'] ?? '');

$fmt = function($v) {
    return is_numeric($v) ? number_format((float)$v, 2, '.', '') : '—';
};

$decisionRu = function(string $d) {
    return match ($d) {
        'checked' => 'Домен доступен',
        'unavailable' => 'Домен занят',
        'too_expensive' => 'Домен доступен, но дороже лимита',
        'purchased_dns_configured' => 'Куплен и DNS применён',
        default => ($d !== '' ? $d : '—'),
    };
};
?>

<div class="page-head">
    <h1 class="page-title">Домен и DNS</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/edit?id=<?= $siteId ?>">Настройки сайта</a>
        <a class="btn btn-secondary" href="/sites/subdomains?id=<?= $siteId ?>">Поддомены</a>
    </div>
    <div class="page-subtitle">
        Сайт: <code><?= h($site['domain'] ?? '') ?></code>
    </div>
</div>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Текущее состояние</div>
    <div class="site-context__title"><?= h($site['domain'] ?? '') ?></div>
    <div class="site-context__meta">
        registrar_account_id:
        <?= !empty($site['registrar_account_id']) ? '<code>' . (int)$site['registrar_account_id'] . '</code>' : '—' ?>
        <br>
        registrar_contact_id:
        <?= !empty($site['registrar_contact_id']) ? '<code>' . (int)$site['registrar_contact_id'] . '</code>' : '—' ?>
        <br>
        vps_ip: <?= $ipValue !== '' ? '<code>' . h($ipValue) . '</code>' : '—' ?>
    </div>
</div>

<?php if (($domainPurchaseStatus === 'processing') || ($dnsStatus === 'processing')): ?>
    <div class="alert alert-info mt-16">
        Выполняется покупка домена или применение DNS. Страница обновится через 4 секунды.
    </div>
    <script>setTimeout(function(){ location.reload(); }, 4000);</script>
<?php endif; ?>

<?php if (!empty($pricingError)): ?>
    <div class="alert alert-danger mt-16">
        <b>Ошибка проверки домена:</b><br>
        <?= nl2br(h($pricingError)) ?>
        <?php if (!empty($lastDeployReportId)): ?>
            <div class="mt-12">
                <a href="/deploy/report?id=<?= (int)$lastDeployReportId ?>">Открыть отчёт deploy</a>
            </div>
        <?php endif; ?>
    </div>
<?php elseif (is_array($pricing)): ?>
    <?php
    $domain    = (string)($pricing['domain'] ?? '');
    $available = (bool)($pricing['available'] ?? false);
    $premium   = (bool)($pricing['premium'] ?? false);
    $decision  = (string)($pricing['decision'] ?? '');

    $regular = $pricing['regular_price'] ?? null;
    $your    = $pricing['your_price'] ?? null;
    $coupon  = $pricing['coupon_price'] ?? null;
    $promo   = trim((string)($pricing['promo_code'] ?? ''));
    $min     = $pricing['min_price'] ?? null;
    $max     = $pricing['max_price_usd'] ?? null;
    ?>

    <div class="panel-card mt-16 stack-gap-md">
        <h2 class="section-title">Результат проверки домена</h2>

        <div class="panel-grid panel-grid--2">
            <div class="stack-gap-sm">
                <div><b>Домен:</b> <code><?= h($domain) ?></code></div>
                <div><b>Доступен:</b> <?= $available ? '<span class="badge badge-success">да</span>' : '<span class="badge badge-danger">нет</span>' ?></div>
                <div><b>Премиум:</b> <?= $premium ? '<span class="badge badge-warning">да</span>' : '<span class="badge badge-muted">нет</span>' ?></div>
                <div><b>Результат:</b> <?= h($decisionRu($decision)) ?></div>
            </div>

            <div class="stack-gap-sm">
                <?php
                $finalPrice = null;
                foreach ([$coupon, $your, $min, $regular] as $candidatePrice) {
                    if (is_numeric($candidatePrice)) {
                        $finalPrice = (float)$candidatePrice;
                        break;
                    }
                }
                $isBelowLimit = is_numeric($max) && is_numeric($finalPrice) ? ((float)$finalPrice < (float)$max) : null;
                ?>
                <div><b>Финальная стоимость:</b> <span class="badge <?= $finalPrice !== null ? 'badge-success' : 'badge-muted' ?>"><?= $finalPrice !== null ? h($fmt($finalPrice)) . ' USD' : '—' ?></span></div>
                <div><b>Порог:</b> <?= $isBelowLimit === true ? '<span class="badge badge-success">ниже 7 USD</span>' : ($isBelowLimit === false ? '<span class="badge badge-warning">выше лимита</span>' : '—') ?></div>
                <?php if ($promo !== ''): ?><div><b>Промокод:</b> <?= h($promo) ?></div><?php endif; ?>
                <?php if ($coupon !== null && is_numeric($coupon)): ?><div class="small muted">Показана цена по купону.</div><?php elseif ($your !== null && is_numeric($your)): ?><div class="small muted">Показана цена аккаунта.</div><?php endif; ?>
            </div>
        </div>

        <?php if (!empty($lastDeployReportId)): ?>
            <div>
                <a href="/deploy/report?id=<?= (int)$lastDeployReportId ?>">Открыть отчёт deploy</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="panel-grid panel-grid--2 mt-16">
    <div class="panel-card stack-gap-md">
        <h2 class="section-title">1) Проверить доступность и цену</h2>

        <form method="post" action="/domains/check?id=<?= $siteId ?>" class="stack-gap-md">
            <div class="field-row">
                <label>Аккаунт регистратора</label>
                <select name="registrar_account_id" required>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= ((int)($site['registrar_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '') ?>>
                            #<?= (int)$a['id'] ?> namecheap <?= ((int)$a['is_sandbox'] === 1 ? 'sandbox' : 'prod') ?> — <?= h($a['api_user']) ?> — <?= h($a['api_user']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="vps_ip" value="<?= h($ipValue) ?>">

            <div class="page-actions">
                <button type="submit" class="btn btn-primary">Проверить домен и цену</button>
            </div>
        </form>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">2) Купить домен и применить DNS</h2>

        <form method="post" action="/domains/purchase-dns?id=<?= $siteId ?>" class="stack-gap-md">
            <div class="field-row">
                <label>Аккаунт регистратора</label>
                <select name="registrar_account_id" required>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= ((int)($site['registrar_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '') ?>>
                            #<?= (int)$a['id'] ?> namecheap <?= ((int)$a['is_sandbox'] === 1 ? 'sandbox' : 'prod') ?> — <?= h($a['api_user']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <label>Контактный профиль</label>
                <select name="registrar_contact_id" required>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ((int)($site['registrar_contact_id'] ?? 0) === (int)$c['id'] ? 'selected' : '') ?>>
                            #<?= (int)$c['id'] ?> <?= h($c['label']) ?> (<?= h($c['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <label>IP для VPS</label>

                <?php if (!empty($availableIps)): ?>
                    <select name="vps_ip" required>
                        <?php
                        $cur = trim((string)$ipValue);
                        $inList = in_array($cur, $availableIps, true);
                        if ($cur !== '' && filter_var($cur, FILTER_VALIDATE_IP) && !$inList) {
                            echo '<option value="' . h($cur) . '" selected>' . h($cur) . ' (current)</option>';
                        }
                        ?>
                        <?php foreach ($availableIps as $ip): ?>
                            <option value="<?= h($ip) ?>" <?= ($ip === $cur ? 'selected' : '') ?>>
                                <?= h($ip) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input name="vps_ip" value="<?= h($ipValue) ?>" placeholder="95.129.234.77" required>
                <?php endif; ?>
            </div>

            <div class="page-actions">
                <button type="submit" class="btn btn-primary">Купить домен и применить DNS</button>
            </div>
        </form>
    </div>
</div>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Текущие статусы</h2>

    <ul class="status-list small">
        <li><b>Статус домена:</b> <?= h($domainPurchaseStatus !== '' ? $domainPurchaseStatus : '—') ?></li>
        <li><b>Цена, USD:</b> <?= h((string)($site['domain_price_usd'] ?? '')) ?></li>
        <li><b>Статус DNS:</b> <?= h($dnsStatus !== '' ? $dnsStatus : '—') ?></li>
    </ul>


    <div class="page-actions">
        <form method="post" action="/domains/recheck-dns?id=<?= $siteId ?>">
            <button type="submit" class="btn btn-secondary">Перепроверить DNS и сбросить ошибку</button>
        </form>
    </div>

    <?php if (!empty($site['domain_purchase_error'])): ?>
        <div class="alert alert-danger">
            <b>Последняя ошибка домена:</b><br>
            <?= nl2br(h((string)$site['domain_purchase_error'])) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($site['dns_error'])): ?>
        <div class="alert alert-danger">
            <b>Последняя ошибка DNS:</b><br>
            <?= nl2br(h((string)$site['dns_error'])) ?>
        </div>
    <?php endif; ?>
</div>