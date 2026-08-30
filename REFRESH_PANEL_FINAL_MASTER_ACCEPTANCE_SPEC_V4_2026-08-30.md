# Refresh Panel — единое финальное ТЗ и acceptance-spec для следующего cumulative patch

**Дата:** 30.08.2026  
**Целевой продукт:** Refresh Panel  
**Исходная база текущего цикла:** `refresh3008`  
**Текущий проверенный кандидат:** `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v3(1).zip`  
**SHA-256 кандидата v3:** `b3179df86db37d3c8d0eecf64430e71b29e4192c8ad92874c03d021d9152d5a0`  
**Статус v3:** NO-GO  
**Следующая поставка:** один cumulative `v4` (или другое одно финальное имя), без промежуточных v4.1/v4.2 и без серии дополнительных hotfix.

---

## 0. Главное правило этой итерации

Это не список «очередных четырёх правок». Это единый нормативный документ для разработки и приёмки всей затрагиваемой функциональности.

Разработчик **не должен отдавать следующий ZIP пользователю**, пока самостоятельно не выполнены все обязательные проверки настоящего документа и пока в итоговом `REPORT.md` нет воспроизводимых доказательств их прохождения.

Недопустима схема:

1. исправить один найденный дефект;
2. собрать новый ZIP;
3. отдать на внешний аудит;
4. после обнаружения следующего дефекта выпустить ещё один ZIP.

Допустима только схема:

1. реализовать все требования;
2. прогнать полный unit + integration + production-path + installer/rollback + migration acceptance;
3. устранить найденные собственными тестами дефекты;
4. повторить весь suite;
5. только после полного PASS собрать **один cumulative release candidate**;
6. отдать его на финальный аудит.

Если часть обязательных тестов невозможно выполнить в среде разработчика, такой архив можно считать только внутренним draft, **но не production candidate и не выдавать как «готовый»**.

## 0.1. CHANGE FREEZE — жёсткое ограничение области правок

Следующая сборка является **корректирующим cumulative patch, а не рефакторингом Refresh Panel**.

Разрешено изменять production-код **только в объёме, необходимом для выполнения конкретных требований этого документа**. Запрещены любые инициативные изменения «заодно».

Без отдельного блокирующего доказательства запрещено:

- менять архитектуру state-machine;
- переписывать уже работающие Yandex Webmaster recrawl/routing/reconciliation-механизмы;
- менять алгоритм `update_classes` / randomization, кроме прямо перечисленного regression-fix;
- менять публичные API/форматы данных/названия стадий/тексты UI/Telegram, если это не требуется конкретным requirement ID;
- делать stylistic refactor, rename, cleanup, перенос классов/методов, dependency upgrade или «улучшение читаемости» в production-файлах;
- добавлять новые миграции БД сверх уже требуемой 051 без доказанной невозможности решить задачу в существующей схеме;
- менять поведение canonical/recrawl/IndexNow сверх узких исправлений origin validation, durable evidence и duplicate-POST protection;
- затрагивать другие подсистемы панели только потому, что они находятся в том же `RefreshOrchestrator.php`.

Если разработчик обнаружил, что для исправления требуется изменение вне указанного scope, он **не должен молча расширять патч**. В `REPORT.md` надо отдельно указать:

```text
SCOPE_EXPANSION_REQUIRED
requirement_id=...
file=...
why=...
minimal_change=...
risk=...
```

и не включать такое расширение в production candidate без отдельного согласования.

### Обязательный diff-budget контроль

В `REPORT.md` должна быть таблица **каждого изменённого production-файла**:

```text
file | requirement_id | зачем изменён | какие методы/участки изменены | почему нельзя было не менять
```

Любой изменённый production-файл без привязки к requirement ID = `NO-GO`.

Для уже закрытых механизмов предпочтительный способ проверки — **регрессионные тесты без изменения production-кода**.

Критерий минимальности: если требование можно закрыть тестом или локальной правкой существующего helper, нельзя переписывать соседний рабочий workflow.

---

# 1. Почему предыдущих патчей получилось слишком много

Основная причина — последние итерации проверялись как delta-аудит очередного патча, а не как единая acceptance-модель всего изменяемого production-path.

Это приводило к четырём типовым проблемам:

1. Исправлялся конкретный симптом, но соседняя ветка того же state-machine не прогонялась настоящим E2E.
2. Часть тестов проверяла копию алгоритма, regex по исходнику или in-memory модель вместо реального production-кода.
3. Зелёный unit-suite ошибочно воспринимался как доказательство installer/rollback/DB integration.
4. В REPORT попадали более сильные формулировки, чем реально доказывали тесты.

Следующий патч должен закрыть это организационно: требования и acceptance ниже являются частью самого задания разработчику, а не отдельной работой аудитора после сборки.

---

# 2. Формат единственной следующей поставки

Нужен один ZIP, собранный от **того же подтверждённого ground-truth baseline `refresh3008`**, если перед сборкой контрольные SHA всё ещё совпадают.

Внутри обязательно:

```text
REPORT.md
MANIFEST.sha256
BASE_SHA256.txt
install.sh
rollback.sh
payload/
rollback/
install/probe_051.php
payload/app/Migrations/051_ssl_transient_alert_confirmation.php
tests/
tests/run_all.sh
ACCEPTANCE_STDOUT.txt
```

Если для интеграционных тестов нужны fixtures или DB schema fixtures, они также входят в ZIP.

Запрещено:

- отдельный «fix для rollback» после v4;
- отдельный «fix canonical» после v4;
- отдельный SQL-файл, который пользователь должен выполнить вручную;
- обязательная ручная правка production-файлов после `install.sh`;
- выдавать пакет с пометкой «остальное проверите на сервере» по тем пунктам, которые можно проверить автоматически;
- собирать v4 поверх случайно изменённого рабочего дерева без сверки baseline.

---

# 3. Ground-truth и baseline gates

Перед любой мутацией production/staging target установщик обязан:

1. проверить `MANIFEST.sha256` самого пакета;
2. сверить SHA каждого существующего изменяемого файла с `BASE_SHA256.txt`;
3. проверить отсутствие непредусмотренных коллизий NEW-файлов;
4. при mismatch остановиться **до первой записи**;
5. не использовать `--force` в штатном install-path.

Если baseline отличается от ожидаемого:

```text
BASE_MISMATCH
exit != 0
zero writes
```

Разработчик должен пересобрать cumulative patch от фактической authoritative base, а не заставлять пользователя перетирать более новое дерево.

---

# 4. Непереговорные системные инварианты

Любое нарушение любого пункта ниже = NO-GO независимо от числа зелёных тестов.

## 4.1. State-machine / recovery / re-entry

1. Повторный tick стадии после crash/re-entry не должен повторять необратимую операцию, если есть доказательство, что она уже произошла.
2. Любая mutating-операция должна иметь durable state/evidence, достаточный для безопасного resume.
3. Best-effort запись не может использоваться как единственное доказательство необратимой внешней операции.
4. Состояния `pending/retry` обязаны иметь `next_run_at` и конечную диагностическую причину.
5. `awaiting_user` используется только когда автоматическое безопасное продолжение действительно невозможно.
6. Recovery не создаёт site/job-specific исключений для конкретного job ID.
7. Recovery существующей job (включая ранее использованный сценарий job `#227`) должна продолжать сохранённый state, а не требовать искусственного пересоздания job.

## 4.2. Диагностика и presentation

1. UI, Telegram и event-log не должны противоречить фактическому состоянию стадии.
2. Техническая ошибка best-effort внешнего источника не превращает успешную основную проверку в ложный fatal.
3. Реальный fatal не маскируется как warning только ради продолжения pipeline.
4. `DiagnosticCatalog`/event-code должны быть источником семантики; текстовые эвристики допустимы только для backward compatibility старых событий.
5. Текст «следующий этап» должен формироваться из реального pipeline graph, а не из устаревшего hardcode.

## 4.3. Данные и evidence

1. Persist → обязательный readback там, где от записи зависит дальнейшая необратимая операция.
2. Readback должен проверять не только наличие ключа, но и semantic identity: phase/count/SHA/state по контракту.
3. Ошибка DB write/readback не должна молча проглатываться на critical evidence path.
4. Evidence не записывается «заранее» до фактической операции.
5. Повторный tick после failure evidence не должен автоматически дублировать внешний POST.

## 4.4. URL/origin security

1. Canonical submission URL не может быть создан путём молчаливого изменения origin входного URL.
2. Только HTTPS same-host.
3. Userinfo запрещён.
4. Non-default port запрещён; `:443` допустим как эквивалент стандартного HTTPS.
5. Fragment может быть удалён согласно текущему контракту.
6. Query/path case не fold-ить без отдельного подтверждённого правила.
7. Redirect на чужой host не принимать как собственную страницу.

## 4.5. Install/rollback

1. Backup всегда физически вне panel root.
2. Это правило одинаково для `install.sh` и `rollback.sh`.
3. Проверка containment выполняется до `mkdir`, copy или любой иной записи.
4. Backup byte-verified до первой мутации.
5. Публикация файлов atomic temp→mv.
6. При аварии install/rollback восстанавливает pre-operation state byte-for-byte.
7. Успешное аварийное восстановление не называется `CRITICAL_ROLLBACK_FAILED`.
8. `chown -R`, массовая смена permissions и лишний restart сервисов запрещены.

---

# 5. Обязательные исправления найденных дефектов v3

Эти пункты подтверждены аудитом текущего `v3` и обязательны для следующей cumulative-сборки.

## 5.1. P1 — containment для `rollback.sh`

Текущий `install.sh` проверяет `BACKUP_BASE`, текущий `rollback.sh` — нет.

Дефект воспроизведён: `BACKUP_BASE=<panel-root>/_rollback_backup_inside_root` приводит к успешному rollback и созданию backup внутри panel root.

### Требование

До создания `RBK`:

- нормализовать `TARGET` абсолютным realpath;
- `BB_ABS=$(realpath -m "$BACKUP_BASE")`;
- запретить `BB_ABS == TARGET`;
- запретить `BB_ABS` как descendant `TARGET/`;
- exit 6;
- zero writes;
- каталог внутри target не создаётся.

Проверка должна быть в production `rollback.sh`, а не только в тестовой обёртке.

---

## 5.2. P1 — запрет молчаливого переписывания port/userinfo в `SubmissionUrlResolver`

Текущий код способен превратить:

```text
https://muta.top:8443/port
```

в:

```text
https://muta.top/port
```

и:

```text
https://user:pass@muta.top/private
```

в URL без userinfo.

Это создаёт URL, которого не было в sitemap/config.

### Требование

Page URL принимается только если:

- `scheme === https`;
- host exact same-host после допустимой host canonicalization;
- `user` и `pass` отсутствуют;
- port отсутствует или `443`;
- URL соответствует остальным текущим фильтрам.

Иначе URL исключается полностью, а не преобразуется в другой origin.

---

## 5.3. P1 — durable consumer evidence для recrawl/IndexNow

`recordSubmissionConsumer()` сейчас идёт через `saveArtifacts()`, который ловит DB exception, пишет warning и возвращает `void`.

Critical evidence-path не знает, сохранилась ли запись.

### Требование

Создать отдельный strict path, например:

```php
saveSubmissionEvidence(...): EvidenceSaveResult
```

или эквивалент.

Он обязан:

1. выполнить DB write;
2. проверить write-result;
3. выполнить fresh readback;
4. проверить конкретный consumer;
5. проверить `phase`;
6. проверить `count`;
7. проверить `sha256`;
8. вернуть явный success/failure либо бросить типизированное исключение.

### Recrawl

`recrawl.planned_*` записывается **только после реального `planUrls()`**.

Если plan произошёл, а evidence не подтверждена:

- stage не считается полностью завершённой;
- назначается bounded retry;
- повторное `planUrls()` должно быть идемпотентно и не создавать дубли;
- после восстановления DB evidence дописывается и pipeline идёт дальше.

### IndexNow

`indexnow.submitted_*` записывается **только после фактического HTTP POST attempt**.

Если POST реально произошёл, но evidence persist/readback упал:

- следующий tick не имеет права повторно делать POST только из-за отсутствия evidence;
- должен существовать durable post-attempt state/idempotency marker, позволяющий восстановить evidence;
- повторный POST разрешён только по текущему документированному retry-контракту внешнего API и только когда доказано, что это не duplicate текущего attempt.

---

## 5.4. P1 — убрать псевдо-E2E из роли acceptance proof

Текущие тесты полезны, но часть из них проверяет не production-path:

- `test_pipeline_copy.php` — regex/static-source checks;
- `test_canonical_parity.php` — in-memory повторение алгоритма;
- `test_rollback_fault.sh` — самостоятельная симуляция rollback-цикла.

Их сохранить как unit/static tests, но они **не заменяют** integration/actual-script tests ниже.

---

## 5.5. P2 — корректное сообщение аварийного восстановления rollback

Если аварийный rollback не завершился, но pre-rollback state полностью восстановлен и byte-verified, сообщение должно быть, например:

```text
ROLLBACK_ABORTED_RECOVERED
```

`CRITICAL_ROLLBACK_FAILED` используется только если recovery itself failed или state не byte-exact.

---

# 6. SSL monitor / transient alert acceptance

Сохранить уже реализованные v3 исправления и доказать их production-path тестами.

## 6.1. Transport-critical classification

Transport-debounce применим к реальным транспортным причинам, включая:

- DNS resolution failure;
- inconclusive DNS;
- connect timeout;
- connect error;
- TLS transport error;
- `https_not_served` / `site_unreachable` согласно текущей модели.

HTTP-level и certificate semantic failures не должны случайно попадать в тот же debounce.

## 6.2. HTTP 5xx

`site_error/http_5xx` идёт по immediate policy, а не ждёт transport streak 2/60.

## 6.3. Candidate identity

Candidate streak должен различать минимум:

```text
status + reason + transport_error_kind
```

Изменение любого компонента сбрасывает предыдущий streak.

## 6.4. Previously trusted / manual never-trusted / fresh provisioning

Проверить отдельно:

- previously-trusted длительная transport outage → один red после подтверждения 2/60;
- manual never-trusted по принятой политике → корректное подтверждение;
- fresh provisioning transient → не создаёт ложный red;
- recovery после outage → candidate очищается по контракту;
- повторные одинаковые measurement не создают alert storm.

### 6.4.1. Обязательный regression именно для `site_error → https_not_served`

Это отдельный release-blocking сценарий, соответствующий фактическому Telegram-алерту вида:

```text
🔴 SSL/домен: <domain>
Статус: site_error → https_not_served
```

После применения миграции 051 результат `status=site_error`, `reason=https_not_served` обязан классифицироваться как **transport-critical**, а не как immediate application error.

Для previously-trusted или явного manual-monitor домена проверить production-path:

1. первое измерение `site_error/https_not_served` → Telegram red count = 0, создаётся candidate;
2. повтор того же результата раньше минимального интервала подтверждения → Telegram red count = 0;
3. второе идентичное подтверждённое измерение при выполненном пороге `>=2 measurements` и `>=60 sec` → ровно один Telegram red;
4. следующие одинаковые измерения → новых одинаковых Telegram red нет в рамках anti-spam/cooldown;
5. восстановление в trusted очищает candidate/recovery-state по контракту;
6. новая независимая подтверждённая outage после recovery снова может дать один red;
7. смена `reason` или `transport_error_kind` между измерениями сбрасывает старый streak;
8. `site_error/http_5xx` не должен наследовать этот debounce и проверяется как immediate policy.

До применения 051 legacy fallback не считается доказательством исправления. Production acceptance проводится **после штатного применения миграции через интерфейс панели**.

## 6.5. Migration 051

Миграция должна остаться:

- idempotent;
- non-destructive;
- fail-closed по обязательной исходной схеме;
- с `ever_trusted_at` backfill;
- с колонками candidate state и `transport_error_kind`.

После `migrate.php` обязательный реальный `probe_051.php`.

---

# 7. robots.txt acceptance

Сохранить bounded retry и Hook A gating.

Обязательные сценарии:

1. valid public robots → verified;
2. transient transport failure → retry с `next_run_at`;
3. invalid content → bounded retries → конечное диагностируемое состояние;
4. foreign host/redirect → bounded retries → block/awaiting_user по контракту;
5. нейтральный UA для публичной HTTP-проверки;
6. strict TLS;
7. best-effort Webmaster robots API failure не подменяет результат реальной public-check;
8. IndexNow Hook A не запускается до `verified_continue`;
9. при blocked robots: IndexNow POST count = 0;
10. event/presentation соответствует фактической причине.

---

# 8. Sitemap discovery acceptance

## 8.1. Depth

Принята модель:

```text
root index = depth 0
child index = depth 1
grandchild index = depth 2
```

Grandchild `urlset` должен реально fetch-иться и отдавать page URLs.

Index на depth 3 не обходится.

## 8.2. Security/limits

Обязательно:

- only HTTPS;
- same-host;
- userinfo reject;
- non-default port reject;
- visited-set;
- child budget;
- page budget;
- body byte cap на transport-level, а не после полной загрузки;
- sitemap documents не включаются в canonical page set;
- malformed XML → fail-closed/диагностируемый fallback по текущему контракту.

## 8.3. Real nested integration

Тест должен использовать реальный `SubmissionUrlResolver` и transport fixture:

```text
/root-sitemap.xml       -> sitemapindex(child.xml)
/child.xml              -> sitemapindex(grandchild.xml)
/grandchild.xml         -> urlset(/, /a, /b)
```

Assert не только `count`, но и факт fetch каждого допустимого уровня.

---

# 9. Canonical submission set — единый источник для recrawl и IndexNow

Canonical URL set должен быть вычислен один раз и сохранён immutable для текущей job/execution.

## 9.1. Persist

После resolve:

- сохранить `urls`;
- `count`;
- deterministic `sha256`;
- version;
- resolved_at/execution identity по текущей модели.

После persist выполнить fresh DB readback и повторно вычислить SHA.

Если readback не совпал:

```text
canonical_persist_failed
recrawl plan = 0
IndexNow POST = 0
stage = pending/retry
next_run_at != NULL
```

## 9.2. Immutable parity

После того как recrawl получил canonical A, изменение sitemap A→B не может заставить IndexNow заново резолвить B.

IndexNow читает persisted A.

Инвариант:

```text
canonical_sha256
== recrawl.planned_sha256
== indexnow.submitted_sha256
```

При условии, что оба consumer фактически выполнялись.

## 9.3. Missing canonical on IndexNow

IndexNow не имеет права «сам тихо дорезолвить» новый набор.

Если canonical отсутствует/повреждён:

- no resolver call;
- no POST;
- диагностируемый retry/skip согласно pipeline-контракту;
- не создавать расходящиеся consumer sets.

---

# 10. Yandex Webmaster recrawl / routing / reconciliation carry-forward

Предыдущие уже принятые требования остаются regression scope, потому что `RefreshOrchestrator` — общий production-файл и новая сборка не должна их сломать.

## 10.1. IP routing

Quota GET, recrawl POST, queue GET, task GET должны использовать один назначенный аккаунту routed transport.

Обязательный инвариант:

```text
expected_source_ip == actual_source_ip
source_ip_verified = 1
```

Никакого fallback на default route после bind/source mismatch.

## 10.2. Reconciliation

Если внешняя task уже существует:

- GET-reconciliation не увеличивает POST attempt;
- `IN_PROGRESS/DONE` может восстановить локальное состояние;
- `DONE` не называется «проиндексировано»;
- queue match требует валидный `task_id + url + state`;
- неполный queue item → direct typed task GET;
- неполный direct GET → fail-closed, POST=0;
- 409 после единственного POST → GET reconcile, не второй POST.

## 10.3. HTTP 4xx classification

Не маскировать все 4xx как одну причину:

- invalid `date_from` → `date_from_filter_rejected` только при доказанном контрактном отказе;
- `HOST_NOT_VERIFIED` → ветка host verification/wait;
- `INVALID_USER_ID`/auth/account → account/routing blocker;
- unknown 4xx → отдельный fail-closed diagnostic.

---

# 11. Randomization / update_classes carry-forward

Новая v4 не должна регрессировать ранее принятую поддержку `update_classes.php`.

Обязательные regression-инварианты:

1. Не более одного mutating HTTP call на один execution reservation.
2. Baseline снимается до reservation/HTTP.
3. HTTP 2xx / `ok:true` сам по себе не доказывает semantic success.
4. После ambiguous timeout/reset/409 нельзя автоматически делать второй mutating call.
5. Verify-only polling — read-only.
6. Legacy families сохраняют собственные контракты.
7. JSON/atomic family не подтверждается legacy marker.
8. Custom contract не подменяется auto-detection.
9. Trusted FTP/root identity сохраняется.
10. Recovery job не переоткрывает уже proven execution.
11. `operator_skipped` не превращается обратно в автоматическую mutation.

Если current baseline уже содержит эти исправления, v4 не обязана переписывать их код, но release suite обязан подтвердить отсутствие регрессии.

---

# 12. Pipeline graph, тексты и `DiagnosticCatalog`

## 12.1. Pipeline graph

Acceptance должен выполнять реальный `nextStageFor()`/эквивалент, а не regex по исходнику.

Проверить режимы минимум:

- `refresh`;
- `move_indexed`;
- `move_simple`;
- SSL LE enabled/disabled ветвление.

## 12.2. Human text

Для каждого проверяемого перехода human-copy должна соответствовать реальному next stage.

Запрещено:

- обещать уже пройденную стадию;
- писать «проиндексировано» при только recrawl DONE;
- `ok` событие показывать как красную критическую ошибку;
- diagnostic external API warning показывать как failure основной stage.

## 12.3. Event codes

Новые/существующие event codes должны быть реально emitted production branches, а не только перечислены в catalog/test fixture.

---

# 13. Install acceptance — выполнять настоящим `install.sh`

Все проверки до mutation.

Обязательная последовательность:

1. MANIFEST;
2. baseline SHA;
3. NEW collision inventory;
4. PHP 8.0 path resolve;
5. полный pre-install test suite;
6. BACKUP_BASE containment;
7. external backup + byte verify;
8. payload lint;
9. migration 051 publish;
10. штатный `public/migrate.php`;
11. `probe_051.php`;
12. только после schema PASS — зависимый код;
13. orchestrator последним среди зависимых changed-файлов;
14. post-copy SHA;
15. owner/group/mode assert;
16. no temp residue inside panel root;
17. final status.

`INSTALL_OK` разрешён только при реально применённой и подтверждённой схеме.

`SKIP_DB_MIGRATION=1` может существовать только как staging-mode и должен завершаться явно отличным статусом типа:

```text
INSTALL_STAGED_NO_DB
```

Это не production PASS.

---

# 14. Rollback acceptance — выполнять настоящим `rollback.sh`

## 14.1. Standard rollback

Из installed v4 state:

- CHANGED → exact baseline SHA;
- NEW, реально созданные патчем → removed;
- pre-existing NEW collision/preserved case → восстановить исходный state;
- DB migration 051 не откатывается destructive DDL, если текущая официальная политика именно такая;
- сообщение соответствует реальной политике.

## 14.2. BACKUP_BASE containment

Запустить настоящий shipment script:

```bash
BACKUP_BASE="$TARGET/_backup" bash rollback.sh "$TARGET"
```

Ожидание:

- exit 6;
- `$TARGET/_backup` отсутствует;
- SHA всех target files unchanged;
- никаких temp/partial files.

## 14.3. Fault injection через production rollback

Тест не имеет права переписывать rollback loop своей копией.

Допустимые реализации:

- официальный test-only fault hook внутри `rollback.sh`, активируемый только env-переменной при явном test mode;
- source-able production functions из отдельной библиотеки, которые одинаково использует и `rollback.sh`, и тест.

Проверить crash после минимум:

- 1-й мутации;
- 2-й;
- 3-й;
- удаления первого NEW-файла.

После каждого injected fault:

```text
весь target == pre-rollback snapshot byte-for-byte
```

Если recovery byte-exact:

```text
ROLLBACK_ABORTED_RECOVERED
```

Если нет:

```text
CRITICAL_ROLLBACK_FAILED
```

---

# 15. DB integration acceptance

Это обязательный блок. In-memory модель не заменяет его.

## 15.1. Test DB isolation

Разрушительный test helper должен быть fail-closed.

Требования:

- обязательная отдельная env переменная test DB;
- никакого fallback test DB → production DB;
- строгий allow-pattern имени тестовой базы;
- явное неравенство production DB name;
- отсутствие test DB parameter → exit non-zero **до connect/DDL**;
- invalid name → exit non-zero;
- prod-name → exit non-zero.

## 15.2. Migration 051

На изолированной копии схемы:

1. initial schema preflight;
2. migrate 051;
3. все колонки существуют;
4. data/backfill semantic checks;
5. повторный migrate idempotent;
6. probe PASS;
7. существующие данные не повреждены.

## 15.3. Canonical DB integration

Выполнить настоящий persistence path orchestrator/repository.

Сценарий A→B:

1. resolver возвращает sitemap A;
2. recrawl resolve + canonical persist;
3. DB readback подтверждает A;
4. sitemap fixture меняется на B;
5. IndexNow получает persisted A;
6. resolver call count остаётся 1;
7. recrawl planned SHA == canonical SHA;
8. после POST IndexNow submitted SHA == canonical SHA.

Fault scenarios:

- canonical DB write exception;
- canonical readback returns missing/corrupt SHA;
- recrawl evidence write failure;
- recrawl evidence readback mismatch;
- IndexNow post-evidence write failure;
- IndexNow post-evidence readback mismatch.

Ключевой assert:

```text
failure after real IndexNow POST MUST NOT cause duplicate POST on next tick
```

---

# 16. Обязательная test matrix следующего пакета

В `tests/run_all.sh` или эквиваленте должны быть отдельные группы и честный итог.

## A. Static/package

- MANIFEST verify;
- no traversal/symlink;
- BASE hash format;
- rollback sources == baseline;
- php -l всех PHP на `/opt/php80/bin/php`;
- bash `-n` install/rollback/tests.

## B. SSL

- DNS outage previously-trusted tick1/tick2;
- inconclusive DNS;
- connect timeout;
- TLS transport error;
- HTTP 500 immediate;
- foreign redirect policy;
- certificate expired/mismatch/not-yet-valid policy;
- candidate identity reset;
- recovery clearing;
- fresh provisioning suppression;
- alert deduplication.

## C. robots

- valid;
- transport retry;
- invalid content retry→block;
- foreign host retry→block;
- neutral UA;
- strict TLS;
- Webmaster diagnostic 401/best-effort;
- Hook A gated;
- blocked → IndexNow POST=0.

## D. sitemap/URL

- root urlset;
- root→child urlset;
- root→child index→grandchild urlset;
- depth3 cutoff;
- visited loop;
- child budget;
- body cap;
- HTTP non-200;
- malformed XML;
- HTTP URL reject;
- foreign host reject;
- userinfo reject;
- `:8443` reject;
- `:443` accept;
- fragment handling;
- path case preservation;
- sitemap URLs not page URLs.

## E. canonical/consumers

- resolve once;
- persist/readback;
- A→B immutable parity;
- missing canonical IndexNow no-resolve;
- corrupt canonical no-submit;
- recrawl planned evidence after plan only;
- IndexNow submitted evidence after POST only;
- evidence phase/count/SHA readback;
- evidence write/readback failures;
- duplicate-POST protection.

## F. pipeline/presentation

- execute real nextStage functions for all modes;
- stage copy matches actual next stage;
- IndexNow only in allowed gate;
- `DONE recrawl != indexed`;
- event severity/source/intervention mapping;
- no stale hardcoded transition messages.

## G. randomization regression

- legacy 7k;
- r7;
- irvin;
- atomic JSON normal;
- 409/concurrent;
- timeout ambiguous;
- exactly-once mutation;
- verify-only resume;
- baseline before HTTP;
- recovery/re-entry;
- trusted-root identity;
- custom contract;
- variant-refresh.

## H. Webmaster regression

- routed quota GET;
- routed POST;
- queue GET;
- task GET;
- source IP mismatch fatal;
- 409 reconcile;
- IN_PROGRESS;
- DONE;
- incomplete queue item;
- invalid date_from;
- HOST_NOT_VERIFIED;
- INVALID_USER_ID;
- unknown 4xx.

## I. DB

- test DB safety negatives;
- migration 051 fresh;
- migration 051 idempotency;
- probe;
- canonical DB integration;
- evidence failure/recovery.

## J. Installer

- baseline mismatch zero writes;
- bad manifest zero writes;
- backup containment zero writes;
- backup copy failure zero mutation;
- migration failure dependent code not published;
- probe failure dependent code not published;
- successful install exact payload SHA;
- pre-existing NEW preservation.

## K. Rollback

- target mismatch fail;
- backup containment;
- standard rollback exact baseline;
- fault #1;
- fault #2;
- fault #3;
- fault during NEW removal;
- recovery message semantics.

**SKIP любого обязательного DB/integration/actual-script test = BLOCK, а не PASS.**

---

# 17. Canary acceptance на копии/стенде и production одной job

После автоматических тестов и до объявления «готово к массовой установке» провести canary.

## 17.1. Staging/копия

Обязательно:

- `/opt/php80/bin/php`;
- production-like config;
- отдельная test DB;
- реальный `migrate.php`;
- `probe_051.php`;
- install;
- smoke;
- rollback;
- повторный install;
- recovery/re-entry smoke.

## 17.2. Одна чистая production refresh-job

Проверить без ручных skip:

- pipeline проходит обычные стадии;
- тексты соответствуют реальным переходам;
- robots verified gate;
- canonical resolved once;
- recrawl и IndexNow используют один набор;
- evidence совпадает с фактическими действиями;
- никаких duplicate external POST;
- stage не зависает в `running/pending` без `next_run_at`;
- SSL candidate diagnostics отображаются корректно.

Не требуется искусственно ломать реальный production DNS/HTTP. Негативные сетевые сценарии должны быть доказаны integration fixtures до production canary.

---

# 18. Что разработчик обязан приложить в REPORT.md

REPORT — не декларация, а индекс доказательств.

Для каждого пункта:

```text
Requirement ID
Implementation files/functions
Test name
Exact command
PASS/FAIL
Краткий stdout/result
```

Обязательно отдельными строками:

- package SHA;
- MANIFEST result;
- baseline gates;
- PHP version `/opt/php80/bin/php -v`;
- mbstring presence на целевой среде;
- migration 051 result;
- probe result;
- DB integration result;
- actual rollback containment result;
- actual rollback fault result;
- canonical A→B DB test result;
- duplicate IndexNow POST prevention result;
- staging install SHA result;
- staging rollback SHA result;
- canary job ID и итог без site-specific кода в patch.

Запрещено писать:

```text
«проверено интеграционно»
```

если тест на самом деле повторяет алгоритм в памяти или grep/regex-ом смотрит исходник.

В таком случае честное название — `unit model` / `static gate`.

---

# 19. Release gate: когда архив разрешено отдать пользователю

Следующий ZIP можно отдавать только если одновременно:

- все P1/P2 настоящего документа исправлены;
- package integrity PASS;
- PHP 8.0 lint PASS;
- все mandatory unit tests PASS;
- все mandatory integration tests PASS;
- DB suite PASS;
- migration+probe PASS;
- actual install tests PASS;
- actual rollback tests PASS;
- actual fault-injection PASS;
- staging install/rollback byte-exact PASS;
- production-path canary PASS;
- REPORT не содержит неподтверждённых утверждений;
- cumulative ZIP построен от правильного baseline;
- нет промежуточных ручных действий, без которых patch считается рабочим.

Если хотя бы один обязательный пункт FAIL или SKIP:

```text
RELEASE_CANDIDATE = NO
```

Архив не передавать как готовый.

---

# 20. Что уже считается закрытым и не надо переписывать без причины

Следующие решения v3 по статическому/локальному аудиту выглядят реализованными и должны переноситься carry-forward без новой архитектурной переделки:

- transport-critical DNS/unreachable classification;
- исключение HTTP 5xx из transport debounce;
- candidate identity `status/reason/kind`;
- sitemap depth 0/1/2;
- transport-level sitemap body cap;
- robots bounded retry;
- Hook A after verified;
- canonical load-only policy для IndexNow;
- canonical readback SHA check;
- migration 051 + schema probe;
- install-side BACKUP_BASE containment;
- pre-existing NEW handling install auto-rollback;
- atomic temp→mv;
- owner/group/mode inheritance;
- отсутствие mass `chown -R`/service restart.

Однако они считаются окончательно принятыми только после прохождения описанных здесь production-path regression tests в следующем пакете.

---

# 21. Финальный ожидаемый результат

После установки единственного следующего cumulative patch:

1. обычная Refresh job проходит без новых ложных ручных остановок;
2. recovery/re-entry безопасен и exactly-once там, где операция необратима;
3. SSL monitor не шумит на краткий transport transient и не пропускает реальную длительную outage;
4. HTTP 5xx не маскируется transport-debounce;
5. robots policy и IndexNow gate не расходятся;
6. nested sitemap реально доходит до допустимого grandchild;
7. canonical page set корректен по origin и не создаёт выдуманных URL;
8. recrawl и IndexNow работают на одном persisted set;
9. evidence отражает фактические plan/POST и переживает DB failure без duplicate external POST;
10. installer fail-closed и не публикует зависимый код до подтверждённой схемы;
11. rollback имеет ту же защиту backup containment, что install;
12. аварийный rollback восстанавливает exact pre-state и честно сообщает результат;
13. test suite исполняет реальный production-path в критических местах, а не только его модель;
14. следующий внешний аудит должен быть финальной независимой проверкой одного кандидата, а не способом постепенно находить базовые дефекты, которые обязан был поймать acceptance разработчика.

---

# 22. Короткая инструкция разработчику

**Не присылайте очередной промежуточный патч после исправления 1–2 пунктов.**

Сначала реализуйте весь документ, затем прогоните весь acceptance, исправьте всё найденное внутри своей итерации, снова прогоните полный suite и только после полного PASS отдайте **один cumulative ZIP** вместе с `REPORT.md` и `ACCEPTANCE_STDOUT.txt`.

