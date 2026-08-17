# ТЗ: Refresh Panel — IndexNow hotfix за 1 час

**Дата:** 17.08.2026  
**Целевой baseline:** установленный `REV3.2-final_6_VERIFIED`  
**Тип патча:** узкий panel-only DELTA hotfix поверх exact baseline  
**Главный принцип:** не менять state machine, стадии, UI, БД, сайты и существующий Yandex Webmaster flow.

---

## 0. Решение по объёму

Нужно добавить в Refresh Panel отправку новых URL в Yandex IndexNow:

1. **первичная отправка** после готовности нового домена;
2. **ограниченные повторные отправки**, пока refresh-джоба находится в `index_watching`;
3. без нового cron;
4. без новой стадии;
5. без миграции БД;
6. без изменений `guard.php`, `robots.php`, `sitemap.php`, `.htaccess`, `config.php`, шаблонов сайтов;
7. без изменения существующих:
   - Webmaster verification;
   - recrawl;
   - sitemap;
   - robots;
   - index monitoring;
   - redirect flow.

Это дополнительный best-effort канал уведомления Yandex, а не замена текущего recrawl/Webmaster.

---

# 1. Почему hotfix реально уложить в час

Scope намеренно ограничен:

### Новый файл

```text
app/Services/IndexNowService.php
```

### Один существующий runtime-файл

```text
app/Services/RefreshOrchestrator.php
```

### Тесты + installer/rollback

Без:

```text
controllers
views
migrations
new stages
new tables
new cron
new UI
site templates
```

Текущее состояние хранится в уже существующем:

```text
refresh_jobs.artifacts_json.indexnow_stage
```

---

# 2. Что берём из приложенного референсного проекта

Из `casino-panel(v36yandexindex)` взять только проверенные концепции:

```text
POST https://yandex.com/indexnow
host
key
keyLocation
urlList

key-файл в docroot
200 / 202 = запрос принят
bounded resend
не слать один URL слишком часто
dirty/retry semantics
```

Но НЕ переносить:

```text
project.json
multisite presets
отдельный cron-indexnow.php
UI IndexNow
multisite host scopes
```

В Refresh Panel уже есть собственный lifecycle, поэтому повторные отправки должны использовать существующие тики `index_watching`.

---

# 3. Официальный контракт, которому должна соответствовать реализация

Endpoint:

```text
https://yandex.com/indexnow
```

Для batch:

```json
{
  "host": "example.com",
  "key": "<key>",
  "keyLocation": "https://example.com/<key>.txt",
  "urlList": [
    "https://example.com/",
    "https://example.com/page"
  ]
}
```

Ограничение:

```text
до 10 000 URL за POST
```

Ключ:

```text
8..128 символов
A-Z a-z 0-9 -
```

Ключ должен быть доступен на том же host.

HTTP:

```text
200 = принято, key подтверждён
202 = принято, новый key ещё проходит verification
400 = invalid params
403 = invalid/unavailable key
422 = host / URL / key mismatch
429 = rate limit
```

`200` и `202` считаем успешным приёмом запроса, но различаем их в telemetry.

IndexNow не гарантирует индексацию и не заменяет текущий Webmaster recrawl/sitemap.

---

# 4. Совместимость

Hotfix разрешён только поверх exact установленного:

```text
REV3.2-final_6_VERIFIED
```

Baseline SHA-256 текущего `RefreshOrchestrator.php`:

```text
10d0e5823e7e030716779aa7cab15916a5fd6995ffc3eb7bc715f43e2d209c9a
```

Installer обязан проверить этот SHA **до первой записи**.

Если SHA не совпал:

```text
STOP
ничего не менять
BASE_MISMATCH
```

Нельзя применять патч поверх неизвестной ревизии.

---

# 5. Scope по режимам

В первом hotfix IndexNow работает **только для**:

```text
mode = refresh
```

Не подключать сейчас к:

```text
move_indexed
move_simple
```

Это отдельная задача после проверки refresh-canary.

Условие в helper должно быть явным:

```php
if (($job['mode'] ?? 'refresh') !== 'refresh') {
    return;
}
```

---

# 6. Никакой новой стадии

НЕ добавлять:

```text
indexnow
indexnow_submit
indexnow_wait
```

в:

```text
STAGES_ORDER
nextStageFor()
pipelineStagesFor()
progress bar
controller skip logic
```

IndexNow подключается как **side action** двух существующих стадий:

```text
wm_robots
index_watching
```

Таким образом риск затронуть state machine минимален.

---

# 7. Точки интеграции

## Hook A — первичная отправка

В самом конце:

```text
runWmRobotsStage()
```

после сохранения robots artifacts, но перед финальным:

```php
$this->setStatus($jobId, 'ok', 'stage_finished_at');
```

вызвать:

```php
$this->maybeSubmitIndexNow($job, $log, 'post_wm_robots');
```

IndexNow failure **не должен** мешать `wm_robots` завершиться.

---

## Hook B — catch-up + repeat

В начале:

```text
runIndexWatchingStage()
```

после получения:

```text
$jobId
$siteId
$newDomain
$artifacts
```

вызвать:

```php
$this->maybeSubmitIndexNow($job, $log, 'index_watching');
```

Назначение:

1. если hotfix установили, когда джоба уже в `index_watching`, а первичного IndexNow ещё не было — выполнить initial submit;
2. если initial уже был — выполнить resend только когда он реально due;
3. если ничего не due — мгновенный return без HTTP/FTP.

**Не перематывать джобу назад на `wm_robots`.**

---

# 8. Новый `IndexNowService`

Создать:

```text
app/Services/IndexNowService.php
```

И явно подключить его из `RefreshOrchestrator.php`, если проект не имеет гарантированного autoload для нового класса:

```php
require_once __DIR__ . '/IndexNowService.php';
```

Никаких глобальных bootstrap-изменений.

---

# 9. Контракт `IndexNowService`

Минимальный API:

```php
final class IndexNowService
{
    public function deriveKey(int $siteId): string;
    public function keyFileName(string $key): string;

    public function probeKey(
        string $host,
        string $key
    ): array;

    public function submit(
        string $host,
        string $key,
        array $urls
    ): array;

    public function normalizeUrls(
        string $host,
        array $urls
    ): array;
}
```

Допустимо другое имя методов, но обязанности должны быть разделены именно так.

Service НЕ должен:

```text
писать refresh_jobs
менять stage
менять сайт через FTP
работать с Webmaster OAuth
отправлять Telegram
```

Он только:

```text
key
validation
public key probe
HTTP IndexNow submit
```

---

# 10. Стабильный ключ без новой таблицы и без secret-файла панели

Для hotfix не заводить отдельную таблицу и UI-настройку.

Стабильный key на уровне `site_id` получить через HMAC от существующего `app_key` с обязательным domain separation:

```php
$appKey = (string)config('app_key', '');

$key = substr(
    hash_hmac('sha256', 'refresh-panel:indexnow:site:' . $siteId, $appKey),
    0,
    32
);
```

Требования:

```text
app_key пуст -> IndexNow disabled for this job + warning
key не логировать
app_key не логировать
```

Преимущество:

- один и тот же managed site получает один и тот же IndexNow key при следующих refresh;
- нет миграции;
- нет отдельного writable storage;
- нет генерации нового ключа на каждом retry;
- нет необходимости хранить key в artifacts.

---

# 11. Key-файл на сайте

Файл:

```text
<32hex>.txt
```

Содержимое:

```text
ровно <32hex>
```

без HTML.

Размещать в **фактическом root текущего managed site**, тем же проверенным механизмом, которым панель размещает Yandex HTML verifier.

### Важно

Сначала разработчик должен проверить реализацию:

```text
WebmasterFileVerifier::uploadFileOnly()
```

Если этот метод действительно универсален по filename/content — переиспользовать его.

Если там есть Yandex-HTML-specific validation:

- НЕ ломать `WebmasterFileVerifier`;
- использовать маленький generic upload helper поверх уже существующего `FtpService`;
- использовать тот же доказанный site-root resolution;
- никаких догадок про произвольный `web_root_path`.

---

# 12. Key upload/readback

Перед IndexNow POST:

1. вычислить key;
2. определить key filename;
3. если `indexnow_stage.key_ready_at` ещё нет:
   - загрузить файл через existing root uploader;
   - выполнить FTP/read-back exact content, если uploader это умеет;
4. выполнить публичный HTTPS probe.

Публичный URL:

```text
https://<new_domain>/<key>.txt
```

---

# 13. Публичный probe key-файла

POST в IndexNow запрещён, пока probe не подтвердил:

```text
trusted TLS
HTTP 200
финальный host = new_domain
body trim === key
```

Не считать достаточными:

```text
301/302 на другой host
200 с HTML
meta refresh
JS redirect
403/404
```

Timeout:

```text
connect <= 3 sec
total <= 8 sec
```

При провале:

```text
IndexNow pipeline only -> retry later
основная refresh job -> продолжает штатно
```

---

# 14. Controlled repair key-файла

Если на due-attempt key probe не прошёл:

- разрешён **один upload/re-upload в рамках этой due-попытки**;
- затем один повторный public probe;
- если снова failed:
  - POST не делать;
  - `key_failures += 1`;
  - следующая попытка не раньше чем через 120 секунд.

После:

```text
key_failures >= 3
```

для этой job:

```text
indexnow_stage.disabled = true
reason = key_unreachable
```

И больше IndexNow не трогать.

Refresh job продолжает работу.

---

# 15. URL для IndexNow

Использовать **тот же смысловой набор страниц**, который уже строит `buildRecrawlUrls()`:

```text
https://new_domain/
+ страницы из $pages в config.php, если они есть
```

Чтобы не дублировать parsing logic, разрешено расширить сигнатуру:

```php
buildRecrawlUrls(
    array $job,
    string $newDomain,
    JobEventLogger $log,
    string $logStage = 'wm_recrawl'
)
```

и заменить внутренние hardcoded `wm_recrawl` в логах на `$logStage`.

Текущий вызов recrawl остаётся прежним благодаря default.

IndexNow вызывает:

```php
$this->buildRecrawlUrls($job, $newDomain, $log, 'indexnow');
```

---

# 16. Фильтр URL перед отправкой

`IndexNowService::normalizeUrls()` должен оставить только:

```text
scheme = https
host EXACTLY new_domain
valid absolute URL
```

Удалить:

```text
duplicates
/robots.txt
/sitemap.xml
/sitemap*.xml
IndexNow key file
yandex_*.html
/reg
/reg/*
```

Не отправлять:

```text
old_domain
partner domain
внешние URLs
www, если new_domain без www
```

Максимум:

```text
10 000 URL
```

На текущих сайтах список маленький.

---

# 17. Initial + bounded resend

Никакого бесконечного resend.

Всего максимум:

```text
4 POST attempt на одну refresh job
```

Расписание относительно **первого POST attempt**:

```text
attempt #1: 0 мин       initial
attempt #2: +10 мин
attempt #3: +30 мин
attempt #4: +2 часа
```

После четвёртого:

```text
indexnow_stage.completed = true
```

Больше IndexNow для job не выполняется.

Если сайт попал в индекс раньше:

```text
index_watching завершился
дальнейших resend нет
```

Это ожидаемое поведение.

---

# 18. Минимальный интервал

Даже если artifacts повреждены/offset logic ошибся:

```text
now - last_submit_at < 600 sec
```

=> POST запрещён.

То есть hard safety:

```text
MIN_RESEND_SEC = 600
```

---

# 19. Семантика HTTP ответа

## 200

```text
accepted = true
key_verified = true
```

Лог:

```text
IndexNow: URL приняты, key подтверждён (HTTP 200)
```

---

## 202

```text
accepted = true
key_verified = false
```

Лог:

```text
IndexNow: запрос принят, key ожидает verification (HTTP 202)
```

Resend schedule продолжать.

---

## 429

Transient:

```text
accepted = false
retry allowed
```

Но POST attempt считается в общий max=4.

Никаких дополнительных немедленных POST.

---

## network / timeout / 5xx

Transient.

POST attempt считается.

Следующая отправка только по bounded schedule / MIN_RESEND_SEC.

---

## 400 / 422

Payload/config error.

Сразу:

```text
indexnow_stage.disabled = true
terminal_reason = invalid_payload
```

Основную job не блокировать.

---

## 403

Key rejected/unavailable.

Поскольку public key probe должен был пройти:

- логировать warning;
- НЕ делать немедленный второй POST;
- оставить bounded retry;
- перед следующим POST снова выполнить key probe;
- после max attempts прекратить.

Не переводить refresh job в failed/awaiting_user.

---

# 20. Никакого `sleep()`

Запрещено:

```php
sleep()
usleep()
```

для retries.

Все retries — через существующие будущие `index_watching` ticks + artifact timestamps.

---

# 21. State только в `artifacts_json`

Никакой DB migration.

Ключ:

```text
indexnow_stage
```

Пример:

```json
{
  "indexnow_stage": {
    "version": 1,
    "host": "7k1008.top",

    "key_ready_at": "2026-08-17 23:10:00",
    "key_failures": 0,

    "first_submit_unix": 1786997400,
    "last_submit_unix": 1786997400,
    "next_due_unix": 1786998000,

    "submit_count": 1,
    "accepted_count": 1,

    "last_http": 202,
    "last_error": "",

    "last_urls_count": 7,
    "last_urls_sha256": "...",

    "last_context": "post_wm_robots",

    "completed": false,
    "disabled": false
  }
}
```

Не сохранять:

```text
IndexNow key
app_key
payload body с key
```

---

# 22. Crash/retry safety

`maybeSubmitIndexNow()` должен быть идемпотентным по времени/artifacts.

Перед POST:

1. reload актуального `indexnow_stage` из DB;
2. проверить `disabled/completed`;
3. проверить `next_due_unix`;
4. проверить `MIN_RESEND_SEC`.

После POST:

- сразу сохранить result state.

Возможен crash после фактического POST, но до записи artifacts.

Поэтому additional safety:

- никаких немедленных повторов;
- при uncertain/crash recovery следующий POST не раньше 10 минут.

Для этого можно перед фактическим POST сохранить:

```text
submit_inflight_at = NOW unix
```

И если worker упал:

```text
submit_inflight_at < 10 мин назад -> POST не повторять
```

После нормального результата заменить на:

```text
last_submit_unix
submit_inflight_at = null
```

Это обязательный anti-duplicate guard.

---

# 23. Concurrency

Два worker процесса не должны одновременно отправить IndexNow для одной job.

Перед submit выполнить атомарный artifact/state guard.

Для hotfix допустим простой DB advisory lock:

```text
GET_LOCK('refresh_indexnow_<jobId>', 0)
```

или существующий job advisory-lock механизм, если он уже доступен в этом execution path.

Если lock не получен:

```text
return
```

Не ждать.

После операции:

```text
RELEASE_LOCK
```

Не создавать новую lock-table.

---

# 24. `maybeSubmitIndexNow()` никогда не меняет stage

Запрещены внутри helper:

```text
setStatusFailed
setStatusAwaitingUser
setStage
next_run_at rewrite
markJobDone
```

Единственная запись в `refresh_jobs`:

```text
JSON_MERGE_PATCH -> artifacts_json.indexnow_stage
```

IndexNow — best-effort side channel.

---

# 25. Логи

Использовать существующий `JobEventLogger`.

### Успех

```text
IndexNow: initial accepted — HTTP 200, URLs=7
IndexNow: repeat #2 accepted — HTTP 200, URLs=7
```

### 202

```text
IndexNow: accepted, key verification pending — HTTP 202, repeat будет не раньше чем через 10 мин
```

### Key problem

```text
IndexNow: key-файл пока публично не подтверждён — POST не выполнялся, повтор позже
```

### 429/network

```text
IndexNow: временная ошибка HTTP 429 — refresh pipeline не блокируется
```

### Terminal payload error

```text
IndexNow отключён для этой job: HTTP 422, payload/host mismatch
```

Не логировать:

```text
key
app_key
полный JSON payload
```

---

# 26. Не менять `DiagnosticCatalog.php` в этом hotfix

Чтобы уложиться в timebox и уменьшить blast radius:

```text
DiagnosticCatalog.php НЕ трогать
```

Использовать обычные понятные `info/warn/ok` сообщения.

Отдельные красивые diagnostic descriptors — позже.

---

# 27. IndexNow не должен использовать Webmaster account/IP routing

Не применять:

```text
YandexWebmasterClient
WebmasterOutboundIpResolver
account_id routing
CURLOPT_INTERFACE закреплённого Webmaster IP
```

IndexNow — отдельный публичный endpoint, не OAuth Webmaster operation.

Использовать обычный server egress.

Timeout короткий и bounded.

---

# 28. cURL contract

POST:

```text
https://yandex.com/indexnow
Content-Type: application/json; charset=utf-8
User-Agent: RefreshPanel-IndexNow/1.0
```

Требования:

```text
CURLOPT_RETURNTRANSFER = true
CURLOPT_POST = true
CURLOPT_SSL_VERIFYPEER = true
CURLOPT_SSL_VERIFYHOST = 2
CONNECTTIMEOUT <= 3 sec
TIMEOUT <= 8 sec
```

Не follow redirect к другому host.

Response body:

- для decision не нужен;
- для debug можно хранить максимум первые 300 символов **только если там нет key**;
- предпочтительно вообще не сохранять body в artifacts.

---

# 29. Существующая текущая job в `index_watching`

Hotfix обязан поддержать уже запущенную job.

Если при установке job уже:

```text
stage = index_watching
```

и:

```text
artifacts.indexnow_stage отсутствует
```

то на ближайшем штатном tick:

```text
maybeSubmitIndexNow()
```

выполняет initial flow.

Не:

```text
создавать новую job
rewind
возвращать на wm_robots
ручной skip
```

---

# 30. Тесты — минимальный обязательный набор

Создать отдельный:

```text
tests/test_indexnow_hotfix.php
```

или эквивалент.

Все assertions настоящие:

```text
PASS / FAIL
exit != 0 при fail
```

---

## A. Key

- stable key для одного site_id;
- разные site_id -> разные key;
- key regex valid;
- пустой app_key -> graceful disabled;
- key не появляется в logs/artifact fixture.

---

## B. URL filter

- root accepted;
- pages accepted;
- duplicate removed;
- foreign host rejected;
- old domain rejected;
- partner URL rejected;
- `/reg` rejected;
- sitemap rejected;
- robots rejected;
- verification/key file rejected;
- max 10000.

---

## C. Key probe

Mock:

- 200 + exact body -> ready;
- 200 + HTML -> not ready;
- 301 external -> not ready;
- 404 -> not ready;
- timeout -> not ready;
- TLS error -> not ready.

---

## D. POST response

Mock:

```text
200 -> accepted/key_verified
202 -> accepted/key_pending
429 -> transient
500 -> transient
network -> transient
400 -> disabled
422 -> disabled
403 -> bounded retry
```

---

## E. Schedule

- no artifact -> initial due;
- +5 min -> not due;
- +10 min -> repeat #2 due;
- +20 min -> not due;
- +30 min -> repeat #3 due;
- +2h -> repeat #4 due;
- count=4 -> completed;
- MIN_RESEND_SEC always dominates.

---

## F. In-flight crash protection

- `submit_inflight_at = now - 60` -> no POST;
- >10 min -> next bounded attempt allowed;
- successful POST clears inflight.

---

## G. Pipeline isolation

Mock IndexNow exceptions at:

```text
key upload
key probe
curl init
curl timeout
artifact save
```

Expected:

```text
wm_robots still can finish
index_watching still executes index checker
stage never failed because of IndexNow
```

---

## H. Existing pipeline regression

Прогнать существующие REV3.2 suites.

Минимальный gate:

```text
все прежние тесты PASS
новые IndexNow tests PASS
PHP lint PASS
```

---

# 31. Installer — delta, fail-closed

Новый пакет, например:

```text
refresh-panel_INDEXNOW_HOTFIX_2026-08-17_v1.zip
```

Состав:

```text
payload/app/Services/RefreshOrchestrator.php
payload/app/Services/IndexNowService.php
tests/...
install.sh
rollback.sh
MANIFEST.sha256
BASE_SHA256.txt
REPORT.md
```

---

# 32. Installer gates

Порядок:

1. `MANIFEST.sha256` самого package;
2. exact baseline SHA текущего `RefreshOrchestrator.php`;
3. проверить, что `IndexNowService.php`:
   - отсутствует;
   - либо совпадает с exact expected previous hotfix version;
4. backup обоих target;
5. copy;
6. PHP 8.0 lint;
7. target SHA == payload SHA;
8. новые tests;
9. carry-forward critical tests;
10. failure после copy -> автоматический byte-verified rollback.

Только после всех gate:

```text
INSTALL_OK INDEXNOW-HOTFIX-v1
```

---

# 33. PHP runtime

Production runtime панели ранее подтверждён:

```text
PHP 8.0.30
/opt/php80/bin/php
```

Lint/tests installer должны выполняться именно этой веткой PHP либо через тот же временный PATH-механизм, который уже использовался для REV3.2.

Не ориентироваться на system PHP 8.3.

---

# 34. Rollback

Rollback возвращает:

```text
RefreshOrchestrator.php -> exact pre-hotfix bytes
```

и:

```text
IndexNowService.php
```

- удалить, если до hotfix его не существовало;
- восстановить backup, если существовал.

Rollback не меняет:

```text
DB
job stage
artifacts
sites
key file на сайте
```

Наличие неиспользуемого `<key>.txt` после rollback безопасно; не делать destructive cleanup в срочном rollback.

---

# 35. Что НЕЛЬЗЯ делать ради скорости

Запрещено:

- добавлять новый stage;
- миграцию;
- новый cron;
- `sleep`;
- бесконечный retry;
- hardcode конкретного домена;
- hardcode site_id;
- hardcode key;
- хранить key в логе;
- менять site `guard.php`;
- менять robots/sitemap;
- менять recrawl;
- менять index checker;
- менять redirect policy;
- считать IndexNow обязательным для успеха refresh.

---

# 36. Acceptance / DoD

Hotfix принят только если:

- [ ] Exact baseline REV3.2-final_6 verified.
- [ ] Из runtime изменён только `RefreshOrchestrator.php`.
- [ ] Добавлен только новый `IndexNowService.php`.
- [ ] Нет DB migration.
- [ ] Нет новой стадии.
- [ ] Нет UI.
- [ ] Нет нового cron.
- [ ] Работает только для `mode=refresh`.
- [ ] Initial submit выполняется после `wm_robots`.
- [ ] In-flight `index_watching` job получает catch-up initial на следующем tick.
- [ ] Resend: 10m / 30m / 2h.
- [ ] Max 4 POST attempts.
- [ ] <10m duplicate POST невозможен.
- [ ] Crash after POST не вызывает немедленный duplicate.
- [ ] Key-файл exact publicly verified до POST.
- [ ] Только same-host HTTPS page URLs.
- [ ] `/reg`, sitemap, robots, verifier/key URLs исключены.
- [ ] 200 и 202 обрабатываются корректно.
- [ ] 400/422 прекращают IndexNow для job.
- [ ] 403/429/network не ломают refresh.
- [ ] Любое исключение IndexNow не меняет stage/status.
- [ ] Existing REV3.2 tests = PASS.
- [ ] New IndexNow tests = PASS.
- [ ] PHP 8.0 lint = PASS.
- [ ] Installer fail-closed.
- [ ] Rollback byte-verified.
- [ ] Нет изменений сайтов.

---

# 37. Canary после установки

На первой реальной refresh-job в событиях должны появиться примерно:

```text
wm_robots
✓ robots verified

IndexNow
✓ key-file confirmed
✓ initial accepted HTTP 202/200, URLs=N

index_watching
...

через >=10 мин, если ещё не indexed:
IndexNow repeat #2 ...

через >=30 мин:
IndexNow repeat #3 ...
```

Если сайт быстро попал в индекс раньше resend:

```text
это нормально
повтор не требуется
```

---

# 38. Отчёт разработчика перед установкой

Вернуть:

```text
1. один ZIP;
2. SHA-256 ZIP;
3. список изменённых runtime-файлов;
4. baseline SHA;
5. PHP lint log;
6. IndexNow test log;
7. carry-forward regression summary;
8. install simulation;
9. rollback simulation;
10. короткий diff summary с точными hook points.
```

Не присылать серию мелких патчей.

Нужна одна законченная review-сборка.

---

# 39. Timebox разработчику

Чтобы уложиться примерно в час:

```text
0–15 мин
IndexNowService + deterministic key + submit/probe

15–30 мин
maybeSubmitIndexNow + 2 hooks + artifacts scheduling

30–40 мин
unit tests / mocks

40–50 мин
installer + rollback + manifest/base hash

50–60 мин
PHP8 lint + regression + package report
```

Если к 45-й минуте появляются требования на:

```text
migration
UI
новый stage
cron
глобальный refactor
```

— STOP: это выходит за scope данного hotfix.

---

# 40. Финальный приоритет

Главная цель hotfix:

> Добавить к уже работающему Webmaster recrawl ещё один независимый, ограниченный и полностью best-effort IndexNow signal, не меняя существующий refresh lifecycle.

Любая реализация, которая ради IndexNow меняет основной state machine или делает IndexNow blocking dependency, считается неправильной для этого срочного hotfix.
