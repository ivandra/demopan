# CORRECTIVE AUDIT — Refresh Panel CUMULATIVE PATCH 2026-08-30 v2

**Вердикт:** **NO-GO / v2 пока НЕ устанавливать в production**

Архив: `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v2.zip`  
SHA-256: `0c767fd06ba3ecdbf49885c7ae7514eebccb46fd7b9d44027c38325aebe4d876`

База аудита:
- `refresh3008.tar.gz` SHA-256 `05ba52ba36533212c49f5d2d92fc7bf1398cf3e2003b6387fd85b7c5b70f1d58`
- `refresh_pane.sql(1).zip` SHA-256 `b47106217a5cf674d3c9491247102bd39104cf3b07a92ac5ebe696d47786e2e6`
- `CORRECTIVE_AUDIT_CUMULATIVE_PATCH_2026-08-30_v1_NO_GO.md`

## 1. Что в v2 реально исправлено и прошло аудит

1. `MANIFEST.sha256` полностью сходится.
2. Все 6 rollback-файлов byte-for-byte совпадают с `refresh3008` baseline.
3. ROB-3 исправлен: foreign-host/content-invalid robots после bounded retry блокируют job; IndexNow Hook A при этом не запускается.
4. Решающий public robots-check переведён на нейтральный UA `RefreshPanel-RobotsCheck/2.0`, strict TLS, проверку body и final host.
5. Canonical URL-set теперь persist-ится в `artifacts_json` и второй consumer пытается читать его из БД вместо повторного sitemap resolve.
6. Повторная case-fold normalization в IndexNow submit-path убрана.
7. HTTP/external/userinfo/non-default-port sitemap child отсекаются до I/O.
8. `ever_trusted_at` backfill использует историю `domain_ssl_check_history`.
9. SSL detail UI получил `transport_error_kind` и candidate-поля.
10. Rollback fault-window из v1 исправлен; fault-injection test 3/0 проходит.
11. Устаревшие pipeline/TG тексты исправлены.
12. Никаких `chown -R`, `chmod -R`, рестартов сервисов, `php8.3-fpm`, reboot/netplan в installer нет.

### Независимо прогнанные тесты

- Robots matrix: **22/0**
- SubmissionUrlResolver package tests: **24/0**
- Pipeline copy: **15/0**
- Canonical parity package test: **8/0**
- Rollback fault injection: **3/0**
- SSL classification/streak: **31/0** после локального polyfill `mb_substr` (в аудиторском PHP 8.4 нет mbstring; это не считаю дефектом патча, baseline уже зависит от mbstring).

Однако package tests не покрывают два критических интеграционных дефекта ниже.

---

# 2. P0 BLOCKER — DNS/unreachable outage после исправленной классификации вообще выпал из Telegram debounce

Это главный новый дефект v2.

`DomainSslCheckService` теперь правильно классифицирует DNS/transient как:

```text
status = unreachable
reason = dns_resolution_failed | inconclusive_dns
```

Это нужно, чтобы кейс `7k2011.top` не становился ложным `site_error/https_not_served`.

Но `DomainSslStatusRepository::maybeSendTransitionAlert()` считает critical только:

```php
hostname_mismatch
expired
not_yet_valid
site_error
```

`unreachable` туда НЕ входит.

А `decideTransportAlert()` вызывается только при:

```php
$status === DomainSslCheckService::S_SITE_ERROR
```

Следствие:

### 2.1. Ранее trusted домен с реальным DNS outage

Ожидание из исходного ТЗ:

```text
measurement #1 DNS outage → candidate count=1, Telegram 0
>=60 сек measurement #2 тот же outage → 1 red Telegram
```

Фактически v2:

```text
DNS outage → status=unreachable
unreachable не critical
candidate НЕ стартует
повторный unreachable → candidate НЕ стартует
Telegram = 0 бесконечно
```

То есть после исправления ложного `site_error` мы случайно отключили подтверждённые аварийные DNS-алерты для уже работающих доменов.

### 2.2. Manual never-trusted dead domain

Audit v1 отдельно требовал:

```text
manual monitor, никогда не trusted
#1 real transport outage → candidate
#2 >=60s → red
```

Package test v2 проверяет `decideTransportAlert(..., newKey='site_error', ...)`, но реальный DNS-dead домен до этого метода не доходит: его status `unreachable`.

То есть тест зелёный, но реальный call-flow требование не выполняет.

## Требуемая правка v3

Ввести единое определение `transportCritical` не по одному status, а по структурным полям:

```text
status
reason
transport_error_kind
```

Debounce должен применяться как минимум к transport outage:

- `unreachable / dns_resolution_failed`
- `unreachable / inconclusive_dns`
- connect timeout/reset/no route
- `site_error / https_not_served`, только если это transport failure

Для fresh active refresh до первого trusted: молчание/ожидание.

Для previously trusted или explicit manual: candidate → confirm 2/60 → один red.

Добавить **интеграционный тест реального результата `DomainSslCheckService` → alert policy**, а не только pure `decideTransportAlert()`.

---

# 3. P0 BLOCKER — v2 debounce-ит ВСЕ `site_error`, включая реальный HTTP 5xx

Исходное ТЗ требовало debounce именно **транспортного** `site_error`.

В v2 условие:

```php
if ($has051 && $status === DomainSslCheckService::S_SITE_ERROR) {
    ... decideTransportAlert(...)
}
```

Причина `$reason` и `transport_error_kind` здесь не учитываются.

Поэтому реальный:

```text
status = site_error
reason = http_5xx
```

тоже попадает в candidate gate.

Особенно опасно для fresh active refresh:

```text
HTTP 500
→ site_error/http_5xx
→ everTrusted=false + activeJob=true
→ suppress_fresh
→ красного Telegram нет
```

Это уже не DNS-propagation, а реальная application/site ошибка. Старую немедленную политику для неё менять не требовалось.

## Требуемая правка

Debounce включать только при `isTransportCritical(...) === true`.

`http_5xx` и другие доказанные non-transport `site_error` должны идти по существующей/немедленной critical policy.

Добавить тест:

```text
fresh active refresh + trusted TLS + HTTP 500
→ site_error/http_5xx
→ НЕ suppress_fresh
→ legacy critical alert policy
```

---

# 4. P0/P1 BLOCKER — candidate key не означает «одинаковое критическое измерение»

ТЗ требует минимум 2 **последовательных одинаковых** transport-critical измерения.

Сейчас:

```php
$newKey = $status;
```

Для всех transport/non-transport site errors ключ один:

```text
site_error
```

Поэтому возможно:

```text
#1 site_error/http_5xx
#2 site_error/https_not_served/connect_timeout через 61 сек
```

и второй sample подтвердит streak первого, хотя это разные причины.

## Требуемая правка

Candidate identity должен быть структурным, например:

```text
transport:<status>:<reason>:<transport_error_kind>
```

или детерминированным hash этих полей.

Поля должны быть ограничены до 64 символов либо хранить hash + диагностические поля отдельно.

Тесты:

```text
same kind 2/60 → confirm
different reason → reset/start candidate
different transport_error_kind → reset/start candidate
```

---

# 5. P1 BLOCKER — обязательный nested sitemap-index case из corrective audit всё ещё НЕ реализован

Corrective audit v1 явно требовал fixture:

```text
root sitemapindex
→ child sitemapindex
→ grandchild urlset
→ /page-a, /page-b
```

с результатом:

```text
/
/page-a
/page-b
```

В v2:

```php
const MAX_SITEMAP_DEPTH = 2; // root(1) → child(2); глубже не ходим
```

и `grandchild` вызывается как depth=3, после чего сразу reject.

Я отдельно прогнал именно требовавшийся fixture. Фактический результат v2:

```json
{
  "urls": ["https://x.test/"],
  "source": "homepage_fallback",
  "fetched": [
    "https://x.test/sitemap.xml",
    "https://x.test/child-index.xml",
    "https://x.test/sitemap_index.xml"
  ]
}
```

`https://x.test/pages.xml` вообще не запрашивается.

Package test с названием:

```text
root index → child index → grandchild urlset
```

на самом деле **не тестирует child index**. В fixture `child` сразу `<urlset>`. Комментарий прямо говорит, что depth3 намеренно не берётся.

То есть тест был ослаблен относительно corrective audit, а REPORT.md утверждает, что блокер закрыт.

## Требуемая правка

Чётко определить depth как число переходов от root, например:

```text
root depth=0
child depth=1
grandchild depth=2
```

Тогда bounded depth `<=2` позволит требуемый fixture.

Добавить реальный test:

```text
root index → child index → grandchild urlset → pages
```

и assert, что grandchild URL был fetched.

---

# 6. P1 — canonical persistence пока не fail-closed, evidence может быть ложным

`resolveOrLoadSubmissionUrls()` делает:

```php
$this->saveArtifacts(... canonical ...);
$this->recordSubmissionConsumer(...);
return $urls;
```

Но `saveArtifacts()` внутри ловит exception и только пишет warning; caller не получает `false`/exception.

Следствие: если canonical set не сохранился, Recrawl всё равно продолжит с set A, а позднее IndexNow может повторно resolve set B. Это нарушает главный инвариант именно в error-path.

Кроме того `recordSubmissionConsumer()` записывает:

```text
submitted_count
sha256
```

**до фактического Recrawl/IndexNow submit**.

Для IndexNow запись происходит до reservation и до `$svc->submit()`. Если reservation не сохранилась или POST не состоялся, artifacts уже говорят `submitted_count`, хотя отправки не было.

## Требуемая правка

Сделать отдельный strict helper для canonical evidence:

```text
persist canonical → verify readback/SHA → только затем consumer может идти дальше
```

Не менять глобально best-effort `saveArtifacts`, если это рискованно; сделать отдельный метод для этого инварианта.

Развести evidence:

```text
canonical/planned
attempted
accepted/outcome
```

`submitted_*` писать только после фактической отправки/attempt.

---

# 7. P1 DEPLOYMENT — installer не делает настоящий schema assert после migrate.php

`install.sh` после:

```bash
$PHP_BIN public/migrate.php
```

при `exit 0` просто ставит:

```bash
SCHEMA_OK=1
```

Но прямой проверки наличия всех колонок 051 нет.

Это имеет edge-case:

```text
migrations table уже содержит 051
но schema была вручную/частично повреждена
→ migrate.php пишет Nothing to apply
→ installer считает schema confirmed
→ публикует зависимый код
```

Для текущего присланного SQL это не немедленная проблема: в migrations есть только 001..050, 051 отсутствует, поэтому первый install обязан реально выполнить 051. Но заявленный fail-closed schema gate всё равно не выполнен полностью.

## Требуемая правка

После migrate.php выполнить отдельный schema probe всех обязательных колонок 051 и только после него `SCHEMA_OK=1`/`INSTALL_OK`.

---

# 8. P1 DEPLOYMENT — installer не запускает два обязательных теста, хотя REPORT утверждает полный gate

В архиве есть:

```text
tests/test_canonical_parity.php
tests/test_rollback_fault.sh
```

но `install.sh` запускает только:

```text
test_ssl_classification
test_robots_decision
test_submission_url_resolver
test_pipeline_copy
```

То есть canonical-parity и rollback fault test присутствуют, но в install gate не входят.

## Требуемая правка

Перед mutation запускать все обязательные tests, включая:

```bash
$PHP_BIN tests/test_canonical_parity.php
bash tests/test_rollback_fault.sh
```

на staging/runtime совместимой среде.

---

# 9. P1 DEPLOYMENT — BACKUP_BASE не проверяется как реально внешний путь

Комментарий говорит «backup вне panel root», но installer просто использует:

```bash
BACKUP_BASE=${BACKUP_BASE:-/root/refresh-panel-hotpatch-backups}
```

и не проверяет containment.

Если оператор случайно передаст:

```bash
BACKUP_BASE=$TARGET/.backups
```

installer это примет.

## Требуемая правка

После `realpath -m`/эквивалента fail-closed проверить:

```text
BACKUP_BASE != TARGET
BACKUP_BASE не находится внутри TARGET
```

---

# 10. P1 DEPLOYMENT — auto-rollback не сохраняет pre-existing byte-identical NEW files

Gate разрешает ситуацию:

```text
051 migration file / SubmissionUrlResolver уже существует и byte-identical payload
```

Но после write installer безусловно добавляет файл в `CREATED_NEW[]`.

При последующем install failure `restore_all()` делает `rm -f` всех `CREATED_NEW`, то есть удалит файл, который существовал ДО запуска installer.

Это нарушает exact pre-install rollback state при повторной/staged установке.

## Требуемая правка

До mutation запомнить для каждого NEW:

```text
pre_existed=0|1
pre_sha
```

Если существовал — backup/restore как existing file; удалять при rollback только реально созданные текущим запуском файлы.

---

# 11. Дополнительное hardening — sitemap body cap сейчас post-download, а не transport cap

`httpGetStrict()` сначала загружает всё тело через `CURLOPT_RETURNTRANSFER`, а уже после этого `walkSitemap()` делает:

```php
if (strlen($xml) > MAX_SITEMAP_BYTES) $xml = substr(...)
```

То есть 5MB — parser cap, но не download/memory cap.

Не blocker для текущих сайтов, но если заявляется bounded fetch, лучше ограничить размер на уровне write callback/abort.

---

# 12. Что НЕ надо переделывать в v3

Сохранить из v2 без регресса:

- ROB-3 foreign/content block;
- neutral public robots decision;
- IndexNow Hook A только после verified robots;
- same-host HTTPS sitemap ref validation;
- visited-set/child budget;
- единый normalization contract без IndexNow case-fold dedupe;
- `ever_trusted_at` history backfill;
- `transport_error_kind` UI;
- foreign business redirect + healthy TLS → trusted;
- stale pipeline/TG copy fixes;
- rollback atomic restore/fault-window fix;
- baseline SHA gates;
- external backup default;
- no service/network restart;
- no mass ownership changes.

---

# 13. Обязательные новые tests для v3

## SSL integrated

1. **Previously trusted + DNS outage**:
   - DomainSslCheckService result = `unreachable/dns_resolution_failed`;
   - sample #1 → candidate 1, TG 0;
   - same sample >=60s → confirm, TG 1.

2. **Previously trusted + inconclusive DNS**:
   - same 2/60 policy.

3. **Manual never-trusted + DNS/connect dead**:
   - #1 candidate;
   - #2 >=60s red.

4. **Fresh active refresh + DNS propagation**:
   - no red indefinitely during provisioning;
   - UI/history still show transient state.

5. **Fresh/previous + HTTP 500**:
   - `site_error/http_5xx` НЕ проходит transport debounce;
   - legacy critical behavior preserved.

6. **Different critical identities**:
   - `connect_timeout` → `connect_error` resets candidate;
   - `http_5xx` → `https_not_served` never combines.

## Sitemap

7. Реальный fixture:

```text
root index → child index → grandchild urlset → /a,/b
```

8. assert grandchild fetched.

## Canonical evidence

9. Persist canonical fails → consumer submit запрещён / controlled failure; второй consumer не может re-resolve иной set.
10. `submitted_sha/count` появляются только после фактического attempt.

## Installer

11. migration record exists but one 051 column missing → installer MUST NOT `INSTALL_OK`.
12. BACKUP_BASE inside TARGET → zero writes + fail.
13. pre-existing identical NEW + injected failure → exact pre-install state restored.
14. installer executes canonical parity + rollback fault tests.

---

# 14. Итог

## v2 реально улучшен

Большинство блокеров v1 закрыто корректно, особенно robots, rollback, canonical-path direction, migration backfill и SSL classification кейса `7k2011.top`.

## Но production GO пока нельзя дать

Главный P0 regression: **после правильной переклассификации DNS outage в `unreachable` alert-policy перестала подтверждать/сообщать реальные длительные DNS outages для previously-trusted/manual доменов.** Одновременно transport-debounce слишком широко применяется к любому `site_error`, включая HTTP 5xx.

Второй явный функциональный blocker: **nested sitemap-index fixture из corrective audit по-прежнему не поддержан; package test был ослаблен и не проверяет заявленный случай.**

**Вердикт: `refresh-panel_CUMULATIVE_PATCH_2026-08-30_v2.zip` НЕ УСТАНАВЛИВАТЬ. Нужен v3 по этому corrective audit.**
