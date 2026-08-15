# Итоговая приёмка пакета `refresh-panel_CUMULATIVE_HOTPATCH_2026-08-15_REV2.zip`

Дата: 2026-08-15

## 1. Вердикт

**REV2 отклонён от production-установки.**

Большинство блокеров прошлой приёмки разработчик действительно исправил. Однако обнаружен новый критический дефект именно в основном production-пути cloaked-recrawl: код читает несуществующие ключи артефактов Webmaster, а тест подменяет их искусственными. В результате после удаления временного bypass из сайта панель снова заблокирует переход на собственный редиректор и не выполнит нужный POST переобхода.

Кроме этого, обязательные тесты installer не изолированы в указанной тестовой БД и выполняют `CREATE/DROP DATABASE` с жёстко заданными именами на том же MariaDB-сервере. Это недопустимо для production-установщика.

Оценка:

- реализация целевой функциональности: приблизительно **85%**;
- готовность к production-установке: **0% до исправления P0-блокеров**;
- переписывать весь патч не требуется — нужны точечные исправления, dump-shaped тест и безопасный installer.

SHA-256 проверенного REV2 ZIP:

`8780e12d1575e70705fe135491c6c68bd90a6f781212e94c8a08bd6dbd37473f`

## 2. Что в REV2 подтверждено

### 2.1. Целостность и база

- В архиве 51 запись.
- Path traversal и symlink-записей нет.
- `MANIFEST.sha256` сходится по всем указанным файлам.
- Все семь `BASE_SHA256` совпадают с последним фактическим архивом панели.
- Патч не собран поверх смешанного дерева.

### 2.2. Рандомизация

Подтверждено статическим анализом production-кода:

- достаточно одной реально изменённой пары классов: aggregate `changed_pairs >= 1`;
- обязательное одновременное изменение desktop и mobile отсутствует;
- `ok:true` без semantic delta не даёт `verified`;
- для нового JSON/atomic updater проверяется completion signal;
- 409/timeout переводится в verify-only polling;
- повторный mutating-вызов на recovery-пути не предусмотрен;
- старое семейство updater не получает новый обязательный JSON-контракт.

### 2.3. Закреплённый IP Webmaster

- typed GET и POST проходят через общий `apiRequest`/routed transport;
- отдельный curl для queue/task GET не добавлен;
- source-IP mismatch/bind остаётся hard-stop без fallback.

### 2.4. Исправленные блокеры прошлой приёмки

| Прошлый блокер | Состояние REV2 | Подтверждение |
|---|---|---|
| Реальная строка №232 не выбиралась | Исправлен | Селектор понимает фактический русский текст без synthetic marker; добавлен `error_kind` |
| Reconciliation только внутри `wm_recrawl` | Исправлен | Есть `reconcileStaleRecrawlRows()` и CLI `bin/reconcile_stale_recrawl.php` |
| Installer менял owner на `root:root` | Основная замена исправлена | Для заменяемых файлов применяются `chown/chmod --reference` до `mv` |
| Ручной rollback не выполнял `rj_pre049.sql` | Технически исправлен | `rollback.sh` выполняет snapshot первым |
| Миграция 049 не видела legacy evidence | Исправлен | Добавлен путь через `sites_managed.uc_detection_json.reason` |
| `added_time` отсутствует → повторный POST | Основной риск закрыт | Совпавшая задача без времени даёт `indeterminate/no POST` |
| Не было полного test output | Исправлен частично | Вывод целевых тестов приложен, но заявленная полная регрессия 47 suites не приложена |

## 3. P0-блокеры

### P0-1. Production cloaked-recrawl всё ещё блокируется из-за неверных ключей артефактов

В `RefreshOrchestrator::buildRedirectFacts()` вычисляется:

```php
$hostVerified = !empty($arts['wm_verify_stage']['verified'])
    || !empty($arts['wm_added_stage']['verified'])
    || !empty($arts['wm_added_stage']['host_verified']);
```

Но фактический production-код панели сохраняет подтверждение в другом месте:

```text
wm_verified_stage.verification_state = verified
wm_verified_stage.confirmed_at       = ...
wm_verified_stage.verified_at        = ...
wm_verified_stage.ok                 = true
```

Это подтверждается и исходным кодом самой панели: стадия Webmaster повсеместно записывает `wm_verified_stage`, а не `wm_verify_stage`.

Фактическая job №223 в последнем дампе содержит:

- `wm_added_stage.ok=true`;
- `wm_added_stage.account_id=2`;
- `wm_added_stage.user_id=2085456521`;
- `wm_added_stage.host_id=https:7k5281.casino:443`;
- `wm_verified_stage.verification_state=verified`;
- `wm_verified_stage.confirmed_at` и `verified_at` заполнены.

При этом в ней отсутствуют:

- `wm_verify_stage`;
- `wm_added_stage.verified`;
- `wm_added_stage.host_verified`.

Следовательно, на реальных данных `$hostVerified=false`.

Дальнейшее фактическое решение политики:

- final host = `dealredirectfast.com`;
- host входит в allowlist;
- `redirect_enabled=1`;
- read-back успешен;
- `host_verified=false` из-за неправильного пути.

Итог: `own_redirector_missing_facts:host_verification`, POST не выполняется, строка снова уходит в retry/failed/awaiting.

#### Почему тесты этого не нашли

В `tests/_gate_w_wiring_migration.php` разработчик создаёт выдуманные данные:

```php
'wm_added_stage' => ['verified' => true]
'wm_verify_stage' => ['verified' => true]
```

Таких ключей нет в фактической job №223 и их не пишет production-стадия `wm_verified`.

Тест проверяет согласованность кода с искусственной фикстурой, а не с реальной схемой артефактов. Поэтому W-05b зелёный, хотя production-путь не работает.

#### Обязательное исправление

1. Получать подтверждение из фактического `wm_verified_stage`:
   - `verification_state IN ('verified','success')`; либо
   - заполнен `confirmed_at`/`verified_at`;
   - `skipped_by_operator` не должен считаться реальным подтверждением.
2. В качестве дополнительного факта разрешается читать `site_webmaster_links.verified=1`, но только с точным совпадением сайта, домена, Webmaster account и host ID.
3. Не считать простое `wm_added_stage.ok=true` доказательством подтверждения прав — это только факт добавления host.
4. Добавить stage-level тест через настоящий `runWmRecrawlStage()` с `artifacts_json`, скопированным по структуре из job №223:
   - только `wm_added_stage.ok/account_id/user_id/host_id`;
   - `wm_verified_stage.verification_state=verified`;
   - никаких `wm_verify_stage` и `wm_added_stage.verified`;
   - probe заканчивается на allowlisted redirector;
   - итог: ровно один POST, `status=accepted`.
5. Добавить отрицательный тест: `wm_verified_stage.skipped_by_operator=true` без фактического verified → POST=0.

### P0-2. Installer игнорирует `RP_TEST_DB_NAME` и выполняет destructive DDL в жёстко заданных БД

`install.sh` требует:

```text
RP_TEST_DB_NAME=<panel_db>_hotpatch_test
```

и передаёт его тестам как `RP_DB_NAME`. Но тесты это имя не используют.

Фактические действия тестов:

- `test_stage_uc_randomization.php` → `CREATE DATABASE IF NOT EXISTS rpstage`;
- `test_hotpatch_json_split.php` → `CREATE DATABASE IF NOT EXISTS rphp`;
- `test_migration_049.php` → `DROP DATABASE IF EXISTS rpm49`, а также `rpm49b/rpm49c/rpm49d`;
- `test_gate_w_recrawl.php` → `CREATE DATABASE IF NOT EXISTS rpgatew`;
- `_gate_w_wiring_migration.php` → `DROP DATABASE IF EXISTS rpgatew_bad`;
- `test_wm_pipeline_routing.php` → `CREATE DATABASE IF NOT EXISTS rpwmp`.

Часть тестов дополнительно подключается как `root` с пустым паролем, игнорируя `RP_DB_USER/RP_DB_PASS`.

Риски:

1. Installer может удалить чужую существующую БД с совпавшим именем.
2. Указанная оператором изолированная тестовая БД фактически не используется.
3. При непустом пароле/root socket policy обязательные тесты могут ложно остановить установку.
4. Параллельные установки/прогоны конфликтуют через общие фиксированные базы.

#### Обязательное исправление

1. Все тесты обязаны использовать только `RP_TEST_DB_NAME`, переданное installer.
2. Запретить `CREATE DATABASE`/`DROP DATABASE` внутри PHP-тестов production installer.
3. Installer до тестов обязан проверить:
   - test DB существует;
   - test DB отличается от production DB;
   - имя соответствует строгому допустимому формату;
   - разрешены операции только внутри этой БД.
4. Изоляцию делать уникальным префиксом таблиц либо временной test DB, созданной самим installer под проверенным уникальным именем и удаляемой только по точному имени.
5. Все подключения используют `RP_DB_USER/RP_DB_PASS`, никаких захардкоженных `root,''`.
6. Добавить статический release-gate: в installer-наборе тестов отсутствуют `DROP DATABASE`, фиксированные `USE rpm49/rpgatew/...` и hardcoded credentials.

До исправления этого пункта `install.sh` запускать на production MariaDB нельзя.

## 4. P1-дефекты

### P1-1. Ручной rollback может перемотать уже продвинувшуюся job назад

`rj_pre049.sql` содержит безусловные запросы вида:

```sql
UPDATE refresh_jobs SET stage_status=<старое>, stage_error=<старое>, next_run_at=<старое>
WHERE id=<id>;
```

Если после миграции 049 worker успел обработать requeued job и продвинуть её дальше, ручной rollback через часы или дни всё равно вернёт её в старое `awaiting_user`. Это может уничтожить корректный прогресс.

Текущий e2e проверяет только немедленный rollback до продвижения job и поэтому не видит риск.

Обязательное исправление:

- compensating rollback во время неуспешной установки может восстанавливать snapshot;
- ручной поздний rollback должен использовать compare-and-set и восстанавливать строку только если она всё ещё находится в точном безопасном post-049 состоянии и execution не начинался;
- если job уже продвинулась, не перематывать её, вывести отдельный `NOT_REWOUND_PROGRESS_DETECTED` и понятное решение оператору;
- добавить тест: после 049 job переведена в `completed/verified`, затем запускается rollback — completed job не изменяется.

### P1-2. `ROLLBACK.md` противоречит фактическому `rollback.sh`

Документация утверждает:

- requeue миграции 049 не отменяется;
- evidence-колонки после отката остаются.

Фактический скрипт:

- выполняет `rj_pre049.sql` и отменяет requeue;
- удаляет evidence-колонки, если их не было до установки.

Перед выпуском документация должна точно соответствовать выбранной и протестированной политике отката.

### P1-3. Проверка валидности queue-task не требует `task_id`

Комментарий и REPORT утверждают: валидная queue-задача содержит `task_id + state`. Но `RecrawlReconciliationService::taskValid()` фактически проверяет только `url + state`.

При URL-match ответа без `task_id` сервис может поставить `api_accepted=1` и вызвать `markAcceptedFromQueue()` с пустым task ID.

Исправление:

- для queue-list обязательно требовать `task_id`, `url`, `state`;
- для прямого GET по уже известному локальному task ID допускается использовать локальный ID, но он должен быть явно возвращён в нормализованной задаче;
- добавить отрицательный тест queue item без `task_id` → не accepted, POST=0 до получения определённого результата.

### P1-4. Заявленный `date_from` не реализован

REPORT говорит, что reconciliation использует `date_from`, но `getRecrawlTasks()` принимает и передаёт только `offset` и `limit`. Это не создаёт повторный POST благодаря `page_cap_exhausted → indeterminate`, но на большой очереди может приводить к бесконечному ожиданию, хотя нужная задача находится дальше первых 500 элементов.

Исправление:

- добавить typed `date_from` в `getRecrawlTasks()` и routed `apiRequest`;
- pagination выполнять внутри заданного временного окна;
- если API не принял фильтр — fail-closed `indeterminate/no POST` с точной диагностикой.

### P1-5. Заявленная полная регрессия `47 suites / PASS=1193` не подтверждена приложенным runner

`tests/run_all.sh` запускает четыре PHP-файла и e2e installer/rollback. Он не запускает даже приложенный `test_wm_pipeline_routing.php`.

В `TEST_STDOUT.txt` присутствуют результаты:

- 27 pass randomization;
- 22 pass JSON component;
- 21 pass migration 049;
- 75 pass Gate W;
- 50 pass installer/rollback.

Это суммарно 195 видимых проверок, а строки `PASS=1193` и полного списка 47 suite в stdout нет.

Следовательно, целевые тесты приложены, но утверждение о полной регрессии остаётся недоказанным.

Исправление:

- единый runner должен запускать полный regression-набор, включая `test_wm_pipeline_routing.php`;
- приложить сырой stdout каждого suite и итоговую таблицу с арифметически проверяемой суммой;
- не писать `PASS=1193`, если эта цифра отсутствует в выводе runner.

## 5. Точное ответное ТЗ разработчику

Передать разработчику следующий текст.

---

### Статус

REV2 не принят. Не переписывай реализованную рандомизацию, typed Webmaster GET, reconciliation-сервис, миграции и owner-preserving replacement. Исправь конкретные P0/P1 ниже и передай один REV3 ZIP.

### 1. Исправить фактический Webmaster verification evidence

В `buildRedirectFacts()` запрещено читать выдуманные `wm_verify_stage.verified` и `wm_added_stage.verified` как единственный production-путь.

Использовать фактические данные панели:

- `wm_verified_stage.verification_state IN ('verified','success')`;
- либо подтверждённые `confirmed_at/verified_at` при отсутствии `skipped_by_operator`;
- опционально точная строка `site_webmaster_links.verified=1` для текущих site/domain/account/host.

`wm_added_stage.ok` — только факт добавления host, не доказательство прав.

Добавить stage-level acceptance с dump-shaped `artifacts_json` job №223. Никаких synthetic `wm_verify_stage`/`wm_added_stage.verified`. Для allowlisted redirector результат: `POST=1`, accepted. Для skipped/nonverified: `POST=0`.

### 2. Изолировать тестовую БД installer

- Все installer-тесты работают только в `RP_TEST_DB_NAME`.
- Production DB и test DB обязаны различаться.
- Удалить из PHP-тестов `CREATE/DROP DATABASE` и фиксированные имена `rpstage`, `rphp`, `rpm49*`, `rpgatew*`, `rpwmp`.
- Удалить hardcoded `root`/пустой пароль.
- Использовать переданные credentials.
- Любой destructive SQL должен быть ограничен проверенной test DB.
- Добавить gate, который падает при наличии hardcoded database DDL/credentials.

### 3. Сделать rollback безопасным при прогрессе job

- Немедленный compensating rollback восстанавливает 049 snapshot.
- Поздний ручной rollback не перематывает уже started/completed/verified job.
- Использовать compare-and-set по post-049 состоянию и execution-полям.
- Добавить тест `049 → job progressed → rollback`: прогресс сохранён, оператор получает явное предупреждение.
- Привести `ROLLBACK.md` в полное соответствие коду.

### 4. Ужесточить queue-task и пагинацию

- Queue item валиден только при непустых `task_id`, `url`, `state`.
- Для direct task GET нормализовать известный local task ID.
- Реализовать `date_from` через тот же routed `apiRequest`.
- При неполном ответе/исчерпанном окне — `indeterminate/no POST`.

### 5. Исправить доказательства

- `run_all.sh` запускает весь заявленный regression, включая `test_wm_pipeline_routing.php`.
- `TEST_STDOUT.txt` содержит полный необрезанный вывод и exit code каждого suite.
- Итоговый PASS должен совпадать с арифметической суммой видимых результатов.

### Инварианты, которые нельзя менять

1. Одного реально изменённого класса достаточно.
2. `ok:true` без delta не является успехом.
3. 409/timeout → verify-only, mutating не более одного.
4. Старые updater продолжают работать по aggregate delta.
5. Webmaster GET/POST используют закреплённый IP, mismatch/bind → hard-stop без fallback.
6. Для job №223 повторный POST запрещён; только GET-reconciliation stale-строки.
7. Правки сайта и `guard.php` в ZIP запрещены.
8. Хардкоды job/domain/account/IP/task запрещены.

### Обязательный результат REV3

- один ZIP;
- SHA-256;
- `MANIFEST.sha256` и точный `BASE_SHA256`;
- исправленные production-файлы;
- безопасные `install.sh`/`rollback.sh`;
- dump-shaped stage test реального `wm_verified_stage`;
- изолированные DB tests;
- полный runner/output;
- INSTALL/ROLLBACK/REPORT без противоречий.

### Definition of Done

REV3 принимается только при одновременном выполнении:

- реальный формат job №223 даёт `host_verified=true`;
- allowlisted redirector проходит stage и выполняет ровно один POST;
- skipped/nonverified не выполняет POST;
- installer никогда не создаёт и не удаляет произвольно названные БД;
- test DB физически отделена от production DB;
- поздний rollback не перематывает progressed job;
- queue item без task ID не принимается;
- `date_from` реально присутствует в typed GET;
- полный runner имеет `FAIL=0`, `SKIP=0`, сумма PASS подтверждается stdout;
- сайт и `guard.php` не изменяются.

---

## 6. Решение по текущей job №223

Job №223 уже `done/ok`. REV2 не следует устанавливать даже только ради неё.

После выпуска принятого REV3:

1. выполнить dry-run общего reconciler;
2. выполнить GET-only reconciliation строки №232;
3. убедиться, что строка стала `accepted/queue_reconciled` с реальным task ID;
4. не выполнять повторный POST;
5. не возвращать job на `wm_recrawl`;
6. после успешного canary удалить временные изменения `guard.php`, сделанные вручную во время диагностики.

## 7. Ограничение независимой проверки

В текущей среде приёмки отсутствуют PHP и MariaDB, поэтому приложенные тесты не были независимо перезапущены. Bash-синтаксис installer/rollback проверен, архив и SHA проверены, а описанные P0/P1 подтверждены прямым сопоставлением production-кода с последним SQL-дампом. Критический P0-1 не зависит от выполнения тестов: несовпадение имён ключей видно напрямую в коде и реальных артефактах.
