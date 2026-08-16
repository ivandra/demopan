# ТЕХНИЧЕСКОЕ ЗАДАНИЕ
## Refresh Panel — точечный cumulative patch REV3.2-final
### Область: robots.txt + randomization/update_classes + recovery job #227

Дата: 17.08.2026

---

# 0. ГЛАВНОЕ ПРАВИЛО ДЛЯ РАЗРАБОТЧИКА

Это НЕ задача на рефакторинг Refresh Panel.
Это НЕ задача на изменение сайтов.
Это НЕ задача на изменение общей архитектуры pipeline.

Нужно исправить ДВЕ конкретные production-проблемы:

1. неверное/бесконечное поведение проверки robots.txt;
2. зависание randomization/update_classes_call на job #227.

Все исправления должны быть минимальными, локальными, обратно совместимыми и накладываться поверх уже установленного REV3.1.

НЕЛЬЗЯ решать проблему путём переписывания ядра, изменения общей state machine, изменения сайтовых шаблонов, guard.php, update_classes.php, config.php или структуры сайтов.

Если для исправления требуется изменение вне разрешённого scope — СТОП, сначала описать необходимость и доказать failing case. Самостоятельно расширять scope запрещено.

---

# 1. ИСХОДНОЕ СОСТОЯНИЕ

Production baseline:

- REV3.1 уже установлен;
- актуальный дамп панели и актуальные файлы находятся в репозитории;
- проблемная job:
  - job_id: 227
  - old_domain: mellstroy77.casino
  - new_domain: mellstroy80.casino
  - stage: update_classes_call
  - stage_status: pending
  - uc_execution_state: NULL
  - uc_execution_attempts: 0

Ключевой факт:

job #227 НЕ выполняет update_classes.php бесконечно.

Зациклен PRE-FLIGHT перед фактическим mutating-вызовом.

Это подтверждается:

- uc_execution_attempts = 0;
- uc_execution_state = NULL;
- stage остаётся update_classes_call;
- preflight возвращает preflight_failed;
- pipeline повторно планирует stage примерно через 60 секунд.

Следовательно, исправлять нужно state/preflight logic, а не сам update_classes.php сайта.

---

# 2. ЗАПРЕЩЁННАЯ ОБЛАСТЬ

В рамках этого патча ЗАПРЕЩЕНО:

## 2.1. Не менять сайты как продукт

Не менять постоянно и не требовать миграции:

- guard.php;
- update_classes.php;
- config.php;
- index.php;
- .htaccess;
- robots.php;
- sitemap.php;
- templates/*;
- структуру source/mapping-файлов;
- текущие редиректоры сайтов;
- текущие способы определения Yandex;
- текущие IP allowlist / DNS verification guard;
- текущие партнёрские URL.

Важно:

Допустимы только те ВРЕМЕННЫЕ runtime-изменения файлов сайта, которые уже являются частью штатного refresh pipeline и которые панель обязана:
- сделать контролируемо;
- подтвердить exact read-back;
- сохранить snapshot;
- гарантированно восстановить.

Нельзя выпускать patch, который требует вручную обновить guard.php или другой файл на существующих сайтах.

## 2.2. Не менять ядро панели

Запрещено:

- переписывать RefreshOrchestrator целиком;
- вводить новую state machine;
- менять смысл существующих стадий;
- переименовывать существующие production stages;
- менять общий scheduler/worker;
- менять очередь jobs;
- менять механизм блокировок jobs;
- менять FastPanel integration;
- менять Namecheap integration;
- менять Yandex Webmaster integration;
- менять SSL pipeline;
- менять index_watching;
- менять xmlstock;
- менять Telegram pipeline;
- менять old-domain removal flow;
- менять unrelated DB schema;
- добавлять новые таблицы без отдельного согласования.

## 2.3. Не ослаблять проверки

Запрещено:

- считать randomization успешной просто по HTTP 200;
- считать randomization успешной по тексту "Завершено";
- считать randomization успешной после N неудачных попыток;
- пропускать stage автоматически без доказательства результата;
- заменять verification на best-effort;
- делать operator_skip автоматическим штатным путём;
- считать HTTP 200 доказательством отсутствия redirect;
- считать applied_rules=[] доказательством успешного redirect_disable.

---

# 3. РАЗРЕШЁННЫЙ SCOPE ИЗМЕНЕНИЙ

Разрешено изменять только то, что необходимо для двух проблем:

- app/Services/RefreshOrchestrator.php
- app/Services/RedirectToggler.php
- app/Services/SiteVerifier.php
- app/Services/DiagnosticCatalog.php
- существующие тесты этих компонентов
- новые тесты только для данного patch
- one-shot repair/recovery CLI только если невозможно безопасно self-recover job #227 существующим worker

Не расширять список файлов без доказанной необходимости.

Если какой-либо из перечисленных файлов не требуется менять — не менять его.

---

# 4. ПРОБЛЕМА №1 — ROBOTS.TXT

## 4.1. Текущее неправильное поведение

Для нового домена возможна ситуация:

HTTP 200
robots.txt валиден
содержит:
    User-agent: *
    Disallow: /

и отсутствует разрешающий root rule.

Такой robots является ЗАВЕДОМО закрытым для индексирования.

Текущее/старое поведение панели ошибочно может считать этот случай transient/cache problem и повторять verify_replacement.

Это неверно.

## 4.2. Требуемая классификация

Если новый robots:

- успешно получен;
- HTTP находится в корректном диапазоне;
- синтаксически распознан;
- содержит фактический root-block;
- не содержит разрешающего правила, которое делает новый домен открытым;

то результат должен иметь структурированный признак:

robots_closed = true

и:

retryable = false

## 4.3. Диагностика

Технический код:

new_domain_robots_closed

Ожидаемые свойства:

source = site
severity = error
human_action = required
auto_recovery = false

UI не должен показывать:

unknown_stage_error
unknown_stage_warning
"само восстановится"
"продолжаю ждать"

## 4.4. Приоритет deterministic robots failure

Если одновременно есть:

- robots_closed = true;
- другой transient check (DNS, timeout, 502 и т.п.);

robots_closed должен иметь приоритет.

Нельзя делать:

if any_failure_retryable => retry whole stage

для случая, когда уже доказано deterministic:
new_robots.robots_closed=true.

Правило:

if new_robots.robots_closed === true
    verify_replacement = non-retryable
    awaiting_user
    stop retry loop

Только после этого анализировать остальные retryable failures.

## 4.5. Что НЕ менять в robots

Не менять sites robots.php.
Не менять формат robots.txt существующих сайтов.
Не автоматически переписывать robots на сайтах.
Не добавлять новые robots policy.

Задача только в корректной интерпретации существующего ответа.

---

# 5. ПРОБЛЕМА №2 — JOB #227 / RANDOMIZATION

## 5.1. Фактическая причина зависания

Job #227 зависла на:

update_classes_call

но реальный update_classes.php не выполнялся.

State:

uc_execution_state = NULL
uc_execution_attempts = 0

Preflight получает не контент ожидаемого сайта, а поведение активного партнёрского redirect.

В результате:

preflight_failed
stage_status=pending
next_run_at=+60s

и это повторяется бесконечно.

Это и есть дефект, который нужно исправить.

---

# 6. КЛЮЧЕВОЙ ИНВАРИАНТ RANDOMIZATION

Разделить два принципиально разных действия:

A. PRE-FLIGHT
B. ACTUAL MUTATION

Они не должны смешиваться.

## 6.1. Preflight

Preflight:

- ничего не меняет;
- не увеличивает uc_execution_attempts;
- не пишет лог "запускаю update_classes.php";
- может retry только bounded;
- должен возвращать точную структурированную причину.

## 6.2. Actual mutation

uc_execution_attempts увеличивается ТОЛЬКО непосредственно перед фактическим HTTP-вызовом:

/update_classes.php

Лог "запускаю update_classes.php" разрешён ТОЛЬКО в этот момент.

---

# 7. ИСПРАВИТЬ MISLEADING LOGGING

Сейчас оператор видит повторяющееся сообщение вида:

"Найден update_classes.php, дёргаю..."

даже когда реального mutating request не происходит.

Это запрещено.

Нужна последовательность событий:

uc.detected_update_classes
    Найден поддерживаемый механизм randomization.

uc.preflight_started
    Выполняется preflight перед запуском randomization.

uc.preflight_retry
    Временный preflight failure, будет повторная read-only проверка.

uc.preflight_blocked
    Deterministic failure, автоматическое ожидание прекращено.

uc.execution_started
    Только здесь реально вызывается update_classes.php.

uc.execution_result
    Зафиксирован HTTP/result фактического вызова.

uc.class_delta_verified
    Изменение подтверждено read-back.

Никакой текст "дёргаю / запускаю" до uc.execution_started недопустим.

---

# 8. PREFLIGHT: НЕ ПУТАТЬ OWN REDIRECT И DEFAULT VHOST

## 8.1. Неверное старое правило

Нельзя считать:

newDomain отсутствует literal-строкой в HTML
=
default vhost

Это слишком слабое доказательство.

## 8.2. Structured result

Preflight должен возвращать минимум:

ok
reason_code
http_code
location
redirect_host
is_own_redirect
retryable
trusted_site_evidence
details

## 8.3. Own redirect

Если response указывает на внешний redirect и host входит в уже существующий конфиг:

wm.own_redirect_domains

то reason_code:

own_redirect_active

а НЕ:

default_vhost

Список own redirect domains нельзя дублировать hardcoded массивом в другом сервисе.

Использовать существующий источник конфигурации.

---

# 9. SAME-HOST 301/302 — ОБЯЗАТЕЛЬНАЯ ОБРАТНАЯ СОВМЕСТИМОСТЬ

REV3.1 мог пройти:

https://site.com/
    -> 301 https://site.com/ru/
    -> 200 content

REV3.2 не имеет права сломать такой сценарий.

Если redirect_host после нормализации совпадает с newHost:

это same-host redirect.

Он НЕ должен классифицироваться как:

unsupported_site_response

Требование:

- разрешить ограниченную same-host redirect chain;
- максимум 3 перехода;
- на каждом шаге нормализовать host;
- внешний host не follow;
- redirect loop обнаруживать;
- после финального same-host ответа выполнять обычную проверку.

Обязательные тесты:

301 same-host -> 200 = PASS
302 same-host -> 200 = PASS
www -> non-www same logical host = PASS
same-host -> own external redirect = own_redirect_active
redirect loop = bounded failure

---

# 10. REDIRECT_DISABLE — НЕ ДОПУСКАТЬ ЛОЖНОГО SUCCESS

Job #227 доказала:

redirect_disable может вернуть ok=true,
хотя реальный redirect остаётся активным.

Это недопустимо.

## 10.1. Нельзя трактовать applied_rules=[] как автоматический success

Возможны два разных случая:

CASE A:
applied_rules=[]
redirect действительно отсутствует
=> допустимый no_active_redirect

CASE B:
applied_rules=[]
redirect существует
=> FAIL

Эти случаи необходимо различать.

---

# 11. СУЩЕСТВУЮЩИЕ ТИПЫ REDIRECT-МЕХАНИЗМОВ

Панель должна продолжать поддерживать существующие сайты.

Не заставлять сайты переходить на новый единый формат.

## 11.1. Existing config-based mechanism

Если найден существующий механизм:

php_redirect_enabled_var

то authoritative verification:

- изменить только существующую переменную штатным механизмом;
- exact FTP read-back;
- доказать ожидаемое значение.

Не менять guard.php.

## 11.2. Legacy Mellstroy mechanism

Для реального fixture job #227 существует legacy вызов:

handleRequest($configRed);

в index.php.

Для него разрешён точечный существующий/подготовленный механизм:

legacy_index_handle_request

Правила:

- обнаружить ровно один поддерживаемый активный вызов;
- если 0 или >1 — не угадывать;
- сохранить snapshot;
- выполнить только точечную reversible mutation;
- exact FTP read-back;
- записать artifact;
- после randomization восстановить исходное содержимое.

Не переписывать index.php целиком.

## 11.3. Existing .htaccess mechanism

Существующий рабочий .htaccess flow сохранить без изменения поведения.

---

# 12. LIVE HTTP PROBE — ТОЛЬКО ДОПОЛНИТЕЛЬНЫЙ SIGNAL

Критично:

HTTP live-probe НЕ является универсальным authoritative evidence.

Причина:

некоторые существующие guard implementation могут пропускать panel IP/служебный запрос мимо пользовательского redirect.

В таком случае probe получает 200 даже при активном redirect для обычного пользователя.

Поэтому запрещено:

HTTP 200
+
applied_rules=[]
= no_active_redirect

без других доказательств.

## 12.1. Authoritative verification strategy по механизму

php_redirect_enabled_var
    -> exact read-back переменной

legacy_index_handle_request
    -> exact read-back модифицированного файла

.htaccess rule
    -> exact read-back ожидаемого правила

live-probe
    -> дополнительный signal

Не менять guard, чтобы "починить probe".

Панель должна корректно работать с существующим поведением сайтов.

---

# 13. PREFLIGHT RETRY ДОЛЖЕН БЫТЬ FINITE

Никаких бесконечных:

pending
+60 sec
pending
+60 sec
...

Ввести отдельное состояние/счётчик:

uc_preflight_attempts

Не смешивать с:

uc_execution_attempts

Рекомендуемый budget:

max_preflight_attempts = 10
max_preflight_age = 15 минут

Останавливать по первому достигнутому лимиту.

Значения можно вынести в существующую конфигурацию, но не создавать новую подсистему.

---

# 14. КЛАССИФИКАЦИЯ PREFLIGHT FAILURES

## 14.1. Retryable transient

Допустимо retry:

dns_not_resolving
connection_failed
temporary_tls_failure
http_502
http_503
http_504
temporary_alias_sni_propagation

Но только в finite budget.

## 14.2. Non-retryable / deterministic

Не повторять минутами:

own_redirect_active
external_redirect_unknown
unsupported_site_response
redirect_disable_ineffective

Для таких случаев:

- controlled recovery, если он предусмотрен;
- иначе awaiting_user;
- точная диагностика.

---

# 15. ONE-SHOT RECOVERY ДЛЯ OWN REDIRECT

Если update_classes preflight обнаружил:

own_redirect_active

разрешён максимум ОДИН controlled recovery:

update_classes_call
    -> own_redirect_active
    -> redirect_disable
    -> verify disable
    -> update_classes_call

Записать artifact:

uc_redirect_repair_attempted = true

Если после этого redirect снова активен:

НЕ retry loop.

Результат:

awaiting_user
technical_code=redirect_disable_ineffective

---

# 16. JOB #227 ДОЛЖНА ВОССТАНОВИТЬСЯ

Обязательное требование:

НЕ создавать новую job.

После установки final patch существующая #227 должна продолжиться.

Исходное state:

stage=update_classes_call
stage_status=pending
uc_execution_state=NULL
uc_execution_attempts=0

Ожидаемый flow:

#227
  ->
preflight видит own redirect
  ->
one-shot redirect repair
  ->
redirect_disable доказан
  ->
preflight повторяется
  ->
preflight PASS
  ->
uc_execution_attempts 0 -> 1
  ->
реальный update_classes.php вызывается один раз
  ->
read-back / delta verification
  ->
uc.class_delta_verified
  ->
следующая штатная stage

Если безопасный self-recovery невозможен в текущей архитектуре:

допустим one-shot CLI repair tool.

Но он обязан:

- работать только для указанного job_id;
- проверять expected stuck-state;
- проверять uc_execution_attempts=0;
- не менять другие jobs;
- писать audit event;
- не использовать raw manual SQL оператором.

---

# 17. AT-MOST-ONCE MUTATION

Даже после исправления preflight закрепить инвариант:

update_classes.php не вызывается повторно на каждом worker tick.

Если:

uc_execution_state = started/in_progress/completed

следующий worker НЕ должен автоматически повторить mutation.

Если HTTP timeout и неизвестно, выполнился ли скрипт:

сначала read-only verification / mapping read-back.

Не повторять mutating endpoint немедленно.

---

# 18. LEGACY MAPPING — НЕ ПЕРЕПИСЫВАТЬ БЕЗ FAILING TEST

Реальный Mellstroy fixture использует legacy layout:

templates/1/source/class_name_mapping.txt

Современные сайты могут использовать другие mapping paths.

В этом patch нельзя заранее переписывать mapping verifier "на всякий случай".

Сначала characterization-test на реальном fixture.

Если текущий verifier после исправленного preflight успешно подтверждает delta:

НИЧЕГО НЕ МЕНЯТЬ.

Если появляется реальный failing test именно на mapping discovery/read-back:

сделать минимальную совместимую правку в рамках этого же patch.

---

# 19. CRASH / RE-ENTRY ДЛЯ LEGACY REDIRECT DISABLE

Если patch временно модифицирует:

handleRequest($configRed);

обязателен crash-safe restore.

Проблемный сценарий:

1. snapshot прочитан;
2. remote index.php изменён;
3. worker падает ДО сохранения artifacts;
4. следующий worker видит уже disabled marker;
5. pipeline позже должен уметь восстановить исходный вызов.

Требование:

- snapshot/exact restore остаётся primary mechanism;
- новый disabled marker должен участвовать в stale marker detection;
- post-validation должна видеть этот marker;
- нужен deterministic аварийный fallback восстановления только для доказанного собственного marker;
- после restore marker отсутствует;
- нельзя оставить сайт навсегда с выключенным redirect.

Обязательный test:

disable
-> crash before artifact save
-> re-entry
-> recovery
-> original active call restored
-> disabled marker absent

---

# 20. DIAGNOSTIC CATALOG — ИСПОЛЬЗОВАТЬ STRUCTURED TECHNICAL_CODE

DiagnosticCatalog должен учитывать:

payload.technical_code

а не пытаться угадывать всё только по human-readable message.

Приоритет:

1. известный payload.technical_code;
2. известный event_code/reason_code;
3. только потом generic text classifier.

Обязательные codes:

new_domain_robots_closed
own_redirect_active_during_refresh
redirect_disable_ineffective
update_classes_preflight_exhausted
update_classes_external_redirect

Реальный emitter + real payload должны давать правильную классификацию.

Нельзя писать тест:

message = "redirect_disable_ineffective ..."

если production emitter кладёт code только в payload.

Тестировать production-shaped event.

---

# 21. DESTRUCTIVE DB TEST SAFETY

Любой test, который делает:

DROP TABLE
TRUNCATE
CREATE TABLE поверх существующего имени

должен быть защищён.

Если test_db_scenarios.php использует:

DROP TABLE refresh_jobs

он обязан отказываться запускаться, если DB явно не помечена как disposable test DB.

Минимум одно из:

- обязательный suffix _hotpatch_test;
- отдельный env ALLOW_DESTRUCTIVE_TEST_DB=YES;
- двойная проверка имени DB;
- запрет совпадения с production DB.

Нельзя полагаться только на README-инструкцию.

---

# 22. ОБЯЗАТЕЛЬНЫЕ TEST SUITES

## 22.1. Robots

- normal robots -> PASS
- closed robots -> immediate deterministic diagnostic
- robots_closed + DNS transient -> robots_closed dominates
- DNS-only transient -> bounded retry
- cache/transient old behavior -> regression PASS

## 22.2. Redirect mechanisms

- existing .htaccess site -> no regression
- existing config redirect -> no regression
- site without redirect -> PASS
- legacy Mellstroy index handleRequest -> PASS
- exact read-back -> PASS
- exact restore -> PASS

## 22.3. Preflight

- own redirect -> correct classification
- unknown external redirect -> awaiting_user
- same-host 301 -> 200 -> PASS
- same-host 302 -> 200 -> PASS
- www/non-www logical same host -> PASS
- same-host chain -> external own redirect -> own_redirect_active
- redirect loop -> bounded failure
- DNS/TLS/502/503/504 -> bounded retry
- exhausted budget -> awaiting_user + exact technical_code

## 22.4. Randomization state

- preflight does NOT increment uc_execution_attempts
- actual call increments exactly once
- 10 worker ticks while waiting do NOT create 10 mutations
- timeout -> read-back first, not immediate repeat
- successful delta -> uc.class_delta_verified -> next stage

## 22.5. Recovery job #227

Использовать state, соответствующий реальной #227.

Не подменять E2E симуляцией, где вручную вызываются только decision helpers.

Минимум integration-level flow:

stuck state
-> own redirect
-> one-shot recovery
-> redirect disabled
-> preflight pass
-> actual execution
-> verification
-> next stage

## 22.6. Crash recovery

legacy disable
-> simulated crash before artifact save
-> re-entry
-> restore
-> marker cleaned

---

# 23. TESTS НЕ ДОЛЖНЫ ЛГАТЬ ОБ УРОВНЕ ПОКРЫТИЯ

Запрещено писать в REPORT:

"existing job #227 recovery PASS"

если тест:

- не вызывает реальный stage method;
- вручную симулирует redirect_disable;
- вручную копирует SQL;
- не проходит реальный recovery path.

В отчёте точно указывать тип проверки:

unit
integration
fixture
DB integration
manual production verification

---

# 24. INSTALLATION PACKAGE

Нужен ОДИН cumulative package:

REV3.2-final = installed REV3.1
             + robots fix
             + randomization/preflight fix
             + redirect recovery required specifically by #227
             + diagnostics/tests

Не делать:

REV3.2
REV3.2-hotfix1
REV3.2-hotfix2

---

# 25. BASELINE PROTECTION

Installer обязан:

- проверить SHA256 production baseline файлов;
- убедиться, что они соответствуют ожидаемому установленному REV3.1;
- если SHA отличается — STOP;
- не перетирать неизвестное production tree.

---

# 26. BACKUP / ROLLBACK

Перед install:

- backup изменяемых production files;
- DB dump;
- сохранить hashes.

Rollback:

- вернуть REV3.1 files;
- НЕ выполнять rollback вслепую, если job находится между redirect_disable и redirect_enable;
- сначала проверить временно изменённые сайты;
- восстановить redirect из snapshot;
- только после этого завершать rollback.

---

# 27. PRODUCTION ACCEPTANCE JOB #227

После установки final patch необходимо наблюдать именно #227.

Нужна доказательная цепочка событий:

own redirect correctly detected
redirect repair started once
redirect mechanism identified
exact read-back verified
preflight passed
uc.execution_started attempt=1
actual HTTP result recorded
class delta verified
uc.class_delta_verified
next stage entered

Недопустимо:

detected
detected
detected
detected
каждую минуту

---

# 28. КРИТЕРИИ ПРИЁМКИ

Patch принимается только если одновременно:

[ ] Full REV3.1 regression PASS
[ ] Robots suite PASS
[ ] Randomization/preflight suite PASS
[ ] Same-host redirect suite PASS
[ ] Legacy Mellstroy fixture PASS
[ ] Crash/re-entry restore PASS
[ ] DiagnosticCatalog real-payload tests PASS
[ ] Destructive DB guard PASS
[ ] PHP lint PASS
[ ] No unexpected DB migrations
[ ] Installer baseline SHA protection PASS
[ ] Rollback verified
[ ] Existing job #227 recovery verified
[ ] No permanent site/template changes required
[ ] No guard.php migration required
[ ] No unrelated panel functionality changed

---

# 29. ОБЯЗАТЕЛЬНЫЙ REPORT ОТ РАЗРАБОТЧИКА

Перед передачей ZIP предоставить:

1. точный список изменённых production файлов;
2. diffstat;
3. краткое объяснение каждой правки;
4. какие инварианты сохранены;
5. список новых technical_code;
6. test matrix;
7. количество passed/failed tests;
8. тип каждого ключевого теста: unit/integration/fixture/DB;
9. SHA256 каждого production файла;
10. SHA256 ZIP;
11. baseline SHA REV3.1;
12. rollback SHA;
13. отдельный результат fixture Mellstroy;
14. отдельный результат job #227 recovery;
15. подтверждение:
    "guard.php/sites/templates/update_classes.php permanently NOT modified".

---

# 30. HARD STOP CONDITIONS

Не собирать production ZIP, если:

- robots_closed всё ещё может уйти в infinite retry;
- same-host 301/302 ломается;
- own redirect всё ещё классифицируется как default vhost;
- preflight может быть pending бесконечно;
- applied_rules=[] автоматически означает success;
- legacy disable не имеет crash-safe restore;
- DiagnosticCatalog игнорирует real payload.technical_code;
- preflight увеличивает uc_execution_attempts;
- update_classes mutates повторно без доказанной необходимости;
- job #227 требует создания новой job;
- для работы patch требуется вручную менять guard.php сайта;
- patch затрагивает unrelated Refresh Panel functions.

---

# 31. ФИНАЛЬНЫЙ PROMPT ДЛЯ РАЗРАБОТЧИКА

Работай ТОЛЬКО поверх текущего установленного REV3.1.

Не переписывай Refresh Panel.
Не меняй архитектуру.
Не меняй сайты.
Не требуй миграции guard.php.
Не исправляй "заодно" unrelated code.
Не ослабляй verification.

Твоя задача — закрыть только два production defects:

A) robots deterministic classification/retry;
B) randomization/update_classes preflight loop + safe recovery реальной job #227.

Любое новое изменение должно иметь:
- конкретный failing case;
- минимальный diff;
- regression test;
- доказательство отсутствия side effects.

Сначала characterization существующего поведения.
Потом минимальный failing test.
Потом минимальный production fix.
Потом полный regression.
Потом cumulative ZIP.

Не считай stage успешной без доказательства postcondition.
Не считай retry безопасным без конечного budget.
Не выполняй mutation повторно, если можно сначала проверить результат.
Не используй HTTP 200 как универсальное доказательство отключённого redirect.
Не меняй существующий сайт, чтобы компенсировать дефект панели.

Целевой результат:

- robots закрыт -> точная terminal diagnostic;
- robots transient -> bounded retry;
- job #227 сама выходит из update_classes_call;
- update_classes.php реально вызывается максимум контролируемо;
- randomization подтверждается delta/read-back;
- redirect временно отключается и гарантированно восстанавливается;
- старые рабочие сайты продолжают работать без изменений;
- другие stages Refresh Panel не меняют поведения.

Если есть сомнение между "добавить ещё логику" и "не трогать работающий код" — выбирать НЕ ТРОГАТЬ, пока failing test не докажет необходимость.
