# REV3.2-final_4 — FINAL INTEGRATION GATE
## Не собирать следующий ZIP, пока не закрыты эти production-path разрывы

Проверен архив:

`refresh-panel_CUMULATIVE_HOTPATCH_2026-08-16_REV3.2-final_4.zip`

SHA-256:

`4c4d5528624443ad079403b4471f07cd158260bac796c22b28a759d32d919a5b`

## Вердикт

`final_4` НЕ устанавливать.

Большинство прежних corrective issues исправлено, но внутренний convergence-test всё ещё тестирует
`RedirectToggler` напрямую и пропускает критический serialization boundary:

```text
RedirectToggler::disable()
        ↓
RefreshOrchestrator сохраняет ЧАСТЬ результата в artifacts_json
        ↓
между стадиями config.php меняется
        ↓
redirect_enable читает state уже ИЗ artifacts_json
```

Именно на этом реальном boundary final_4 ломает scoped restore.

---

# P0-1. КРИТИЧНО: `snapshot_restore_policy` НЕ СОХРАНЯЕТСЯ В artifacts_json

## Где

`app/Services/RefreshOrchestrator.php`

### Обычный redirect_disable

В районе строк 2367–2383 сохраняются:

```php
'applied_rules'
'snapshots'
'original_sha256'
'base_path'
...
```

НО отсутствует:

```php
'snapshot_restore_policy'
```

### One-shot recovery update_classes

В районе строк 6196–6211 — та же ошибка:

```php
'snapshots'
'original_sha256'
'base_path'
```

НО снова отсутствует:

```php
'snapshot_restore_policy'
```

## Почему это P0

`RedirectToggler::disable()` действительно правильно создаёт:

```php
snapshot_restore_policy['config.php'] = 'scoped'
```

для `$redirect_enabled`.

Но production orchestrator выбрасывает это поле при сериализации.

На `redirect_enable`:

```php
restoreSnapshotsExact()
```

получает state без policy.

А внутри:

```php
($restorePolicy[$f] ?? 'exact')
```

по умолчанию считает `config.php` EXACT.

То есть исправление final_3/final_4 фактически НЕ работает в реальном pipeline.

## Независимое воспроизведение НА production-коде final_4

Исходно:

```php
$domain = 'https://old.example';
$title = 'OLD';
$redirect_enabled = 1;
```

`RedirectToggler::disable()` возвращает:

```text
snapshot_restore_policy = {"config.php":"scoped"}
```

Далее эмулирован EXACT state, который реально сохраняет `runRedirectDisableStage`
(то есть БЕЗ `snapshot_restore_policy`).

После штатного `config_replaced`:

```php
$domain = 'https://new.example';
$title = 'NEW';
$redirect_enabled = 0; // REFRESH-DISABLED
```

После:

```php
restoreSnapshotsExact(...)
enable(...)
```

фактический final_4:

```php
$domain = 'https://old.example';   // ОТКАТИЛОСЬ
$title = 'OLD';                    // ОТКАТИЛОСЬ
$redirect_enabled = 1;
```

При этом:

```text
restoreSnapshotsExact.ok = true
enable.ok = true
```

То есть stage способен зелёным статусом вернуть старый config.

## Исправление

В ОБА места сериализации state обязательно добавить:

```php
'snapshot_restore_policy' => $result['snapshot_restore_policy'] ?? []
```

и:

```php
'snapshot_restore_policy' => $tg['snapshot_restore_policy'] ?? []
```

Но этого недостаточно для backward compatibility.

### Fail-safe для уже существующих jobs

Если state старой/промежуточной job не содержит `snapshot_restore_policy`,
`restoreSnapshotsExact()` НЕ должен по default считать `config.php` exact.

Минимальный безопасный fallback:

```text
config.php snapshot
+ applied rule относится к config.php
=> scoped by default
```

или:

```text
любой config.php в redirect-disabled state
=> whole-file exact restore запрещён,
   если нельзя доказать, что между disable и enable config не менялся
```

Для этого pipeline `config.php` является intentional output `config_replaced`,
поэтому default exact для config — unsafe.

---

# P0-2. `php_site_redirect_array_enabled` (enomo v3) ВСЁ ЕЩЁ WHOLE-FILE EXACT

В test fixture / baseline redirect rules есть production mechanism:

```text
id   = php_site_redirect_array_enabled
file = config.php
```

Он меняет:

```php
'site' => [
    'redirect' => [
        'enabled' => 1
    ]
]
```

на:

```php
'enabled' => 0, /* REFRESH-DISABLED */
```

В `RedirectToggler::disable()` restore policy считается scoped ТОЛЬКО если среди applied rules есть:

```text
php_redirect_enabled_var
```

Код примерно:

```php
$isConfigVar = false;
foreach ($appliedToFile as $a) {
    if (($a['rule_id'] ?? '') === 'php_redirect_enabled_var') {
        $isConfigVar = true;
    }
}
$restorePolicy[$fileName] = $isConfigVar ? 'scoped' : 'exact';
```

Поэтому для:

```text
php_site_redirect_array_enabled
```

получаем:

```text
config.php => exact
```

и та же межстадийная проблема:

```text
disable old config
-> config_replaced new config
-> redirect_enable
-> whole old config restored
```

## Независимое воспроизведение final_4

Для config:

```php
$domain='https://old.example';
$title='OLD';
$site = ['redirect' => ['enabled' => 1]];
```

final_4 `disable()` возвращает:

```text
policy={"config.php":"exact"}
```

После simulate config_replaced NEW и exact restore:

```php
$domain='https://old.example';
$title='OLD';
$site = ['redirect' => ['enabled' => 1]];
```

То есть OLD снова возвращается.

## Исправление

Policy не должна зависеть от одного rule id.

Для ЭТОГО pipeline безопаснее:

```text
если file == config.php
=> scoped restore
```

потому что `config_replaced` легитимно меняет config.php между disable и enable.

Enable должен применить inverse redirect-rule к ТЕКУЩЕМУ post-config_replaced config,
а не заливать старый snapshot whole-file.

Обязательно data-driven проверить ВСЕ текущие redirect rules, у которых:

```text
file = config.php
```

---

# P0-3. ЕДИНЫЙ marker detector НЕ ВИДИТ поддерживаемый block marker enomo v3

Текущий:

```php
RedirectToggler::contentHasRefreshDisabledMarker()
```

распознаёт:

```text
# REFRESH-DISABLED:
 // REFRESH-DISABLED:
REFRESH_DISABLED_HANDLEREQUEST
$redirect_enabled=0 ... REFRESH-DISABLED
```

Но НЕ распознаёт:

```php
/* REFRESH-DISABLED */
```

который сам же supported rule `php_site_redirect_array_enabled` пишет в `config.php`.

Независимая проверка final_4:

```php
RedirectToggler::contentHasRefreshDisabledMarker(
    "'redirect' => ['enabled' => 0, /* REFRESH-DISABLED */]"
)
```

возвращает:

```text
false
```

## Последствия

При crash после remote write ДО artifact save:

```text
config.php содержит supported panel marker
/* REFRESH-DISABLED */
```

но:

```text
scanRefreshDisabledMarkers()
```

его не видит.

Тогда:

```text
redirect_enable
-> scan says no marker
-> not_required
```

и redirect может остаться disabled.

Также post-validation не сможет заметить оставшийся block-marker.

## Исправление

Единый detector обязан покрывать ВСЕ marker forms, которые текущие supported rules способны записать.

Минимум:

```text
# REFRESH-DISABLED:
 // REFRESH-DISABLED:
 /* REFRESH-DISABLED */
 REFRESH_DISABLED_HANDLEREQUEST
 canonical redirect_enabled marker
```

Но не использовать просто:

```php
strpos($content, 'REFRESH-DISABLED')
```

без контекста, если это может зацепить документацию/строки.

Добавить точный block-comment matcher.

---

# P0-4. Lost-artifacts recovery всё ещё фактически начинает с ALL RULES, а не с marker evidence

В `RedirectToggler::enable()` при пустом state:

```php
foreach ($this->rules as $r) {
    $appliedBefore[] = ...
}
```

Комментарий даже говорит:

```text
прогоняю все известные правила blindly
```

Часть правил безопасна, потому что `enable_find` требует marker.
Но это НЕ настоящий marker-scoped recovery.

Побочный эффект:
если supported file отсутствует, `enable()` добавляет error:

```text
.htaccess: не удалось скачать
index.php: не удалось скачать
```

даже когда реальный marker есть только в `config.php`.

Тест `test_crash_matrix` проверяет, что marker снялся,
но в W1 НЕ проверяет:

```php
$en['ok'] === true
```

для каждого mechanism.

А production `runRedirectEnableStage()` требует `enable.ok===true`.

## Исправление

При lost artifacts сначала определить:

```text
authoritative root
+
какие конкретно файлы несут наши markers
+
какой mechanism соответствует marker
```

И строить recovery state ТОЛЬКО из них.

Не добавлять отсутствующие/непомеченные files в `rulesByFile`.

Минимум:

```text
marker only in config.php
=> recovery вообще не должен пытаться открыть .htaccess/index.php как обязательные restore targets

marker only in index.php
=> config.php не мутировать

marker only in .htaccess
=> остальные файлы не являются recovery errors
```

---

# P0-5. Обязательный integration test должен проходить ЧЕРЕЗ artifact serialization

Текущий `test_lifecycle_harness` делает:

```php
$dis = $T->disable(...);
...
$T->enable(..., $dis, ...)
```

Это обходит production boundary и именно поэтому P0-1 не был найден.

Новый тест должен использовать тот state, который сохраняет orchestrator.

Нужно вынести/использовать production state-builder либо тестировать фактический stage serialization.

Минимальный flow:

```text
RedirectToggler::disable()
-> build redirect_disabled_stage EXACTLY как production runRedirectDisableStage
-> JSON encode
-> JSON decode (simulate next worker tick)
-> config_replaced mutation
-> redirect_enable path
```

Assertions:

```text
domain remains NEW
title remains NEW
redirect state restored
markers absent
snapshot policy survived serialization
```

Повторить для:

```text
php_redirect_enabled_var
php_site_redirect_array_enabled
legacy index
.htaccess
```

---

# P1-1. `rollback.sh` всё ещё НЕ выполняет required pre-write integrity gate

Текущий `rollback.sh` сразу делает:

```bash
cp rollback/... target/...
```

и только ПОСЛЕ этого сверяет target с BASE_SHA.

Это противоречит convergence task:

```text
MANIFEST valid
rollback source SHA == BASE_SHA
BEFORE first rollback write
```

Если `rollback/` повреждён, script сначала зальёт повреждённый файл в production,
и только потом обнаружит mismatch.

`tests/rollback_adversarial.sh` проверяет source SHA СНАРУЖИ,
но сам production `rollback.sh` этого gate не имеет.

## Исправление

До первого `cp` в rollback.sh:

1. verify package `MANIFEST.sha256`;
2. для каждого rollback source:
   `sha256(rollback/$rel) == BASE_SHA256[$rel]`;
3. только после полного PASS начинать writes.

Желательно standalone rollback тоже делать transaction-style,
а не оставить partial rollback при fail посередине.

---

# P1-2. Regression report нельзя называть full-green

В приложенных логах:

```text
REGRESSION_baseline.txt
REGRESSION_candidate.txt
```

оба заканчиваются:

```text
READY_TO_INSTALL=NO
```

и содержат не только 20 UI structure FAIL,
но и реальные Fatal PDO errors из-за отсутствующих таблиц:

```text
refresh_jobs doesn't exist
ban_monitoring_settings doesn't exist
xmlstock_settings doesn't exist
...
```

Да, baseline и candidate падают одинаково,
поэтому PATCH regression delta по этим логам действительно 0.

Но REPORT не должен описывать это как будто единственная причина:
`20 FAIL — pre-existing xmlstock UI-structure`.

Корректно:

```text
candidate не добавил новых failure относительно этого сломанного test environment,
НО full regression green НЕ доказан.
```

Перед production предпочтительно прогнать baseline/candidate на корректно восстановленной disposable DB,
чтобы штатный regression runner завершился READY_TO_INSTALL=YES.

---

# ЧТО УЖЕ ПРОВЕРЕНО НЕЗАВИСИМО И СОХРАНИТЬ

Для final_4 независимо подтверждено:

```text
ZIP SHA:
4c4d5528624443ad079403b4471f07cd158260bac796c22b28a759d32d919a5b

MANIFEST.sha256 = ALL OK
ZIP path traversal = none
ZIP symlinks = none
payload/rollback/tests php -l = PASS
install/rollback/test shell syntax = PASS
```

В review environment нет native mbstring/MySQL.
С test-only mb_* polyfill (package НЕ изменялся) независимо повторены ВСЕ 15 non-DB suites:

```text
238 assertions PASS
0 suite FAIL
```

Developer package log:

```text
TOTAL 255 PASS / 0 FAIL
включая DB 17/17
```

То есть проблема НЕ в количестве тестов.
Проблема в том, что текущие тесты не проходят production serialization boundary
и не покрывают второй существующий config.php mechanism.

---

# JOB #227

Важно:

НЕ переписывать снова Mellstroy logic.

В final_4 для #227 уже подтверждены:

```text
real fixture index
HTTP200 meta/JS -> partners7k-promo.com -> own_redirect_active
legacy disable/readback
one-shot recovery decision
at-most-once mutation
legacy fixture restore
```

Основной оставшийся риск сейчас — НЕ #227 happy path,
а регрессия config-based/enomo v3 сайтов.

---

# ОБЯЗАТЕЛЬНЫЙ GATE ПЕРЕД СЛЕДУЮЩИМ ZIP

Не собирать ZIP, пока локально не PASS всё:

```text
[ ] snapshot_restore_policy сохраняется в ОБОИХ orchestrator artifact writers

[ ] state переживает JSON encode/decode и policy остаётся scoped

[ ] missing policy backward compatibility:
    config.php НЕ whole-file exact by default

[ ] ВСЕ supported config.php redirect rules используют scoped restore

[ ] php_site_redirect_array_enabled:
    disable -> config_replaced NEW -> enable
    FINAL domain/title remain NEW

[ ] block marker /* REFRESH-DISABLED */ обнаруживается stale scan/post-validation

[ ] array-enabled crash-before-artifact recovery реально автоматически проходит

[ ] lost-artifacts recovery uses only files/mechanisms with marker evidence

[ ] W1 crash matrix asserts enable.ok === true for each mechanism,
    not only marker disappearance

[ ] lifecycle test passes THROUGH actual serialized redirect_disabled_stage

[ ] rollback.sh verifies MANIFEST + rollback source BASE SHA BEFORE first write

[ ] regression baseline/candidate run in valid test DB OR REPORT честно говорит,
    что full-green regression не получен
```

После этого снова internal red-team именно ПО:
- serialization boundaries;
- all current `redirect_rules.php` entries, data-driven;
- stage-to-stage mutation ownership.

И только после полного PASS собирать ОДИН cumulative ZIP.

Не присылать mini-hotfix.
