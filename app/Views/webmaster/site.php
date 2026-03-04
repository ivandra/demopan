<?php 
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$log = $_SESSION['wm_log'] ?? [];
unset($_SESSION['wm_log']);

$siteId = (int)($site['id'] ?? 0);

// helper: unique labels list from $desired
$labels = ['' => '(root)'];
$seen = [];
foreach (($desired ?? []) as $hrow) {
    $lbl = (string)($hrow['label'] ?? '');
    if ($lbl === '') continue;
    if (isset($seen[$lbl])) continue;
    $seen[$lbl] = true;
    $labels[$lbl] = $lbl;
}
?>
<h2>Webmaster: сайт #<?= $siteId ?> — <?= h($site['domain'] ?? '') ?></h2>

<p><a href="/webmaster">← Назад</a></p>

<?php if (!empty($log)): ?>
  <pre style="background:#111;color:#0f0;padding:12px;border-radius:8px;white-space:pre-wrap;"><?= h(implode("\n", $log)) ?></pre>
<?php endif; ?>

<form method="post" action="/webmaster/sync?id=<?= $siteId ?>" style="display:inline"
      onsubmit="return confirm('Синхронизировать хосты + получить HTML verify + записать файлы в build?');">
  <button type="submit">1) Синхронизировать + получить HTML verify + записать файлы</button>
</form>

<form method="post" action="/webmaster/verify?id=<?= $siteId ?>" style="display:inline;margin-left:10px"
      onsubmit="return confirm('Проверить верификацию в Яндексе (verifyHost)? Перед этим файлы должны быть задеплоены на домены.');">
  <button type="submit">2) Проверить верификацию в Яндексе (verifyHost)</button>
</form>

<p style="margin-top:12px;color:#555">
  Важно: после шага 1 файлы окажутся в build папке сайта. Чтобы Яндекс реально увидел их по URL — сделай Deploy → update-files (или твой обычный пайплайн выкладки).
</p>

<hr>

<h3>Recrawl</h3>

<form method="post" action="/webmaster/recrawl?id=<?= $siteId ?>" id="recrawlForm" style="border:1px solid #ddd;padding:12px;">
  <div style="margin-bottom:8px;">
    <label><b>Host label:</b></label>

    <select name="label" id="recrawlLabel">
      <option value="ALL">ALL (все хосты)</option>
      <?php foreach ($labels as $val => $title): ?>
        <option value="<?= h($val) ?>"><?= h($title) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="button" onclick="fillFromPages()">Вставить из pages</button>

    <button type="submit" onclick="return confirm('Отправить recrawl на выбранный label?')">
      Отправить recrawl
    </button>

    <button type="button" style="margin-left:10px" onclick="submitRecrawlFromPagesAll()">
      Массово: recrawl from pages (ALL hosts)
    </button>
  </div>

  <textarea name="urls" id="recrawlUrls" rows="8" style="width:100%;font-family:monospace;"
            placeholder="/&#10;/new&#10;/404"></textarea>

  <div style="margin-top:6px;color:#666">
    Поддерживаются относительные (/path) и абсолютные https://... URL.
  </div>
</form>

<hr>

<h3>Sitemap</h3>

<form method="post" action="/webmaster/sitemap/add?id=<?= $siteId ?>" style="border:1px solid #ddd;padding:12px;margin-bottom:14px;">
  <div style="margin-bottom:8px;">
    <label><b>Host label:</b></label>
    <select name="label" id="sitemapLabel">
      <option value="ALL">ALL (все хосты)</option>
      <?php foreach ($labels as $val => $title): ?>
        <option value="<?= h($val) ?>"><?= h($title) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" onclick="return confirm('Добавить sitemap.xml для выбранного label?')">Add sitemap.xml</button>

    <button type="button" style="margin-left:10px" onclick="submitSitemapGet()">
      Get sitemaps
    </button>
  </div>

  <div style="color:#666">
    По умолчанию отправляем <code>https://HOST/sitemap.xml</code>. Если у тебя другой путь — сделаем override (ниже).
  </div>

  <div style="margin-top:8px;">
    <label><b>Override sitemap URL (необязательно):</b></label>
    <input type="text" name="sitemap_url" id="sitemapUrlInput" style="width:100%;font-family:monospace;"
           placeholder="https://example.com/sitemap.xml">
    <div style="margin-top:6px;color:#999">
      Если поле пустое — будет использован <code>host_url + /sitemap.xml</code>.
    </div>
  </div>
</form>

<hr>

<h3>Robots</h3>

<form method="post" action="/webmaster/robots/confirm?id=<?= $siteId ?>" style="border:1px solid #ddd;padding:12px;">
  <div style="margin-bottom:8px;">
    <label><b>Host label:</b></label>
    <select name="label" id="robotsLabel">
      <option value="ALL">ALL (все хосты)</option>
      <?php foreach ($labels as $val => $title): ?>
        <option value="<?= h($val) ?>"><?= h($title) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" onclick="return confirm('Подтвердить robots.txt для выбранного label?')">Confirm robots.txt</button>

    <button type="button" style="margin-left:10px" onclick="submitRobotsGet()">
      Get robots
    </button>
  </div>

  <div style="color:#666">
    Confirm robots — это подтверждение текущего <code>https://HOST/robots.txt</code> (тело запроса не требуется).
  </div>

  <div style="margin-top:8px;">
    <label><b>Override robots URL (необязательно, только для записи в БД):</b></label>
    <input type="text" name="robots_url" id="robotsUrlInput" style="width:100%;font-family:monospace;"
           placeholder="https://example.com/robots.txt">
    <div style="margin-top:6px;color:#999">
      Если поле пустое — в БД будет сохранен <code>host_url + /robots.txt</code>.
    </div>
  </div>
</form>

<hr>

<style>
  .wm-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;line-height:1;border:1px solid transparent;white-space:nowrap}
  .wm-ok{background:#e8fff1;border-color:#23b26b;color:#0b6b3a}
  .wm-warn{background:#fff8e6;border-color:#f2c94c;color:#8a5a00}
  .wm-bad{background:#ffecec;border-color:#eb5757;color:#9b1c1c}
  .wm-na{background:#f4f4f4;border-color:#d0d0d0;color:#666}

  /* === FIX: таблица не вмещается === */
  .wm-table-wrap{
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
    border:1px solid #ddd;
    border-radius:8px;
  }
  table.wm-table{
    border-collapse:collapse;
    width:100%;
    min-width: 1300px; /* чтобы не превращалось в кашу */
  }
  .wm-table td, .wm-table th{
    vertical-align:top;
    white-space:normal;
    word-break:break-word;
  }
  .wm-small{
    color:#777;
    font-size:12px;
    margin-top:4px;
    line-height:1.35;
    word-break:break-word;
  }
</style>

<h3>Хосты (статусы по каждому поддомену)</h3>

<div class="wm-table-wrap">
  <table class="wm-table" border="1" cellpadding="8" cellspacing="0">
    <tr>
      <th>Метка</th>
      <th>Хост</th>
      <th>Host ID</th>
      <th>Verify файл</th>
      <th>Файл записан</th>
      <th>Верифицирован</th>
      <th>Robots добавлен</th>
      <th>Sitemap добавлен</th>
      <th>Страницы добавлены</th>
      <th>Последняя синхронизация</th>
    </tr>

    <?php foreach ($desired as $hrow): ?>
      <?php
        $label   = (string)($hrow['label'] ?? '');
        $hostUrl = (string)($hrow['host_url'] ?? '');
        $r       = $rowMap[$label] ?? null;

        $fileWritten = $r ? (int)($r['file_written'] ?? 0) : 0;

        $verifiedAt = $r ? (string)($r['verified_at'] ?? '') : '';
        $robotsAt   = $r ? (string)($r['robots_confirmed_at'] ?? '') : '';
        $robotsUrl  = $r ? (string)($r['robots_url'] ?? '') : '';
        $sitemapAt  = $r ? (string)($r['sitemap_added_at'] ?? '') : '';
        $sitemapUrl = $r ? (string)($r['sitemap_url'] ?? '') : '';

        $recrawlAt  = $r ? (string)($r['last_recrawl_at'] ?? '') : '';
        $recrawlCnt = $r ? (int)($r['last_recrawl_count'] ?? 0) : 0;

        $isVerified = ($verifiedAt !== '');
        $hasRobots  = ($robotsAt !== '' || $robotsUrl !== '');
        $hasSitemap = ($sitemapAt !== '' || $sitemapUrl !== '');

        // === FIX: "Страницы добавлены" считаем не только по дате, но и по count ===
        // (на случай, если дата не записалась, но count уже есть)
        $hasPages   = ($recrawlAt !== '' || $recrawlCnt > 0);

        $badge = function(bool $ok, string $yes='Да', string $no='Нет') {
            return $ok
                ? '<span class="wm-badge wm-ok">'.$yes.'</span>'
                : '<span class="wm-badge wm-bad">'.$no.'</span>';
        };
      ?>
      <tr>
        <td><?= h($label === '' ? '(root)' : $label) ?></td>
        <td><?= h($hostUrl) ?></td>
        <td><?= $r ? h($r['host_id'] ?? '') : '' ?></td>
        <td><?= $r ? h($r['verification_file'] ?? '') : '' ?></td>

        <td>
          <?php if (!$r): ?>
            <span class="wm-badge wm-na">—</span>
          <?php else: ?>
            <?= $fileWritten === 1
                  ? '<span class="wm-badge wm-ok">Да</span>'
                  : '<span class="wm-badge wm-bad">Нет</span>' ?>
          <?php endif; ?>
        </td>

        <td>
          <?= $badge($isVerified) ?>
          <?php if ($isVerified): ?>
            <div class="wm-small"><?= h($verifiedAt) ?></div>
          <?php endif; ?>
        </td>

        <td>
          <?= $badge($hasRobots) ?>
          <?php if ($robotsUrl !== ''): ?>
            <div class="wm-small"><?= h($robotsUrl) ?></div>
          <?php endif; ?>
        </td>

        <td>
          <?= $badge($hasSitemap) ?>
          <?php if ($sitemapUrl !== ''): ?>
            <div class="wm-small"><?= h($sitemapUrl) ?></div>
          <?php endif; ?>
        </td>

        <td>
          <?php if ($hasPages): ?>
            <span class="wm-badge wm-ok">Да<?= $recrawlCnt > 0 ? ' ('.$recrawlCnt.')' : '' ?></span>
            <?php if ($recrawlAt !== ''): ?>
              <div class="wm-small"><?= h($recrawlAt) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <span class="wm-badge wm-bad">Нет</span>
          <?php endif; ?>
        </td>

        <td><?= $r ? h($r['last_sync_at'] ?? '') : '' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
async function fillFromPages() {
  const siteId = <?= (int)$siteId ?>;
  let label = document.getElementById('recrawlLabel').value;

  // root -> _default для template-multy (как у тебя)
  if (label === '') label = '_default';

  if (label === 'ALL') {
    alert('Для ALL используй кнопку "Массово". Для вставки выбери конкретный label.');
    return;
  }

  const url = '/webmaster/pages-urls?id=' + encodeURIComponent(siteId) + '&label=' + encodeURIComponent(label);
  const r = await fetch(url, { credentials: 'same-origin' });
  const txt = await r.text();

  if (!r.ok) {
    alert('Ошибка получения pages urls: ' + txt);
    return;
  }

  document.getElementById('recrawlUrls').value = (txt || '').trim();
}

function submitRecrawlFromPagesAll() {
  const siteId = <?= (int)$siteId ?>;
  if (!confirm('Отправить recrawl по pages сразу для всех хостов этого сайта?')) return;

  const f = document.createElement('form');
  f.method = 'post';
  f.action = '/webmaster/recrawl-from-pages?id=' + encodeURIComponent(siteId);

  const i = document.createElement('input');
  i.type = 'hidden';
  i.name = 'label';
  i.value = 'ALL';
  f.appendChild(i);

  document.body.appendChild(f);
  f.submit();
}

// sitemap: GET helper
function submitSitemapGet() {
  const siteId = <?= (int)$siteId ?>;
  const label = document.getElementById('sitemapLabel').value;

  const f = document.createElement('form');
  f.method = 'post';
  f.action = '/webmaster/sitemap/get?id=' + encodeURIComponent(siteId);

  const i = document.createElement('input');
  i.type = 'hidden';
  i.name = 'label';
  i.value = label;
  f.appendChild(i);

  document.body.appendChild(f);
  f.submit();
}

// robots: GET helper
function submitRobotsGet() {
  const siteId = <?= (int)$siteId ?>;
  const label = document.getElementById('robotsLabel').value;

  const f = document.createElement('form');
  f.method = 'post';
  f.action = '/webmaster/robots/get?id=' + encodeURIComponent(siteId);

  const i = document.createElement('input');
  i.type = 'hidden';
  i.name = 'label';
  i.value = label;
  f.appendChild(i);

  document.body.appendChild(f);
  f.submit();
}
</script>