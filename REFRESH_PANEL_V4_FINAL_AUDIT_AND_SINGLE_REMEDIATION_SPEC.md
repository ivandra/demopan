# Refresh Panel — финальный аудит cumulative v4 и единое ТЗ на доведение до релиза

**Дата:** 30.08.2026  
**Проверенный архив:** `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v4.zip`  
**SHA-256 ZIP:** `1437dfd11333752e0981f4f47f58afaaac72fb64b6f488552f1650e369fb9b1d`  
**Итог:** **NO-GO для рабочей панели в текущем виде.**

Это один итоговый список. Не выпускать следующий ZIP после исправления отдельного пункта. Сначала закрыть весь документ, прогнать полный acceptance и только затем собрать один следующий cumulative candidate.

---

## 1. Что в v4 действительно исправлено и НЕ должно переписываться снова

Проверено по diff v3 → v4 и исполнением доступных тестов/скриптов.

1. `rollback.sh` теперь действительно запрещает `BACKUP_BASE` внутри panel root до записи. Реальный запуск возвращает exit 6.
2. Fault-injection выполняет настоящий `rollback.sh`; после fault #1/#2/#3 target восстанавливается byte-for-byte, используется честный статус `ROLLBACK_ABORTED_RECOVERED`.
3. `SubmissionUrlResolver::normalizeUrls()` больше не превращает `https://host:8443/path` в `https://host/path`; non-443 и userinfo отбрасываются, `:443` принимается, case пути страницы сохраняется.
4. В `RefreshOrchestrator` добавлен strict `saveSubmissionEvidence()` с DB write + fresh readback + проверкой phase/count/SHA.
5. Recrawl при неподтверждённом planned-evidence не завершает стадию и назначает retry.
6. IndexNow пишет submitted-evidence только после фактического submit-path; pre-submit reservation существует до POST.
7. Пакетный `MANIFEST.sha256` сходится; rollback-копии совпадают с заявленным baseline.
8. Новый production diff v3 → v4 ограничен двумя файлами: `SubmissionUrlResolver.php` и `RefreshOrchestrator.php`. Новой архитектурной переделки v4 не сделал.
9. Логика кейса `site_error / https_not_served` после 051 присутствует: transport-critical debounce, `http_5xx` остаётся immediate. DB production-path тест проверяет основной сценарий 0 → 0 → 1 alert и anti-spam.

**Эти части не рефакторить и не «улучшать заодно».**

---

# 2. Доказанные остаточные дефекты production-кода

## P1-1. Malformed/truncated sitemap сейчас принимается как валидный partial sitemap

Это прямое нарушение обязательного acceptance `malformed XML → fail-closed`.

Текущий resolver определяет тип XML регуляркой по наличию `<urlset` / `<sitemapindex` и затем регуляркой извлекает `<loc>`. Структурная корректность XML не проверяется.

### Воспроизведено на v4

В transport fixture вернуть HTTP 200 и тело без закрывающего `</urlset>`:

```xml
<urlset><url><loc>https://example.top/partial-page</loc></url>
```

Фактический результат v4:

```text
source = public_sitemap
urls = [
  https://example.top/,
  https://example.top/partial-page
]
```

Ожидание:

```text
malformed sitemap = reject/fail-closed
partial-page НЕ включается
resolver переходит к следующему допустимому sitemap/fallback
```

### Связанный дефект body-cap

`httpGetStrict()` при превышении `MAX_SITEMAP_BYTES` прерывает cURL, затем оставляет и обрезает уже накопленный буфер и отдаёт его parser-у. Такой truncated XML также может быть принят как partial sitemap.

### Требование

- overflow считается невалидным документом, а не «усечённым валидным sitemap»;
- malformed XML не отдаёт ни одного page `<loc>`;
- неизвестный/оборванный root — fail-closed;
- сохранить transport byte cap, не загружать бесконечное тело полностью;
- не менять остальной canonical/recrawl/IndexNow workflow.

### Обязательные tests

1. well-formed `urlset` → PASS;
2. well-formed `sitemapindex` → PASS;
3. missing closing root → reject;
4. broken `<loc>` / broken nesting → reject;
5. body > cap с несколькими complete `<loc>` до места обрыва → **ни один partial URL не принимается**;
6. HTTP 200 HTML с `<loc>` → reject;
7. fallback после malformed работает по текущему контракту.

---

## P1-2. visited-set sitemap case-fold'ит весь URL и может потерять реальные sitemap-документы

В `walkSitemap()` сейчас:

```php
$key = strtolower($url);
```

HTTPS host case-insensitive, но **path и query case-sensitive/meaningful**. Полное `strtolower()` нарушает ранее заданный инвариант «query/path case не fold-ить».

### Воспроизведено на v4

Root sitemap index содержит два допустимых child:

```text
https://example.top/A.xml
https://example.top/a.xml
```

Оба могут быть разными ресурсами на Linux/web server.

Фактически v4 fetch-ит только:

```text
/sitemap.xml
/A.xml
```

`/a.xml` считается уже visited и не загружается.

### Требование

Ключ visited должен нормализовать только то, что действительно case-insensitive по контракту (scheme/host/default port), но сохранять path/query case.

### Обязательные tests

- `/A.xml` и `/a.xml` → оба fetch;
- `?part=A` и `?part=a` → не схлопываются;
- настоящий цикл A→B→A по идентичному URL по-прежнему bounded;
- page URL case preservation не регрессирует.

---

# 3. Остаточные дефекты acceptance/test harness

Это не просьба переписать production. Большинство пунктов должны закрываться **тестами**, а не новыми изменениями ядра.

## P1-3. `tests/run_all.sh` может вывести `RESULT: PASS`, хотя обязательные G/H и J/K не входят в release-summary

В конце runner сейчас:

```text
GROUPS G/H — CARRY-FORWARD ... НЕ integration-proof
```

но `GROUPS_BLOCK` для G/H не увеличивается.

J/K также только выводятся как `INFO` и не участвуют в итоговом счётчике.

Поэтому при PASS A–F/I и наличии env runner способен закончить `RESULT: PASS`, даже если обязательные G/H вообще не выполнены и actual J/K не были запущены этим release gate.

### Требование

Сделать один честный release runner:

- обязательная группа не выполнена → BLOCK;
- G/H `carry-forward` без исполнения → BLOCK, не PASS;
- J/K actual script execution отсутствует → BLOCK;
- итог PASS возможен только после всех обязательных групп;
- `REPORT.md` и runner не должны противоречить друг другу.

Разрешено сохранить отдельные test scripts, но верхнеуровневый release gate обязан собирать их exit codes.

---

## P1-4. Обязательные randomization/update_classes и Webmaster regression G/H не выполнены

Сам `REPORT.md` v4 это честно признаёт:

```text
§16.G = BLOCK
§16.H = BLOCK
```

Это нельзя считать доказанным carry-forward только на основании «код этих подсистем специально не меняли», потому что `RefreshOrchestrator.php` — общий production-файл и он всё равно изменён.

### G — выполнить реальные regression сценарии

Минимум:

- legacy7k;
- r7;
- irvin;
- atomic JSON;
- concurrent reservation / 409;
- ambiguous timeout/reset;
- exactly-one mutating call на execution reservation;
- verify-only resume;
- recovery/re-entry;
- trusted root identity;
- custom contract;
- variant-refresh;
- `operator_skipped` не переоткрывает mutation.

**Не изменять production randomization/update_classes, если эти тесты проходят.**

### H — выполнить реальные Webmaster regression сценарии

Минимум:

- routed quota GET;
- recrawl POST;
- queue GET;
- direct task GET;
- source IP mismatch → fail-closed;
- 409 → reconcile GET, не второй POST;
- `IN_PROGRESS` / `DONE` reconciliation;
- incomplete queue item → typed task GET;
- incomplete direct GET → POST=0;
- `HOST_NOT_VERIFIED`;
- `INVALID_USER_ID`/auth/account;
- unknown 4xx отдельным diagnostic;
- `DONE` не подписывается как «проиндексировано».

**Не изменять Webmaster production-код, если тесты проходят.**

---

## P1-5. Canonical DB test всё ещё не доказывает реальные failure/duplicate-POST сценарии

`test_db_canonical.php` стал лучше v3: он вызывает реальные private production methods через Reflection и реальную test DB. Но критический сценарий duplicate POST всё ещё моделируется вручную:

```text
UPDATE artifacts_json -> indexnow_stage.submit_count=1
assert submit_count exists
```

Это **не** доказывает:

- что реальный `maybeSubmitIndexNow()` сделал один POST;
- что post-evidence write/readback реально упал;
- что следующий настоящий tick делает POST count = 0 для того же attempt;
- что после допустимого retry-интервала работает именно документированный resend, а не duplicate текущего attempt.

Также в suite не найдено реального fault injection для обязательных DB-failure случаев:

- canonical persist/write failure;
- canonical readback mismatch;
- recrawl evidence write failure;
- recrawl evidence readback mismatch;
- IndexNow post-evidence write failure;
- IndexNow post-evidence readback mismatch.

### Требование

Добавить production-path test seam/harness, который реально вызывает IndexNow path с fake transport/service, **считает HTTP POST calls** и умеет инъектировать DB failure на нужной записи/readback.

Обязательные asserts:

```text
canonical failure before plan => recrawl plan count = 0; IndexNow POST = 0
recrawl plan happened + evidence failure => retry; повторный plan идемпотентен
real IndexNow POST = 1
post-evidence failure => immediate next tick POST = 0
post-evidence recovery => evidence можно подтвердить без duplicate текущего attempt
следующий POST разрешается только по штатному resend schedule
```

Если production-код эти тесты уже проходит — production не менять.

---

## P1-6. SSL/robots/pipeline acceptance покрыт не полностью

### SSL

DB production test проверяет основной `https_not_served` 2/60, anti-spam, candidate clear, reason reset и `http_5xx`. Но обязательный пункт 6.4.1(6) отсутствует как production-path: **новая независимая подтверждённая outage после полного recovery**.

Также fresh provisioning и manual never-trusted в основном проверены pure decision-тестом, а не реальным `saveResult()` + DB + Telegram-stub.

Добавить production-path сценарии без переписывания SSL-кода.

### robots

Сейчас основная матрица — pure `RobotsCheckDecision`, а часть wiring проверяется regexp'ом в source. Нужен actual-stage integration для:

- retry действительно записал `stage_status=pending` и `next_run_at`;
- blocked robots → реальный IndexNow POST count = 0;
- foreign redirect → конечный awaiting_user;
- strict TLS / neutral UA на production transport seam;
- API 401 best-effort не подменяет public-check;
- routing fatal остаётся fail-closed;
- event source/severity соответствует причине.

### Pipeline/presentation

`test_pipeline_integration.php` реально вызывает `nextStageFor()`, это хорошо. Но обязательные presentation checks отсутствуют:

- human text соответствует фактическому next stage;
- новые event codes испускаются из реальных веток;
- severity/source не перевёрнуты;
- `DiagnosticCatalog`/эквивалент не показывает «успех» для fail-closed причины и наоборот.

Закрыть тестами. Production менять только если тест обнаружил реальное расхождение.

---

# 4. Runtime/staging gate

v4 прогонялся разработчиком на PHP 8.3, а target — PHP 8.0. Сам код по статическому просмотру не показывает очевидной PHP 8.1+-синтаксической зависимости, но нормативный `/opt/php80/bin/php` acceptance **не выполнен**.

До выдачи финального candidate должны пройти на копии/стенде с target runtime:

- PHP 8.0 lint всех payload-файлов;
- full release runner;
- isolated test DB;
- migration 051 + повторная migration idempotency;
- schema verification;
- install/rollback/fault suite;
- G/H regression.

### Важное уточнение к прежнему master-spec

Production canary физически возможен только **после контролируемой установки**. Поэтому разделяем два gate:

1. **До передачи ZIP для установки:** все offline/staging/DB/regression/install/rollback tests = PASS. Никаких SKIP/BLOCK.
2. **После FileZilla + migration, до массового использования:** одна чистая refresh-job через UI = canary PASS.

Пользователь не обязан выполнять SSH-команды для canary.

---

# 5. Обязательный no-SSH способ установки для владельца панели

Это пользовательское эксплуатационное требование. В предыдущем master-spec было записано «миграция через интерфейс панели», но полный **FileZilla-only install path** не был выделен отдельным release-blocking сценарием. Закрыть это сейчас.

Финальный ZIP должен позволять штатную установку так:

1. сделать backup текущих изменяемых файлов через FileZilla/панель хостинга;
2. загрузить **содержимое `payload/` в корень Refresh Panel** с сохранением путей;
3. зайти в Refresh Panel;
4. выполнить штатную **«Миграцию»** через интерфейс панели;
5. интерфейс должен завершить 051 без ручного SQL;
6. пользователь не запускает `mysql`, `ALTER TABLE`, `public/migrate.php` по SSH и не использует `probe_051.php` CLI;
7. после миграции панель открывается, SSL/джобы/диагностика доступны;
8. запустить одну чистую refresh-job через UI и проверить canary;
9. только после canary разрешить обычную работу.

`install.sh`/`probe_051.php` можно и нужно сохранить **для developer/staging acceptance**, но они не являются обязательным пользовательским способом установки.

В ZIP добавить короткий `FILEZILLA_INSTALL.md` с точными 8 payload paths:

```text
app/Migrations/051_ssl_transient_alert_confirmation.php
app/Services/DomainSslCheckService.php
app/Services/DomainSslStatusRepository.php
app/Services/RefreshOrchestrator.php
app/Services/RobotsCheckDecision.php
app/Services/SiteVerifier.php
app/Services/SubmissionUrlResolver.php
app/Views/ssl/show.php
```

Если существующий интерфейс миграций уже показывает успешное применение 051, **не добавлять новый UI ради этого документа**. Достаточно документировать и проверить существующий путь.

---

# 6. CHANGE FREEZE для следующей и последней корректирующей сборки

## Production-код разрешено менять только так

### Разрешено без дополнительного согласования

`app/Services/SubmissionUrlResolver.php`:

- malformed/truncated XML fail-closed;
- overflow fail-closed;
- visited identity без path/query case-fold.

### Не менять, если новые обязательные тесты проходят

- `DomainSslCheckService.php`;
- `DomainSslStatusRepository.php`;
- `RobotsCheckDecision.php`;
- `SiteVerifier.php`;
- randomization/update_classes production logic;
- Webmaster routing/reconciliation production logic;
- migration 051;
- pipeline architecture;
- public API/stage names/UI texts;
- canonical/recrawl/IndexNow workflow сверх уже реализованного durable-evidence fix.

`RefreshOrchestrator.php` **не менять для красоты/cleanup**. Его можно менять только если новый реальный failure/duplicate test докажет конкретный production defect. В этом случае REPORT обязан привести reproducer до изменения и PASS после изменения.

Никакой migration 052 без отдельного доказательства необходимости. Для перечисленных текущих дефектов новая DB migration не нужна.

Если тест выявил проблему вне этого списка:

```text
SCOPE_EXPANSION_REQUIRED
requirement_id=...
file=...
reproducer=...
why_existing_code_fails=...
minimal_change=...
risk=...
```

Сначала зафиксировать в REPORT; не делать инициативный рефакторинг соседнего кода.

---

# 7. Единый acceptance перед передачей следующего ZIP

Следующий ZIP **не передавать после первого найденного/исправленного пункта**.

Один верхнеуровневый release runner обязан дать только один из двух результатов:

```text
RELEASE_ACCEPTANCE = PASS
```

или

```text
RELEASE_ACCEPTANCE = NO
```

PASS допускается только если одновременно:

- package/manifest/baseline PASS;
- PHP 8.0 lint PASS;
- SSL unit + production DB PASS;
- robots unit + actual-stage integration PASS;
- sitemap/URL PASS, включая malformed/overflow/case-sensitive visited;
- canonical DB/failure/real duplicate-POST PASS;
- pipeline/presentation PASS;
- randomization G PASS;
- Webmaster H PASS;
- isolated DB safety PASS;
- migration 051 fresh/idempotent/backfill PASS;
- install actual PASS;
- rollback actual/containment/fault PASS;
- staging copy PASS;
- no mandatory SKIP/BLOCK.

J/K/G/H нельзя выводить только как `INFO`/`CARRY-FORWARD` и затем давать общий PASS.

---

# 8. Post-install canary через интерфейс, без SSH

После controlled FileZilla deployment + штатной migration:

1. открыть панель и убедиться, что нет PHP/DB fatal;
2. проверить страницу SSL;
3. запустить **одну новую чистую refresh-job** через UI;
4. job не должна остановиться из-за новых технических ошибок patch-а;
5. `update_classes` не должен сделать duplicate mutation;
6. Webmaster recrawl/reconciliation не должен получить routing regression;
7. `wm_robots`/IndexNow gate соответствует public robots state;
8. recrawl/IndexNow используют один canonical URL set;
9. при кратком `site_error/https_not_served` первый красный Telegram не отправляется после 051;
10. только после этого запускать обычный поток задач.

Если canary FAIL — восстановить 6 baseline-файлов из backup через FileZilla. Добавленные `SubmissionUrlResolver.php` и migration-файл можно оставить только если baseline безопасно их игнорирует; предпочтительно выполнить документированную FileZilla rollback-инструкцию из пакета.

---

# 9. Copy-paste prompt разработчику

> Подготовь ОДИН следующий cumulative candidate Refresh Panel. Не присылай промежуточный ZIP после исправления одного пункта. Сначала выполни весь документ `REFRESH_PANEL_V4_FINAL_AUDIT_AND_SINGLE_REMEDIATION_SPEC.md`, затем запусти полный release acceptance и исправь всё, что он найдёт внутри своей итерации. Production scope заморожен: без отдельного доказанного reproducer разрешена только локальная правка `SubmissionUrlResolver.php` для malformed/truncated XML fail-closed, overflow fail-closed и case-sensitive visited identity. Не переписывай SSL, robots, randomization/update_classes, Webmaster, pipeline и canonical/IndexNow workflow, если новые regression tests проходят. `RefreshOrchestrator.php` меняй только если реальный fault/duplicate test докажет конкретный дефект; приложи reproducer до и PASS после. Новую миграцию не добавляй: 051 должна остаться единственной. Исправь release runner так, чтобы G/H/J/K и все обязательные integration tests реально участвовали в итоговом exit code; никакой общий PASS при CARRY-FORWARD/SKIP/BLOCK. Добавь реальные DB failure tests canonical/recrawl/IndexNow с подсчётом фактических POST, SSL production-path недостающих сценариев, robots actual-stage и pipeline/presentation tests. Прогоняй на PHP 8.0 и изолированной test DB. В итоговом REPORT дай requirement→test→command→result. Добавь `FILEZILLA_INSTALL.md`: владелец ставит только через FileZilla + штатную кнопку миграции в панели, без SSH и ручного SQL. Передавай ZIP только после `RELEASE_ACCEPTANCE = PASS` без обязательных SKIP/BLOCK.

---

# 10. Итог по v4

v4 не является очередным «почти плохим» патчем: четыре главных дефекта v3 в коде в основном закрыты, rollback заметно укреплён, scope v3→v4 маленький.

Но **заливать v4 в рабочую панель сейчас нельзя**, потому что:

1. найдено два воспроизводимых дефекта `SubmissionUrlResolver` (malformed/truncated XML и case-fold visited);
2. обязательные G/H не выполнены;
3. release runner не блокирует общий PASS на пропущенных G/H/J/K;
4. critical canonical failure/duplicate-POST acceptance всё ещё частично моделирует состояние вместо фактического production action;
5. SSL/robots/presentation matrix закрыта не полностью production-path тестами;
6. target PHP 8.0/staging gate не выполнен;
7. no-SSH FileZilla operator path должен быть формально проверен и документирован.

Следующая внешняя проверка должна проверять уже **один полностью зелёный candidate**, а не начинать новую цепочку микропатчей.
