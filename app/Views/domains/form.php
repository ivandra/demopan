<?php



function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$ipValue = trim((string)($site['vps_ip'] ?? ''));

// если ip не сохранен в sites, пробуем взять из fastpanel_servers по fastpanel_server_id
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

$cardStyle = "border:1px solid #e5e5e5;border-radius:8px;padding:12px;margin:12px 0;background:#fff;";
$muted = "color:#666;";
?>

<h2>Domains: <?= h($site['domain']) ?></h2>

<?php if (!empty($pricingError)): ?>
    <div style="<?= $cardStyle ?>">
        <b>Проверка домена</b><br>
        <div style="margin-top:6px;color:#b00;">
            Ошибка: <?= h($pricingError) ?>
        </div>

        <?php if (!empty($lastDeployReportId)): ?>
            <div style="margin-top:8px;">
                <a href="/deploy/report?id=<?= (int)$lastDeployReportId ?>">Открыть deploy report</a>
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

        $min = $pricing['min_price'] ?? null;

        $max = $pricing['max_price_usd'] ?? null;

        $fmt = function($v) {
            return is_numeric($v) ? number_format((float)$v, 2, '.', '') : '—';
        };

        $decisionRu = function(string $d) {
            return match ($d) {
                'checked' => 'Домен доступен',
                'unavailable' => 'Домен занят',
                'too_expensive' => 'Домен доступен, но дороже лимита',
                'purchased_dns_configured' => 'Куплен и DNS применен',
                default => ($d !== '' ? $d : '—'),
            };
        };
    ?>
    <div style="<?= $cardStyle ?>">
        <b>Проверка домена</b><br>

        <div style="margin-top:8px;">
            <div><b>Домен:</b> <?= h($domain) ?></div>
            <div><b>Доступен:</b> <?= $available ? 'да' : 'нет' ?></div>
            <div><b>Премиум:</b> <?= $premium ? 'да' : 'нет' ?></div>
        </div>

        <div style="margin-top:10px;">
            <b>Цены (USD, 1 год)</b><br>
            <span style="<?= $muted ?>">Regular:</span> <?= h($fmt($regular)) ?><br>
            <span style="<?= $muted ?>">Your:</span> <?= h($fmt($your)) ?><br>
            <span style="<?= $muted ?>">Coupon:</span> <?= h($fmt($coupon)) ?><br>
            <span style="<?= $muted ?>">Promo:</span> <?= $promo !== '' ? h($promo) : '—' ?><br>

            <div style="margin-top:8px;">
                <b>Минимальная:</b> <?= h($fmt($min)) ?>
                <?php if (is_numeric($max)): ?>
                    <span style="<?= $muted ?>">&nbsp;(лимит: <?= h($fmt($max)) ?>)</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top:10px;">
            <b>Результат:</b> <?= h($decisionRu($decision)) ?>
        </div>

        <?php if (!empty($lastDeployReportId)): ?>
            <div style="margin-top:10px;">
                <a href="/deploy/report?id=<?= (int)$lastDeployReportId ?>">Открыть deploy report</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" action="/domains/check?id=<?= (int)$site['id'] ?>">
    <label>Registrar account:</label>
    <select name="registrar_account_id" required>
        <?php foreach ($accounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= ((int)($site['registrar_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '') ?>>
                #<?= (int)$a['id'] ?> namecheap <?= ((int)$a['is_sandbox']===1 ? 'sandbox' : 'prod') ?> (<?= h($a['api_user']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <!-- чтобы vps_ip сохранялся и после Check -->
    <input type="hidden" name="vps_ip" value="<?= h($ipValue) ?>">

    <button type="submit">Check availability + price</button>
</form>

<hr>

<form method="post" action="/domains/purchase-dns?id=<?= (int)$site['id'] ?>">
    <label>Registrar account:</label>
    <select name="registrar_account_id" required>
        <?php foreach ($accounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= ((int)($site['registrar_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : '') ?>>
                #<?= (int)$a['id'] ?> namecheap <?= ((int)$a['is_sandbox']===1 ? 'sandbox' : 'prod') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Contact profile:</label>
    <select name="registrar_contact_id" required>
        <?php foreach ($contacts as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($site['registrar_contact_id'] ?? 0) === (int)$c['id'] ? 'selected' : '') ?>>
                #<?= (int)$c['id'] ?> <?= h($c['label']) ?> (<?= h($c['email']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

   <label>VPS IP:</label>

<?php if (!empty($availableIps)): ?>
    <select name="vps_ip" required>
        <?php
            // если текущий ipValue не в списке — добавим его первой опцией
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
    <input name="vps_ip"
           value="<?= h($ipValue) ?>"
           placeholder="95.129.234.77"
           required>
<?php endif; ?>


    <button type="submit">Purchase domain + apply DNS</button>
</form>

<hr>
<?php if (($site['domain_purchase_status'] ?? '') === 'processing' || ($site['dns_status'] ?? '') === 'processing'): ?>
  <div style="margin-top:10px;color:#666;">
    Выполняется покупка/применение DNS... обновлю страницу через 4 секунды.
  </div>
  <script>setTimeout(() => location.reload(), 4000);</script>
<?php endif; ?>

<div style="<?= $muted ?>">
    <b>Domain status:</b> <?= h((string)($site['domain_purchase_status'] ?? '')) ?><br>
    <b>Price USD:</b> <?= h((string)($site['domain_price_usd'] ?? '')) ?><br>
    <b>DNS status:</b> <?= h((string)($site['dns_status'] ?? '')) ?><br>
    <b>Last domain error:</b> <?= nl2br(h((string)($site['domain_purchase_error'] ?? ''))) ?><br>
    <b>Last dns error:</b> <?= nl2br(h((string)($site['dns_error'] ?? ''))) ?><br>
</div>
