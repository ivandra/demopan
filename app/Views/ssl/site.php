<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$domain = (string)($site['domain'] ?? '');
?>
<h2>SSL: сайт #<?= (int)$siteId ?> — <?= h($domain) ?></h2>

<p>
  <a href="/sites">← К сайтам</a>
  | <a href="/ssl">Общий список</a>
</p>

<div style="display:flex;gap:14px;align-items:center;margin:14px 0;">
  <?php if (!empty($allOk)): ?>
    <span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#e8fff1;color:#0b6b3a;font-weight:600;">ALL OK</span>
  <?php else: ?>
    <span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#ffecec;color:#9b1c1c;font-weight:600;">NOT OK</span>
  <?php endif; ?>

  <div style="color:#666">
    last check: <b><?= h($lastUpdated ?: '—') ?></b>
  </div>

  <div style="color:#666">
    cron: <?php if (!empty($cronAlive)): ?>
      <b style="color:#0b6b3a">alive</b>
    <?php else: ?>
      <b style="color:#9b1c1c">stale</b>
    <?php endif; ?>
    <?php if (!empty($cronLast)): ?>
      <span style="opacity:.8">(last: <?= h($cronLast) ?>)</span>
    <?php endif; ?>
  </div>

  <form method="post" action="/ssl/site/check-now?id=<?= (int)$siteId ?>" style="margin-left:auto;"
        onsubmit="return confirm('Принудительно проверить SSL/HTTP сейчас?');">
    <button type="submit" style="padding:10px 14px;border-radius:8px;border:0;background:#2f80ed;color:#fff;font-weight:600;">
      Принудительно проверить сейчас
    </button>
  </form>
</div>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <tr>
    <th style="text-align:left;width:160px;">Label</th>
    <th style="text-align:left;">Domain</th>
    <th style="text-align:left;width:90px;">Enabled</th>
    <th style="text-align:left;width:80px;">HTTP</th>
    <th style="text-align:left;width:80px;">SSL</th>
    <th style="text-align:left;">Details</th>
    <th style="text-align:left;width:170px;">Updated</th>
  </tr>

  <?php foreach (($rows ?? []) as $x): ?>
    <?php
      $label = (string)($x['label'] ?? '');
      $fqdn  = (string)($x['fqdn'] ?? '');
      $r     = $x['row'] ?? null;

      $enabled = !empty($x['enabled']);
      $httpsOk = !empty($x['https_ok']);
      $http    = (int)($x['http_code'] ?? 0);
      $upd     = (string)($x['updated_at'] ?? '');

      $expires = $r ? (string)($r['ssl_expires_at'] ?? '') : '';
      $issuer  = $r ? (string)($r['ssl_issuer'] ?? '') : '';
      $subject = $r ? (string)($r['ssl_subject'] ?? '') : '';
      $err     = $r ? (string)($r['ssl_error'] ?? '') : '';
    ?>
    <tr>
      <td><?= h($label === '' ? '(root)' : $label) ?></td>
      <td style="font-weight:600;"><?= h($fqdn) ?></td>

      <td>
        <?php if ($enabled): ?>
          <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#e8fff1;color:#0b6b3a;font-size:12px;">ON</span>
        <?php else: ?>
          <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#f2f2f2;color:#666;font-size:12px;">OFF</span>
        <?php endif; ?>
      </td>

      <td><?= $http > 0 ? (int)$http : '—' ?></td>

      <td>
        <?php if ($httpsOk): ?>
          <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#e8fff1;color:#0b6b3a;font-size:12px;">OK</span>
        <?php else: ?>
          <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#ffecec;color:#9b1c1c;font-size:12px;">FAIL</span>
        <?php endif; ?>
      </td>

      <td style="color:#555;font-size:12px;">
        <?php if ($httpsOk): ?>
          <?php if ($expires !== ''): ?>expires: <b><?= h($expires) ?></b><br><?php endif; ?>
          <?php if ($issuer !== ''): ?>issuer: <?= h($issuer) ?><br><?php endif; ?>
          <?php if ($subject !== ''): ?>subject: <?= h($subject) ?><?php endif; ?>
        <?php else: ?>
          <?= $err !== '' ? '<span style="color:#b00">'.h($err).'</span>' : '—' ?>
        <?php endif; ?>
      </td>

      <td style="color:#666;"><?= h($upd ?: '—') ?></td>
    </tr>
  <?php endforeach; ?>
</table>