# Refresh Panel — ФИНАЛЬНОЕ ТЗ НА РЕЛИЗ v5

Дата: 2026-08-30  
Исходный кандидат: `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v5.zip`  
Цель: **довести именно v5 до окончательного production-GO одним финальным циклом, без серии v6/v7/v8 и без расширения scope.**

---

# 0. ГЛАВНОЕ ПРАВИЛО

Это не ТЗ на новую функциональность и не приглашение к дополнительному рефакторингу.

Текущий v5 уже считается основным release candidate. Из известных по предыдущему аудиту воспроизводимых production-дефектов в нём на текущий момент не осталось открытых пунктов. Сейчас требуется **закрыть недостающие release-gates на целевой среде и доказать отсутствие регрессий**.

## Разработчик НЕ должен отдавать промежуточные ZIP

Запрещён процесс:

1. запустить один тест;
2. найти один дефект;
3. исправить;
4. сразу прислать `v6`;
5. потом прислать `v7` по следующему тесту.

Правильный процесс:

1. взять v5 как ground-truth;
2. выполнить **весь** acceptance из этого документа;
3. если что-либо упало — исправить минимально;
4. повторить **весь** acceptance с начала;
5. продолжать у себя до тех пор, пока одновременно не будет `0 FAIL`, `0 BLOCK`, `0 SKIP`;
6. только после этого выдать **один финальный ZIP**.

Финальный runner имеет право закончиться только так:

```text
RELEASE_ACCEPTANCE = PASS
FAIL = 0
BLOCK = 0
SKIP = 0
```

Любой другой итог означает: **архив пользователю не передавать, работу продолжить у себя.**

---

# 1. GROUND-TRUTH: ЧТО УЖЕ СЧИТАЕТСЯ ИСПРАВЛЕННЫМ

Не переделывать перечисленные механизмы заново. Они остаются regression scope и должны только подтверждаться тестами.

## 1.1. SSL / Telegram alert

Сохранить текущее поведение после migration 051:

- `site_error -> https_not_served` относится к transport/transient-кандидату;
- первая краткая ошибка не отправляет красный Telegram alert;
- подтверждение — минимум 2 одинаковых измерения и минимум 60 секунд;
- до подтверждения alert = 0;
- после подтверждения — один alert;
- одинаковая продолжающаяся авария не создаёт alert storm;
- recovery очищает candidate;
- новая независимая авария после recovery снова может пройти обычную 2/60 процедуру;
- смена `reason`/`transport_error_kind` не наследует старый streak;
- `http_5xx` не переводить в этот debounce, если текущая принятая политика оставляет его immediate.

**SSL-код не переписывать, если финальные regression tests проходят.**

## 1.2. Sitemap / SubmissionUrlResolver

Сохранить v5-поведение:

- malformed/truncated XML -> fail-closed;
- overflow/body cap -> fail-closed;
- HTML с `<loc>` не является sitemap XML;
- `/A.xml` и `/a.xml` считаются разными sitemap URL;
- lower-case допустим только для scheme/host identity, но не для path/query;
- page URL с userinfo исключается;
- page URL с `:8443` исключается;
- explicit `:443` допускается;
- HTTP URL и foreign-host URL не попадают в canonical page set;
- nested sitemap depth/budget остаются bounded.

## 1.3. Canonical -> recrawl -> IndexNow

Это не должно перепроектироваться снова.

Сохранить действующий контракт:

```text
Sitemap/config -> canonical URL set A -> persisted/readback A
                                  |-> recrawl uses A
                                  |-> IndexNow uses persisted A
```

Если оба consumer реально выполнились:

```text
canonical_sha256
== recrawl.planned_sha256
== indexnow.submitted_sha256
```

Обязательные свойства:

- IndexNow не должен повторно резолвить изменившийся sitemap и получать B вместо A;
- missing/corrupt canonical -> IndexNow POST = 0;
- canonical persist/readback failure -> downstream mutation = 0;
- evidence сохраняется и проверяется readback;
- failure после реального IndexNow POST не должен вызывать немедленный duplicate POST;
- следующий POST разрешён только штатной resend/retry логикой.

**Не менять canonical/recrawl/IndexNow архитектуру, если обязательные финальные тесты проходят.**

## 1.4. Migration 051

Оставить единственную migration 051:

`051_ssl_transient_alert_confirmation.php`

Требования остаются:

- fresh apply PASS;
- повторный apply idempotent;
- требуемые колонки существуют;
- backfill/DEFAULT/nullability соответствуют текущей реализации;
- старые данные не повреждаются;
- ручной SQL владельцу не нужен.

**Migration 052 не создавать**, если реальный новый defect не доказывает объективную необходимость изменения схемы.

## 1.5. Install / rollback

Сохранить уже исправленные свойства:

- MANIFEST/baseline checks;
- backup containment;
- backup внутри panel root запрещён;
- rollback fault injection восстанавливает target byte-for-byte;
- корректное сообщение `ROLLBACK_ABORTED_RECOVERED` при успешном аварийном восстановлении;
- install/rollback developer scripts остаются test/staging tooling, а не обязательным пользовательским способом установки.

---

# 2. CHANGE FREEZE — ЖЁСТКОЕ ОГРАНИЧЕНИЕ ПРАВОК

## 2.1. Базовое правило

Если нижеперечисленные недостающие тесты проходят на текущем production-коде v5, **production-код не менять вообще**.

Идеальный финальный release допускает:

```text
production diff v5 -> FINAL = 0 files
```

Можно менять test harness, fixtures, runner, документацию и release-report, если это нужно для доказательства acceptance.

## 2.2. Запрещено делать «заодно»

Без нового воспроизводимого дефекта запрещено:

- рефакторить `RefreshOrchestrator.php`;
- менять state-machine/stage graph;
- менять `update_classes`/randomization contracts;
- менять Webmaster routing/reconciliation;
- менять SSL debounce/state;
- менять robots policy;
- менять canonical workflow;
- менять IndexNow resend policy;
- переименовывать production classes/methods ради чистоты;
- менять public API/stage names/UI просто ради улучшения;
- добавлять migration 052;
- менять тексты/цвета/diagnostic severity без доказанного mismatch;
- расширять затронутые файлы только потому, что они рядом по коду.

## 2.3. Если обязательный тест действительно обнаружил production defect

Тогда разрешён только минимальный исправляющий diff.

До изменения в `REPORT.md` обязательно записать:

```text
SCOPE_EXPANSION_REQUIRED
requirement_id=<ID этого ТЗ>
file=<production file>
reproducer=<точный падающий сценарий>
expected=<ожидаемое>
actual=<фактическое>
root_cause=<причина>
minimal_change=<минимальная правка>
risk=<что может затронуть>
```

После исправления:

- добавить regression test;
- повторить весь suite;
- не присылать промежуточный ZIP.

---

# 3. ЧТО ИМЕННО НЕ ЗАКРЫТО В v5

По `REPORT.md` v5 выполненные группы PASS, но остаются 4 обязательных BLOCK:

1. target runtime PHP 8.0;
2. G(full) — полный E2E randomization/update_classes;
3. H — полный E2E Webmaster routing/reconciliation;
4. robots(actual-stage) — реальная стадия robots с DB state/next_run_at/IndexNow gate.

Именно эти четыре gate необходимо закрыть. Не искать искусственно новый scope вне них, если текущий полный regression остаётся зелёным.

---

# 4. FINAL GATE A — ЦЕЛЕВОЙ PHP 8.0

Acceptance выполнить **на той же major/minor версии PHP, что используется Refresh Panel production**.

Ожидаемый runtime:

```text
/opt/php80/bin/php
PHP 8.0.x
```

## Обязательно

1. `/opt/php80/bin/php -v` показывает PHP 8.0.
2. `/opt/php80/bin/php -l` проходит для каждого PHP-файла payload/test helper, который исполняется в acceptance.
3. Проверить required extensions, минимум те, от которых реально зависит патч:
   - PDO/MySQL;
   - SimpleXML;
   - mbstring, если production код действительно его использует в данном path.
4. `tests/run_all.sh` должен быть запущен с:

```text
PHP_BIN=/opt/php80/bin/php
```

5. Никакой PASS на 8.3 не заменяет PASS на 8.0.

Acceptance:

```text
PHP8.0 = PASS
```

---

# 5. FINAL GATE G(full) — RANDOMIZATION / update_classes E2E

Цель: доказать, что изменения общего orchestrator/pipeline не сломали ранее работающий механизм обновления классов и recovery/re-entry.

## 5.1. Требование к harness

Тест должен исполнять **production-path**, а не копию алгоритма в тесте и не grep/regexp исходника.

Допустим контролируемый fake/local HTTP/FTP/Wm transport, но:

- вызываются реальные production services/stage methods;
- transport записывает фактические mutating/read-only calls;
- можно инъектировать timeout/reset/409/ambiguous response;
- test DB изолирована;
- никакие реальные клиентские сайты не мутируются.

## 5.2. Обязательная матрица

Проверить минимум:

### G-01 Legacy 7k family

- корректный contract;
- ровно один mutating call;
- verify подтверждает semantic result.

### G-02 r7 family

- текущий r7 contract не подменён generic path;
- mutation count <= 1 на reservation.

### G-03 irvin family

- family-specific contract сохраняется.

### G-04 atomic JSON normal success

- baseline снят **до** reservation/mutation;
- mutation = 1;
- response 2xx/`ok:true` сам по себе не является единственным доказательством;
- verify подтверждает конечное состояние.

### G-05 HTTP 409/concurrent

- никакого автоматического второго mutation call;
- перейти в reconcile/verify-only согласно текущему contract.

### G-06 ambiguous timeout/reset после отправки

- mutation call count не становится 2;
- execution считается ambiguous;
- дальнейшая проверка read-only.

### G-07 verify-only resume

- после re-entry/recovery выполняются только допустимые read-only checks;
- новая mutation не открывается без нового законного reservation.

### G-08 baseline-before-HTTP

Прямой assert порядка событий:

```text
baseline captured < execution reserved < mutating HTTP
```

### G-09 recovery/re-entry

- уже proven execution не переоткрывается;
- ambiguous execution не создаёт duplicate mutation.

### G-10 trusted root / FTP identity

- ранее подтверждённая identity не заменяется соседним/автоопределённым root.

### G-11 custom contract

- custom family не подменяется auto-detection.

### G-12 variant-refresh

- существующий вариант refresh работает по своему contract.

### G-13 operator_skipped

- `operator_skipped` не возвращается в автоматическую mutation ветку.

## 5.3. Главный инвариант G

Для каждого одного execution reservation:

```text
mutating_call_count <= 1
```

При ambiguous result:

```text
next automatic action = read-only verify/reconcile
```

а не второй mutation.

Acceptance:

```text
G(full) = PASS
```

Если всё проходит — production randomization/update_classes **не менять**.

---

# 6. FINAL GATE H — YANDEX WEBMASTER ROUTING / RECONCILIATION E2E

Цель: доказать отсутствие regression в уже работающем Webmaster path.

## 6.1. Harness

Использовать реальные production methods/services с контролируемым Webmaster transport.

Harness должен уметь:

- записывать каждый GET/POST;
- фиксировать выбранный routed account/transport;
- моделировать assigned source IP и actual source IP;
- возвращать typed responses для quota/queue/task;
- моделировать 409 и 4xx;
- считать фактические POST attempts.

Нельзя доказывать эту группу только pure decision-функцией или regexp исходника.

## 6.2. Обязательная матрица

### H-01 Routed quota GET

Quota GET использует тот же назначенный routed transport/account.

### H-02 Recrawl POST

POST выполняется через назначенный route.

### H-03 Queue GET

Queue reconciliation использует тот же route.

### H-04 Direct task GET

Typed task GET использует тот же route.

### H-05 Source-IP mismatch

Если:

```text
expected_source_ip != actual_source_ip
```

то:

- fail-closed;
- POST не продолжать через default route;
- fallback на другой transport запрещён.

### H-06 HTTP 409

После единственного POST, вернувшего 409:

- второй POST = 0;
- выполняется reconcile GET.

### H-07 IN_PROGRESS reconciliation

Существующая внешняя task может восстановить локальный state без повторного POST.

### H-08 DONE reconciliation

- локальное состояние восстанавливается;
- DONE не подписывается как «страница уже проиндексирована», если API этого не гарантирует.

### H-09 Incomplete queue item

Если queue item неполный:

- сделать typed direct task GET;
- не угадывать состояние.

### H-10 Incomplete direct task GET

Если typed GET тоже не даёт достаточного доказательства:

```text
POST count = 0
state = fail-closed/retry diagnostic
```

### H-11 invalid date_from

`date_from_filter_rejected` используется только при доказанном contract rejection этого параметра.

### H-12 HOST_NOT_VERIFIED

Перейти в host verification/wait branch, а не generic error.

### H-13 INVALID_USER_ID / auth/account

Это account/routing blocker, а не host error и не generic date_from.

### H-14 unknown 4xx

Отдельный fail-closed diagnostic; не маскировать известной причиной.

## 6.3. Главный инвариант H

Для одной логической recrawl submission:

```text
POST attempts <= 1 до reconciliation результата
```

И весь связанный API path использует один назначенный route.

Acceptance:

```text
H = PASS
```

Если всё проходит — production Webmaster logic **не менять**.

---

# 7. FINAL GATE ROBOTS(actual-stage)

Цель: перестать доказывать robots только pure-моделью и выполнить настоящий stage path.

## 7.1. Harness

Исполнить фактическую production-стадию `wm_robots` / `runWmRobotsStage` либо её реальный эквивалент.

Допускается fake SiteVerifier / fake HTTP / fake Webmaster diagnostic / fake IndexNow transport, но:

- вызывается настоящий stage method;
- используется настоящая test DB/state persistence;
- проверяются `stage_status`, `next_run_at`, events и следующий stage;
- IndexNow POST counter должен считать реальный вызов transport seam, а не вручную выставленный JSON marker.

## 7.2. Обязательные сценарии

### R-01 Valid public robots

- verified;
- stage продолжает pipeline;
- Hook A/IndexNow разрешается только по принятой текущей логике.

### R-02 Transport failure — первый transient

- stage остаётся `pending`/retry;
- `next_run_at` реально записан в DB;
- IndexNow POST = 0.

### R-03 Transport failure — bounded retry exhaustion

- после установленного числа попыток конечное состояние соответствует действующей policy;
- никакого бесконечного busy-loop.

### R-04 Invalid robots content

- сначала bounded retry;
- после исчерпания — block/awaiting_user согласно принятой логике;
- IndexNow POST = 0.

### R-05 Foreign-host redirect

- не считается verified robots текущего host;
- bounded retry -> конечный `awaiting_user`/block;
- IndexNow POST = 0.

### R-06 Strict TLS

Production HTTP seam не должен молча отключать TLS verification для robots public check.

### R-07 Neutral User-Agent

Проверить используемый production UA согласно принятому contract, без подмены crawler-specific UA, если это ранее зафиксированная политика.

### R-08 Webmaster diagnostic 401/404/best-effort

Внешний диагностический API не имеет права подменить результат public robots check.

Если public robots valid, а diagnostic API недоступен:

- основная stage не должна стать false failure только из-за diagnostic API;
- событие остаётся diagnostic/best-effort.

### R-09 Routing fatal

Если diagnostic API требует route и route доказанно invalid/mismatched — соответствующий routing diagnostic должен оставаться fail-closed там, где это влияет на operation; не маскировать его generic robots content error.

### R-10 Event severity/source

Проверить реальные emitted events:

- transport != content_invalid;
- foreign_host != transport;
- diagnostic warning != primary stage critical failure;
- verified success не показывается красной ошибкой.

## 7.3. Главный robots gate

Ключевой assert:

```text
robots not verified/blocked => IndexNow POST count = 0
```

и для transient retry:

```text
stage_status = pending
next_run_at > now
```

Acceptance:

```text
robots(actual-stage) = PASS
```

Если проходит — production robots code не менять.

---

# 8. ПОЛНЫЙ CARRY-FORWARD REGRESSION ПОСЛЕ ЛЮБОЙ ПРАВКИ

Даже если реальная правка потребовалась только в одном месте, после неё снова выполнить весь существующий suite v5.

Минимально:

```text
A static/package
B-E unit/static
F pipeline integration
I DB
G(part)
G(full)
H
robots(actual-stage)
J/K install/rollback/fault
PHP8.0
```

Плюс уже существующие tests v5:

- SSL classification;
- SSL production DB;
- robots decision;
- SubmissionUrlResolver;
- canonical parity;
- canonical DB;
- IndexNow evidence/real POST-count;
- migration 051;
- pipeline integration;
- randomization deciders;
- actual install;
- actual rollback;
- rollback fault injection.

Никакой mandatory group не может называться `INFO`, `CARRY-FORWARD`, `NOT RUN` и при этом позволять общий PASS.

---

# 9. TEST DB И БЕЗОПАСНОСТЬ ACCEPTANCE

Все DB/integration/fault tests выполнять только на отдельной тестовой базе.

Обязательная fail-closed защита:

- имя test DB задаётся явно;
- никакого fallback на production DB;
- имя соответствует allow-pattern;
- test DB != production DB;
- переменная отсутствует -> exit non-zero **до connect/DDL**;
- production DB name -> exit non-zero;
- invalid test DB name -> exit non-zero.

Запрещено выполнять destructive acceptance на production БД Refresh Panel.

---

# 10. ЕДИНЫЙ RELEASE RUNNER

Доработать `tests/run_all.sh` или добавить его финальный эквивалент так, чтобы он **реально запускал** G(full), H, robots(actual-stage), J/K и PHP8.0 gate, а не безусловно печатал BLOCK.

## 10.1. Итог runner

Он должен считать:

```text
PASS=<n>
FAIL=<n>
BLOCK=<n>
SKIP=<n>
```

И только при:

```text
FAIL=0
BLOCK=0
SKIP=0
```

вывести:

```text
RELEASE_ACCEPTANCE = PASS
```

## 10.2. Запрещённые обходы

Нельзя:

- удалить обязательный тест из runner;
- перевести обязательный тест в INFO;
- заменить actual-stage чистой моделью и назвать это E2E;
- заменить реальный HTTP POST counter ручной записью marker в DB;
- считать PHP 8.3 эквивалентом target PHP 8.0;
- объявить production subsystem «не менялся» и поэтому не запускать его regression, если он входит в обязательный gate.

---

# 11. ФОРМАТ ФИНАЛЬНОЙ ПОСТАВКИ

Выдать **один** архив, например:

```text
refresh-panel_CUMULATIVE_PATCH_2026-08-30_FINAL.zip
```

Версионное имя не принципиально; принципиально, что это один окончательный cumulative package.

В архиве обязательно:

```text
REPORT.md
ACCEPTANCE_STDOUT.txt
MANIFEST.sha256
BASE_SHA256.txt
FILEZILLA_INSTALL.md
payload/
rollback/
tests/
install.sh
rollback.sh
install/probe_051.php
```

## REPORT.md должен содержать

1. точный baseline;
2. production diff `v5 -> FINAL`;
3. если production diff = 0 — написать это явно;
4. если production-файл изменён — requirement ID + reproducer + minimal fix;
5. точную PHP version;
6. exact commands всех mandatory groups;
7. summary всех groups;
8. `FAIL=0 BLOCK=0 SKIP=0`;
9. `RELEASE_ACCEPTANCE = PASS`;
10. migration 051 fresh/idempotent PASS;
11. G(full) PASS;
12. H PASS;
13. robots(actual-stage) PASS;
14. actual install/rollback/fault PASS;
15. canonical/IndexNow evidence PASS;
16. SSL 2/60 production regression PASS.

`ACCEPTANCE_STDOUT.txt` должен быть полным stdout реального финального runner без ручной правки результата.

---

# 12. УСТАНОВКА ВЛАДЕЛЬЦЕМ — ТОЛЬКО FileZilla + UI MIGRATION

Финальная поставка должна сохранять пользовательский способ установки без SSH.

Владелец делает:

1. backup текущих изменяемых файлов через FileZilla;
2. загружает содержимое `payload/` в корень панели с сохранением путей;
3. открывает Refresh Panel;
4. идёт в **Настройки -> «📦 Миграции БД»**;
5. нажимает штатную кнопку применения миграций;
6. 051 применяется автоматически;
7. никакого ручного `mysql`, `ALTER TABLE`, `public/migrate.php`, `probe_051.php` через SSH;
8. после миграции запускает одну чистую canary refresh-job через UI.

`install.sh` и `probe_051.php` остаются developer/staging tooling и не являются обязательными действиями владельца.

---

# 13. POST-INSTALL CANARY — ПОСЛЕДНИЙ USER GATE

Этот пункт выполняется уже после того, как разработчик отдал архив с `RELEASE_ACCEPTANCE = PASS`.

Через UI, без SSH:

1. панель открывается без PHP/DB fatal;
2. SSL page открывается;
3. migration 051 показана как применённая;
4. запустить **одну новую чистую refresh-job**;
5. job проходит обычный pipeline без новой technical failure;
6. `update_classes` не делает duplicate mutation;
7. Webmaster recrawl/reconciliation не получает routing regression;
8. robots gate не запускает IndexNow до verified state;
9. recrawl и IndexNow используют один canonical set;
10. краткий первый `site_error/https_not_served` не даёт немедленный красный Telegram alert;
11. после успешного canary разрешить обычный поток.

Если canary падает — обычный поток не запускать; выполнить документированный FileZilla rollback.

---

# 14. STOP CONDITIONS: КОГДА НЕЛЬЗЯ ПЕРЕДАВАТЬ FINAL ZIP

Не отдавать пакет пользователю, если существует хотя бы одно из условий:

- PHP8.0 не запущен;
- G(full) BLOCK/FAIL;
- H BLOCK/FAIL;
- robots(actual-stage) BLOCK/FAIL;
- любой mandatory SKIP;
- любой старый regression test FAIL;
- DB acceptance запущен не на изолированной test DB;
- install/rollback/fault не выполнены настоящими scripts;
- production defect найден, но regression test не добавлен;
- scope расширен без `SCOPE_EXPANSION_REQUIRED`;
- migration требует ручного SQL владельца;
- для установки владельцу обязателен SSH.

---

# 15. КРИТЕРИЙ «ГОТОВО»

Работа считается завершённой только при одновременном выполнении:

```text
Target PHP 8.0                         PASS
Static/package                         PASS
SSL unit + production DB               PASS
robots unit                            PASS
robots actual-stage                    PASS
SubmissionUrlResolver                  PASS
canonical DB                           PASS
IndexNow real POST/evidence             PASS
pipeline integration/presentation      PASS
randomization G(part)                  PASS
randomization G(full)                  PASS
Webmaster H                            PASS
migration 051 fresh/idempotent         PASS
DB safety                              PASS
actual install                         PASS
actual rollback                        PASS
rollback fault injection               PASS
FAIL                                   0
BLOCK                                  0
SKIP                                   0
RELEASE_ACCEPTANCE                     PASS
```

После этого пакет разрешено передать владельцу для FileZilla install + UI migration + одной canary job.

---

# 16. ГОТОВЫЙ PROMPT РАЗРАБОТЧИКУ

Ниже текст можно передать исполнителю без дополнительных пояснений.

> Возьми `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v5.zip` как единственный ground-truth release candidate. Не создавай очередной промежуточный патч после первого найденного дефекта. Твоя задача — довести v5 до одного FINAL archive, выполнив полностью `REFRESH_PANEL_V5_FINAL_RELEASE_TZ.md`.
>
> Не расширяй production scope и не делай рефакторинг «заодно». Если четыре оставшихся mandatory gate проходят на текущем v5, production diff v5->FINAL должен быть 0. Production-код разрешено менять только если реальный обязательный production-path/E2E test воспроизводит конкретный дефект. До изменения зафиксируй `SCOPE_EXPANSION_REQUIRED`, reproducer, expected/actual и minimal fix; затем добавь regression test и снова прогони весь acceptance.
>
> Обязательно закрой на целевой среде: PHP 8.0, G(full) randomization/update_classes E2E, H Webmaster routing/reconciliation E2E и robots(actual-stage). Это должны быть реальные production-path tests с контролируемыми transport seams/test DB, а не regexp/source inspection, не копия алгоритма в тесте и не вручную выставленные DB markers.
>
> Сохрани все уже закрытые инварианты v5: SSL `site_error -> https_not_served` 2/60 без первого мгновенного Telegram alert; HTTP 5xx текущую policy; malformed/overflow sitemap fail-closed; case-sensitive sitemap path/query; canonical A один для recrawl и IndexNow; durable evidence/readback; отсутствие duplicate IndexNow POST; migration 051; install/rollback containment/fault recovery.
>
> Не меняй SSL, canonical/recrawl/IndexNow, robots, Webmaster, randomization/update_classes, migration/schema или pipeline, если соответствующий реальный обязательный тест проходит. Migration 052 не добавлять без доказанного schema defect.
>
> Один release runner обязан реально запускать все mandatory группы и вывести `RELEASE_ACCEPTANCE = PASS` только при `FAIL=0 BLOCK=0 SKIP=0`. Никакие G/H/J/K/robots/PHP8.0 не могут быть INFO/CARRY-FORWARD и при этом разрешать PASS.
>
> До полного PASS архив не отдавай. Если тесты находят ошибки — исправляй их у себя и повторяй весь suite. Пользователю передай только один окончательный cumulative ZIP с `REPORT.md`, полным `ACCEPTANCE_STDOUT.txt`, hashes, payload, rollback и `FILEZILLA_INSTALL.md`.
>
> Пользовательская установка должна оставаться без SSH: FileZilla -> загрузка payload -> Настройки -> «📦 Миграции БД» -> применить 051 -> одна canary refresh-job через UI. Никакого обязательного ручного SQL/ALTER/mysql/CLI migrate.

---

# 17. ОЖИДАЕМЫЙ ИТОГ ЭТОЙ ИТЕРАЦИИ

Не «следующий патч для следующей проверки», а один из двух результатов:

## Вариант A — предпочтительный

Все четыре оставшихся gate проходят без изменения production-кода:

```text
production diff v5 -> FINAL = 0
RELEASE_ACCEPTANCE = PASS
```

Тогда v5 фактически становится FINAL после добавления полного acceptance evidence.

## Вариант B

Один из обязательных тестов реально воспроизводит defect:

- исправить минимально;
- закрыть regression test;
- повторить абсолютно весь acceptance;
- продолжать у разработчика до `0 FAIL / 0 BLOCK / 0 SKIP`;
- отдать один FINAL ZIP.

**Не должно быть серии промежуточных архивов.**
