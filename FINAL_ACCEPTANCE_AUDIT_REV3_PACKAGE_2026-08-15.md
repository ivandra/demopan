# Приёмочный аудит REV3 — refresh-panel cumulative hotpatch

Дата проверки: 2026-08-15  
Пакет: `refresh-panel_CUMULATIVE_HOTPATCH_2026-08-15_REV3.zip`  
SHA-256 пакета: `77b491461f77b326982501ca45535aa8d72bcb6801e67cbc3750032cdec11f7b`

## 1. Итоговый вердикт

**REV3 существенно лучше REV2 и закрывает основной функциональный путь, но в текущем виде ещё не должен
устанавливаться в production.**

Оценка:

- готовность реализованной логики: **примерно 96%**;
- три главные задачи пользователя: основная логика реализована;
- production-приёмка: **не пройдена**, пока не устранён остаток P0-2 и два точных дефекта схемы/диагностики;
- требуемый следующий выпуск: **REV3.1/REV4 как точечная коррекция**, без новой архитектуры и без
  переписывания уже принятой рандомизации.

## 2. Что проверено и принято

### 2.1. Целостность и база

- ZIP содержит 53 записи, traversal/symlink не обнаружены.
- `MANIFEST.sha256` сходится для всех перечисленных файлов.
- Все семь `BASE_SHA256` совпадают с последним фактическим архивом панели.
- Все семь файлов `rollback/` побайтно соответствуют последней базе.
- Коллизий новых файлов с текущим деревом панели нет.
- Bash-синтаксис `install.sh`, `rollback.sh`, `tests/run_all.sh` и
  `tests/test_install_rollback.sh` корректен.
- Пакет содержит только файлы панели; файлов сайта и `guard.php` нет.

### 2.2. Рандомизация — принята

- Для успеха достаточно **aggregate semantic delta >= 1**, то есть хотя бы одного реально изменённого
  класса.
- Отдельное обязательное изменение desktop и mobile отсутствует.
- `ok:true` без фактической delta не считается успехом.
- Новый JSON/atomic updater использует completion-proof и verify-only после `409`/timeout.
- Повторный mutating-вызов после уже выполненного вызова запрещён.
- Старые версии updater сохраняют aggregate-проверку и не ужесточены новой atomic-политикой.
- REV3 не переписывал принятую в REV2 реализацию рандомизации.

### 2.3. Webmaster IP routing — принят

- Typed GET очереди и отдельной задачи идут через тот же `apiRequest()` и routed transport, что POST.
- Закреплённый IP сохраняется для GET и POST.
- `expected_source_ip === actual_source_ip` и `source_ip_verified=1` сохраняются как инварианты.
- Ошибка routing/source-IP пробрасывается без fallback на другой адрес.
- `date_from` действительно является официальным параметром API очереди переобхода и добавлен в typed GET.

### 2.4. Основной cloaked-recrawl — исправлен

`resolveHostVerified()` теперь читает фактический:

```text
wm_verified_stage.verification_state
wm_verified_stage.confirmed_at
wm_verified_stage.verified_at
```

Это соответствует job №223 из последнего дампа. Для неё основной путь теперь даёт
`host_verified=true`; `wm_added_stage.ok=true` отдельно не считается подтверждением прав.

### 2.5. Reconciliation — основная часть принята

- Реальная stale-строка №232 выбирается по существующему русскому сообщению, без синтетического маркера.
- Независимый от стадии GET-only reconciler может работать с job в `done/index_watching`.
- `IN_PROGRESS/DONE` подтверждают локальную строку без повторного POST.
- `DONE` не подменяет факт индексации.
- `409` после одного POST приводит к повторному GET, а не ко второму POST.
- Queue-item без `task_id` не принимается по URL-поиску.
- Невалидный/отсутствующий `added_time` и недочитанная очередь дают fail-closed `indeterminate`.
- Job №223 нельзя возвращать на `wm_recrawl` и нельзя переотправлять; для неё нужен только GET-reconcile.

### 2.6. Прежние P1 в основном закрыты

- Rollback migration 049 переведён на compare-and-set: продвинувшаяся job не перематывается.
- Добавлен `NOT_REWOUND_PROGRESS_DETECTED` и операторская диагностика.
- Queue URL-match требует `task_id + url + state`.
- Реализован официальный `date_from`.
- `run_all.sh` теперь включает `test_wm_pipeline_routing.php` и печатает проверяемую сумму:
  PHP-suites `PASS=193`, e2e отдельно `PASS=57`, `FAIL=0`.

## 3. Блокирующие и обязательные замечания

## P0 — изоляция тестовой БД всё ещё не fail-closed внутри PHP-helper

Файл: `tests/_test_db.php`.

Текущий код:

```php
$db = getenv('RP_TEST_DB_NAME');
if ($db === false || $db === '') { $db = getenv('RP_DB_NAME'); }
```

Далее `rp_test_reset()` удаляет все таблицы выбранной базы. Поэтому прямой запуск suite с заданным
`RP_DB_NAME=<production>` и забытым `RP_TEST_DB_NAME` способен очистить production-схему.

Это противоречит собственному REPORT REV3 («тесты используют только RP_TEST_DB_NAME») и прямому
требованию аудита REV2: **никакого fallback с тестовой БД на production-переменную**.

Почему стандартный `install.sh` сам по себе стал безопаснее: он требует отдельное имя, проверяет формат,
проверяет неравенство production и создаёт test DB. Но защитная граница должна находиться также внутри
destructive helper — запуск теста вне installer не должен зависеть от дисциплины оператора.

### Обязательное исправление

1. В `_test_db.php` удалить fallback на `RP_DB_NAME` полностью.
2. `RP_TEST_DB_NAME` сделать безусловно обязательной.
3. Проверять в helper строгий формат `*_hotpatch_test` / `*_hotpatch_test_<N>`.
4. Перед `rp_test_reset()` повторно проверять, что имя равно уже валидированному test DB.
5. Статический gate должен падать, если helper содержит fallback `RP_TEST_DB_NAME -> RP_DB_NAME`.
6. Добавить негативные тесты:
   - задан только `RP_DB_NAME`, `RP_TEST_DB_NAME` отсутствует → exit non-zero **до подключения/DDL**;
   - `RP_TEST_DB_NAME` не соответствует формату → exit non-zero;
   - test DB совпадает с явно переданным production-name → exit non-zero;
   - корректная test DB → reset затрагивает только её.

## P1 — fallback `site_webmaster_links` использует несуществующую колонку

Файл: `payload/app/Services/RefreshOrchestrator.php`, метод `resolveHostVerified()`.

Текущий SQL:

```sql
WHERE site_id=? AND account_id=? AND host_id=? AND domain=? AND verified=1
```

Фактическая схема последнего дампа:

```text
site_webmaster_links.webmaster_account_id
```

Колонки `site_webmaster_links.account_id` нет. Исключение подавляется `catch`, поэтому fallback всегда
молча возвращает false. В дампе при этом существует точная строка для сайта №7:

```text
site_id=7
domain=7k5281.casino
webmaster_account_id=2
host_id=https:7k5281.casino:443
verified=1
```

Основной путь job №223 работает по `wm_verified_stage`, поэтому текущий инцидент этим дефектом уже не
блокируется. Но legacy/recovery job без полного artifacts_json снова получит ложный
`own_redirector_missing_facts:host_verification` и потребует человека.

### Обязательное исправление

Заменить `account_id` на `webmaster_account_id` и добавить dump-shaped тест fallback-пути:

- в artifacts отсутствует `wm_verified_stage`;
- `wm_added_stage` содержит реальные `account_id/user_id/host_id`;
- в `site_webmaster_links` лежит реальная схема с `webmaster_account_id`;
- точное совпадение site/domain/account/host и `verified=1` → `host_verified=true`, POST=1;
- несовпадение любого поля или `verified=0` → POST=0.

Тестовая таблица уже создаётся с правильной колонкой в `test_wm_pipeline_routing.php`, но сам fallback
этим тестом не вызывается — именно поэтому дефект не был обнаружен.

## P1 — любой HTTP 4xx ошибочно называется `date_from_filter_rejected`

Файл: `RecrawlReconciliationService::reconcile()`.

Текущий catch классифицирует **любой** `HTTP 4xx` как отказ параметра `date_from`. Однако API очереди
также возвращает, например, `403 INVALID_USER_ID` и `404 HOST_NOT_VERIFIED`. Такие ответы не являются
отказом фильтра и требуют другой диагностики/маршрутизации стадии.

### Обязательное исправление

- `date_from_filter_rejected` выставлять только при доказанном отказе самого параметра/контракта;
- `HOST_NOT_VERIFIED` передавать в штатную ветку ожидания/повторной проверки прав;
- `INVALID_USER_ID` и account/auth ошибки классифицировать как account/routing blocker;
- неизвестный 4xx не маскировать как ошибку `date_from`; fail-closed сохранить;
- тесты минимум на 400 invalid date_from, 404 HOST_NOT_VERIFIED, 403 INVALID_USER_ID.

## P1 — неполный queue-item с совпавшим local task_id обходит валидацию

В ветке поиска по `$localTaskId` совпадение `task_id` сразу присваивается `$match`, без
`taskValidQueue()`. Если queue-item содержит ID, но не содержит URL/state, прямой task GET уже не
выполняется, а downstream получает `UNKNOWN`.

### Обязательное исправление

- совпадение local task ID в queue принимать только через `taskValidQueue()`;
- неполный queue-item → выполнить direct typed task GET;
- если и direct GET неполный → fail-closed/indeterminate, без POST;
- тест должен доказывать `mutating POST=0` на неполных данных.

## P1/P2 — сообщения rollback всё ещё противоречат фактической политике

`ROLLBACK.md` исправлен, но в `rollback.sh` остались неверные сообщения:

```text
«появившиеся после установки данные сохраняются»
«reconciliation-evidence сохранены»
```

Фактически evidence-колонки, добавленные установкой, удаляются вместе с данными. В e2e также осталась
подпись `evidence-колонка сохранена (не дропнута)` при ожидаемом значении `0`, то есть тест реально
проверяет удаление.

Исправить комментарии, финальное сообщение `ROLLBACK_OK` и подпись теста под фактическое поведение.

## 4. Точное ответное ТЗ разработчику

Не расширять архитектуру и не переписывать принятые части. Сделать один точечный cumulative
REV3.1/REV4 поверх точной базы REV3:

1. Закрыть PHP-level DB isolation: только обязательный `RP_TEST_DB_NAME`, без fallback на
   `RP_DB_NAME`, с проверкой формата и негативными destructive-safety тестами.
2. Исправить `site_webmaster_links.account_id` на `webmaster_account_id`; добавить dump-shaped stage-test
   реального fallback-пути.
3. Развести 4xx reconciliation по реальным причинам; не называть `HOST_NOT_VERIFIED` ошибкой date_from.
4. Валидировать queue-item по local task ID через `taskValidQueue`; неполный ответ не должен разрешать POST.
5. Синхронизировать все сообщения rollback/e2e с реальной политикой удаления evidence-колонок.
6. Не менять:
   - aggregate randomization proof (`changed_pairs >= 1`);
   - JSON/atomic completion proof и verify-only;
   - поддержку старых updater;
   - PATCH 047 source-IP routing;
   - GET-only stale reconciliation;
   - миграции 049/050, кроме необходимости тестовой проводки;
   - файлы сайтов.
7. Передать один ZIP, обновлённые SHA/MANIFEST/REPORT/TEST_STDOUT/INSTALL/ROLLBACK.

## 5. Обязательные acceptance-gates следующего пакета

| Gate | Обязательный результат |
|---|---|
| Integrity | manifest green, traversal/symlink отсутствуют |
| Baseline | семь BASE SHA совпадают с последней базой |
| R | старый/new updater; один изменённый класс достаточен; `ok:true+delta0` не проходит |
| IP | GET/POST expected=actual; mismatch/bind hard-stop без fallback |
| Host facts | реальный `wm_verified_stage` и fallback `webmaster_account_id` оба проверены stage-level |
| Queue | local task incomplete → direct GET/indeterminate, POST=0 |
| 4xx | date filter / host verification / account error различаются |
| DB safety | отсутствие RP_TEST_DB_NAME → fail до DDL; production schema не меняется |
| Rollback | compare-and-set; progressed job не перематывается; сообщения соответствуют фактам |
| Runner | все suites реально запущены; FAIL=0, SKIP=0; сумма PASS проверяема |

## 6. Что делать с job №223

- Job уже `done/ok`; не возвращать её на `wm_recrawl`.
- У Яндекса задача уже `DONE`; новый POST запрещён.
- После принятой установки выполнить только `reconcile_stale_recrawl.php` в GET-only режиме.
- Сначала `--dry-run`, затем обычный запуск.
- После подтверждения строки №232 как `queue_reconciled` проверить evidence.
- Временные изменения сайта/`guard.php` удалять только после успешного canary принятого пакета.

## 7. Ограничение независимого прогона

В текущем аудиторском окружении отсутствуют PHP CLI и MariaDB client/server, поэтому PHP/MariaDB suites
из `TEST_STDOUT.txt` не были независимо перезапущены. Проверены их полный сохранённый вывод, состав runner,
исходники тестов, shell-синтаксис, реальные схемы/строки последнего дампа и соответствие пакета последней
базе. Найденные блокеры являются статически и dump-backed доказуемыми и не зависят от повторного запуска.

