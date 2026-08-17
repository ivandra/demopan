# REV3.2-final_2 — DELTA REVIEW / ОСТАВШИЕСЯ БЛОКЕРЫ

Проверен архив:
`refresh-panel_CUMULATIVE_HOTPATCH_2026-08-16_REV3.2-final_2.zip`

SHA-256:
`52e1d5e143da06f17da1e9cf9efe2ff6fc5fada3752040e1167674f4547dd091`

## Вердикт

НЕ устанавливать в production в текущем виде.

Разработчик действительно закрыл значительную часть прошлого corrective review:
- P0-3 strict applied-file readback — исправлен;
- full URL support для `//host/path` и `?query` — добавлен;
- synthetic terminal 200 в exhausted same-host chain — убран;
- stale-scan теперь вызывается до `not_required`;
- `detected_mechanisms` / `verified_inactive_mechanisms` — добавлены;
- основной HTTP robots-path получил wildcard/root helper;
- MANIFEST/BASE/post-copy SHA gate добавлены;
- pre-existing repair CLI backup/restore в installer добавлен;
- updater subsystem удалена из runtime payload — scope снова правильный.

Но ниже остаются реальные blockers.

---

# P0-1 — КРИТИЧНО ДЛЯ РЕАЛЬНОЙ JOB #227:
## HTTP 200 + meta-refresh/JS external redirect preflight всё ещё принимает за наш сайт

Реальный Mellstroy `index.php` на первом посещении возвращает не HTTP `Location`, а:

```html
HTTP 200
<meta http-equiv="refresh"
      content="0;url=https://partners7k-promo.com/...">
```

Текущий `followSameHostChain()` рассматривает HTTP 200 как terminal same-host response.
`preflightUpdateClasses()` не проверяет terminal body на meta-refresh/JS redirect.
После этого `classifyPreflightResult()` принимает terminal 2xx как `site_responsive`.

При независимой проверке текущего payload получено:

```text
terminal_http = 200
terminal_body = meta refresh -> partners7k-promo.com
external_host = null

classification:
ok = true
reason_code = site_responsive
```

То есть текущая сборка может **не увидеть именно тот own redirect, который был причиной зависания #227**.

## Исправление

До принятия terminal 2xx:

```php
$bodyRedirectHost = RedirectToggler::extractRedirectTargetHost($terminalBody, null);
```

Если host найден и это НЕ same logical site:

```text
wm.own_redirect_domains -> own_redirect_active
иначе                  -> external_redirect_unknown
```

Внешний redirect НЕ follow.

Проверка должна выполняться ДО `site_responsive`.

Использовать уже существующий `RedirectToggler::extractRedirectTargetHost()`;
не писать второй parser и не hardcode'ить partners7k.

## Тесты

Обязательно production-path через `preflightUpdateClasses()`:

```text
HTTP 200 + Mellstroy meta-refresh -> partners7k-promo.com
=> ok=false, own_redirect_active

HTTP 200 + window.location='https://partners7k-promo.com/...'
=> own_redirect_active

HTTP 200 + external unknown meta-refresh
=> external_redirect_unknown

HTTP 200 + обычный HTML своего сайта
=> normal evidence logic
```

---

# P0-2 — terminal same-host 2xx сейчас проходит БЕЗ доказательства, что это наш сайт

`classifyPreflightResult()` содержит логику:

```php
if ($tHttp >= 200 && $tHttp < 300) {
    if ($hasMarker || $trustedSiteEvidence) {
        return site_responsive;
    }
    return site_responsive;
}
```

То есть обе ветки дают success.

Сценарий:

```text
new-domain /
 -> 301 same-host /ru/
 -> 200 generic/default-vhost HTML

new_domain_in_html = false
trusted_site_evidence = false
```

сейчас всё равно может стать:

```text
site_responsive
```

Это нарушает смысл preflight.

## Требование

После проверки body redirect:

```text
terminal 2xx
AND (new_domain_in_html || trusted_site_evidence)
=> site_responsive

terminal 2xx
AND no marker
AND no trusted evidence
=> unsupported_site_response / deterministic failure
```

Не возвращать success только потому, что цепочка осталась на том же host.

## Тесты

```text
301 same-host -> 200 generic body, no trusted evidence
=> NOT success

301 same-host -> 200 + new-domain marker
=> success

301 same-host -> 200 + trusted evidence
=> success
```

---

# P0-3 — crash stale-marker scan НЕ видит config.php marker

Текущий stale scan проверяет только:

```text
.htaccess
index.php
```

Но существующий config-based mechanism записывает:

```php
$redirect_enabled = 0; // REFRESH-DISABLED
```

Причём stale regex сейчас ищет преимущественно:

```text
# REFRESH-DISABLED:
 // REFRESH-DISABLED:
```

с двоеточием.

Config marker:
`// REFRESH-DISABLED`
— без `:`.

Следовательно crash-window:

```text
config.php уже записан с redirect_enabled=0
-> worker падает до artifacts
-> redirect_enable tick
-> stale scan не видит config.php
-> not_required
```

может оставить redirect выключенным.

## Требование

В stale scan и post-validation добавить поддерживаемый config mechanism:

```text
config.php
$redirect_enabled = 0
REFRESH-DISABLED
```

Лучше переиспользовать один общий marker detector, а не разводить разные regex в трех местах.

Минимум проверяемых файлов:

```text
.htaccess
index.php
config.php
```

## Тест

```text
config.php active redirect
-> disable writes redirect_enabled=0 + own marker
-> crash before artifacts
-> next enable stage
-> marker detected
-> recovery
-> redirect_enabled=1 / marker removed
-> exact read-back
```

---

# P0-4 — strict stale scan всё ещё fail-open на ошибке чтения файлов

`strict=true` сейчас fail-closed только если:
- не загрузился site/FTP config;
- не удалось FTP connect.

Но если FTP подключён, а `tryGetContent()` по candidate files бросает исключение,
код делает `continue`.

Если все candidate reads не удались:

```text
return []
```

что интерпретируется как:
"маркеры доказанно отсутствуют".

Это неверно.

## Требование

Strict scan должен различать:

```text
PROVEN_ABSENT
READ_OK_NO_MARKER
MARKER_FOUND
READ_FAILED / INSPECTION_INCOMPLETE
```

Если необходимые supported files не удалось надёжно проверить:

```text
scan_failed
-> awaiting_user
```

а не `[] -> not_required`.

## Тест

```text
FTP connect OK
tryGetContent throws / permission error on all candidates
=> awaiting_user
```

---

# P0-5 — crash recovery success не требует `enable().ok`

В recovery branch сейчас:

```php
$recResult = $toggler->enable(...);
$remaining = $this->verifyNoRefreshDisabledMarkers(...);
$recOk = empty($remaining);
```

`$recResult['ok']` не входит в условие success.

То есть enable может сообщить failure, а пустой/неполный verifier — success.

Требование:

```text
recOk =
    recResult.ok === true
    AND strict post-validation completed
    AND markers_remaining is empty
```

---

# P0-6 — post-validation redirect_enable тоже fail-open

`verifyNoRefreshDisabledMarkers()` сейчас:

- если `base_path` отсутствует -> `return []`;
- FTP не настроен -> `return []`;
- DB error -> `return []`;
- FTP connect fail -> `return []`;
- download file fail -> continue;
- проверяет только `.htaccess`, `index.php`;
- не проверяет config marker без двоеточия.

В crash-recovery это означает:
`[]` может означать как "marker нет", так и "ничего не смог проверить".

Так быть не должно.

## Требование

Сделать strict structured result:

```php
[
  'ok' => bool,          // сам scan выполнен надёжно
  'markers' => [...],
  'errors' => [...]
]
```

Recovery success только:

```text
ok=true
markers=[]
```

Не использовать `[]` одновременно как success и inability-to-check.

Для lost-artifacts recovery не полагаться только на `base_path` из artifacts,
потому что artifacts как раз могли потеряться.

---

# P1-1 — robots wildcard/root исправлен только в основном методе, но НЕ в fallback Method 2/3

Основной path `checkRobotsViaCurl()` теперь использует `robotsWildcardRootClosed()`.

Но общий `parseRobotsBody()`, который используется Method 2 / Method 3,
по-прежнему использует старую глобальную логику:

```php
$hasDisallowAll = robotsHasDisallowAll($body);
$hasAllow = robotsHasAllow($body);

$ok = $hasAllow && !robotsIsOnlyDisallowAll($body);
```

Из-за этого fallback может ошибочно принять:

```text
User-agent: *
Disallow: /

User-agent: Googlebot
Allow: /
```

за открытый новый домен.

То же для:

```text
User-agent: *
Disallow: /
Allow: /public/
```

## Требование

Для `expect=allow` в `parseRobotsBody()` использовать ту же wildcard/root semantics,
что и в основном HTTP method.

Не менять old-domain semantics без необходимости.

## Тесты

Тестировать именно parser/fallback path:

```text
UA:* Disallow:/ only -> closed
UA:* Disallow:/ + UA:Googlebot Allow:/ -> closed
UA:* Disallow:/ + Allow:/public/ -> closed
UA:* Disallow:/ + Allow:/ -> open
```

---

# P1-2 — URL resolver теряет trailing slash для path-relative Location

Сейчас:

```text
base:     https://a.test/dir/a
Location: next/
```

разрешается как:

```text
https://a.test/dir/next
```

вместо:

```text
https://a.test/dir/next/
```

При этом loop normalizer ещё и схлопывает `/path` и `/path/`.

На сервере, который канонизирует:

```text
/dir/next -> /dir/next/
```

можно получить ложный `redirect_loop`.

## Требование

- сохранять trailing slash при RFC-style relative resolution;
- не считать `/path` и `/path/` одним visited URL, если сервер реально различает их;
- query сохранять как сейчас.

## Тест

```text
/dir/a -> Location: next/
-> request MUST be /dir/next/
-> terminal 200, no loop
```

---

# P1-3 — `test_preflight_realpath` не полностью deterministic

Test subclass подменяет `probeUrlOnce()`,
но production preflight при пустом terminal body делает:

```php
$this->doUpdateClassesCurl(...)
```

Метод private и тестом не переопределяется.

Следовательно часть тестов может делать реальный outbound curl.
Это не deterministic transport test.

Кроме того второй fetch использует другой UA/FOLLOWLOCATION semantics.

## Требование

Не делать второй скрытый сетевой fetch другим transport'ом.

`probeUrlOnce()` уже получает body.
Если body отсутствует — либо:
- это explicit no-evidence result;
- либо повторный fetch идёт через тот же injectable probe path.

Тесты не должны обращаться во внешний интернет.

---

# P1-4 — redirect_disable www/apex comparison inconsistent

`evaluateRedirectDisableResult()` определяет external примерно:

```php
$redirHost !== '' && $redirHost !== $siteHost
```

Тогда `www.site.com` <-> `site.com` может считаться external,
хотя preflight использует `sameLogicalHost()`.

Использовать одинаковую logical-host policy.

---

# PACKAGING — ЧТО УЖЕ ХОРОШО

В final_2 подтверждено:

- MANIFEST gate до write;
- BASE_SHA gate до write;
- post-copy target SHA == payload;
- pre-existing repair CLI backup/restore;
- updater subsystem отсутствует;
- production scope снова узкий;
- MANIFEST самого архива сходится;
- payload/rollback PHP lint PASS;
- shell syntax PASS;
- rollback hashes соответствуют BASE_SHA.

Это сохранить.

---

# PACKAGING — ЕЩЁ ДОЖАТЬ

1. `ROLLBACK.md` устарел:
   сейчас script умеет restore/preserve pre-existing repair CLI,
   а документация всё ещё описывает unconditional delete.

2. `install.sh` использует:

```bash
set -uo pipefail
```

Для production installer предпочтительно `set -euo pipefail`
ИЛИ каждую backup/copy/mkdir command явно проверять.
Особенно backup failure не должен позволять перейти к mutation.

3. `restore()` не должен только печатать warning при byte mismatch.
Automatic rollback должен завершаться отдельным hard failure,
если rollback не byte-identical.

4. Нужен package-level install/forced-failure/rollback simulation,
а не только утверждение в REPORT.

---

# РЕГРЕССИЯ

REPORT заявляет:

```text
REV3.1 baseline: 164 OK / 20 FAIL
patched:         164 OK / 20 FAIL
delta regressions = 0
```

Это полезно, но это НЕ "full green regression".

Перед production желательно:
- приложить raw baseline/patched logs;
- либо прогнать 20 environment-dependent checks в корректном bootstrap/render environment.

Не маркировать 20 tests как PASS, если они фактически FAIL.

---

# DB TESTS

В `TEST_STDOUT.txt` разработчик показывает:

```text
DB scenarios: 17 PASS / 0 FAIL
TOTAL: 155 PASS / 0 FAIL
```

В независимом окружении повторить DB suite невозможно из-за отсутствия MySQL/PDO driver.
Поэтому developer output считаем evidence, но не независимой DB-verification.

---

# ФИНАЛЬНЫЙ SCOPE

Не менять:
- сайты;
- guard.php;
- site update_classes.php;
- templates;
- state-machine architecture;
- unrelated panel services;
- updater subsystem.

Production payload оставить узким:

```text
RefreshOrchestrator.php
RedirectToggler.php
SiteVerifier.php
DiagnosticCatalog.php
bin/repair_stuck_job.php
```

Никакой новой job #227.

---

# ОБЯЗАТЕЛЬНЫЙ RELEASE GATE ПОСЛЕ ИСПРАВЛЕНИЙ

Перед новым единым ZIP:

```text
[ ] actual Mellstroy HTTP200 meta-refresh -> own_redirect_active
[ ] external JS redirect detected
[ ] same-host terminal generic 200 without evidence -> NOT success
[ ] same-host terminal 200 with marker/trusted evidence -> success
[ ] config.php stale marker crash recovery
[ ] strict scan read failure -> awaiting_user
[ ] recovery requires enable.ok + strict post-validation
[ ] fallback robots parser wildcard/root tests
[ ] relative trailing slash preserved
[ ] preflight tests have zero real network calls
[ ] www/apex comparison consistent
[ ] MANIFEST PASS
[ ] BASE gate PASS
[ ] post-copy SHA PASS
[ ] forced test failure -> byte-verified rollback
[ ] pre-existing repair CLI restored/preserved
[ ] all PHP/shell lint PASS
[ ] DB suite real PASS
[ ] regression delta = 0
```

После этого:
- MANIFEST пересчитать последним;
- собрать ОДИН cumulative `REV3.2-final`;
- распаковать готовый ZIP заново;
- повторить package tests уже из собранного ZIP;
- дать SHA-256 ZIP.

Не присылать hotfix поверх final_2.
