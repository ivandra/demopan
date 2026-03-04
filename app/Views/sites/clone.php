<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$site = $site ?? [];
$labels = $labels ?? [];
?>

<h2>Мастер копирования сайта</h2>

<p>
  <a href="/sites">← К сайтам</a>
</p>

<div style="padding:12px;border:1px solid #ddd;border-radius:10px;max-width:900px;">
  <p style="margin-top:0;">
    <b>Источник:</b>
    #<?= (int)($site['id'] ?? 0) ?> — <?= h($site['domain'] ?? '') ?>
    <br>
    <small style="opacity:.8;">
      Шаблон: <?= h($site['template'] ?? '') ?> |
      VPS: <?= (int)($site['fastpanel_server_id'] ?? 0) > 0 ? 'ID ' . (int)$site['fastpanel_server_id'] : '—' ?> |
      IP: <?= h($site['vps_ip'] ?? '—') ?>
    </small>
  </p>

  <form method="post" action="/sites/clone?id=<?= (int)($site['id'] ?? 0) ?>" style="margin:0;">
    <div style="margin:12px 0;">
      <label><b>Новый основной домен</b></label><br>
      <input type="text" name="new_domain" value="<?= h($defaultNewDomain ?? '') ?>"
             style="width:100%;max-width:520px;padding:8px;border:1px solid #ccc;border-radius:8px;">
      <div style="font-size:12px;opacity:.8;margin-top:6px;">
        Пример: <code>mynewdomain.com</code>
      </div>
    </div>

    <div style="margin:12px 0;">
      <label style="display:block;margin-bottom:6px;"><b>Параметры клона</b></label>

      <label style="display:block;margin:6px 0;">
        <input type="checkbox" name="same_vps" value="1" <?= ((int)($defaultSameVps ?? 1) === 1 ? 'checked' : '') ?>>
        Оставить тот же VPS (FastPanel сервер и IP)
      </label>

      <label style="display:block;margin:6px 0;">
        <input type="checkbox" name="reset_state" value="1" <?= ((int)($defaultResetState ?? 1) === 1 ? 'checked' : '') ?>>
        Сбросить статусы развёртывания (FastPanel/FTP/Файлы/SSL/DNS/Покупка домена)
      </label>

      <div style="font-size:12px;opacity:.85;margin-top:6px;">
        Рекомендация: держать включённым “сброс”, чтобы новый сайт прошёл все этапы как “с нуля”.
      </div>
    </div>

    <div style="margin:12px 0;">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <b>Какие поддомены копировать</b>
        <button type="button" onclick="selectAll(true)" style="padding:4px 10px;">Выбрать все</button>
        <button type="button" onclick="selectAll(false)" style="padding:4px 10px;">Снять все</button>
      </div>

      <div style="margin-top:10px;padding:10px;border:1px solid #eee;border-radius:10px;max-height:320px;overflow:auto;">
        <?php if (empty($labels)): ?>
          <div style="opacity:.8;">Поддомены не найдены (кроме <code>_default</code>, он копируется всегда)</div>
        <?php else: ?>
          <?php foreach ($labels as $r): ?>
            <label style="display:block;margin:6px 0;">
              <input class="lbcb" type="checkbox" name="labels[]" value="<?= h($r['label']) ?>" checked>
              <b><?= h($r['label']) ?></b>
              <span style="opacity:.8;">(<?= h($r['fqdn']) ?>)</span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div style="font-size:12px;opacity:.8;margin-top:8px;">
        <code>_default</code> копируется всегда (это базовая конфигурация).
      </div>
    </div>

    <div style="margin-top:16px;">
      <button type="submit"
              style="padding:10px 16px;border-radius:10px;border:1px solid #0a66c2;background:#0a66c2;color:#fff;cursor:pointer;"
              onclick="return confirm('Создать клон сайта и применить выбранные параметры?');">
        Создать копию сайта
      </button>
    </div>
  </form>
</div>

<script>
function selectAll(flag){
  document.querySelectorAll('.lbcb').forEach(cb => cb.checked = !!flag);
}
</script>