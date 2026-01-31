<?php
// app/Views/sites/create.php
// Ожидаемые переменные из контроллера:
// $templates (array)
//
// Может приходить одно из двух (поддерживаем оба варианта):
// A) Новый вариант:
//   $domain (string), $template (string), $checkResult (array)
// B) Старый вариант:
//   $form (array) или $_POST, $domainCheck (array), $domainCheckError (string), $suggestions (array)

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

function isUnsupportedTldError(string $msg): bool {
    $m = strtolower($msg);
    return (strpos($m, 'tld') !== false && strpos($m, 'not found') !== false)
        || (strpos($m, 'unsupported tld') !== false)
        || (strpos($m, 'tld for') !== false && strpos($m, 'is not found') !== false);
}

function decisionRu(string $decision): string {
    return match ($decision) {
        'checked'       => 'Домен доступен для регистрации',
        'unavailable'   => 'Домен занят (недоступен для регистрации)',
        'too_expensive' => 'Домен доступен, но дороже лимита',
        'error'         => 'Ошибка проверки',
        default         => ($decision !== '' ? $decision : '—'),
    };
}

function fmtUsd($v): string {
    return is_numeric($v) ? number_format((float)$v, 2, '.', '') : '—';
}

/**
 * Унифицируем данные проверки из разных источников:
 * - $checkResult (новый контроллер)
 * - или $domainCheck/$domainCheckError (старый)
 */
$check = null;          // итоговый массив проверки
$suggestions = $suggestions ?? null;

if (isset($checkResult) && is_array($checkResult)) {
    // Новый формат
    $check = $checkResult;
} else {
    // Старый формат
    $domainCheck = $domainCheck ?? null;
    $domainCheckError = (string)($domainCheckError ?? '');

    if ($domainCheckError !== '') {
        $check = ['error' => $domainCheckError];
    } elseif (is_array($domainCheck)) {
        $check = $domainCheck;
    }
}

// Значения формы
$formDomain = '';
$formTemplate = '';

if (isset($domain)) {
    $formDomain = (string)$domain;
} elseif (isset($form) && is_array($form)) {
    $formDomain = (string)($form['domain'] ?? '');
} else {
    $formDomain = (string)($_POST['domain'] ?? '');
}

if (isset($template)) {
    $formTemplate = (string)$template;
} elseif (isset($form) && is_array($form)) {
    $formTemplate = (string)($form['template'] ?? '');
} else {
    $formTemplate = (string)($_POST['template'] ?? '');
}

if ($formTemplate === '' && !empty($templates[0])) {
    $formTemplate = (string)$templates[0];
}
?>

<h2>Создать сайт</h2>

<?php if (is_array($check)): ?>
    <div style="border:1px solid #ddd;padding:10px;margin:10px 0;">
        <b>Проверка домена</b><br>

        <?php $err = (string)($check['error'] ?? ''); ?>

        <?php if ($err !== ''): ?>
            <?php if (isUnsupportedTldError($err)): ?>
                <div style="color:#b00;margin-top:6px;">
                    Ошибка: доменная зона не поддерживается у регистратора (Namecheap) или недоступна для покупки через API.
                </div>
                <div style="color:#666;margin-top:6px;">
                    Детали: <?= h($err) ?>
                </div>
                <div style="margin-top:6px;">
                    Попробуите другую зону: <b>.com</b>, <b>.net</b>, <b>.org</b>, <b>.site</b>, <b>.online</b> и т.д.
                </div>
            <?php else: ?>
                <div style="color:#b00;margin-top:6px;">
                    Ошибка: <?= h($err) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php
                // базовые поля
                $dcDomain    = (string)($check['domain'] ?? '');
                $dcAvailable = (bool)($check['available'] ?? false);
                $dcPremium   = (bool)($check['premium'] ?? false);
                $dcDecision  = (string)($check['decision'] ?? '');

                // цена (старое поле)
                $dcPrice = $check['price_usd'] ?? null;

                // доп. прайсинг (новые поля) — поддерживаем разные ключи:
                // - check['pricing'] (как я делал в deploy response)
                // - check['variants'] / check['prices'] / плоские поля
                $pricing = null;
                if (isset($check['pricing']) && is_array($check['pricing'])) {
                    $pricing = $check['pricing'];
                } elseif (isset($check['variants']) && is_array($check['variants'])) {
                    $pricing = $check['variants'];
                } elseif (isset($check['prices']) && is_array($check['prices'])) {
                    $pricing = $check['prices'];
                }

                // пробуем вытащить “разные цены” (retail/promo/special) если они есть
                $pPrice        = $pricing['price'] ?? ($check['price'] ?? null);
                $pRegular      = $pricing['regular_price'] ?? ($check['regular_price'] ?? null);
                $pYour         = $pricing['your_price'] ?? ($check['your_price'] ?? null);
                $pCoupon       = $pricing['coupon_price'] ?? ($check['coupon_price'] ?? null);
                $pPromoCode    = $pricing['promo_code'] ?? ($check['promo_code'] ?? null);
                $pCandidates   = $pricing['candidates'] ?? ($check['candidates'] ?? null);

                // если старое price_usd есть, оставляем его как “основную” цену отображения
                // иначе берем your/coupon/price
                if (!is_numeric($dcPrice)) {
                    $dcPrice = $pYour ?? $pCoupon ?? $pPrice ?? null;
                }
            ?>
		<?php
$pricing = $check['pricing'] ?? null;

$fmt = function($v): ?string {
    return is_numeric($v) ? number_format((float)$v, 2, '.', '') : null;
};

$regular = is_array($pricing) ? $fmt($pricing['regular_price'] ?? null) : null;
$your    = is_array($pricing) ? $fmt($pricing['your_price'] ?? null) : null;
$coupon  = is_array($pricing) ? $fmt($pricing['coupon_price'] ?? null) : null;
$promo   = is_array($pricing) ? trim((string)($pricing['promo_code'] ?? '')) : '';

$min = null;
foreach ([$coupon, $your, $regular] as $x) {
    if ($x === null) continue;
    $f = (float)$x;
    if ($min === null || $f < $min) $min = $f;
}
$minStr = $min !== null ? number_format($min, 2, '.', '') : null;
?>

<?php if (is_array($pricing)): ?>
    <div style="margin-top:8px; padding:8px 10px; border:1px solid #e5e5e5; background:#fafafa;">
        <div style="font-weight:bold; margin-bottom:6px;">Цены (Namecheap)</div>

        <div>Regular: <b><?= $regular ?? '—' ?></b></div>
        <div>Your: <b><?= $your ?? '—' ?></b></div>
        <div>Coupon: <b><?= $coupon ?? '—' ?></b></div>
        <div>Promo: <b><?= $promo !== '' ? h($promo) : '—' ?></b></div>

        <div style="margin-top:6px;">
            Минимальная цена: <b><?= $minStr ?? '—' ?></b> USD
        </div>
    </div>
<?php endif; ?>

            <div style="margin-top:6px;">
                <div><b>Домен:</b> <?= h($dcDomain) ?></div>
                <div><b>Доступен:</b> <?= $dcAvailable ? 'да' : 'нет' ?></div>
                <div><b>Премиум:</b> <?= $dcPremium ? 'да' : 'нет' ?></div>

                <div><b>Цена за 1 год (USD):</b> <?= h(fmtUsd($dcPrice)) ?></div>
                <div><b>Результат:</b> <?= h(decisionRu($dcDecision)) ?></div>

                <?php if (!$dcAvailable): ?>
                    <div style="margin-top:6px;color:#b00;">
                        Этот домен нельзя купить: он уже зарегистрирован или недоступен.
                    </div>
                <?php endif; ?>
            </div>   
        <?php endif; ?>

        
    </div>
<?php endif; ?>

<form method="post" action="/sites/create">
    <p>
        <label>Домен</label><br>
        <input
            type="text"
            name="domain"
            placeholder="example.com"
            required
            style="width:100%"
            value="<?= h($formDomain) ?>"
        >
        <small style="color:#666;">
            Можно вводить без https:// (например: testovoe.casino). Путь /... будет отброшен.
        </small>
    </p>
	
	<?php
		$selectedAccId = (int)($registrar_account_id ?? ($_POST['registrar_account_id'] ?? 0));
	?>
	<p>
	  <label>Registrar account</label><br>
	  <select name="registrar_account_id" required>
		<?php foreach (($accounts ?? []) as $a): ?>
		  <?php
			$id = (int)$a['id'];
			$isSandbox = (int)($a['is_sandbox'] ?? 1) === 1;
			$label = '#'.$id.' namecheap '.($isSandbox ? 'sandbox' : 'prod').' ('.($a['api_user'] ?? '').')';
		  ?>
		  <option value="<?= h($id) ?>" <?= ($selectedAccId === $id ? 'selected' : '') ?>>
			<?= h($label) ?>
		  </option>
		<?php endforeach; ?>
	  </select>
	</p>


    <p>
        <label>Шаблон</label><br>
        <select name="template" required>
            <?php foreach ($templates as $t): ?>
                <?php $t = (string)$t; ?>
                <option value="<?= h($t) ?>" <?= ($formTemplate === $t ? 'selected' : '') ?>>
                    <?= h($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
	
	

    <div style="margin-top:10px;">
        <button type="submit" formaction="/sites/create">Создать</button>
        <button type="submit" formaction="/sites/check-domain">Проверить домен и цену</button>
    </div>
</form>
