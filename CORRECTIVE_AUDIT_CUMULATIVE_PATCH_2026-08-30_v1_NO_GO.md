# CORRECTIVE AUDIT — Refresh Panel CUMULATIVE PATCH 2026-08-30 v1

**Статус независимого аудита:** **NO-GO / НЕ УСТАНАВЛИВАТЬ v1**

**Архив:** `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v1.zip`  
**SHA-256 архива:** `8144cb00a7b9963e9e59d34a2a2848043d1315c5440162fdd45e6fbbaa4612cf`

Базовая идея патча правильная, значительная часть P0 реализована качественно, package integrity и baseline-gates хорошие. Но в v1 остаются несколько функциональных дефектов, в том числе прямое нарушение обязательного ROB-3, нарушение контракта «один канонический URL-set для Recrawl + IndexNow», неполная рекурсия sitemap и опасное окно между публикацией кода и миграцией БД.

До устранения пунктов ниже архив в production **не ставить**.

---

## 1. Что уже прошло аудит

### 1.1. Целостность пакета

- `MANIFEST.sha256` проходит полностью.
- Rollback-источники совпадают с baseline `refresh3008`.
- Baseline SHA gates присутствуют.
- Установщик не делает `chown -R` / `chmod -R`.
- Backup размещается вне panel root.
- Новые сервисы публикуются до `RefreshOrchestrator.php`.
- В install-path используется атомарная публикация через temp + `mv`.
- PHP syntax payload-файлов проходит.

### 1.2. SSL-классификация

Направление исправления инцидента `7k2011.top` правильное:

- DNS resolution error больше не должен превращаться в ложный `site_error/https_not_served`.
- Есть `dns_resolution`, `connect_timeout`, `connect_error`, `tls_error`, `http_error`, `none`.
- Противоречивые DNS/TLS probes могут классифицироваться как транзиентные.
- Жёсткие cert-errors не маскируются.
- Внешний business redirect при живом исходном TLS больше не обязан становиться красным `foreign_redirect`.

Это сохранить.

### 1.3. Pipeline copy

Исправлены stale-тексты:

- нет ложного `Pipeline → wm_recrawl`;
- нет «Переход к redirect_enable» после уже выполненного redirect restore;
- Telegram больше не обещает «включит партнёрский редирект»;
- убрана ложная формулировка про 30-минутный deadline.

Это сохранить.

### 1.4. Neutral public robots check

Решающий robots-check переведён на нейтральный UA:

`RefreshPanel-RobotsCheck/2.0`

Это правильное изменение. Не возвращать YandexBot UA для deciding public-check.

---

# 2. P0 BLOCKER — ROB-3: redirect `/robots.txt` на foreign host сейчас НЕ блокирует pipeline

## Фактическая реализация

В `RefreshOrchestrator.php`:

```php
$transportOk = ...;
$hostOk = ...same final host...;
$httpOk = $transportOk && $hostOk;

$decision = RobotsCheckDecision::decide(
    $httpOk,
    $contentOk,
    $apiForDecision,
    $attemptCount
);
```

То есть `foreign_host` схлопывается в обычный `$httpOk=false`.

В `RobotsCheckDecision.php` любая ситуация `!$httpOk` после исчерпания retry-budget превращается в:

```text
continue_best_effort
```

В package test это даже зафиксировано как ожидаемое поведение:

```text
ROB-3 foreign/!httpOk после лимита → continue_best_effort
```

Это **противоречит ТЗ**.

## Требуемое поведение

Foreign-host redirect для публичного `/robots.txt` — это не transient HTTP outage.

Матрица должна быть:

| Ситуация | attempt < max | attempt == max |
|---|---|---|
| DNS/timeout/connect/TLS transport unavailable | retry | continue_best_effort |
| final host != expected host | retry | **block / awaiting_user** |
| HTTP доступен, body HTML/meta/js redirect / не robots | retry | **block / awaiting_user** |
| valid robots | verified_continue | verified_continue |

### Обязательная архитектурная правка

Не передавать в policy один boolean `$httpOk`.

Передать явный result-kind, например:

```php
public_check_kind =
    verified
    transport_unavailable
    foreign_host
    content_invalid
```

или отдельные параметры:

```php
transportOk
hostOk
contentOk
transportErrorKind
```

`foreign_host` после bounded retry обязан давать:

```text
robots.public_blocked
stage_status = awaiting_user
IndexNow Hook A = NOT CALLED
```

### Обязательный тест

Добавить тест:

```text
ROB-3 attempt #1 foreign_host → retry
ROB-3 attempt #2 foreign_host → retry
ROB-3 final attempt foreign_host → block_content_invalid / awaiting_user
ROB-3 Hook A → 0 calls
```

Тест, который ожидает `continue_best_effort` для foreign host, удалить как неверный.

---

# 3. P0/P1 BLOCKER — один канонический URL-set фактически НЕ используется двумя consumer-ами

ТЗ требует:

```text
resolve once
→ canonical URL set
→ wm_recrawl uses exactly this set
→ IndexNow uses exactly this same set
```

## Фактическая реализация v1

`buildRecrawlUrls()` вызывается отдельно:

1. в `wm_recrawl`;
2. позднее заново внутри IndexNow.

То есть второй consumer снова:

- перечитывает `config.php`;
- снова получает sitemap;
- снова нормализует URL;
- может получить уже другой набор.

Если sitemap изменился между стадиями, Recrawl и IndexNow получают разные URL.

`submission_urls.sha256` также может быть перезаписан вторым resolve и перестать описывать фактический набор, отправленный Recrawl.

Package test `URL-5` этого не проверяет: он лишь дважды вызывает resolver над статическим fake-response.

## Требуемая реализация

Резолвить canonical set **один раз на job/revision** и сохранять immutable evidence, например:

```json
submission_urls: {
  "version": 1,
  "source": "config_pages|public_sitemap|homepage_fallback",
  "urls": [
    "https://example/",
    "https://example/page"
  ],
  "count": 2,
  "sha256": "...",
  "resolved_at": "...",
  "sitemap_url": "...",
  "recrawl": {
    "submitted_count": 2,
    "sha256": "..."
  },
  "indexnow": {
    "submitted_count": 2,
    "sha256": "..."
  }
}
```

Допустим другой storage, но обязательный инвариант:

```text
canonical_sha256
== recrawl_actual_sha256
== indexnow_actual_sha256
```

IndexNow после `wm_recrawl` не должен повторно читать sitemap для этой же job.

Если canonical set ещё не существует (например, старая job, которая вошла в stage до патча), допускается один catch-up resolve с сохранением.

### Обязательный тест

Mutable fixture:

```text
первый fetch sitemap → set A
второй потенциальный fetch → set B
```

Orchestrator должен:

```text
Recrawl → A
IndexNow → A
sitemap fetch для IndexNow повторно не выполняется
```

И проверить:

```text
recrawl_actual_sha256 == indexnow_actual_sha256 == canonical_sha256
```

---

# 4. P1 BLOCKER — sitemap index реализован не как depth<=2 recursion

## Фактическая проблема

`SubmissionUrlResolver::fetchSitemap()`:

- распознаёт root `<sitemapindex>`;
- берёт его child `<loc>`;
- fetch-ит child;
- затем **без проверки root child-документа** просто извлекает из него `<loc>` и считает их page URLs.

Если child сам является `<sitemapindex>`, его `<loc>` указывают на следующие sitemap-файлы, но v1 добавляет эти sitemap URL как кандидаты страниц.

Пример:

```text
/sitemap.xml
  → /sitemap-child.xml        (sitemapindex)
       → /sitemaps/pages.xml  (urlset)
            → /page-a
```

v1 может закончить с:

```text
/
/sitemaps/pages.xml
```

и вообще не fetch-нуть `/sitemaps/pages.xml`.

## Требуемая реализация

Сделать bounded recursive function, например:

```php
walkSitemap($url, $expectedHost, $depth)
```

Правила:

- root + child recursion до глубины, согласованной с ТЗ (`depth <= 2`);
- максимум 50 child sitemap на index-node;
- visited-set против циклов;
- общий cap URL;
- `<sitemapindex>` → recurse;
- `<urlset>` → collect page `<loc>`;
- неизвестный root XML → reject;
- sitemap-файлы никогда не попадают в page URL set.

### Обязательный тест

Fixture:

```text
root sitemapindex
→ child sitemapindex
→ grandchild urlset
→ /page-a, /page-b
```

Ожидание:

```text
/
/page-a
/page-b
```

И ни одного:

```text
/sitemap*.xml
/sitemaps/*.xml
```

---

# 5. P1 BLOCKER — child sitemap может быть HTTP, хотя fallback обязан быть strict HTTPS

## Фактическая проблема

Для child `<loc>` проверяется host, но схема `https` не проверяется до `httpGetStrict()`.

В результате sitemap index может содержать:

```text
http://same-host/sitemap-child.xml
```

и resolver реально выполнит HTTP-request.

Это нарушает strict HTTPS contract.

## Требуемая правка

Для каждого sitemap reference до I/O:

```text
scheme == https
host == exact expected host
port == default/allowed HTTPS port
fragment absent
userinfo absent
```

HTTP child не fetch-ить и не принимать.

### Обязательный тест

Root:

```xml
<sitemapindex>
  <sitemap>
    <loc>http://example.test/child.xml</loc>
  </sitemap>
</sitemapindex>
```

Ожидание:

- HTTP URL **не запрашивался**;
- его содержимое не влияет на set;
- если других sitemap нет → homepage fallback.

---

# 6. P1 — фильтр sitemap service URL слишком узкий

Текущий `isExcludedPath()` ориентирован на root-level `sitemap*.xml`.

Он не гарантирует отбрасывание вложенных sitemap URL вида:

```text
/sitemaps/pages.xml
/maps/sitemap-products.xml
```

Если такой URL по ошибке попал в candidate pages, он может уйти в Recrawl/IndexNow.

## Требуемая правка

Sitemap URLs должны отсеиваться по provenance и по path:

- любой URL, который был sitemap-document reference, никогда не становится page URL;
- дополнительно исключать разумные sitemap XML endpoints на любом уровне path;
- учитывать `.xml` и при необходимости `.xml.gz`, если parser это поддерживает.

Не надо глобально исключать все `.xml` страницы без доказанной необходимости; фильтр должен быть sitemap-aware.

---

# 7. P1 BLOCKER — resolver и IndexNow применяют разные правила dedupe

## Фактическая проблема

`SubmissionUrlResolver::normalizeUrls()` использует свой `$seen`.

Существующий `IndexNowService::normalizeUrls()` затем ещё раз нормализует и dedupe-ит через:

```php
$key = strtolower($canon);
```

Поэтому canonical set:

```text
/
/Case
/case
```

может превратиться перед фактическим IndexNow POST в:

```text
/
/Case
```

Recrawl и IndexNow снова расходятся.

## Требуемая архитектура

Должен быть **один normalization contract**.

Предпочтительно:

- canonical resolver делает всю validation/normalization/dedupe;
- Recrawl принимает canonical set;
- IndexNow submit принимает уже canonical set и не меняет семантику списка.

Либо вынести единую normalization-функцию и использовать везде.

### Обязательный тест

Вход:

```text
https://x.test/
https://x.test/Case
https://x.test/case
```

Какой бы policy ни был выбран, результат обязан быть **одинаковым у обоих consumer-ов**.

---

# 8. P0 DEPLOYMENT BLOCKER — код зависит от migration 051, но install.sh миграцию не применяет

## Фактическая проблема

Новый `DomainSslStatusRepository` делает SELECT новых колонок:

```text
ever_trusted_at
alert_candidate_key
alert_candidate_since
alert_candidate_last_at
alert_candidate_count
```

Но `install.sh`:

1. публикует новый код;
2. заканчивает `INSTALL_OK`;
3. только пишет оператору:

```text
НЕ забудьте выполнить миграцию 051
```

Если миграция ещё не применена, SELECT новых колонок падает. Исключение местами проглатывается широким catch.

Результат: после установки кода до миграции SSL Telegram alerting может молча перестать работать.

Это запрещённое broken deployment window.

## Допустимые варианты исправления

### Вариант A — предпочтительный

Installer:

1. baseline/package/preflight;
2. backup;
3. pre-validate migration;
4. применяет migration 051 штатным DB runner;
5. подтверждает schema columns;
6. только после этого публикует зависимый код;
7. post-gates;
8. `INSTALL_OK`.

### Вариант B

Код остаётся backward-compatible до миграции:

- feature-detect new columns;
- до 051 продолжает использовать старый alert behavior;
- никогда не «молчит» из-за missing columns.

Но перед финальным `INSTALL_OK` всё равно нужен явный schema status.

### Обязательный acceptance

Нельзя выдавать:

```text
INSTALL_OK
```

если код, уже опубликованный в target, требует колонок, которых в DB ещё нет.

---

# 9. P0 — `ever_trusted_at` backfill неверно понимает слово “ever”

Migration 051 заполняет `ever_trusted_at` только для строк, у которых **прямо сейчас**:

```sql
status = 'trusted'
```

Но домен мог:

- много раз быть trusted исторически;
- прямо в момент установки быть `site_error` / `unreachable`.

У него `ever_trusted_at` останется NULL.

Дальше debounce может считать его «свежим никогда не trusted» и подавлять реальный outage.

## Требуемый backfill

Использовать `domain_ssl_check_history`.

Для каждого current status row:

```text
если ever_trusted_at NULL
и в history есть status='trusted'
→ заполнить временем trusted history
```

Допустимо earliest/first trusted или другой детерминированный timestamp, главное — семантика `ever`.

Текущий `status='trusted'` оставить как fallback.

### Обязательный тест

DB fixture:

```text
current domain_ssl_statuses.status = site_error
history: trusted → trusted → site_error
ever_trusted_at = NULL
```

После 051:

```text
ever_trusted_at IS NOT NULL
```

---

# 10. P0 — never-trusted manual monitor не должен подавляться навсегда

В v1 pure-decision фактически считает:

```text
everTrusted == false → suppress_fresh
```

Но exemption из ТЗ относится к **свежему домену активной refresh-job во время provisioning**.

Ручной monitor — другая ситуация.

Если пользователь вручную добавил домен в SSL-monitor, который никогда не был trusted и реально лежит, он не должен бесконечно молчать.

## Требуемое решение

Передавать в decision provisioning context:

```text
has_active_refresh_job
is_explicit_manual_monitor
refresh_stage
job_age/provisioning_window (если нужно)
```

Например:

```text
suppressFreshProvisioning =
    hasActiveRefreshJob
    && !explicitManual
    && !everTrusted
```

Для manual monitor:

- первый critical transport sample → candidate;
- второй одинаковый после >=60 сек → один red alert;
- дальше существующий cooldown.

### Обязательные тесты

1. Fresh active refresh + DNS propagation:
   ```text
   3 transient measurements → 0 red alerts
   ```

2. Manual never-trusted dead domain:
   ```text
   measurement #1 → candidate
   >=60 sec, measurement #2 same → 1 red alert
   ```

---

# 11. P1 — требуемая SSL detail UI диагностика не реализована

В payload нет изменения `app/Views/ssl/show.php`.

ТЗ требовало отображать:

- `transport_error_kind`;
- candidate streak:
  - key;
  - count;
  - since;
  - last_at.

Сейчас candidate columns есть в status table, но `transport_error_kind` сохраняется в history `result_json`, а current detail UI его не получает/не показывает.

## Требуемая правка

Либо:

- добавить current diagnostic fields в `domain_ssl_statuses`;

либо:

- controller/repository detail query получает последний history row и декодирует `result_json`.

UI должна показывать минимум:

```text
Transport: dns_resolution
Alert candidate: 1/2
Candidate since: ...
Last sample: ...
```

Если streak отсутствует — явно `нет`.

---

# 12. P1 — event codes должны быть доступны в панели/аудите, не только в PHP error_log

`emitEvent()` сейчас пишет event code через `error_log`.

Это полезно, но недостаточно для операторского аудита.

Минимум ключевые события должны быть видимы в panel history/evidence:

```text
ssl.dns_transient
ssl.inconclusive_dns
ssl.alert_candidate_started
ssl.alert_candidate_confirmed
ssl.alert_candidate_cleared
robots.public_verified
robots.public_retry
robots.public_blocked
submission_urls.config_pages
submission_urls.sitemap
submission_urls.homepage_fallback
```

Не обязательно делать новую глобальную event-table, если архитектура панели уже имеет подходящий sink. Но события должны быть доступны при разборе job/domain из UI/DB, а не только из системного PHP log.

---

# 13. P1 — rollback имеет окно partial-rollback

В `rollback.sh` текущий файл попадает в `RESTORE_CHANGED` только **после**:

1. `cp rollback-source → target`;
2. `php -l`;
3. SHA verify.

Если, например, copy уже заменил target, а `php -l`/verify упал, EXIT handler ещё не знает о текущем файле и не восстановит его из pre-rollback backup.

Получается partial rollback.

## Требуемая правка

Перед первой mutation текущего target:

```bash
RESTORE_CHANGED+=("$f")
```

или отдельный `CURRENT_MUTATION="$f"`.

Ещё лучше rollback тоже делать атомарно:

```text
temp same-dir
→ owner/group/mode
→ php-l temp
→ sha temp
→ mv
→ post-sha
```

При injected failure восстановить **точно pre-rollback state** всех уже затронутых файлов.

### Обязательный fault-injection test

Искусственно уронить rollback после первой/второй mutation и проверить:

```text
все target SHA == pre-rollback SHA
```

---

# 14. P2 — поправить документацию BACKUP_BASE

Сейчас комментарий:

```text
bash install.sh <root> [BACKUP_BASE=...]
```

создаёт впечатление positional argument.

Реальное использование:

```bash
BACKUP_BASE=/root/refresh-panel-hotpatch-backups \
bash install.sh /var/www/.../refresh-panel.seotop-one.ru
```

Поправить usage/comment и в `rollback.sh`.

---

# 15. P1 hardening — sitemap parser должен валидировать тип XML

Regex `<loc>` недостаточно.

Для public sitemap response нужно определить root:

```text
<urlset>
<sitemapindex>
```

Неизвестный XML/HTML с `<loc>` не принимать.

Если будет поддержка `.xml.gz`, распаковывать bounded; если не будет — явно skip.

Также желательно:

- body size cap;
- request timeout;
- child count cap;
- visited-set;
- no credentials/userinfo in URL;
- no non-default unexpected port.

---

# 16. Уточнение SSL classification

Основная классификация v1 годная и её переписывать не надо.

Но есть диагностический edge:

если:

```text
tls_trusted = 1
HTTP/HTTPS application probes = DNS resolution failure
```

результат желательно маркировать `inconclusive_dns`, а не просто `dns_resolution_failed`, потому что cert-probe уже получил противоречивый успешный сигнал.

Это не причина NO-GO само по себе, но привести в соответствие с заявленной семантикой.

---

# 17. Новые обязательные tests для v2

Помимо существующих зелёных tests, добавить:

### Robots

- `ROB-3 foreign host #1 → retry`
- `ROB-3 foreign host #2 → retry`
- `ROB-3 foreign host final → block`
- `ROB-3 Hook A calls = 0`
- transport unavailable final → best-effort allowed
- content-invalid final → block

### Sitemap

- root index → child index → grandchild urlset;
- no sitemap-document URL in final page set;
- child `http://` rejected before I/O;
- external child rejected;
- loop A→B→A bounded;
- >50 children capped;
- invalid XML with `<loc>` but not urlset/index rejected;
- nested sitemap paths not submitted as pages.

### Canonical consumer parity

- mutable sitemap A→B: Recrawl and IndexNow both use persisted A;
- actual SHA equality:
  ```text
  canonical == recrawl == indexnow
  ```
- case/path normalization contract identical in both consumers.

### Migration / alerts

- schema-before-code deployment gate;
- migration idempotent twice;
- history-based `ever_trusted_at` backfill;
- active fresh refresh transient = 0 alert;
- manual never-trusted permanent outage = 1 confirmed red after 2/60;
- previously trusted transient one sample = 0;
- previously trusted same critical sample 2/60 = 1;
- recovery clears candidate.

### UI

- SSL detail exposes `transport_error_kind`;
- SSL detail exposes candidate count/since/key/last.

### Rollback

- failure injection during changed-file restore returns exact pre-rollback SHA set.

### Runtime

Обязательно прогнать lint/tests **именно**:

```bash
/opt/php80/bin/php
```

на staging/copy среды.

В аудиторском контейнере системный PHP не имел `mbstring`, поэтому `test_ssl_classification.php` упал на уже используемом `mb_substr()`. Это не доказательство production-дефекта: baseline уже зависит от mbstring. Но финальный acceptance должен идти на реальном `/opt/php80`.

---

# 18. Installer v2 — обязательный порядок

Рекомендуемый порядок:

1. verify `MANIFEST.sha256`;
2. verify baseline SHA;
3. verify target owner/group/mode;
4. verify backup base outside panel root;
5. create + byte-verify external backup;
6. DB preflight;
7. migration 051 apply/validate **до публикации кода, который требует новые поля**, либо гарантированный backward-compatible feature gate;
8. `/opt/php80/bin/php -l` всех payload temp-файлов;
9. unit tests;
10. publish new helper/service files;
11. publish dependent existing services;
12. **RefreshOrchestrator.php последним**;
13. post-copy SHA;
14. owner/group/mode assert;
15. DB schema assert;
16. no temp/backup junk inside panel root;
17. no daemon/network restart;
18. `INSTALL_OK`.

Любой fail до commit → exact rollback к pre-install state.

---

# 19. Acceptance после v2

Перед production install:

- independent audit archive;
- all package hashes;
- source diff versus `refresh3008`;
- static call-flow audit;
- tests above;
- installation dry-run/staging DB copy if возможно.

После install:

1. health-check panel;
2. Namecheap;
3. XMLStock;
4. Webmaster API;
5. IndexNow;
6. FastPanel API;
7. DNS resolver;
8. FTP;
9. HTTPS;
10. cron/workers;
11. OPcache real `/opt/php80/bin/php-cgi`.

Затем одна новая clean refresh-job без ручных вмешательств.

Acceptance job должна доказать:

```text
DNS transient → no false red
robots public neutral check → verified
foreign robots redirect → blocks if simulated
canonical URL set saved once
Recrawl actual set == IndexNow actual set
IndexNow crawler response observable
index_watching → correct next stage text
Telegram copy matches real pipeline
SSL monitor has candidate diagnostics
```

---

# 20. Итог аудита v1

## GO по направлениям

- package integrity;
- baseline SHA gates;
- основная SSL/DNS reclassification;
- neutral robots UA;
- stale copy cleanup;
- general installer atomicity;
- no mass permission changes.

## NO-GO blockers

1. foreign-host robots после retries продолжает pipeline;
2. canonical URL set резолвится отдельно для Recrawl и IndexNow;
3. sitemap recursion не depth<=2 и может принять sitemap XML как page;
4. HTTP child sitemap допускается;
5. два consumer-а имеют разную normalization/dedupe semantics;
6. код публикуется до обязательной DB migration;
7. `ever_trusted_at` backfill не покрывает historically trusted/currently failing;
8. never-trusted manual monitors могут подавляться как «fresh»;
9. SSL detail UI из ТЗ не реализован;
10. rollback может оставить partial state при failure после mutation.

**Вердикт: `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v1.zip` НЕ УСТАНАВЛИВАТЬ.  
Нужен v2 по этому corrective audit.**
