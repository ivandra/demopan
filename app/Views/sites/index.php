<?php
// /app/Views/sites/index.php

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

function badge(string $text, string $bg, string $fg, string $border = ''): string {
    $b = $border !== '' ? "border:1px solid {$border};" : "border:1px solid transparent;";
    return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;'
        . 'font-size:12px;line-height:1.2;white-space:nowrap;'
        . "background:{$bg};color:{$fg};{$b}\">" . $text . '</span>';
}
?>

<h2>Сайты</h2>

<p>
    <a href="/sites/create">➕ Создать сайт</a>
</p>

<p>
  <a href="/servers">FASTPANEL серверы</a>
  | <a href="/subdomains">Поддомены (каталог)</a>
  | <a href="/ssl">SSL monitor (общий)</a>
  | <a href="/ssl/settings">TG settings</a>
</p>

<style>
  .sites-table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid #e6e6e6; border-radius:12px; overflow:hidden; }
  .sites-table th, .sites-table td { border-top:1px solid #eee; padding:10px 12px; vertical-align:top; }
  .sites-table thead th { border-top:none; background:#fafafa; font-weight:700; }
  .sites-table td small { display:block; color:#666; margin-top:4px; }
  .cell-nowrap { white-space:nowrap; }
  .ssl-cell { min-width:240px; }
  .ssl-line { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .ssl-note { margin-top:8px; padding:8px 10px; border-radius:10px; background:#fff8e1; border:1px solid #f2d27a; color:#6b4b00; font-size:12px; }
  .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  .actions a { white-space:nowrap; }
  .btn { padding:7px 10px; border-radius:10px; border:1px solid #ddd; background:#fff; cursor:pointer; }
  .btn-primary { background:#2f80ed; border-color:#2f80ed; color:#fff; }
  .btn-danger { background:#eb5757; border-color:#eb5757; color:#fff; }
</style>

<?php if (empty($sites)): ?>
    <p>Сайтов пока нет</p>
<?php else: ?>

<table class="sites-table">
    <thead>
      <tr>
          <th style="width:60px;">ID</th>
          <th>Домен</th>
          <th style="width:140px;">Шаблон</th>
          <th style="width:90px;">Статус</th>
          <th style="width:110px;">VPS</th>
          <th style="width:120px;">FTP</th>
          <th style="width:120px;">Файлы</th>
          <th class="ssl-cell">SSL (FP + Monitor)</th>
          <th style="width:520px;">Действия</th>
      </tr>
    </thead>

   <tbody>
   <?php foreach ($sites as $site): ?>
    <?php
      $siteId = (int)($site['id'] ?? 0);

      // ---------- FP status (как было) ----------
      $vpsOk     = ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0);
      $sslReady  = (int)($site['ssl_ready'] ?? 0) === 1;
      $sslHasCert= (int)($site['ssl_has_cert'] ?? 0) === 1;
      $sslCertId = (int)($site['ssl_cert_id'] ?? 0);
      $sslErr    = (string)($site['ssl_error'] ?? '');

      $fpState = '—';
      $fpBadge = badge('FP: —', '#f2f2f2', '#666');

      if (!$vpsOk) {
          $fpState = 'NO_VPS';
          $fpBadge = badge('FP: —', '#f2f2f2', '#666');
      } elseif ($sslErr !== '') {
          $fpState = 'ERROR';
          $fpBadge = badge('FP: Ошибка', '#ffecec', '#9b1c1c', '#ffb3b3');
      } elseif ($sslReady) {
          $fpState = 'READY';
          $fpBadge = badge('FP: Готов', '#e8fff1', '#0b6b3a', '#bfead0');
      } elseif ($sslHasCert) {
          $fpState = 'HAS_NOT_APPLIED';
          $fpBadge = badge('FP: Не применен', '#fff4e6', '#8a5a00', '#ffd7a8');
      } else {
          $fpState = 'NONE';
          $fpBadge = badge('FP: Нет', '#f2f2f2', '#666');
      }

      // ---------- Monitor summary (через ssl_checks по site_id) ----------
      $monCount = 0;
      $monOkCount = 0;
      $monLast = '';
      $monAllOk = null; // null => нет данных

      try {
          $pdo = DB::pdo();
          $st = $pdo->prepare("
              SELECT
                SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) AS cnt,
                SUM(CASE WHEN enabled=1 AND https_ok=1 THEN 1 ELSE 0 END) AS ok_cnt,
                MAX(updated_at) AS last_dt
              FROM ssl_checks
              WHERE site_id=?
          ");
          $st->execute([$siteId]);
          $agg = $st->fetch(PDO::FETCH_ASSOC) ?: [];

          $monCount = (int)($agg['cnt'] ?? 0);
          $monOkCount = (int)($agg['ok_cnt'] ?? 0);
          $monLast = (string)($agg['last_dt'] ?? '');

          if ($monCount > 0) {
              $monAllOk = ($monOkCount === $monCount);
          }
      } catch (Throwable $e) {
          $monAllOk = null;
      }

      if ($monAllOk === true) {
          $monBadge = badge('Monitor: ALL OK', '#e8fff1', '#0b6b3a', '#bfead0');
      } elseif ($monAllOk === false) {
          $monBadge = badge('Monitor: NOT OK', '#ffecec', '#9b1c1c', '#ffb3b3');
      } else {
          $monBadge = badge('Monitor: —', '#f2f2f2', '#666');
      }

      // ---------- Special highlight ----------
      $needWarn = ($fpState === 'HAS_NOT_APPLIED' && $monAllOk === true);
    ?>

    <tr>
        <td class="cell-nowrap"><?= $siteId ?></td>

        <td style="font-weight:700;"><?= h($site['domain'] ?? '') ?></td>
        <td><?= h($site['template'] ?? '') ?></td>
        <td><?= h($site['status'] ?? '') ?></td>

        <td class="cell-nowrap">
            <?php if ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0): ?>
                ✅ (#<?= (int)$site['fp_site_id'] ?>)
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>

        <td class="cell-nowrap">
            <?php if ((int)($site['fp_ftp_ready'] ?? 0) === 1): ?>
                ✅
                <?php if (!empty($site['fp_ftp_last_ok'])): ?>
                    <small><?= h(date('d.m H:i', strtotime((string)$site['fp_ftp_last_ok']))) ?></small>
                <?php endif; ?>
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>

        <td class="cell-nowrap">
            <?php if ((int)($site['fp_files_ready'] ?? 0) === 1): ?>
                ✅
                <?php if (!empty($site['fp_files_last_ok'])): ?>
                    <small><?= h(date('d.m H:i', strtotime((string)$site['fp_files_last_ok']))) ?></small>
                <?php endif; ?>
            <?php else: ?>
                ❌
            <?php endif; ?>
        </td>

        <td class="ssl-cell">
            <div class="ssl-line">
              <?= $fpBadge ?>
              <?php if ($sslCertId > 0 && $vpsOk): ?>
                <?= badge('#' . (int)$sslCertId, '#f7f7f7', '#555', '#ddd') ?>
              <?php endif; ?>
            </div>

            <div class="ssl-line" style="margin-top:6px;">
              <?= $monBadge ?>
              <?php if ($monCount > 0): ?>
                <?= badge((int)$monOkCount . '/' . (int)$monCount, '#f7f7f7', '#555', '#ddd') ?>
              <?php endif; ?>
              <?php if ($monLast !== ''): ?>
                <span style="color:#666;font-size:12px;white-space:nowrap;">
                  last: <?= h(date('Y-m-d H:i', strtotime($monLast))) ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($needWarn): ?>
              <div class="ssl-note">
                <b>Сертификат есть и работает, но не включен в FastPanel.</b><br>
                FP показывает “Не применен”, но по мониторингу все домены OK.
              </div>
            <?php endif; ?>

            <div style="margin-top:8px;">
              <a href="/ssl/site?id=<?= $siteId ?>" style="font-size:12px;">SSL Site</a>
            </div>
        </td>

        <td>
          <div class="actions">
            <form method="post"
                  action="/sites/build?id=<?= $siteId ?>"
                  style="display:inline"
                  onsubmit="return confirm('Запустить сборку и проверку?');">
              <button class="btn btn-primary" type="submit">Build</button>
            </form>

            <a href="/deploy?id=<?= $siteId ?>">Deploy</a>
            <a href="/domains?id=<?= $siteId ?>">Domains</a>
            <a href="/sites/subdomains?id=<?= $siteId ?>">Subs</a>
            <a href="/sites/subcfg?id=<?= $siteId ?>">SubCfg</a>
			<a href="/sites/clone?id=<?= (int)($site['id'] ?? 0) ?>">Клонировать</a>
            <a href="/sites/resetFastpanelState?id=<?= $siteId ?>"
               onclick="return confirm('Сбросить статусы VPS/FTP/Files?')">Reset</a>

            <a href="/sites/edit?id=<?= $siteId ?>">Редактировать</a>
            <a href="/sites/pages?id=<?= $siteId ?>">Pages</a>
            <a href="/sites/texts?id=<?= $siteId ?>">Texts</a>
            <a href="/sites/files?id=<?= $siteId ?>">Files</a>
            <a href="/webmaster/site?id=<?= $siteId ?>">Webmaster</a>

            <form method="post"
                  action="/ssl/check-now?id=<?= $siteId ?>"
                  style="display:inline"
                  onsubmit="return confirm('Принудительно проверить SSL сейчас для корня и enabled=1 поддоменов?');">
              <button class="btn" type="submit">Проверить SSL</button>
            </form>

            <?php if (!empty($site['build_path'])): ?>
              <a href="/sites/export?id=<?= $siteId ?>">ZIP</a>
            <?php endif; ?>

            <form method="post"
                  action="/sites/delete?id=<?= $siteId ?>"
                  style="display:inline"
                  onsubmit="return confirm('Удалить сайт #<?= $siteId ?>?');">
              <button class="btn btn-danger" type="submit">Удалить</button>
            </form>
          </div>
        </td>
    </tr>
<?php endforeach; ?>
   </tbody>
</table>

<?php endif; ?>