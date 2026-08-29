# ТЗ: кумулятивный патч Refresh Panel после аудита SSL-мониторинга, IndexNow и служебных URL

**Дата:** 2026-08-30  
**Основание:** последний дамп БД и код панели, предоставленные для аудита.

## 0. Проверенные исходники

- `refresh_pane.sql(1).zip` — SHA-256: `b47106217a5cf674d3c9491247102bd39104cf3b07a92ac5ebe696d47786e2e6`
- `refresh3008.tar.gz` — SHA-256: `05ba52ba36533212c49f5d2d92fc7bf1398cf3e2003b6387fd85b7c5b70f1d58`
- Runtime панели: PHP 8.0, web runtime `/opt/php80/bin/php-cgi`, CLI для проверок `/opt/php80/bin/php`.
- `php8.3-fpm` к панели не относится и патч не должен его перезапускать.

Патч должен быть **кумулятивным**, а не серией мелких hotfix. Сначала независимый аудит архива патча, затем установка, затем один чистый production-canary.

---

# 1. P0 — ложный красный SSL/site_error во время DNS-пропагации

## 1.1. Зафиксированный реальный кейс

Джоба **#244: `7k2010.top → 7k2011.top`**.

В `domain_ssl_check_history` видно:

- `2026-08-28 23:03:47 UTC` — `unreachable`, `no_connection`, DNS `getaddrinfo failed`;
- `23:05:32` — **`site_error / https_not_served`**, при этом одновременно:
  - `technical_ready=1`;
  - `tls_trusted=1`;
  - сертификат Let's Encrypt валиден для `7k2011.top`;
  - один из HTTP-пробников увидел `301 → https://7k2011.top/`;
  - строгий HTTPS-запрос дал `0` из-за `Could not resolve host: 7k2011.top`;
- `23:11:32` — `ssl_issuing`, снова смешанные успешные/неуспешные DNS-зависимые пробы;
- `23:13:47` — `unreachable`, DNS failure;
- `23:16:21` — второй `site_error / https_not_served` с тем же противоречивым набором признаков;
- `23:18:19` — **`trusted`, HTTP 301, HTTPS 200, валидный LE**, без ошибки.

Артефакты job #244 прямо содержат: `DNS ещё пропагируется`, проверки `new_http`/`new_robots` на раннем этапе проходили через `--resolve`. Позже Webmaster, IndexNow и индексирование завершились штатно; XMLStock увидел `indexed=true` в `23:22:16 UTC`.

**Вывод:** Telegram `🔴 SSL/домен ... site_error — https_not_served` был ложным аварийным алертом во время временного DNS-расхождения/пропагации. Это не подтверждает постоянный SSL-сбой.

## 1.2. Причина в текущем коде

`app/Services/DomainSslCheckService.php` выполняет несколько независимых DNS-зависимых операций последовательно:

1. `resolveIps()`;
2. HTTP;
3. дополнительный HTTP-пробник;
4. строгий HTTPS;
5. non-strict HTTPS;
6. отдельный `inspectCertificate()`.

При кратковременном DNS-расхождении часть вызовов успевает успешно отработать, а часть получает `Could not resolve host`/`getaddrinfo failed`. Затем классификатор видит `tls_trusted=1`, но `site_reachable=0` и переводит результат в `site_error`; `siteErrorReason()` при `https_final_code=0` возвращает `https_not_served`.

`CronController::sslCheckOneLocked()` уже умеет считать исключение `could not resolve` transient, но это **не помогает**, потому что `DomainSslCheckService::check()` не бросает исключение — он возвращает обычный результат `site_error`, который сохраняется и проходит в Telegram anti-spam.

`DomainSslStatusRepository::maybeSendTransitionAlert()` на первом критическом `site_error` для активной job отправляет красный Telegram немедленно.

## 1.3. Требуемая правка классификации

В `DomainSslCheckService` добавить нормализацию транспортной ошибки. Минимальный набор типов:

- `dns_resolution`;
- `connect_timeout`;
- `connect_error`;
- `tls_error`;
- `http_error`;
- `none`.

Тип должен определяться по фактическим cURL/stream error каждого ключевого запроса и сохраняться в `result_json` истории. Секреты и URL с токенами не логировать.

### Инварианты

1. Если `dns_ok=0` **или** основной HTTPS завершился ошибкой `dns_resolution`, результат НЕ МОЖЕТ быть `site_error/https_not_served`.
2. Такой результат должен быть транзиентным:
   - статус: существующий `unreachable`;
   - reason: `dns_resolution_failed`;
   - при противоречивой картине (например DNS=0, но другая проба/сертификат успели пройти): reason `inconclusive_dns`.
3. Для `dns_resolution_failed`/`inconclusive_dns` следующая проверка — через 30–60 секунд.
4. `tls_trusted=1` из отдельного сертификатного запроса не должен превращать DNS failure в `site_error`.
5. Классификация должна быть детерминированной: одинаковый нормализованный набор входных признаков → одинаковый статус/reason.

## 1.4. Не смешивать SSL-health и штатный внешний бизнес-редирект

В текущем коде `site_reachable` требует, чтобы конечный URL после follow оставался на том же host. Поэтому штатный внешний партнёрский redirect может дать `site_error/foreign_redirect`, хотя TLS исходного домена полностью исправен.

Для вкладки/Telegram **«SSL/домен»** это семантически неверно.

Требование:

- SSL-health оценивать по TLS исходного `https://<domain>/` и первому HTTPS hop;
- внешний `Location` сохранять как диагностический факт (`external_redirect=true`, target host), но **не считать сам по себе SSL-аварией**;
- `foreign_redirect` не должен давать красный SSL Telegram только из-за того, что приложение штатно отправляет пользователя на внешний домен;
- реальный `https_not_served`, 5xx первого hop, expired/mismatch остаются отдельными проблемами.

Не менять бизнес-логику сайтов/Guard в рамках этого патча.

---

# 2. P0 — debounce первого аварийного SSL Telegram

## 2.1. Миграция 051

Создать новую идемпотентную миграцию, например:

`app/Migrations/051_ssl_transient_alert_confirmation.php`

Добавить в `domain_ssl_statuses`:

- `ever_trusted_at DATETIME NULL` — домен хотя бы один раз был реально trusted;
- `alert_candidate_key VARCHAR(64) NULL`;
- `alert_candidate_since DATETIME NULL`;
- `alert_candidate_last_at DATETIME NULL`;
- `alert_candidate_count INT NOT NULL DEFAULT 0`.

Все `ALTER` — только после `SHOW COLUMNS`; повторный запуск миграции безопасен.

При сохранении `trusted`:

- `ever_trusted_at = COALESCE(ever_trusted_at, NOW())`;
- transient alert-candidate сбрасывается.

## 2.2. Политика Telegram

### Никогда не отправлять красный аварийный Telegram по одному измерению для:

- `dns_resolution_failed`;
- `inconclusive_dns`;
- transient connect timeout/reset;
- `https_not_served`, если причиной является transport/DNS failure.

### Для ранее trusted домена

Для транспортного `site_error` требуется подтверждение:

- минимум **2 последовательных одинаковых критических измерения**;
- между первым и вторым не менее **60 секунд**;
- только после этого один Telegram;
- cooldown/stable-recovery из текущей реализации сохранить.

### Для свежей active refresh job до первого trusted

Пока домен ещё ни разу не был trusted, DNS/transport-проблемы в период provisioning — это состояние ожидания, а не авария. Красный Telegram не отправлять; история и UI должны показывать `unreachable / dns_resolution_failed` либо `inconclusive_dns`.

### Жёсткие сертификатные ошибки

`expired`, `hostname_mismatch`, `not_yet_valid` не маскировать как DNS-transient. Если данные сертификата достоверно получены, текущую политику критических событий можно сохранить.

## 2.3. Сброс кандидата

Любой успешный `trusted` или другой некритический устойчивый результат рвёт critical-candidate streak. Старый `last_alert_key/cooldown/stable recovery` не ломать.

---

# 3. P0 — исправить устаревшие тексты pipeline / Telegram

В текущем pipeline `redirect_enable` стоит **до** `trusted_ssl_gate` и Webmaster. Для refresh после `index_watching` реальный следующий этап — `old_delurl_notify`.

Но текущий код в `runIndexWatchingStage()` всё ещё пишет:

- `Переход к redirect_enable`;
- Telegram: `Pipeline продолжает: включит партнёрский редирект...`.

Это фактически неверно.

## Требование

1. Не хардкодить следующий stage в тексте.
2. Использовать `nextStage()`/`nextStageFor()` как single source of truth.
3. Для refresh после `index_watching` писать по смыслу:
   - следующий этап `old_delurl_notify`;
   - будет подготовлен/отправлен список URL старого домена для удаления из поиска.
4. Не писать «включит партнёрский редирект», если он уже восстановлен раньше.
5. Поискать аналогичные устаревшие hardcoded «Pipeline → ...» по всему Orchestrator и привести к фактическому pipeline. Уже найден ещё один конкретный баг: в `wm_robots` при успешном robots сейчас логируется `Pipeline → wm_recrawl`, хотя `wm_recrawl` уже был раньше, а для refresh фактический следующий stage после `wm_robots` — `index_watching`.
6. В `index_watching` убрать вводящий в заблуждение runtime-текст `Прошло X/30 мин`, потому что начиная с v18.1.22 30-минутного timeout больше нет. Допустимо писать `Прошло X мин` + фактический интервал следующей проверки. Комментарии/докблоки, которые всё ещё утверждают `30 мин: STOP → awaiting_user`, также привести в соответствие с реальным бесконечным adaptive polling/auto-stop policy.

Acceptance: тексты UI/event/TG совпадают с `nextStageFor()` для `refresh`, `move_indexed`, `move_simple`; ни один runtime-текст не обещает переход на уже пройденную стадию и не показывает несуществующий 30-минутный дедлайн.

---

# 4. P0 — robots.txt: нейтральная публичная проверка и fail-closed для неверного содержимого

## 4.1. Найденный остаточный дефект

`runWmRobotsStage()` декларирует «прямой HTTP GET», строгий TLS и проверку тела/host — это хорошо. Но `httpGetRobots()` всё ещё отправляет UA с `YandexBot`.

Такой тест не должен быть единственным основанием утверждать, что публичный `robots.txt` исправен: сайт может отвечать по-разному в зависимости от UA.

## 4.2. Правка

Для **решающего public-check** использовать нейтральный UA, например:

`RefreshPanel-RobotsCheck/2.0`

Требования:

- GET, не HEAD;
- strict TLS: `VERIFYHOST=2`, `VERIFYPEER=true`;
- follow redirects ограниченно;
- final host обязан быть исходным доменом;
- HTTP 200–399 без валидного robots-body не считается verified;
- HTML/JS/meta redirect/пустое тело → `robots.content_invalid`;
- внешний final host → invalid;
- Yandex undocumented robots API 404 остаётся отдельным `robots.api_unavailable` best-effort и не влияет на факт публичной доступности.

## 4.3. Изменить политику `RobotsCheckDecision`

Сейчас `robots.http_unavailable` и `robots.content_invalid` дают warning, после чего IndexNow запускается и стадия всё равно `ok`.

Новая политика:

- `robots.api_unavailable` → **continue**, warning;
- `robots.http_unavailable` → bounded retry, например до 3 попыток с интервалом 60 сек;
- `robots.content_invalid` → bounded retry, затем `awaiting_user`/явный блокер;
- routing fatal → как сейчас fail-closed;
- **IndexNow Hook A не запускать**, пока public robots не verified.

Артефакты должны хранить `attempt_count`, `last_http`, `final_url`, `final_host`, `content_ok`, `last_error_kind`.

## 4.4. Early verifier

В `SiteVerifier` YandexBot-UA не должен быть единственным pass-критерием для `new_robots`. Нейтральный public-check должен быть основным. `--resolve` разрешён только как ранняя диагностика vhost во время DNS propagation и обязан помечаться `dns_pending=true`; он не заменяет поздний публичный neutral-check в `wm_robots`.

---

# 5. P1 — общий источник URL для Recrawl + IndexNow, fallback через sitemap

## 5.1. Зафиксированный дефект

`RefreshOrchestrator::buildRecrawlUrls()` сейчас:

- читает `$pages` из `config.php` по FTP;
- если `$pages` пуст/не найден — возвращает только `https://<new_domain>/`.

Этот же метод используется `wm_recrawl` и IndexNow. Поэтому на сайтах без `$pages` оба механизма получают только главную.

Эксперименты показали, что IndexNow корректно вызывает обход конкретно переданного URL; проблема не в доставке IndexNow, а в бедном URL-set при пустом `$pages`.

## 5.2. Новый resolver

Создать отдельный сервис, например:

`app/Services/SubmissionUrlResolver.php`

Он должен вернуть **канонический кандидатный набор URL** и evidence:

```php
[
  'urls' => [...],
  'source' => 'config_pages|public_sitemap|homepage_fallback',
  'source_url' => null|string,
  'source_http' => null|int,
  'candidate_count' => int,
  'sha256' => string,
]
```

### Приоритет

1. Валидный `$pages` из `config.php`.
2. Если `$pages` пуст — строгий публичный sitemap нового домена.
3. Если sitemap недоступен/пуст — только homepage.

## 5.3. Публичный sitemap fallback

**Не использовать текущий `SitemapFetcher` as-is для submission fallback**, потому что он:

- использует YandexBot UA;
- отключает проверку TLS.

Для нового resolver нужен нейтральный strict fetch:

- UA `RefreshPanel-SitemapResolver/1.0`;
- strict TLS;
- только исходный host;
- `/sitemap.xml`, затем `/sitemap_index.xml`;
- sitemap-index разрешено развернуть рекурсивно с bounded depth (не более 2) и bounded child count;
- не следовать на внешний host.

Старый `SitemapFetcher` для legacy `old_delurl_notify` не менять без отдельной необходимости, чтобы не получить побочный regression.

## 5.4. Нормализация URL

Оставлять только:

- `https://`;
- exact same host нового домена;
- без fragment;
- dedupe;
- homepage в начале списка.

Исключать:

- `/reg` и подмаршруты `/reg/...`;
- `/robots.txt`;
- `/sitemap.xml`, `/sitemap_index.xml`, другие sitemap service URLs;
- Yandex verifier `yandex_*.html`;
- IndexNow key `.txt`;
- внешние URL.

Канонический набор ограничить разумным cap, ориентированным на квоту Recrawl (по умолчанию 150). Если URL больше — deterministic truncation, homepage сохраняется первой.

## 5.5. Один набор — два потребителя

`wm_recrawl` и IndexNow должны получать один и тот же `SubmissionUrlResolver` result. Не должно быть двух независимых парсеров.

В `artifacts_json` сохранить:

- `submission_urls.source`;
- `submission_urls.count`;
- `submission_urls.sha256`;
- `submission_urls.sitemap_url/http`, если использован sitemap;
- фактический count, ушедший в Recrawl;
- фактический count, ушедший в IndexNow.

Не хранить IndexNow key в artifacts/log.

---

# 6. P1 — диагностика и наблюдаемость

Добавить структурированные event codes, как минимум:

- `ssl.dns_transient`;
- `ssl.inconclusive_dns`;
- `ssl.alert_candidate_started`;
- `ssl.alert_candidate_confirmed`;
- `ssl.alert_candidate_cleared`;
- `robots.public_verified`;
- `robots.public_retry`;
- `robots.public_blocked`;
- `submission_urls.config_pages`;
- `submission_urls.sitemap`;
- `submission_urls.homepage_fallback`.

Логи должны объяснять, **что произошло**, не выдавая DNS propagation за SSL outage.

Для SSL detail UI показать transport error kind и, если есть, alert candidate streak.

---

# 7. P2 — cleanup временных диагностических артефактов

Нужна явная политика очистки, но без агрессивного удаления:

- временные manual IndexNow sender-файлы должны удаляться после теста;
- manual key-файлы — удалять только после завершения наблюдения/по явной cleanup-команде;
- `.refresh-ready-*` и verifier-файлы — определить TTL/условия удаления отдельно;
- backup патчей не хранить внутри web-root/panel tree.

**Запрещено:** массовый `chown -R` панели.

---

# 8. Отдельный обязательный OPS health-check после теста/патча

Это не кодовый hotfix и не должно смешиваться с установщиком патча.

## 8.1. Проверить сервер Refresh Panel

- DNS resolution;
- Namecheap API;
- XMLStock;
- Yandex Webmaster API;
- Yandex IndexNow;
- FastPanel API;
- FTP/SFTP к тестовому сайту;
- внешний HTTPS;
- disk/inodes;
- RAM/load;
- main cron heartbeat;
- SSL cron heartbeat;
- отсутствие зависших job locks/processes.

## 8.2. Закрепить DNS resolver

Ранее сервер уже имел инцидент с `nameserver 198.18.18.18`; временно были поставлены `1.1.1.1` и `8.8.8.8`.

После отсутствия активных критичных jobs проверить источник resolver-конфига:

- `/etc/netplan`;
- `/etc/cloud`;
- `/etc/systemd`;
- `/run/systemd/network`;
- NetworkManager, если используется.

До выяснения persistent source не считать проблему окончательно закрытой. Не выполнять `netplan apply`/network restart/reboot во время активных важных jobs.

Важно: кейс `7k2011.top` из этого аудита сам по себе **не доказывает возврат старого серверного DNS-инцидента** — дамп показывает fresh-domain propagation/временную resolution inconsistency.

## 8.3. Проверить OPcache реального web runtime

Проверять `/opt/php80/bin/php-cgi`, а не системный PHP-FPM.

Если `opcache.validate_timestamps=On` — restart не нужен. Если `Off` — отдельно определить корректный recycle Apache mod_fcgid/php-cgi. `php8.3-fpm` не трогать.

---

# 9. Acceptance tests

## 9.1. SSL / DNS

### Case SSL-1 — fresh domain, DNS ещё не появился

Вход:

- `dns_ok=0`;
- HTTPS curl: resolve error;
- job активна и домен ещё ни разу не trusted.

Ожидание:

- `unreachable / dns_resolution_failed`;
- quick retry 30–60 sec;
- **0 красных Telegram**.

### Case SSL-2 — противоречивый transient

Вход как у `7k2011.top`:

- DNS probe fail;
- один HTTP probe успел вернуть 301;
- cert inspector увидел trusted LE;
- strict HTTPS получил resolve error.

Ожидание:

- `unreachable / inconclusive_dns`;
- не `site_error/https_not_served`;
- 0 красных Telegram.

### Case SSL-3 — ранее trusted, единичный DNS failure

- первая transient ошибка → candidate count=1, Telegram 0;
- повторный trusted → candidate cleared.

### Case SSL-4 — ранее trusted, подтверждённый реальный outage

- два одинаковых transport-critical результата ≥60 sec;
- один Telegram после второго;
- последующие те же результаты молчат до cooldown/escalation.

### Case SSL-5 — hard cert failure

- достоверный expired / hostname mismatch;
- критический статус сохраняется и не маскируется как DNS transient.

### Case SSL-6 — штатный внешний redirect при здоровом TLS

- TLS исходного host trusted;
- первый hop HTTPS жив;
- приложение затем уводит на внешний target.

Ожидание:

- не красный SSL `site_error/foreign_redirect`;
- external redirect записан только как diagnostic evidence.

## 9.2. robots

### ROB-1

`200 text/plain` с валидными директивами, same host, trusted TLS → verified, pipeline продолжает.

### ROB-2

`200 HTML`/meta/JS redirect вместо robots → `content_invalid`; bounded retries; IndexNow Hook A не вызывается; после лимита awaiting_user.

### ROB-3

redirect на foreign host → invalid/block.

### ROB-4

TLS invalid / transport unavailable → bounded retry, не false verified.

### ROB-5

Yandex robots API 404 при валидном public robots → warning only, pipeline продолжает.

## 9.3. Submission URL resolver

### URL-1

`$pages=7` → source=`config_pages`, ровно те же 7 нормализованных URL.

### URL-2

`$pages` пуст, sitemap содержит 7 валидных URL → source=`public_sitemap`, 7 URL.

### URL-3

Sitemap содержит external/http/reg/service/verifier/key → всё запрещённое отфильтровано.

### URL-4

Sitemap недоступен/пуст → homepage fallback.

### URL-5

`wm_recrawl` и IndexNow используют один artifact hash canonical URL-set.

## 9.4. Pipeline copy

Для refresh после `index_watching`:

- log/TG не упоминает включение redirect;
- следующий stage в тексте соответствует `old_delurl_notify`.

---

# 10. Unit/integration tests, обязательные в архиве патча

Добавить исполняемые тесты без внешних production-вызовов:

- pure classification tests `DomainSslCheckService`;
- alert decision/streak tests;
- `RobotsCheckDecision` matrix;
- `SubmissionUrlResolver` parsing/filtering tests;
- next-stage/copy consistency test.

Тесты не должны покупать домен, менять FastPanel, дергать production Webmaster/IndexNow или писать на реальные сайты.

---

# 11. Требования к упаковке/установщику

1. Патч должен быть delta поверх актуального `refresh3008`.
2. До изменения файлов — SHA-256 preflight ожидаемой базы либо явный список допустимых base hashes.
3. Бэкап **вне panel root**, например `/root/refresh-panel-hotpatch-backups/<patch-id>/`.
4. Сохранять owner/group/mode каждого заменяемого файла.
5. Публикация atomic same-directory temp → `mv`.
6. Новые helper/service файлы публиковать до `RefreshOrchestrator.php`; интеграционный файл — последним.
7. Миграция 051 — идемпотентная, без destructive DDL.
8. Перед commit:
   - `/opt/php80/bin/php -l` для всех изменённых PHP;
   - unit tests;
   - проверка миграции.
9. Установщик не должен перезапускать `php8.3-fpm`, сеть, сервер или Apache без отдельной явной необходимости.
10. Rollback должен восстанавливать файлы и проверять их SHA-256; миграционные дополнительные nullable/candidate-поля можно оставить совместимыми, если rollback кода их просто игнорирует.
11. Не создавать root-owned мусор внутри panel tree.

---

# 12. Финальный production acceptance

После успешного staging/static аудита и установки выполнить **одну новую чистую refresh job**, не использовать старые #230/#231 как финальный acceptance.

На canary должно быть без ручного вмешательства:

1. покупка/alias/self-signed/LE;
2. при DNS propagation нет ложного красного `site_error`;
3. `redirect_enable` и trusted SSL gate проходят штатно;
4. Webmaster verification/recrawl/sitemap;
5. neutral public robots = verified;
6. если `$pages` пуст — URL-set получен из sitemap;
7. IndexNow получает тот же canonical URL-set;
8. index_watching → корректный Telegram с реальным next stage;
9. `old_delurl_notify`;
10. ban_monitoring;
11. нет дублей Telegram и нет stale text.

Только после этого патч считать принятой production-версией.

---

# 13. Что НЕ входит в этот патч

- изменение логики Guard/партнёрских redirect на сайтах;
- добавление или улучшение поведения, зависящего от поискового User-Agent/IP;
- изменение Yandex-specific cloaking/redirect rules;
- серверный network restart/reboot;
- массовая смена владельцев файлов панели.

Патч панели должен, наоборот, проверять публичные служебные ресурсы нейтральным клиентом и корректно различать DNS-transient, SSL-health и бизнес-redirect.
