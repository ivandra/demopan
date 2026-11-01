# Повторная проверка свежего бэкапа

Это инструменты для разработчика на изолированном Linux/Docker-стенде.
На shared-хостинг они не загружаются. Для пользователя достаточно инструкции
`../../START_HERE_RU.md`; SSH, Python и Docker на хостинге не нужны.

## Ограничения стенда

`reset.py` удаляет содержимое только `/site` в специальных контейнерах
`demopan-repro-web` / `demopan-repro-web83`. Он проверяет, что `/site` привязан
к отдельной папке `/tmp/demopan-*`, а не к исходникам. Пересоздаются только
тестовые базы `lifepodr` / `lifepodr83` в `demopan-repro-db`.
Не используйте эти имена и каталоги для других данных.

Входные файлы и SQL задаются через переменные; их нельзя коммитить.
Результаты, контрольные суммы базы, HTML неудачных проверок и служебные
точки авторизации создаются только в приватном `/tmp/demopan-*`.
В Git публикуются лишь очищенные отчёты с итогами проверок.

Проверенные версии: Netcat 7.2.0, PHP 7.4.33 / 8.3.36, MySQL 5.7.27.
Нужны Python с PyMySQL, Node с Playwright и Chromium, Docker CLI.
`Dockerfile.php74` собирает расширения для PHP 7.4.33; основной
`../Dockerfile` описывает прежнюю сборку PHP 8.3. В управляемой среде
обязательно соблюдайте её инструкции proxy/CA. На Docker VFS заранее
проверьте свободное место: каждый слой может дублировать файловую систему.

## Подготовка контейнеров

Подготовьте образы `netcat-qa-php:7.4.33` и `netcat-qa-php:8.3` с mysqli,
mbstring, gd, zip, exif и dom; `short_open_tag=On`, `sendmail_path=/bin/true`.
Ниже PHP-контейнер запускается только во внутренней сети без внешнего egress.
У MySQL не должно быть других баз с ценными данными.

```sh
mkdir -p /tmp/demopan-qa-mysql /tmp/demopan-diagnosis-site /tmp/demopan-qa
docker network create --internal demopan-repro-net
docker run -d --name demopan-repro-db --network demopan-repro-net \
  --mount type=bind,src=/tmp/demopan-qa-mysql,dst=/var/lib/mysql \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes mysql:5.7.27 \
  --sql-mode=NO_ENGINE_SUBSTITUTION --socket=/tmp/mysql.sock
docker run -d --name demopan-repro-web --network demopan-repro-net \
  --mount type=bind,src=/tmp/demopan-diagnosis-site,dst=/site \
  --mount "type=bind,src=$PWD/netcat-integration-v5/tests/router.php,dst=/qa/router.php,readonly" \
  -e LP_TEST_ROOT=/site netcat-qa-php:7.4.33 \
  php -S 0.0.0.0:8080 -t /site /qa/router.php
```

Дождитесь готовности MySQL. Скрипты получают приватные адреса контейнеров;
публичный домен из исходных данных перенаправляется в браузере на тестовый IP.

## Полный цикл

Команды выполняются из корня репозитория. Подставьте путь к свежему SQL,
а не к прежнему одноимённому дампу. `NETCAT_TEST_SOURCE` указывает на папку
с `vars.inc.php`, `netcat` и `netcat_template`.

```sh
export NETCAT_TEST_SOURCE="$PWD/lifepodr3110"
export NETCAT_TEST_SQL=/path/outside/repository/fresh.sql
export NETCAT_QA_OUTPUT=/tmp/demopan-qa
export NETCAT_TEST_SITE=/tmp/demopan-diagnosis-site
export NETCAT_REPRO_WEB_CONTAINER=demopan-repro-web
export NETCAT_REPRO_DB_NAME=lifepodr
export NETCAT_REPRO_ADDRESS="$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' demopan-repro-web)"
export NETCAT_REPRO_DB_ADDRESS="$(docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' demopan-repro-db)"
export NETCAT_CHROMIUM_MODULE=/path/to/@sparticuz/chromium/build/index.js
export CODEX_PRIMARY_RUNTIME_NODE_MODULES=/path/to/node_modules

python3 netcat-integration-v5/tests/shared-hosting/reset.py
python3 "$NETCAT_QA_OUTPUT/db_snapshot.py" before
LIFE_SCENARIO=fresh-php74 node netcat-integration-v5/tests/shared-hosting/browser.mjs
```

Runner проверяет авторизацию, предварительную проверку, установку и её
идемпотентность, активацию, административные редакторы, страницы, регионы,
четыре формы, полный откат, точные контрольные суммы и повторную установку.
`verify-restore.py` сравнивает все исходные таблицы, допуская изменения только
в `Logging` и `Session`, отсутствие новых таблиц и SHA-256 файлов снимка.

В свежем бэкапе регион привязан к `medelta.clinic`. Для проверки контактов
runner временно задаёт существующей записи тестовый домен через редактор
Netcat. Откат обязан вернуть исходное значение. Это изменение только копии,
а не новая настройка установщика.

Для PHP 8.3 создайте другой каталог `/tmp/demopan-diagnosis-site83` и контейнер
`demopan-repro-web83` с образом `netcat-qa-php:8.3`. Повторите команды с
`NETCAT_REPRO_DB_NAME=lifepodr83`, новым IP и отдельным `NETCAT_QA_OUTPUT`.
Два сценария не должны использовать один каталог или одну базу.

## Дополнительные проверки

```sh
export NETCAT_PACKAGE_ROOT="$PWD/netcat-integration-v5"
node netcat-integration-v5/tests/shared-hosting/smoke.mjs
node netcat-integration-v5/tests/shared-hosting/navigation.mjs
```

Smoke проверяет 13 готовых разделов, локальные ресурсы, подробные страницы,
фильтры, калькулятор и адаптивность относительно исходных HTML. Navigation
временно открывает два черновика для проверки трёх уровней меню и мобильного
окна, затем закрывает их и проверяет все 18 черновиков снова.

Искусственные ошибки include и контрольное исключение для диагностики
проверялись отдельно в папке копии, без изменения ядра. Это проверка журнала,
а не воспроизведение старого сбоя хостинга. Протоколы —
`../shared-hosting-php74.json` и `../shared-hosting-php83.json`.

## Регрессия загрузки v5.1.1

`check-distribution.py` экспортирует staged Git index с Windows-настройками и
сравнивает все файлы с полной поставкой и SHA-256 манифеста. Перед запуском
добавьте подготовленные файлы в index; скрипт не меняет рабочее дерево.

```sh
python3 netcat-integration-v5/tests/shared-hosting/check-distribution.py deliverables/lifepodrostka-netcat-v5.1.1-upload.zip
python3 netcat-integration-v5/tests/shared-hosting/simulate-crlf.py
```

Вторую команду запускают после `reset.py`, до browser runner, с теми же
`NETCAT_TEST_SITE` и `NETCAT_QA_OUTPUT`. Она меняет только текстовые файлы
поставки на тестовой копии. `upload-check.php` извлекает настоящий верификатор
из установщика и проверяет LF/CRLF, отсутствие записи в исходные файлы,
изменённые директивы, бинарные повреждения, неизвестные расширения, отсутствие
файла и недоступный файл; для проверки прав нужен непривилегированный UID.
