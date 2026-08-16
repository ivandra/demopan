# ТЕХНИЧЕСКОЕ ЗАДАНИЕ
## Refresh Panel — corrective pass для `REV3.2-final`
### Исправить оставшиеся блокеры после аудита. НЕ расширять scope.

Дата: 17.08.2026

---

# 0. ОБЯЗАТЕЛЬНОЕ ПРАВИЛО

Текущий `REV3.2-final` НЕ переписывать с нуля.

Основная архитектура патча уже принята как направление:
- robots deterministic diagnostic;
- разделение preflight / mutation;
- one-shot recovery own redirect;
- legacy `handleRequest($configRed)` support;
- snapshot/read-back/restore;
- bounded preflight;
- structured diagnostics.

Нужно выполнить ТОЛЬКО corrective pass по найденным дефектам ниже.

НЕЛЬЗЯ:
- менять сайты как продукт;
- менять `guard.php`;
- менять `update_classes.php`;
- менять templates;
- менять общую архитектуру Refresh Panel;
- переписывать `RefreshOrchestrator`;
- менять unrelated stages;
- вводить новую state machine;
- менять FastPanel / Namecheap / Webmaster / SSL / index monitoring;
- добавлять миграции без доказанной необходимости;
- исправлять что-либо "заодно".

Цель:
довести текущий `REV3.2-final` до безопасного production-состояния, не ломая уже рабочие сценарии REV3.1.

---

# 1. BLOCKER — SAME-HOST 301/302 В PREFLIGHT

## Проблема

Текущий production path preflight обрабатывает `Location` недостаточно корректно.

При:

    https://site.com/
      -> 301 https://site.com/ru/
      -> 200 content

нельзя терять `/ru/` и снова probe'ить `/`.

Также нельзя считать относительный:

    Location: /ru/

успешным без фактической проверки конечной страницы.

## Требование

Реализовать bounded follow ПОЛНЫХ URL, а не только hostname.

Правила:

1. Начальный URL:
   `https://<new-domain>/`

2. На каждом redirect:
   - получить `Location`;
   - если relative — разрешить относительно текущего URL;
   - сохранить scheme/host/path/query;
   - нормализовать host;
   - follow разрешён только если redirect остаётся внутри того же логического host;
   - внешний host НЕ follow.

3. Максимум:
   - 3 redirects;
   - после лимита должен быть terminal failure;
   - нельзя синтезировать фиктивный `HTTP 200`.

4. Redirect loop:
   - хранить visited full URLs;
   - повтор URL => deterministic bounded failure.

5. Если цепочка:
   same-host -> external own redirect
   => `own_redirect_active`.

6. Если:
   same-host -> unknown external
   => `external_redirect_unknown`.

## Обязательные тесты

- `/` -> 301 `/ru/` -> 200 = PASS
- `/` -> 302 `/home/` -> 200 = PASS
- absolute same-host Location -> PASS
- relative same-host Location -> PASS
- www -> non-www, если существующая нормализация считает их одним сайтом = PASS
- same-host -> own external redirect = `own_redirect_active`
- same-host -> unknown external = terminal diagnostic
- loop `/` -> `/ru/` -> `/` = bounded failure
- >3 redirects = bounded failure

Важно:
тестировать РЕАЛЬНЫЙ production method/path preflight, а не helper с заранее подготовленным массивом hops.

---

# 2. BLOCKER — CRASH/RE-ENTRY LEGACY REDIRECT RESTORE

## Проблема

В текущем `REV3.2-final` fallback восстановления:

    REFRESH_DISABLED_HANDLEREQUEST

может быть недостижим.

Если процесс упал после FTP mutation, но ДО сохранения artifacts:

    index.php уже disabled
    applied_rules отсутствует

а `runRedirectEnableStage()` делает ранний:

    empty(applied_rules) -> "нечего включать" -> return

то `RedirectToggler::enable()` не вызывается и marker может остаться навсегда.

## Требование

Изменить только порядок/guard logic стадии.

Перед ранним `return` необходимо проверить наличие собственного stale marker:

    REFRESH_DISABLED_HANDLEREQUEST

Если marker присутствует:

- НЕ считать "нечего включать";
- передать управление существующему recovery path;
- восстановить активный:
  `handleRequest($configRed);`
- выполнить exact read-back;
- убедиться, что marker исчез;
- записать recovery event/artifact.

Primary restore:
- snapshot / exact original content.

Fallback:
- только для доказанного собственного marker;
- только если snapshot metadata потеряна из-за crash/re-entry.

Не делать generic uncomment произвольного PHP.

## Обязательный integration test

Реальный stage-flow:

1. legacy active call присутствует;
2. disable выполнен;
3. эмулировать crash ДО записи artifacts;
4. следующий запуск приходит с `applied_rules=[]`;
5. redirect_enable/recovery всё равно обнаруживает marker;
6. active call восстановлен;
7. marker отсутствует;
8. exact read-back PASS.

Тест чистого helper `disableLegacy -> enableLegacy` недостаточен.

---

# 3. BLOCKER — ROBOTS_CLOSED ДОЛЖЕН ДОМИНИРОВАТЬ

## Проблема

В текущем `isRetryableVerifyFailure()` `robots_closed=true` может лишь пропустить текущую проверку через `continue`, после чего другой transient failure вернёт `retryable=true`.

Пример:

    new_robots:
      HTTP 200
      robots_closed=true

    new_http:
      DNS temporary failure

Нельзя продолжать retry, потому что robots уже доказанно закрыт.

## Требование

Сделать deterministic pre-scan/priority.

До общей логики transient failures:

    if new_robots.robots_closed === true:
        return false

То есть:
- verify non-retryable;
- job -> awaiting_user;
- technical_code -> `new_domain_robots_closed`.

После этого остальные failures уже не имеют значения для решения retry.

## Тесты

- robots_closed only -> non-retryable
- robots_closed + DNS -> non-retryable
- robots_closed + 503 -> non-retryable
- DNS only -> retryable bounded
- 503 only -> retryable bounded

---

# 4. BLOCKER — ROBOTS ROOT SEMANTICS

## Проблема

Нельзя считать любой `Allow:` достаточным при:

    Disallow: /

Пример:

    User-agent: *
    Disallow: /
    Allow: /favicon.ico

Сайт всё ещё закрыт для основной индексации.

## Требование

Разделить:

- has_any_allow
- has_allow_root

Для определения открытого root нужен точный семантический критерий разрешения `/`.

Минимум:

    Allow: /

должен считаться root allow.

А:

    Allow: /favicon.ico
    Allow: /assets/
    Allow: /images/

не должны отменять `Disallow: /` для корня.

Не менять sites robots.php.
Исправляется только интерпретация ответа в `SiteVerifier`.

## Тесты

1.
    Disallow: /
    Allow: /
=> OPEN

2.
    Disallow: /
    Allow: /favicon.ico
=> CLOSED

3.
    Disallow: /
    Allow: /assets/
=> CLOSED

4.
    Disallow: /
=> CLOSED

5.
    Allow: /
    no Disallow: /
=> OPEN

---

# 5. BLOCKER — EXACT READ-BACK НЕ МОЖЕТ БЫТЬ FAIL-OPEN

## Проблема

Текущая evaluation может стартовать с:

    readbackVerified = true

и сбросить его только если запись в readback map существует и false.

Таким образом:

    applied_rules = [config.php]
    readback = []

может ошибочно считаться verified.

## Требование

Для КАЖДОГО applied rule/file:

    array_key_exists(file, readbackMap)
    &&
    readbackMap[file] === true

Иначе:

    readback_verified = false

Отсутствие evidence = failure.

Нельзя:
- default true;
- unknown -> true;
- missing -> true.

## Тесты

- applied file + readback true -> PASS
- applied file + readback false -> FAIL
- applied file + readback missing -> FAIL
- two files, one missing -> FAIL
- applied_rules empty -> отдельная логика, не подменять read-back success

---

# 6. BLOCKER — DESTRUCTIVE DB TEST GUARD

## Проблема

`test_db_scenarios.php` может выполнять destructive SQL:

    DROP TABLE IF EXISTS refresh_jobs

Текущая защита недостаточна, если test DB задаётся оператором ошибочно именем production DB.

Нельзя считать безопасным только:

    RP_TEST_DB_NAME == RP_DB_NAME

или факт явной передачи переменной.

## Требование

Destructive DB suite должен иметь HARD FAIL-SAFE.

Минимально:

1. test DB обязана иметь явный disposable suffix:
   `_hotpatch_test`

И

2. обязательный explicit opt-in:

    ALLOW_DESTRUCTIVE_TEST_DB=YES

И

3. запрет известных production DB names / совпадения с production config.

Если хотя бы одно условие не выполнено:
    ABORT до первого destructive SQL.

## Пример безопасного запуска

    RP_TEST_DB_NAME=refresh_panel_hotpatch_test
    ALLOW_DESTRUCTIVE_TEST_DB=YES

Без explicit opt-in:
    STOP.

## Тесты

- production DB name -> refused
- normal DB without suffix -> refused
- suffix but no opt-in -> refused
- disposable suffix + opt-in -> allowed

---

# 7. INSTALLER — ЗАПУСКАТЬ ВСЕ PURE TESTS

## Проблема

Обычный install path не должен сообщать:

    pure suites OK

если не были запущены:
- carryforward/crash recovery suite;
- same-host chain suite;
- robots mixed-priority suite;
- root-Allow semantics.

## Требование

`install.sh` должен запускать ВСЕ pure/non-DB suites данного patch.

Минимально включить:

- legacy index
- redirect disable evaluation
- preflight classify
- preflight decision
- roundtrip
- diagnostic codes
- carryforward
- same-host integration/path
- robots priority
- robots root semantics
- exact-readback missing evidence

Любой FAIL:
    install STOP.

Не маскировать отсутствующие тесты как success.

---

# 8. FULL REV3.1 REGRESSION

Patch-specific tests недостаточны.

Перед production ZIP должен быть прогнан существующий regression suite REV3.1.

Цель:
доказать, что не сломаны:

- existing `.htaccess` redirect;
- existing config redirect;
- sites without redirect;
- existing update_classes modern flow;
- SSL;
- aliases;
- verify_replacement;
- redirect_enable;
- unrelated stages, которые покрывались штатным regression suite.

Не добавлять новые unrelated тесты.
Просто прогнать уже существующий regression baseline.

В REPORT разделить:

    REV3.1 regression
    REV3.2 corrective suites

---

# 9. JOB #227 — КОНКРЕТНЫЙ ACCEPTANCE

После исправления ZIP job #227 должна оставаться основной production acceptance job.

Исходно:

    job_id=227
    stage=update_classes_call
    stage_status=pending
    uc_execution_state=NULL
    uc_execution_attempts=0

Ожидаемо:

    own redirect detected
      ->
    one-shot repair
      ->
    legacy redirect disabled
      ->
    exact read-back PASS
      ->
    preflight terminal own-site response PASS
      ->
    uc.execution_started
      ->
    uc_execution_attempts = 1
      ->
    actual update_classes.php one call
      ->
    delta/read-back verified
      ->
    uc.class_delta_verified
      ->
    next normal stage

Не создавать новую job.

---

# 10. САЙТЫ НЕ МЕНЯТЬ

Повторно фиксируется как HARD RULE.

В рамках corrective pass:

НЕ менять постоянно:
- `guard.php`;
- `config.php`;
- `index.php`;
- `.htaccess`;
- `robots.php`;
- `update_classes.php`;
- templates;
- source mappings.

Разрешены только уже предусмотренные refresh pipeline временные reversible mutations с:
- snapshot;
- exact read-back;
- guaranteed restore.

Никаких требований:
"обновите guard на всех сайтах"
"добавьте новые IP"
"добавьте HMAC"
"смените UA whitelist"
"мигрируйте старые сайты"

в рамках этого patch НЕ должно быть.

---

# 11. ЯДРО И ДРУГИЕ ФУНКЦИИ НЕ ТРОГАТЬ

Не менять:

- scheduler semantics;
- global worker;
- job locking;
- Namecheap;
- FastPanel API;
- Webmaster;
- IndexNow;
- XMLStock;
- index watching;
- SSL;
- Telegram;
- cleanup;
- other job types;
- unrelated DB schema.

Если новая правка требует это затронуть:
СТОП и отдельное согласование.

---

# 12. DIAGNOSTIC CATALOG

Существующий исправленный механизм `payload.technical_code` СОХРАНИТЬ.

Не переписывать заново.

Проверить regression минимум:

- new_domain_robots_closed
- redirect_disable_ineffective
- update_classes_preflight_exhausted
- own_redirect_active_during_refresh
- update_classes_external_redirect

Production-shaped event:
- real message;
- real event_code;
- real payload.

---

# 13. INSTALL.md

Исправить CLI path repair tool, если installer кладёт файл в:

    bin/repair_stuck_job.php

Документация должна показывать:

    php bin/repair_stuck_job.php --job=227

а не неверный путь через `app/bin`.

CLI используется только если self-recovery действительно невозможно/не сработал.

Primary expectation:
worker сам продолжает #227 после установки.

---

# 14. НЕ ДЕЛАТЬ

Категорически запрещено:

- считать fixed только потому, что unit tests зелёные;
- симулировать #227 recovery и писать E2E PASS;
- менять guard;
- добавлять новые panel IP в сайты;
- менять UA сайтов;
- повторно запускать update_classes без verification;
- увеличивать retry budget вместо исправления причины;
- снимать verification;
- автоматически skip'ать randomization;
- считать missing read-back success;
- follow'ить внешний redirect;
- бесконечно follow'ить same-host redirect;
- оставлять marker после rollback/recovery;
- выполнять destructive DB tests на production DB.

---

# 15. ОБЯЗАТЕЛЬНЫЙ TEST MATRIX ПЕРЕД СБОРКОЙ

## Robots
[ ] closed root -> terminal
[ ] closed + DNS -> terminal
[ ] closed + 503 -> terminal
[ ] DNS only -> bounded retry
[ ] Allow:/ -> open
[ ] Allow:/favicon.ico + Disallow:/ -> closed
[ ] Allow:/assets + Disallow:/ -> closed

## Same-host
[ ] absolute 301 -> 200
[ ] relative 301 -> 200
[ ] 302 -> same host -> 200
[ ] same host -> own external
[ ] same host -> unknown external
[ ] loop
[ ] >3 redirects

## Redirect disable/readback
[ ] config mechanism
[ ] htaccess mechanism
[ ] legacy index mechanism
[ ] site without redirect
[ ] missing readback -> fail
[ ] partial readback -> fail

## Crash recovery
[ ] mutation completed
[ ] crash before artifact save
[ ] re-entry with no applied_rules
[ ] marker detected
[ ] restore completed
[ ] marker removed

## Randomization
[ ] preflight doesn't increment execution attempts
[ ] actual invocation increments once
[ ] repeated worker ticks do not re-mutate
[ ] timeout -> read-back first
[ ] delta verified
[ ] next stage

## Diagnostics
[ ] real payload technical codes

## DB safety
[ ] production DB refused
[ ] disposable test DB accepted only with opt-in

## Regression
[ ] full REV3.1 regression PASS

---

# 16. REPORT

Перед передачей нового ZIP предоставить:

1. список изменённых production files;
2. diffstat;
3. объяснение ТОЛЬКО corrective changes;
4. подтверждение, что сайты не изменяются;
5. подтверждение, что guard.php не изменяется;
6. подтверждение, что unrelated panel core не изменяется;
7. результаты всех test suites;
8. отдельно full REV3.1 regression;
9. отдельно real-path same-host test;
10. отдельно crash-before-artifact recovery;
11. отдельно robots mixed deterministic/transient;
12. отдельно DB destructive guard;
13. SHA256 каждого production file;
14. SHA256 ZIP;
15. rollback SHA;
16. результат job #227 recovery test.

Не писать:
    "E2E #227 PASS"
если был протестирован только helper/decision function.

---

# 17. FINAL DEFINITION OF DONE

Patch принимается только если:

[ ] same-host 3xx реально проходит production preflight path
[ ] relative Location не теряется
[ ] redirect loops bounded
[ ] external redirects not followed
[ ] legacy crash/re-entry restore реально достижим через stage
[ ] robots_closed dominates transient failures
[ ] Allow favicon/assets не отменяет Disallow:/
[ ] missing exact read-back = failure
[ ] destructive DB test физически не может случайно работать на prod
[ ] installer запускает все corrective pure tests
[ ] full REV3.1 regression PASS
[ ] #227 может продолжить существующую job
[ ] no site migration
[ ] no guard changes
[ ] no unrelated core changes

---

# 18. КОРОТКИЙ PROMPT ДЛЯ РАЗРАБОТЧИКА

Работай поверх текущего `REV3.2-final`.
Не переписывай уже сделанную реализацию.

Исправь ТОЛЬКО 7 оставшихся defects:

1. real-path same-host Location handling;
2. crash/re-entry marker restore через настоящий stage;
3. robots_closed deterministic priority;
4. root-Allow semantics;
5. missing exact read-back must fail;
6. destructive DB suite hard guard;
7. installer must run all corrective pure suites.

Плюс:
- исправить CLI path в INSTALL.md;
- прогнать full REV3.1 regression.

НЕ менять:
- сайты;
- guard.php;
- update_classes.php;
- templates;
- unrelated Refresh Panel core;
- state machine architecture;
- other stages.

Каждая правка:
failing test -> minimal diff -> regression test -> full regression.

Цель:
собрать тот же `REV3.2-final`, только без оставшихся blockers.
Никакого REV3.3 и никаких дополнительных архитектурных улучшений.
