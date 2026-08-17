# FINAL AUDIT — `refresh-panel_INDEXNOW_HOTFIX_2026-08-17_v1.zip`

**Дата:** 17.08.2026  
**Результат:** **NO-GO на production в текущем виде**  
**Архитектура:** правильная и узкая; глобальный refactor не требуется.  
**Оценка исправления:** точечная REV2, без изменения общей схемы hotfix.

SHA-256 проверенного ZIP:

```text
e906dfdb69ff8700b4525e821bef6f3ee5753f0f8e96825229cc584bb60d4620
```

---

# 1. Что проверено и что уже хорошо

## Package integrity

`MANIFEST.sha256`:

```text
PASS
```

ZIP:

```text
path traversal: отсутствует
symlink entries: отсутствуют
```

Baseline:

```text
BASE_SHA256:
10d0e5823e7e030716779aa7cab15916a5fd6995ffc3eb7bc715f43e2d209c9a
```

совпадает byte-for-byte с `RefreshOrchestrator.php` из установленного/проверенного
`REV3.2-final_6_VERIFIED`.

Rollback source также имеет exact baseline SHA.

## Diff scope

Существующий runtime:

```text
RefreshOrchestrator.php: +307 / -1
```

Новый runtime:

```text
IndexNowService.php
```

В `RefreshOrchestrator` только четыре diff-hunk:

1. `require_once IndexNowService.php`;
2. Hook A после `wm_robots`;
3. IndexNow helper/state + расширение `buildRecrawlUrls`;
4. Hook B в `index_watching`.

Новых стадий, миграций, UI, cron и изменений сайтов нет.

## Unit tests

В независимом прогоне:

```text
IndexNow tests: 57 PASS / 0 FAIL
preflight classify: 19 PASS / 0 FAIL
preflight decide: 12 PASS / 0 FAIL
PHP syntax: PASS
```

Большая часть старых REV3.2 suites также независимо прогнана против пропатченного orchestrator.

Все suites, не зависящие от отсутствующего в audit-container `mbstring`, прошли:

```text
legacy index: 28/0
redirect disable: 17/0
preflight classify: 19/0
preflight decide: 12/0
roundtrip: 5/0
samehost: 10/0
robots priority: 14/0
crash recovery: 5/0
marker recovery: 14/0
Mellstroy fixture: 12/0
crash matrix: 40/0
lifecycle: 31/0
serialized lifecycle: 24/0
```

Три оставшихся suite падают в этой audit-среде на отсутствии `mbstring`, и точно так же падают на **неизменённом baseline**, поэтому это не регрессия данного hotfix.

---

# 2. P0 BLOCKER — anti-duplicate state сейчас не fail-closed

Это главный runtime-блокер.

Сейчас перед POST выполняется:

```php
$this->saveIndexNowStage(
    $jobId,
    ['submit_inflight_at' => $now],
    $log
);

$result = $svc->submit(...);
```

Но:

```php
saveIndexNowStage(): void
```

ловит любую ошибку DB и просто логирует её.

То есть возможен сценарий:

```text
UPDATE artifacts_json FAILED
        ↓
submit_inflight_at НЕ сохранён
        ↓
код всё равно выполняет HTTP POST
        ↓
следующий tick видит старое состояние
        ↓
снова initial POST
```

Это нарушает:

```text
MIN_RESEND_SEC
crash protection
bounded attempts
```

## Ещё хуже: load failure превращается в пустой state

Сейчас:

```php
private function loadIndexNowStage(...): array
{
    try {
       ...
    } catch (...) {
       return [];
    }
}
```

DB read error трактуется как:

```text
indexnow_stage отсутствует
```

а:

```text
submit_count=0
```

означает:

```text
initial POST due сейчас
```

То есть при ошибке чтения durable-state компонент способен выполнить **неотслеживаемый POST**.

## Требуемое исправление

### `loadIndexNowStage`

Должен различать:

```text
state отсутствует корректно
DB/state read FAILED
```

Например:

```php
[
    'ok' => true,
    'state' => [...]
]
```

или:

```php
?array
```

где:

```text
null = read failed → RETURN, POST запрещён
[]   = корректно прочитано, state отсутствует
```

### `saveIndexNowStage`

Должен возвращать:

```php
bool
```

### Перед POST

Обязательный gate:

```php
if (!$this->saveIndexNowStage(...pre-submit reservation...)) {
    $log->warn(...);
    return; // POST ЗАПРЕЩЁН
}
```

---

# 3. P0 BLOCKER — actual POST count не bounded при crash

Текущий код увеличивает:

```text
submit_count
```

**после** HTTP POST.

До POST сохраняется только:

```text
submit_inflight_at
```

Сценарий:

```text
submit_count = 0
save inflight = success
POST реально ушёл
process crash
final state не записан
        ↓
через 10 минут inflight guard истёк
submit_count всё ещё 0
        ↓
ещё один initial POST
```

Если такой crash повторится, фактических HTTP POST может быть больше четырёх.

Это противоречит заявленному:

```text
MAX 4 POST attempts
```

## Исправление

Под advisory lock **до POST** атомарно зарезервировать попытку:

```json
{
  "submit_count": old + 1,
  "submit_inflight_at": now,
  "last_attempt_unix": now,
  "first_submit_unix": existing_or_now,
  "last_context": "..."
}
```

И только если эта запись подтверждена:

```text
HTTP POST разрешён
```

После POST записать только outcome:

```text
last_http
last_submit_unix
accepted_count
last_error
submit_inflight_at=null
last_urls_count
last_urls_sha256
completed
next_due_unix
```

Тогда crash после POST всё равно оставляет попытку **засчитанной**.

После четырёх зарезервированных attempts новые POST запрещены.

---

# 4. P0 BLOCKER — installer снова создаёт root-owned объекты внутри панели

Это проверено **реальной install simulation**, а не только чтением shell.

Target был смоделирован с владельцем site-user.

После запуска installer от root получилось:

```text
RefreshOrchestrator.php      site-user   ← старый inode, owner сохранился
IndexNowService.php          root        ← НОВЫЙ root-owned runtime file

.indexnow_backup_*           root
.indexnow_backup_*/...       root
```

То есть hotfix снова создаст именно ту проблему, которую мы сейчас исправляем после REV3.2:

```text
root-owned файлы/backup внутри дерева панели
→ FastPanel/site-user не может нормально архивировать/управлять деревом
```

## Обязательное исправление

### Никаких backup-каталогов внутри panel root

Не создавать:

```text
$TARGET/.indexnow_backup_*
$TARGET/.indexnow_rbk_*
```

если installer запускается root.

Backup вынести, например, в:

```text
/root/refresh-panel-hotpatch-backups/indexnow_<id>
```

или configurable:

```text
BACKUP_BASE
```

вне архивируемого дерева сайта.

### Новый runtime-файл должен получить владельца panel user

Перед copy сохранить owner/group/mode существующего:

```text
app/Services/RefreshOrchestrator.php
```

После создания:

```text
IndexNowService.php
```

применить те же:

```text
uid
gid
mode
```

Минимум:

```bash
chown --reference="$TARGET/$ORCH" "$TARGET/$SVC"
chmod --reference="$TARGET/$ORCH" "$TARGET/$SVC"
```

Проверить через:

```text
stat
```

Installer gate после установки должен подтвердить:

```text
owner/group IndexNowService == owner/group RefreshOrchestrator
```

---

# 5. P0 BLOCKER — rollback снова содержит старую небезопасную схему `cp || true`

В `rollback.sh`:

```bash
cp "$TARGET/$ORCH" "$RBK/$ORCH" 2>/dev/null || true

if [ -f "$TARGET/$SVC" ]; then
    ...
    cp "$TARGET/$SVC" "$RBK/$SVC" 2>/dev/null || true
fi
```

Это **тот же класс packaging-проблемы**, который уже исправлялся нами в verified REV3.2.

Rollback объявлен transaction-style, но pre-rollback backup может фактически не создаться.

После этого rollback начинает запись.

Если restore затем упадёт:

```text
дооткатное состояние может быть уже невозможно восстановить
```

## Исправление

До первой rollback-записи:

```text
backup current ORCH → MUST succeed
byte verify → MUST succeed

если SVC существует:
    backup SVC → MUST succeed
    byte verify → MUST succeed
```

Любой failure:

```text
STOP
ни одного write в target
```

Никаких:

```bash
|| true
```

на backup текущего состояния.

---

# 6. P1 — key retry 120 секунд из ТЗ фактически не реализован

При key probe failure:

```php
$key_failures++;
save state;
return;
```

Но state не содержит:

```text
key_retry_after_unix
```

А `decideIndexNowAction()` при:

```text
submit_count = 0
```

всегда возвращает:

```text
initial due
```

Следовательно при раннем `index_watching`, который тикает часто:

```text
probe failed
30 sec
probe failed
30 sec
probe failed
→ key_failures=3
→ IndexNow permanently disabled for job
```

Вместо требуемого:

```text
не чаще одного key retry за 120 sec
```

Это особенно неприятно для свежего домена, где public visibility файла может кратко отставать.

## Исправление

При failed key probe:

```php
'key_retry_after_unix' => $now + 120
```

В `decideIndexNowAction()` **до initial submit logic**:

```php
$keyRetryAfter = ...
if ($keyRetryAfter > $now) {
    return skip(key_retry_wait);
}
```

При успешном probe удалить:

```text
key_retry_after_unix
```

и при необходимости обнулить/сохранить `key_failures` по выбранной семантике.

Добавить tests:

```text
failure -> +30 sec = skip
failure -> +119 sec = skip
failure -> +120 sec = allowed
```

---

# 7. P1 — `$logStage` добавлен, но не используется

REPORT утверждает:

> hardcoded `wm_recrawl` заменены на `$logStage`.

Фактически в новом:

```php
buildRecrawlUrls(..., string $logStage = 'wm_recrawl')
```

внутри осталось **7 hardcoded occurrences**:

```php
$log->info('wm_recrawl', ...)
$log->warn('wm_recrawl', ...)
```

Поэтому вызов:

```php
buildRecrawlUrls(..., 'indexnow')
```

будет писать сообщения в стадию:

```text
wm_recrawl
```

Это не ломает URL, но:

- лог становится недостоверным;
- события IndexNow визуально попадают в старую стадию;
- REPORT не соответствует коду.

## Исправление

Все внутренние:

```text
'wm_recrawl'
```

в этом helper заменить на:

```php
$logStage
```

Default сохранит старое поведение recrawl.

---

# 8. P1 — собственные IndexNow logs всегда пишутся как `index_watching`

Hook A вызывается во время:

```text
wm_robots
```

но helper пишет:

```php
$log->ok('index_watching', ...)
$log->warn('index_watching', ...)
$log->info('index_watching', ...)
```

То есть ещё до перехода в `index_watching` пользователь увидит события якобы другой стадии.

## Исправление

В начале:

```php
$logStage = ($context === 'post_wm_robots')
    ? 'wm_robots'
    : 'index_watching';
```

Все собственные логи helper использовать через `$logStage`.

Это не требует новых stage/event types.

---

# 9. P1 — installer silently пропускает отсутствующий regression test

В installer:

```bash
for t in test_preflight_classify test_preflight_decide test_marker_scan_recovery; do
    [ -f "$HERE/tests/$t.php" ] || continue
```

Но:

```text
test_marker_scan_recovery.php
```

в ZIP **отсутствует**.

Installer всё равно печатает:

```text
carry-forward critical tests OK
```

хотя третий заявленный gate не выполнялся.

## Исправление

Либо включить test в package.

Лучше:

```bash
for t in ...; do
    [ -f ... ] || {
       echo "required carry-forward test missing: $t"
       exit 5
    }
    ...
done
```

Не допускать silent skip обязательного gate.

---

# 10. P1 — rollback не проверяет, что откатывает именно этот hotfix

`rollback.sh` проверяет качество rollback source, но не проверяет текущий target.

То есть его можно случайно запустить позже поверх уже изменённого будущего orchestrator — и он затрёт его baseline REV3.2.

## Исправление

Перед backup/write:

```text
current ORCH SHA должен быть:
- exact hotfix payload SHA
```

или rollback прекращается:

```text
TARGET_MISMATCH
```

Если нужен emergency force — это отдельный явный флаг, не default.

Для `IndexNowService` аналогично:

```text
если существует — exact expected hotfix SHA
```

---

# 11. P1 verification — доказать, что key не утекает через generic uploader logs

Собственный код IndexNow key явно не логирует.

Но key одновременно является filename:

```text
<key>.txt
```

и передаётся:

```php
WebmasterFileVerifier::uploadFileOnly(...)
```

В audit package самого `WebmasterFileVerifier.php` нет.

Поэтому утверждение:

```text
key не логируется
```

нельзя доказать только из данного ZIP.

Перед final package developer должен проверить actual production implementation:

```text
uploadFileOnly
readRemoteSha
FtpService logs
```

Если они логируют remote filename, необходимо либо:

- redact filename именно для IndexNow;
- либо использовать минимальный generic uploader без вывода key в JobEventLogger.

Не требуется переписывать FTP subsystem.

---

# 12. P1 operational — install message про php-fpm не соответствует нашему vhost

Installer сейчас пишет:

```text
Сбросьте OPcache (opcache_reset ИЛИ reload php-fpm).
```

Для панели ранее подтверждён runtime:

```text
Apache mod_fcgid
/opt/php80/bin/php-cgi
PHP 8.0.30
```

а системный `php8.3-fpm` — другой runtime.

Нельзя провоцировать оператора делать:

```text
reload php8.3-fpm
```

который к этому vhost не относится.

## Исправление

В installer/rollback:

```text
НЕ перезапускать php8.3-fpm автоматически.
```

Вывести нейтрально:

```text
INSTALL_OK.
Runtime files заменены.
Если OPcache timestamp validation отключён — отдельно recycle фактические PHP-CGI/mod_fcgid workers.
```

Никаких автоматических restart/reload сервисов из installer.

---

# 13. Что по IndexNow protocol реализовано правильно

Текущий HTTP contract соответствует Yandex IndexNow:

```text
POST https://yandex.com/indexnow
host
key
keyLocation
urlList <= 10000
```

Семантика:

```text
200 = OK
202 = новый key ожидает verification
400 = invalid params
403 = invalid key
422 = invalid key/url/payload
429 = too many requests
```

Также Yandex прямо рекомендует при необходимости повторять один и тот же URL не чаще чем с интервалом около 10 минут.

Поэтому:

```text
MIN_RESEND_SEC=600
```

выбран корректно.

---

# 14. Что НЕ надо переделывать

Чтобы не сорвать срочный hotfix, **не требуется**:

```text
новая стадия
миграция БД
UI
cron
новый Webmaster flow
изменение recrawl
изменение robots
изменение sitemap
изменение sites
переписывание IndexNowService
переписывание RefreshOrchestrator
```

Исправления локальны.

---

# 15. Требуемая REV2 — конкретный список

Разработчик должен вернуть:

```text
refresh-panel_INDEXNOW_HOTFIX_2026-08-17_v2.zip
```

Только с этими изменениями:

### Runtime

1. `loadIndexNowStage`: DB read failure ≠ empty state; при failure POST запрещён.
2. `saveIndexNowStage`: return bool.
3. До POST atomic/persistent attempt reservation:
   - increment submit_count;
   - submit_inflight_at;
   - last_attempt_unix;
   - first_submit_unix.
4. При failure pre-submit state write: POST запрещён.
5. После crash attempt остаётся засчитанным.
6. Key failure retry gate = 120 sec.
7. `$logStage` реально использовать в `buildRecrawlUrls`.
8. Own IndexNow log stage зависит от hook context.
9. Verify/redact key filename logs.

### Installer

10. Backup вне panel root.
11. `IndexNowService.php` owner/group/mode == `RefreshOrchestrator.php`.
12. Post-install ownership assertion.
13. Required test absence = installer FAIL.
14. Не писать про reload неправильного php-fpm.

### Rollback

15. Pre-rollback backup MUST succeed + byte verify.
16. Убрать `cp ... || true`.
17. Temporary rollback backup также вне panel root.
18. Current target SHA gate: откатывать только exact hotfix payload.
19. Restore verification для обоих runtime-файлов.

---

# 16. Новые обязательные тесты REV2

Добавить минимум:

```text
STATE-1 load DB error -> no submit
STATE-2 pre-submit save error -> no HTTP POST
STATE-3 reserved attempt increments BEFORE POST
STATE-4 crash after POST -> submit_count already incremented
STATE-5 four crashes/attempts -> fifth POST prohibited

KEY-1 failed probe -> retry at +30 sec prohibited
KEY-2 failed probe -> +119 prohibited
KEY-3 failed probe -> +120 allowed

LOG-1 indexnow buildRecrawl logs use indexnow stage
LOG-2 Hook A own logs use wm_robots
LOG-3 Hook B own logs use index_watching

INSTALL-OWN-1 new service owner == orchestrator owner
INSTALL-OWN-2 no .indexnow_backup_* inside target root

ROLLBACK-1 backup failure -> zero target writes
ROLLBACK-2 current target mismatch -> zero target writes
```

---

# 17. Final verdict

## Архитектура

```text
PASS
```

## Scope / отсутствие глобальных изменений

```text
PASS
```

## Baseline integrity

```text
PASS
```

## Core IndexNow service

```text
PASS WITH MINOR CORRECTIONS
```

## Durable retry / at-most-bounded semantics

```text
FAIL — BLOCKER
```

## Installer ownership

```text
FAIL — BLOCKER
```

## Rollback transaction safety

```text
FAIL — BLOCKER
```

## Production deployment сейчас

```text
NO-GO
```

Накатывать `v1` сейчас не надо.

Это не требует новой архитектуры. Нужна одна короткая corrective REV2 с перечисленными точечными изменениями.
