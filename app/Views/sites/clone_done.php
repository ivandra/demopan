<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$domain = (string)($site['domain'] ?? '');
$tpl    = (string)($site['template'] ?? '');
$vpsOk  = ((int)($site['fp_site_created'] ?? 0) === 1 && (int)($site['fp_site_id'] ?? 0) > 0);
$serverId = (int)($site['fastpanel_server_id'] ?? 0);

$subsEnabled = (int)($subStats['enabled'] ?? 0);
$subsTotal   = (int)($subStats['total'] ?? 0);
?>

<h2 style="margin:0 0 10px 0;">Клон создан ✅</h2>

<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
  <div style="padding:10px 12px;border:1px solid #eee;border-radius:10px;background:#fff;">
    <div style="font-size:12px;color:#666;">Сайт</div>
    <div style="font-weight:700;"><?= (int)$siteId ?> — <?= h($domain) ?></div>
    <div style="font-size:12px;color:#666;margin-top:2px;">template: <?= h($tpl) ?></div>
    <div style="font-size:12px;color:#666;margin-top:2px;">поддомены: <b><?= $subsEnabled ?></b> включено / <?= $subsTotal ?> всего</div>
  </div>

  <div style="margin-left:auto;display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/sites" style="display:inline-block;padding:10px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;background:#fff;">
      ← К сайтам
    </a>
    <a href="/sites/edit?id=<?= (int)$siteId ?>" style="display:inline-block;padding:10px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;background:#fff;">
      Настройки сайта
    </a>
    <a href="/ssl/site?id=<?= (int)$siteId ?>" style="display:inline-block;padding:10px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;background:#fff;">
      SSL Monitor
    </a>
  </div>
</div>

<div style="border:1px solid #eee;border-radius:14px;padding:14px;background:#fff;">
  <div style="font-weight:700;margin-bottom:10px;">План действий после клона</div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
    <!-- 1) Домен -->
    <div style="border:1px solid #f0f0f0;border-radius:12px;padding:12px;">
      <div style="font-weight:700;">1) Домен</div>
      <div style="font-size:12px;color:#666;margin:6px 0 10px 0;">
        Проверить доступность домена. Если домен уже существует — ок, дальше просто DNS.
      </div>

      <!-- Самая безопасная кнопка: открыть страницу Domains для этого site -->
      <a href="/domains?id=<?= (int)$siteId ?>"
         style="display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#2f80ed;color:#fff;font-weight:700;">
        Проверить домен / DNS
      </a>

      <div style="font-size:12px;color:#777;margin-top:8px;">
        Там же: покупка домена и применение DNS.
      </div>
    </div>

    <!-- 2) Поддомены/DNS -->
    <div style="border:1px solid #f0f0f0;border-radius:12px;padding:12px;">
      <div style="font-weight:700;">2) Поддомены и DNS-записи</div>
      <div style="font-size:12px;color:#666;margin:6px 0 10px 0;">
        Выбрать/включить поддомены, затем применить DNS (A/CNAME) для корня + выбранных сабов.
      </div>

      <a href="/sites/subdomains?id=<?= (int)$siteId ?>"
         style="display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#111;color:#fff;font-weight:700;">
        Поддомены (выбор)
      </a>

      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
        <a href="/domains?id=<?= (int)$siteId ?>"
           style="flex:1;min-width:180px;display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#fff;border:1px solid #ddd;font-weight:700;">
          Применить DNS
        </a>
        <a href="/sites/subcfg?id=<?= (int)$siteId ?>"
           style="flex:1;min-width:180px;display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#fff;border:1px solid #ddd;font-weight:700;">
          SubCfg (конфиги)
        </a>
      </div>
    </div>

    <!-- 3) Билд -->
    <div style="border:1px solid #f0f0f0;border-radius:12px;padding:12px;">
      <div style="font-weight:700;">3) Сделать билд</div>
      <div style="font-size:12px;color:#666;margin:6px 0 10px 0;">
        Перегенерация build-папки/конфигов для нового домена и выбранных поддоменов.
      </div>

      <form method="post" action="/sites/build?id=<?= (int)$siteId ?>"
            onsubmit="return confirm('Запустить билд для <?= h($domain) ?>?');">
        <button type="submit"
                style="width:100%;padding:10px 12px;border-radius:10px;border:0;background:#27ae60;color:#fff;font-weight:700;">
          Сделать билд
        </button>
      </form>

      <div style="font-size:12px;color:#777;margin-top:8px;">
        Рекомендуется перед Deploy.
      </div>
    </div>

    <!-- 4) Deploy -->
    <div style="border:1px solid #f0f0f0;border-radius:12px;padding:12px;">
      <div style="font-weight:700;">4) Deploy на VPS</div>
      <div style="font-size:12px;color:#666;margin:6px 0 10px 0;">
        Создать сайт в FastPanel и выгрузить файлы. (У тебя это уже есть в /deploy.)
      </div>

      <a href="/deploy?id=<?= (int)$siteId ?>"
         style="display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#f2994a;color:#fff;font-weight:700;">
        Открыть Deploy-страницу
      </a>

      <?php if (!$vpsOk): ?>
        <div style="margin-top:10px;font-size:12px;color:#b00;">
          FastPanel site ещё не создан (это нормально). Делай Deploy.
        </div>
      <?php else: ?>
        <div style="margin-top:10px;font-size:12px;color:#0b6b3a;">
          FastPanel: создан ✅ (server_id=<?= (int)$serverId ?>, fp_site_id=<?= (int)($site['fp_site_id'] ?? 0) ?>)
        </div>
      <?php endif; ?>
    </div>

    <!-- 5) SSL -->
    <div style="border:1px solid #f0f0f0;border-radius:12px;padding:12px;">
      <div style="font-weight:700;">5) SSL сейчас</div>
      <div style="font-size:12px;color:#666;margin:6px 0 10px 0;">
        Принудительно проверить SSL (root + enabled поддомены).
      </div>

      <form method="post" action="/ssl/site/check-now?id=<?= (int)$siteId ?>"
            onsubmit="return confirm('Принудительно проверить SSL сейчас?');">
        <button type="submit"
                style="width:100%;padding:10px 12px;border-radius:10px;border:0;background:#2f80ed;color:#fff;font-weight:700;">
          SSL сейчас (проверить)
        </button>
      </form>

      <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="/ssl/site?id=<?= (int)$siteId ?>"
           style="flex:1;min-width:180px;display:block;text-align:center;padding:10px 12px;border-radius:10px;text-decoration:none;background:#fff;border:1px solid #ddd;font-weight:700;">
          Открыть SSL Monitor
        </a>
      </div>
    </div>
  </div>

  <div style="margin-top:12px;font-size:12px;color:#777;">
    Примечание: кнопки ведут на существующие формы/маршруты, чтобы не ломать текущую логику аккаунтов регистратора, DNS и деплоя.
    “Автоматизацию в один клик” (последовательно выполнить шаги) можно добавить позже отдельными POST-эндпоинтами.
  </div>
</div>