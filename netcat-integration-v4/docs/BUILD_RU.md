# Сборка и воспроизведение проверок

Рабочая папка — `netcat-integration-v4`. Исходные пользовательские папки не
изменяются. `build/reference-layout` содержит копии предоставленных HTML;
`build/source-content.json` — исходные демонстрационные записи. Итоговое
разложение v4 задаёт `build/editorial.py`, форматирование — `format.mjs`.
Сайт выполняет обычные PHP-шаблоны Netcat; Python и Node на хостинге не нужны.

## Сборка

Нужны Python с beautifulsoup4, PHP с short_open_tag, Node с prettier,
@prettier/plugin-php, js-beautify и jquery-ui-dist. Сборка использует
сохранённые в `files` изображения, шрифты и библиотеки из полного архива.
Для нового checkout сначала скопируйте содержимое `UPLOAD_TO_ROOT` v4 в
`netcat-integration-v4/files`. В `editorial.py` путь `ui` указывает на
локальную установку jquery-ui-dist; измените его для своей машины.
`NETCAT_FORMAT_MODULES` задаёт каталог модулей форматирования.

Из корня репозитория:

```sh
python3 netcat-integration-v4/build/build.py
python3 netcat-integration-v4/tests/structure.py
php -d short_open_tag=On netcat-integration-v4/tests/lint.php
python3 netcat-integration-v4/build/package.py
```

`build.py` создаёт компоненты, удаляет поясняющие комментарии, форматирует
части и собирает установщик с SHA-256 файлов. Если локального PHP нет,
используется PHP контейнера `netcat-qa-web`.

## Изолированный стенд

Прогоны: Netcat 7.2.0 из пользовательских файлов, PHP 8.3.36,
MariaDB 10.11, Chromium 153, Playwright; Python-зависимости beautifulsoup4
и PyMySQL. Файлы стенда — `/workspace/netcat-runtime/site`, БД — `lifepodr`.

```sh
docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
  -t netcat-qa-php:8.3 -f netcat-integration-v4/tests/Dockerfile .
docker network create netcat-qa
docker run --rm -d --name netcat-qa-db --network netcat-qa \
  -p 127.0.0.1:23307:3306 -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  -e MARIADB_DATABASE=lifepodr mariadb:10.11 --sql-mode=
```

Создайте `/workspace/netcat-runtime/site`, затем запустите PHP:

```sh
docker run --rm -d --name netcat-qa-web --network netcat-qa \
  -p 127.0.0.1:8080:8080 --mount type=bind,src=/workspace,dst=/workspace \
  -e DOCUMENT_ROOT=/workspace/netcat-runtime/site \
  -e LP_TEST_ROOT=/workspace/netcat-runtime/site netcat-qa-php:8.3 \
  php -S 0.0.0.0:8080 -t /workspace/netcat-runtime/site \
  /workspace/demopan/netcat-integration-v4/tests/router.php
```

Дождитесь MariaDB. `tests/run.py` пересоздаёт только тестовую БД и тестовую
папку; запускайте его исключительно на изолированном стенде.
`NETCAT_CHROMIUM_MODULE` должен указывать на
`@sparticuz/chromium/build/index.js`, `CODEX_PRIMARY_RUNTIME_NODE_MODULES` —
на папку с Playwright. Тесты авторизуют тестового администратора только в
копии; их временные HTTP-файлы никогда не входят в установочный пакет.

Выполняйте прогоны последовательно: у них общая БД.

```sh
python3 netcat-integration-v4/tests/run.py --sql /путь/postroisvo_zhizn.sql
node netcat-integration-v4/tests/smoke.mjs
python3 netcat-integration-v4/tests/run.py --upgrade v3 --sql /путь/postroisvo_zhizn.sql
python3 netcat-integration-v4/tests/run.py --upgrade v3 --after-rollback --sql /путь/postroisvo_zhizn.sql
```

Для v3 используется ранее опубликованный `deliverables/lifepodrostka-netcat-v3.zip`.
Для необязательного сценария `--upgrade v2` нужна папка исходного пакета
`lifepodrostka-netcat-ready (2)/UPLOAD_TO_ROOT`. Сверка записей выполняется
перед намеренным редактированием в браузере, затем проверяются результаты
редактирования и неизменённые таблицы. Доставка внешних писем не включается.

Для остановки стенда:

```sh
docker stop netcat-qa-web netcat-qa-db
docker network rm netcat-qa
```
