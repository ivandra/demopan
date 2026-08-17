# FINAL CONVERGENCE TASK
## Refresh Panel REV3.2-final — собрать ОДИН действительно финальный cumulative patch
### Запрещены промежуточные “почти финальные” ZIP

Дата: 17.08.2026

---

# 0. ЦЕЛЬ ЭТОГО ДОКУМЕНТА

Текущая проблема процесса разработки:

- исправляется очередной найденный дефект;
- сразу собирается новый ZIP;
- ZIP попадает на внешний аудит;
- следующий межстадийный/интеграционный дефект находится уже после сборки;
- появляется final_2, final_3, final_4...

Так больше работать нельзя.

Следующий ZIP должен быть собран **ТОЛЬКО ПОСЛЕ внутреннего полного аудита всей изменённой цепочки**.

Не присылать:
- draft ZIP;
- review ZIP;
- candidate ZIP;
- “почти final”;
- hotfix поверх final_3;
- архив “посмотреть, что осталось”.

Следующая передача должна быть только:

`refresh-panel_CUMULATIVE_HOTPATCH_2026-08-16_REV3.2-final.zip`

и разработчик перед отправкой должен сам считать его production candidate.

Если в процессе внутреннего тестирования найден новый дефект:
- исправить локально;
- НЕ собирать/не отправлять ZIP;
- заново прогнать весь gate;
- только после полного PASS собирать архив.

---

# 1. FREEZE SCOPE

Production runtime scope зафиксирован.

Разрешён максимум:

```text
app/Services/RefreshOrchestrator.php
app/Services/RedirectToggler.php
app/Services/SiteVerifier.php
app/Services/DiagnosticCatalog.php
bin/repair_stuck_job.php
```

Packaging/support:

```text
install.sh
rollback.sh
tests/*
fixtures/*
INSTALL.md
ROLLBACK.md
REPORT.md
MANIFEST.sha256
BASE_SHA256
TEST_STDOUT.txt
REGRESSION_*.txt
```

Не добавлять новые runtime-файлы без нового доказанного P0, который невозможно исправить в разрешённом scope.

Категорически не менять:

```text
guard.php
site config.php как шаблон
site index.php как шаблон
site update_classes.php
site robots.php
site .htaccess
templates
FastPanel integration
Namecheap integration
Yandex Webmaster integration
SSL pipeline
IndexNow
index watching
scheduler architecture
worker architecture
job locking
unrelated DB schema
```

Не делать архитектурный рефакторинг.

---

# 2. НЕ ОПТИМИЗИРОВАТЬ ПОД ОДНУ JOB #227

Job #227 — обязательный acceptance fixture, но patch должен сохранять существующие типы сайтов.

Обязательные классы сайтов:

## A. Legacy Mellstroy

Redirect:

```php
handleRequest($configRed);
```

в `index.php`.

Нет `$redirect_enabled`.

## B. Config-based / guard-based site

Redirect управляется:

```php
$redirect_enabled = 1;
```

Panel temporary disable:

```php
$redirect_enabled = 0; // REFRESH-DISABLED
```

## C. .htaccess-based redirect

Existing supported marker/rule mechanism.

## D. Site with no supported redirect

Panel не должна ничего ломать и не должна выдумывать mutation.

Каждый из этих четырёх типов обязан пройти full lifecycle tests.

---

# 3. ГЛАВНЫЙ ИНВАРИАНТ

Для КАЖДОЙ стадии:

```text
SUCCESS
=
реальный postcondition доказан
```

Не допускается:

```text
HTTP 200 => success
empty array => success
отсутствует evidence => success
не получилось проверить => success
applied_rules=[] => success
```

Неизвестность всегда трактуется fail-closed там, где речь идёт о mutation/recovery.

---

# 4. ОБЯЗАТЕЛЬНЫЙ FULL LIFECYCLE TEST — НЕ HELPER TEST

Основная причина прошлых итераций:
тестировались helper-функции, а ошибка находилась между стадиями.

Поэтому следующий release НЕ собирать, пока не создан integration harness, который выполняет цепочку как pipeline.

Минимум:

```text
redirect_disable
-> config_replaced / site mutation simulation
-> verify_replacement
-> update_classes preflight
-> update_classes mutation
-> update_classes verification
-> redirect_enable
-> final state verification
```

Не обязательно запускать весь production worker.
Но должны вызываться реальные production methods/services в том же порядке и с тем же state/artifacts contract.

---

# 5. ОБЯЗАТЕЛЬНЫЕ LIFECYCLE СЦЕНАРИИ

## 5.1. Legacy Mellstroy / job #227

Начальное состояние:

```text
stage=update_classes_call
stage_status=pending
uc_execution_state=NULL
uc_execution_attempts=0
```

Реальный fixture:

`melllarchiveYdEiA.tar.gz`

Проверить:

```text
HTTP 200
meta refresh / JS
-> partners7k-promo.com

own redirect detected
-> one controlled repair
-> handleRequest($configRed) temporarily disabled
-> exact read-back
-> preflight sees real own site
-> actual update_classes exactly once
-> uc_execution_attempts=1
-> mapping delta 37/37 (или реальное количество fixture)
-> next stage
-> redirect restored
-> original legacy redirect behavior restored
```

Новая job не создаётся.

## 5.2. Config-based site — КРИТИЧЕСКИЙ regression test

Исходно:

```php
$domain = 'https://old.example';
$title = 'OLD';
$redirect_enabled = 1;
```

После redirect_disable:

```php
$domain = 'https://old.example';
$redirect_enabled = 0; // REFRESH-DISABLED
```

После штатного config_replaced:

```php
$domain = 'https://new.example';
$title = 'NEW';
$redirect_enabled = 0; // REFRESH-DISABLED
```

После redirect_enable ОБЯЗАТЕЛЬНО:

```php
$domain = 'https://new.example';
$title = 'NEW';
$redirect_enabled = 1;
```

Запрещено восстанавливать whole-file snapshot старого config поверх нового config.

## 5.3. .htaccess site

```text
active rule
-> disable only own supported rule
-> read-back
-> rest of .htaccess preserved
-> enable
-> original redirect rule restored
-> unrelated rules byte/semantic preserved
```

## 5.4. No redirect site

```text
nothing supported active
-> no mutation
-> absence доказано
-> no_active_redirect
```

Не принимать inability-to-read как absence.

---

# 6. RECOVERY / CRASH MATRIX

Прогнать не один crash case, а matrix.

Для каждого supported mechanism:

```text
legacy index
config redirect_enabled
.htaccess
```

эмулировать crash:

### Window 1
После remote write, ДО artifact save.

### Window 2
После artifact save, ДО следующей stage.

### Window 3
Во время redirect_enable.

### Window 4
После mutation update_classes, но ДО сохранения HTTP result.

Ожидаемо:

- никакой blind second mutation;
- marker-scoped recovery;
- unrelated files/states не меняются;
- exact/semantic postcondition;
- uncertain mutation -> verify first;
- невозможность проверить -> awaiting_user.

---

# 7. LOST-ARTIFACTS RECOVERY — MARKER SCOPED ONLY

При пустых artifacts запрещено:

```text
enable ALL known rules
```

Recovery строится только на evidence текущего remote state.

Пример:

```text
index.php содержит:
REFRESH_DISABLED_HANDLEREQUEST

config.php содержит:
$redirect_enabled = 0;
БЕЗ REFRESH-DISABLED
```

После recovery:

```text
index.php -> legacy marker снят
config.php -> redirect_enabled ОСТАЁТСЯ 0
```

Panel не доказала, что config state принадлежит текущей job.

И наоборот:

```php
$redirect_enabled = 0; // REFRESH-DISABLED
```

является evidence собственного temporary disable.

---

# 8. SNAPSHOT POLICY

Snapshot нужен для:

- audit;
- emergency recovery;
- exact restore, если файл не должен был меняться другими stages.

Но snapshot НЕ означает:

```text
в конце залить старый файл целиком
```

Перед whole-file restore разработчик обязан доказать:

```text
этот файл НЕ является intentional output другой стадии между disable и enable
```

Особенно:

```text
config.php
```

меняется стадией config_replaced.

Поэтому config redirect restore — scoped semantic restore текущего post-config_replaced файла.

---

# 9. PREFLIGHT — ОДИН FINAL CONTRACT

Preflight должен возвращать structured result:

```text
ok
reason_code
retryable
http_code
current_url
location
redirect_host
is_own_redirect
trusted_site_evidence
details
```

## External behavior precedence

До принятия HTTP 2xx как site content проверить:

- HTTP Location;
- meta refresh;
- supported JS immediate redirect.

Если external host:

```text
own allowlist -> own_redirect_active
unknown       -> external_redirect_unknown
```

Внешний URL не follow.

## Same-host redirects

Разрешено:

- максимум 3 redirect;
- максимум 4 HTTP responses;
- absolute URL;
- `//host/path`;
- `/path`;
- path-relative;
- `?query`;
- trailing slash preserved.

Loop по full normalized URL.

Никаких synthetic HTTP 200.

Terminal 2xx success только если:

```text
new-domain marker
OR trusted site evidence
```

Generic 200 без evidence — failure.

---

# 10. ROBOTS FINAL CONTRACT

Для нового домена root accessibility анализировать по `User-agent: *`.

Обязательно одинаково во ВСЕХ fetch/parser fallback methods.

Минимум:

```text
UA:* Disallow:/
=> closed

UA:* Disallow:/ + Allow:/
=> open

UA:* Disallow:/ + Allow:/public/
=> closed

UA:* Disallow:/ + Googlebot Allow:/
=> closed
```

`robots_closed=true` имеет deterministic priority над одновременно возникшим DNS/503 transient.

---

# 11. REDIRECT DISABLE FINAL CONTRACT

Разделять:

```text
detected_mechanisms
verified_inactive_mechanisms
applied_rules
readback
errors
```

Для каждого applied file:

```text
readback[file] exists
AND readback[file] === true
```

Иначе failure.

`detect=true + apply count=0` не может молча стать no_active_redirect.

---

# 12. FTP ROOT FINAL CONTRACT

Нельзя искать:

```text
.htaccess в root A
index.php в root B
config.php в root C
```

Сначала определить ОДИН authoritative site base path.

После этого все marker/readback operations идут относительно него.

Если root не доказан:
fail-closed.

---

# 13. INSTALLER — НАСТОЯЩАЯ TRANSACTION

До первого write:

```text
MANIFEST verified
BASE_SHA verified
backup completed
backup byte-verified
```

После первого write и до COMMIT:

ЛЮБАЯ ошибка обязана вызвать automatic rollback.

Не только:
- lint fail;
- tests fail.

Но и:
- cp fail;
- mkdir fail;
- disk full;
- permission;
- post-copy SHA fail;
- неожиданная shell error.

Нужен transaction trap/state.

После rollback:
BASE SHA должен быть byte-identical.

Если rollback itself не byte-identical:
не печатать обычный FAIL;
печатать CRITICAL_ROLLBACK_FAILED и non-zero special exit.

---

# 14. INSTALLER ADVERSARIAL TEST MATRIX

До сборки ZIP разработчик обязан специально ломать installer.

Минимум:

```text
1. MANIFEST corrupted
2. BASE mismatch
3. first cp fail
4. second/third cp fail
5. mkdir bin fail AFTER service copies
6. repair CLI cp fail
7. php -l fail
8. post-copy SHA fail
9. pure tests fail
10. DB tests fail
11. restore source corrupted
12. pre-existing repair CLI exists
```

После КАЖДОГО failed install:

```text
4 service files == REV3.1 BASE SHA
pre-existing repair CLI preserved if existed
no partial payload remains
```

---

# 15. ROLLBACK ADVERSARIAL TEST

Standalone rollback:

До write:

```text
MANIFEST valid
rollback source SHA == BASE_SHA
```

После restore:

```text
target SHA == BASE_SHA
```

Pre-existing repair CLI policy должна быть доказуема.

Никакого unconditional delete неизвестного файла.

---

# 16. TEST QUALITY GATE

Нельзя засчитывать тест, который проверяет не тот production path.

Перед релизом разработчик должен для каждого critical test указать:

```text
WHAT production method is called?
WHAT state/artifacts are used?
WHAT remote behavior is simulated?
WHAT exact postcondition is asserted?
```

Запрещено:

- вручную вызвать classifier и назвать это E2E;
- вручную записать ожидаемый result другого service;
- тестировать helper вместо orchestration и назвать это pipeline recovery;
- делать hidden real internet fetch в deterministic unit/integration test.

---

# 17. REAL FIXTURES

Обязательные fixtures:

1. Реальный Mellstroy legacy index.
2. Реальный/репрезентативный config-based site.
3. .htaccess redirect fixture.
4. no-redirect fixture.
5. modern update_classes fixture.
6. legacy update_classes fixture.

Если fixture изменён специально под тест:
это явно отметить.

Не менять fixture так, чтобы он перестал представлять production.

---

# 18. FULL REGRESSION

Нужны ДВА отчёта:

## A. Baseline REV3.1

Прогнать штатный regression на чистом REV3.1.

## B. Candidate

Прогнать тот же regression на candidate.

Критерий:

```text
новых FAIL = 0
```

Если baseline уже имеет environment-dependent FAIL:
они должны совпасть и быть отдельно объяснены.

Не писать:

```text
FULL PASS
```

если фактически есть FAIL.

---

# 19. DB SUITE

DB tests реально запускать на disposable MySQL DB.

Нельзя:

- подменять DB suite mock;
- писать PASS без реального запуска.

Hard safety:

```text
database suffix = _hotpatch_test
AND
ALLOW_DESTRUCTIVE_TEST_DB=YES
```

Production DB hard-deny.

---

# 20. INTERNAL RED-TEAM PASS — ОБЯЗАТЕЛЕН ДО ZIP

После того как все известные тесты зелёные, разработчик НЕ собирает ZIP сразу.

Сначала делает отдельный internal red-team review:

Вопросы, на которые нужно письменно ответить:

### A. Межстадийные данные
- Какие файлы изменяются до/после каждой touched stage?
- Может ли snapshot затереть результат следующей stage?
- Может ли restore включить механизм, который эта job не выключала?

### B. Fail-open
- Где `[]`, `null`, `false`, exception могут ошибочно трактоваться как success?
- Где inability-to-check == absence?

### C. Retry
- Может ли deterministic defect всё ещё повторяться бесконечно?
- Может ли mutation повториться после ambiguous timeout?

### D. Filesystem
- Что произойдёт при ошибке после первого production write?
- Что произойдёт при частичном rollback?

### E. Compatibility
- legacy site?
- config site?
- .htaccess site?
- no redirect?
- www/non-www?
- same-host redirect?
- external meta redirect?

Все ответы должны быть подтверждены тестом или конкретным инвариантом.

Если во время red-team найден defect:
вернуться к коду.
ZIP всё ещё НЕ собирать.

---

# 21. ONLY THEN — BUILD THE ZIP

Сборка ZIP разрешена только когда:

```text
known P0 = 0
known P1 affecting production correctness = 0
all mandatory tests passed
regression delta = 0
installer adversarial matrix passed
rollback adversarial matrix passed
DB suite passed
red-team review found no unresolved defect
```

Порядок:

```text
1. freeze working tree
2. generate final docs
3. generate BASE_SHA
4. generate MANIFEST LAST
5. verify MANIFEST
6. build ZIP
7. compute ZIP SHA
8. extract ZIP into NEW EMPTY DIRECTORY
9. run ALL package tests FROM EXTRACTED ZIP
10. run install simulation FROM EXTRACTED ZIP
11. run forced-failure rollback simulation FROM EXTRACTED ZIP
12. verify SHA again
```

Если после шага 4 меняется хотя бы один byte:
MANIFEST пересчитать заново.

---

# 22. НЕ ПРИСЫЛАТЬ ZIP, ЕСЛИ ЕСТЬ ХОТЬ ОДНО ИЗ ЭТОГО

```text
"это почти готово"
"этот edge case потом"
"скорее всего работает"
"этот тест только helper"
"DB не запускал, но должно"
"installer rollback не симулировал"
"на #227 работает, остальные потом"
"REPORT говорит PASS, но raw log нет"
```

В таком состоянии продолжать работу локально.

---

# 23. ЧТО ПРИСЛАТЬ ОДНИМ СООБЩЕНИЕМ

Только после полного gate:

1. Один cumulative ZIP.
2. SHA-256 ZIP.
3. REPORT.md.
4. TEST_STDOUT.txt.
5. baseline regression log.
6. candidate regression log.
7. installer adversarial log.
8. rollback adversarial log.
9. DB test log.
10. список production changed files.
11. diffstat.
12. подтверждение:
   - сайтовые файлы/шаблоны не изменялись;
   - новой migration нет;
   - новая job #227 не создавалась;
   - updater subsystem не добавлялась.

---

# 24. DEFINITION OF DONE

Следующий архив считается готовым только если разработчик может без оговорок подтвердить:

```text
Я прогнал не только отдельные helpers, а полный межстадийный lifecycle.

Я проверил legacy, config-based, htaccess и no-redirect сайты.

Я проверил crash windows.

Я проверил, что snapshot не уничтожает результаты последующих stages.

Я проверил lost-artifacts recovery и он marker-scoped.

Я проверил реальный Mellstroy meta/JS redirect.

Я реально запустил legacy update_classes fixture.

Я проверил robots во всех fallback paths.

Я специально ломал installer после частичной записи и каждый раз получил byte-exact REV3.1 rollback.

Я специально проверил standalone rollback.

Я реально прогнал DB suite.

Я сравнил REV3.1 и candidate regression.

После этого я отдельно сделал red-team review и не нашёл известных P0/P1 correctness blockers.

MANIFEST создан последним.

Я распаковал уже готовый ZIP и повторил проверки именно из него.
```

Только после этого присылать архив.

---

# 25. ФИНАЛЬНЫЙ PROMPT

Не собирай следующий ZIP после исправления очередного пункта.

Твоя задача сейчас — не “закрыть последние замечания”.
Твоя задача — самостоятельно довести текущую ветку до состояния, в котором внешний reviewer НЕ должен находить следующий класс межстадийной ошибки.

Работай итеративно ЛОКАЛЬНО:

```text
исправление
-> targeted test
-> lifecycle tests
-> crash matrix
-> regression
-> installer/rollback adversarial
-> DB
-> internal red-team
```

Если что-то найдено — снова код и весь gate.

ZIP собирать ОДИН РАЗ в самом конце.

Следующая передача — только один cumulative production candidate.
