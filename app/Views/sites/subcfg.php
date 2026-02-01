<?php
// app/Views/sites/subcfg.php
// Ожидает: $site, $siteId, $label, $labels, $cfg, $unused

$siteId = (int)($siteId ?? 0);
$label  = (string)($label ?? '_default');
$labels = is_array($labels ?? null) ? $labels : ['_default'];
$cfg    = is_array($cfg ?? null) ? $cfg : [];
$unused = is_array($unused ?? null) ? $unused : [];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<style>
.subcfg-wrap { max-width: 1100px; }
.subcfg-top { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px; }
.subcfg-top .box { padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; }
.subcfg-top label { display:block; font-size:12px; opacity:.8; margin-bottom:6px; }
.subcfg-top input[type="text"], .subcfg-top select { width: 320px; max-width: 100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; }
.subcfg-actions { display:flex; gap:8px; flex-wrap:wrap; }
.subcfg-actions button { padding:8px 12px; border:1px solid #ccc; background:#f7f7f7; border-radius:6px; cursor:pointer; }
.subcfg-actions button.primary { background:#0b63f6; color:#fff; border-color:#0b63f6; }
.subcfg-actions button.danger { background:#fff0f0; border-color:#ffb7b7; }
.subcfg-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
.subcfg-grid .box { padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; }
.subcfg-grid input[type="text"] { width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; }
.subcfg-grid .row { margin-bottom:10px; }
.small { font-size:12px; opacity:.8; }
.hr { height:1px; background:#eee; margin:12px 0; }
.unused { margin:0; padding-left:18px; }
.unused li { margin:4px 0; }
.note { padding:10px 12px; background:#f6f8ff; border:1px solid #dbe3ff; border-radius:8px; }
</style>

<div class="subcfg-wrap">
    <h2>Subdomain configs: <?= e($site['domain'] ?? '') ?></h2>

    <p class="small">
        <a href="/sites/edit?id=<?= $siteId ?>">Редактировать</a> |
        <a href="/sites/pages?id=<?= $siteId ?>">Pages</a> |
        <a href="/sites/texts?id=<?= $siteId ?>">Texts</a> |
        <a href="/sites/files?id=<?= $siteId ?>">Files</a> |
        <a href="/sites">Назад</a>
    </p>

    <div class="subcfg-top">
        <div class="box">
            <label>Поиск саба</label>
            <input id="subSearch" type="text" placeholder="например: 1win, pinup, _default">
            <div class="small">Фильтрует список. Enter не нужен.</div>
        </div>

        <div class="box">
            <label>Выбор саба</label>
            <select id="subSelect">
                <?php foreach ($labels as $lb): ?>
                    <option value="<?= e($lb) ?>" <?= $lb === $label ? 'selected' : '' ?>>
                        <?= e($lb) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="small">Открывает один экран редактирования для выбранного саба.</div>
        </div>

        <div class="box" style="flex:1; min-width:280px;">
            <label>Быстрые действия</label>
            <div class="subcfg-actions">
                <form method="post" action="/sites/subcfg/regenAll" onsubmit="return confirm('Перегенерировать config.php для всех сабов?');">
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                    <button type="submit">Regen all</button>
                </form>

                <form method="post" action="/sites/subcfg/create" onsubmit="return confirm('Создать саб + папки + config.php?');" style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                    <input type="text" name="new_label" placeholder="new-sub" style="width:180px;">
                    <button type="submit">Create sub</button>
                </form>

                <?php if ($label !== '_default'): ?>
                <form method="post" action="/sites/subcfg/delete" onsubmit="return confirm('Удалить конфиг саба <?= e($label) ?> из БД?');">
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                    <input type="hidden" name="label" value="<?= e($label) ?>">
                    <label class="small" style="display:inline-flex; gap:6px; align-items:center; margin-right:8px;">
                        <input type="checkbox" name="delete_folder" value="1"> удалить папку subs/<?= e($label) ?>
                    </label>
                    <button type="submit" class="danger">Delete sub</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="note">
        <b>Важно:</b> при открытии экрана панель вызывает provisioner и гарантирует наличие
        <code>subs/&lt;label&gt;/</code> (texts + assets + config.php).
        Для основного домена используется <code>subs/_default</code>.
    </div>

    <div class="hr"></div>

    <form method="post" action="/sites/subcfg/save">
        <input type="hidden" name="site_id" value="<?= $siteId ?>">
        <input type="hidden" name="label" value="<?= e($label) ?>">

        <div class="subcfg-grid">
            <div class="box">
                <h3>SEO defaults (для <?= e($label) ?>)</h3>

                <div class="row">
                    <label>title</label>
                    <input type="text" name="title" value="<?= e($cfg['title'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>h1</label>
                    <input type="text" name="h1" value="<?= e($cfg['h1'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>description</label>
                    <input type="text" name="description" value="<?= e($cfg['description'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>keywords</label>
                    <input type="text" name="keywords" value="<?= e($cfg['keywords'] ?? '') ?>">
                </div>
            </div>

            <div class="box">
                <h3>Redirect / partner / assets</h3>

                <div class="row">
                    <label>promolink</label>
                    <input type="text" name="promolink" value="<?= e($cfg['promolink'] ?? '/reg') ?>">
                </div>

                <div class="row">
                    <label>internal_reg_url</label>
                    <input type="text" name="internal_reg_url" value="<?= e($cfg['internal_reg_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>partner_override_url</label>
                    <input type="text" name="partner_override_url" value="<?= e($cfg['partner_override_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label style="display:inline-flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="redirect_enabled" value="1" <?= !empty($cfg['redirect_enabled']) ? 'checked' : '' ?>>
                        redirect_enabled
                    </label>
                </div>

                <div class="row">
                    <label>base_new_url</label>
                    <input type="text" name="base_new_url" value="<?= e($cfg['base_new_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>base_second_url</label>
                    <input type="text" name="base_second_url" value="<?= e($cfg['base_second_url'] ?? '') ?>">
                </div>

                <div class="row">
                    <label>logo (path)</label>
                    <input type="text" name="logo" value="<?= e($cfg['logo'] ?? 'assets/logo.png') ?>">
                    <div class="small">Обычно: <code>assets/logo.png</code></div>
                </div>

                <div class="row">
                    <label>favicon (path)</label>
                    <input type="text" name="favicon" value="<?= e($cfg['favicon'] ?? 'assets/favicon.png') ?>">
                    <div class="small">Обычно: <code>assets/favicon.png</code></div>
                </div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="subcfg-actions">
            <button type="submit" class="primary">Save</button>
            <a class="small" href="/sites/pages?id=<?= $siteId ?>&label=<?= urlencode($label) ?>">Перейти к Pages (если подключишь label)</a>
        </div>
    </form>

    <?php if (!empty($unused)): ?>
        <div class="hr"></div>
        <div class="box">
            <h3>Неиспользуемые texts (<?= e($label) ?>)</h3>
            <ul class="unused">
                <?php foreach ($unused as $f): ?>
                    <li><code><?= e($f) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <div class="small">Это просто список. Удаление можно добавить отдельным action, если надо.</div>
        </div>
    <?php endif; ?>
</div>

<script>
(function(){
  const search = document.getElementById('subSearch');
  const sel = document.getElementById('subSelect');

  function applyFilter(){
    const q = (search.value || '').toLowerCase().trim();
    for (const opt of sel.options) {
      const v = (opt.value || '').toLowerCase();
      opt.hidden = q && v.indexOf(q) === -1;
    }
  }

  search.addEventListener('input', applyFilter);

  sel.addEventListener('change', function(){
    const lb = sel.value;
    const url = new URL(window.location.href);
    url.searchParams.set('label', lb);
    window.location.href = url.toString();
  });

  applyFilter();
})();
</script>
