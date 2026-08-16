# REV3.2 FINAL — обязательная корректировка после независимой проверки

Проверен именно архив:

`refresh-panel_CUMULATIVE_HOTPATCH_2026-08-16_REV3.2-final.zip`

SHA-256:

`c8f51d4c04001933dd5783f91127574174da56dd9f14526f1d7e4eee0049863a`

## Вердикт

Текущий `REV3.2-final` **НЕ выпускать в production**.

Базовая идея патча правильная, MANIFEST текущего архива сходится, PHP/shell syntax проходит, но в рабочей логике и release packaging остались несколько дефектов, которые текущие тесты не ловят. Нужен **один новый кумулятивный ZIP поверх установленного REV3.1**, а не дополнительный мини-патч.

Не переписывать принятую архитектуру REV3.1 и не менять сайт. Исправить только перечисленные ниже места, затем полностью пересобрать payload/rollback/tests/docs/MANIFEST и дать один архив.

---

# P0-1. Same-host redirect chain сейчас может дать ложный success

Файл: `app/Services/RefreshOrchestrator.php`

В текущем архиве проблемные места примерно:

- `resolveSameHostChain()` — строки ~1969–2006;
- `preflightUpdateClasses()` — строки ~6010–6052.

## Фактические дефекты

### 1. Теряется `Location` path/query

Сейчас после same-host redirect следующий запрос строится фактически только по host:

```php
$nextHost = RedirectToggler::normalizeHost($loc);
$curDomain = ($nextHost !== '' ? $nextHost : $newHost);
```

Из-за этого:

```text
https://example.com/
301 Location: /ru/start?x=1
```

может превратиться в повторную проверку:

```text
https://example.com/
```

вместо:

```text
https://example.com/ru/start?x=1
```

### 2. Для 3 redirect hops нужен 4-й HTTP response

Лимит должен означать:

```text
R1 -> R2 -> R3 -> terminal
```

То есть максимум 3 redirect, но максимум 4 HTTP-ответа.

### 3. Нельзя синтезировать HTTP 200

Сейчас `resolveSameHostChain()` при исчерпанном массиве same-host 3xx возвращает synthetic:

```php
terminal_http => 200
reason => chain_same_host_only
```

Это ложное доказательство terminal response.

### 4. Текущий тест закрепляет неправильное поведение

В `tests/test_samehost_chain.php` есть ожидание:

```text
single same-host 3xx (no hops) → ok
```

Такого success быть не должно без terminal evidence.

## Как исправить

Нужно следовать **полному URL**, а не host:

- absolute `https://host/path`;
- scheme-relative `//host/path`;
- root-relative `/path`;
- path-relative `next/page`;
- query-only `?x=1`.

Требования:

1. Нормализовать полный URL для loop detection.
2. Сохранять path + query.
3. Fragment не влияет на HTTP resource.
4. Follow только если normalized logical host тот же.
5. Внешний host никогда не follow.
6. Loop определять по normalized full URL.
7. Максимум 3 redirects / 4 responses.
8. Если terminal response реально не получен — bounded deterministic failure, не synthetic 200.
9. Meta/JS external redirect, если он уже распознан probe, должен классифицироваться как external до трактовки HTTP 200 как обычного terminal content.

## Обязательные тесты

Добавить тест, который входит в **реальный production `preflightUpdateClasses()`**, а не только в helper classifier, через deterministic HTTP transport subclass.

Сценарии минимум:

```text
/ -> 301 /ru/ -> 200
/ -> 302 /x?a=1 -> 200
/dir/a -> 301 ../b?x=1 -> 200
/ -> 301 ?lang=ru -> 200
www -> non-www -> 200
same-host -> own external redirect
same-host -> unknown external redirect
redirect loop A -> B -> A
3 redirects -> terminal 200
4 redirects -> bounded failure
3xx without resolvable Location -> failure
single 3xx without terminal response -> НЕ success
```

---

# P0-2. Crash recovery redirect_enable недостижим при потерянных artifacts

Файлы:

- `app/Services/RefreshOrchestrator.php`
- `app/Services/RedirectToggler.php`

## Фактический дефект

В `RedirectToggler::enable()` уже есть fallback, который умеет снять:

```text
REFRESH_DISABLED_HANDLEREQUEST
```

Но `runRedirectEnableStage()` раньше делает:

```php
if (empty($disabledState) || empty($disabledState['applied_rules'])) {
    ... not_required ...
    return;
}
```

То есть при crash-window:

```text
remote index.php уже изменён
→ process упал
→ artifacts.redirect_disabled_stage не сохранились
```

orchestrator возвращает `not_required` и до recovery внутри `RedirectToggler::enable()` вообще не доходит.

Это опасно: сайт может остаться с временно отключённым редиректом.

## Как исправить

До `not_required` выполнить read-only stale-marker inspection.

Проверять минимум:

```text
.htaccess
index.php
config.php
```

на marker family панели (`REFRESH-DISABLED`, включая legacy marker).

Правила:

- markers найдены → запускать crash-recovery restore;
- marker scan технически не удалось выполнить/ничего нельзя прочитать → **fail closed / awaiting_user**, а не `not_required`;
- markers отсутствуют и это реально доказано FTP-read → `not_required`;
- restore должен иметь exact read-back;
- при наличии snapshot приоритет — exact byte restore из snapshot;
- fallback marker unwrap допустим только для собственного точного marker-контракта.

## Обязательные тесты

Нужен integration-like тест с fake FTP, вызывающий настоящий `RedirectToggler::enable()` и реальный entry decision:

```text
1. index.php active
2. disable -> marker written
3. artifacts deliberately lost
4. new orchestrator tick
5. stale marker detected before not_required
6. enable/recovery invoked
7. handleRequest active again
8. exact read-back
```

Отдельно:

```text
marker scan unavailable -> awaiting_user
marker absent + files successfully inspected -> not_required
```

---

# P0-3. redirect_disable read-back сейчас может быть ложноположительным

Файл: `app/Services/RefreshOrchestrator.php`

Текущее `evaluateRedirectDisableResult()` проверяет примерно так:

```php
if ($f !== '' && array_key_exists($f, $readbackMap) && $readbackMap[$f] !== true) {
    $readbackVerified = false;
}
```

Если applied rule есть, но ключа файла **вообще нет** в `readback`, `readbackVerified` останется `true`.

Это нарушает fail-closed postcondition.

## Требование

Для **каждого** applied rule:

```text
file != ''
AND array_key_exists(file, readback)
AND readback[file] === true
```

Иначе:

```text
readback_verified=false
ok=false
```

---

# P0-4. detect=true + apply=0 может ошибочно превратиться в no_active_redirect

Файл: `app/Services/RedirectToggler.php`

Сейчас generic rule может:

```text
detect = matched
disable_find = count 0
```

но mechanism не обязательно попадает в `detected_mechanisms`.

Далее:

```text
applied_rules=[]
detected_mechanisms=[]
```

может быть ошибочно воспринято как `no_active_redirect`.

## Как исправить

Нужно различать:

```text
detected_mechanisms
    = ACTIVE/suspicious mechanisms, которые требуют disable

verified_inactive_mechanisms
    = поддерживаемые механизмы, которые уже доказанно disabled
```

Правила:

- generic `detect=true` → добавить mechanism в `detected_mechanisms` ДО disable attempt;
- supported legacy active call → detected;
- unsupported/ambiguous legacy mechanism → detected + error;
- canonical `redirect_enabled=0` / собственный legacy marker уже disabled → `verified_inactive_mechanisms`, а не active detected;
- idempotent re-entry не должен re-enable то, что текущая job не выключала.

`evaluateRedirectDisableResult()`:

```text
applied=[] + detected active != [] -> FAIL
applied=[] + verified_inactive != [] + no errors -> допустимый idempotent verified state
applied=[] + ничего не найдено -> success только при действительно доказанном отсутствии external redirect
```

## Тесты

Добавить минимум:

```text
applied rule + missing readback key -> FAIL
detect=true + disable_find count=0 -> FAIL
already disabled supported mechanism -> idempotent PASS
active legacy mechanism -> detected
unsupported ambiguous legacy mechanism -> FAIL
```

---

# P1-1. robots_closed должен учитывать wildcard/root semantics, а не любой Allow

Файл: `app/Services/SiteVerifier.php`

Сейчас helpers фактически ищут:

```text
есть ли где-либо Disallow: /
есть ли где-либо любой Allow: <что-то>
```

Из-за этого robots вида:

```text
User-agent: *
Disallow: /

User-agent: Googlebot
Allow: /
```

или:

```text
User-agent: *
Disallow: /
Allow: /public/
```

может быть ошибочно классифицирован не как закрытый root.

## Требование

Для `robots_closed` анализировать именно группу:

```text
User-agent: *
```

и policy root `/`.

Минимальный инвариант:

```text
wildcard Disallow: /
AND нет effective wildcard Allow: /
=> robots_closed=true
```

Не считать `Allow` другого user-agent или `Allow: /public/` доказательством открытия root.

Не ломать существующий контракт проверки старого домена без необходимости.

## Тесты

```text
UA:* Disallow:/ only -> closed
UA:* Disallow:/ + Allow:/ -> not closed
UA:* Disallow:/ + Allow:/public/ -> closed
UA:* Disallow:/ + Googlebot Allow:/ -> closed
Googlebot Disallow:/ + UA:* Allow:/ -> wildcard root not closed
comments/blank lines/multiple User-agent lines
```

---

# P1-2. Installer/rollback надо сделать fail-closed

Файлы:

```text
install.sh
rollback.sh
```

## Недостатки текущего архива

1. Installer не проверяет `MANIFEST.sha256` первым gate до payload.
2. После `cp` нет обязательной сверки exact SHA target == payload.
3. Если `bin/repair_stuck_job.php` существовал до патча, failure-restore/rollback удаляет его безусловно.
4. Rollback удаляет CLI без доказательства, что файл принадлежит именно этому патчу.
5. После test failure automatic rollback должен быть byte-verified.
6. Инструкция после install не должна требовать ручной repair CLI как штатный путь — normal worker обязан self-recover.

## Требование

Installer:

```text
verify MANIFEST
-> verify BASE_SHA256
-> validate optional destructive DB test configuration
-> backup 4 service files
-> если repair CLI уже существует — backup его + metadata existed=1
-> copy payload
-> php -l
-> exact target SHA == payload SHA
-> corrective tests
-> если любое падение после copy: automatic rollback + byte verify
-> INSTALL_OK только после всех обязательных gates
```

Rollback:

```text
restore 4 REV3.1 files byte-for-byte
verify BASE_SHA256 after restore
repair CLI:
    если installer backup говорит, что существовал -> restore exact old bytes
    если не существовал -> remove patch-owned file
    если backup metadata нет -> remove ТОЛЬКО если current file byte-identical package-owned CLI
    unknown file -> preserve + warning
```

---

# P1-3. Тесты текущего REV3.2-final недостаточны

Текущие `118 PASS` не доказывают production-path целиком.

Особенно:

- `test_samehost_chain.php` сейчас закрепляет неправильный success для одиночного 3xx без terminal response;
- #227 DB test в основном вызывает decision helpers и **симулирует** эффект redirect repair, а не проходит реальный FTP disable/restore;
- нет production-method test полного URL-follow;
- нет marker-lost crash entry integration;
- нет строгого missing-readback теста;
- нет wildcard/root robots semantics.

Нужно оставить полезные текущие suites и добавить новые, а не заменить их.

---

# Что уже отдельно проверено независимым аудитом

На отдельном working tree были реализованы и проверены следующие corrective направления:

```text
full-URL same-host redirect resolution
no synthetic terminal 200
marker-before-not_required crash recovery
strict applied-rule readback
active vs verified-inactive redirect mechanisms
wildcard/root robots scenarios
installer/rollback preservation of pre-existing repair CLI
```

Также на реальном Mellstroy fixture подтверждено:

```text
legacy index.php содержит ровно один поддерживаемый handleRequest($configRed)
existing site update_classes.php запускается отдельно
templates/1/source/class_name_mapping.txt:
before pairs = 37
after pairs  = 37
changed values = 37/37
```

То есть переписывать сайт `update_classes.php` не надо; проблема остаётся в panel orchestration/preflight/recovery.

---

# Жёсткие границы итогового release

Итоговый ZIP должен быть **одним cumulative patch поверх фактического REV3.1**.

Production payload максимум:

```text
app/Services/RefreshOrchestrator.php
app/Services/RedirectToggler.php
app/Services/SiteVerifier.php
app/Services/DiagnosticCatalog.php
bin/repair_stuck_job.php   # fallback only
```

Если для исправления не доказана необходимость другого production-файла — не добавлять.

Запрещено включать:

```text
guard.php
site index.php
site config.php
site update_classes.php
site robots.php
.htaccess сайта
templates сайта
```

Новой DB migration для этой коррекции не требуется.

Не добавлять hardcode конкретного job/domain/IP в production code.

`wm.own_redirect_domains` остаётся единственным источником own redirect allowlist.

---

# Обязательный DoD нового единого ZIP

До передачи архива:

1. `sha256sum -c MANIFEST.sha256` → all OK.
2. ZIP безопасен: no absolute paths, no `../`, no symlink traversal.
3. Все payload/rollback/tests PHP → `php -l` PASS.
4. `install.sh`, `rollback.sh`, `tests/run_all.sh` → `bash -n` PASS.
5. Все existing corrective suites PASS.
6. Новые production-path tests из этого документа PASS.
7. На окружении с MySQL выполнить DB suite реально; не маркировать его PASS, если он не запускался.
8. Выполнить install/rollback simulation на чистом REV3.1 file baseline:
   - normal install;
   - exact payload SHA;
   - test failure -> verified auto rollback;
   - BASE mismatch -> stop pre-write;
   - MANIFEST mismatch -> stop pre-write;
   - normal rollback -> exact REV3.1 SHA;
   - pre-existing repair CLI preserved/restored.
9. В `REPORT.md` отдельно показать:
   - exact changed production files;
   - SHA payload;
   - BASE REV3.1 SHA;
   - test breakdown;
   - Mellstroy fixture result;
   - что DB/real production canary не подменён локальным тестом.
10. Пересобрать `MANIFEST.sha256` **после последнего изменения любого файла**.
11. Дать SHA-256 итогового ZIP.

---

# Ожидаемый production canary после установки

Для существующей зависшей job:

```text
stage=update_classes_call
uc_execution_state=NULL
uc_execution_attempts=0
```

ожидаем:

```text
own_redirect_active
-> один controlled redirect repair
-> read-back verified
-> preflight terminal evidence OK
-> execution reserved exactly once
-> uc_execution_attempts: 0 -> 1 непосредственно перед mutation
-> update_classes HTTP вызван один раз
-> mapping delta verified
-> переход на следующую стадию
-> позже redirect восстановлен
```

Если own redirect после единственного repair всё ещё активен:

```text
awaiting_user
technical_code=redirect_disable_ineffective
```

без бесконечного polling и без второго mutating call.

**Не создавать новую job. Не запускать repair CLI как штатный первый шаг.**
