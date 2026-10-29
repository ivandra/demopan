# Сборка и проверки

Исходники патча — `netcat-integration-v5`. Исходные пользовательские папки
не изменяются. В `build/reference-layout` находятся предоставленные HTML;
`source-components`, `source-layout` и `source-manifest.json` — входные
данные штатных компонентов и общих макетов. `build.py` собирает только v5.

Нужны Python с beautifulsoup4, Node с prettier / @prettier/plugin-php /
js-beautify и PHP. `NETCAT_FORMAT_MODULES` задаёт папку Node-зависимостей.
Если PHP не установлен локально, используется контейнер `netcat-qa-web`.
Для нового checkout сначала скопируйте `UPLOAD_TO_ROOT` полного архива в
`netcat-integration-v5/files`: изображения, шрифты и видео не дублируются в Git.

```sh
python3 netcat-integration-v5/build/build.py
python3 netcat-integration-v5/tests/structure.py
php -d short_open_tag=On netcat-integration-v5/tests/lint.php
python3 netcat-integration-v5/build/package.py
```

Стенд использует исходные `lifepodr` и `postroisvo_zhizn.sql`, контейнеры
`netcat-qa-web` / `netcat-qa-db`, PHP 8.3.36, MariaDB 10.11 и Chromium 153.
Файлы — `/workspace/netcat-runtime/site`, отдельная тестовая БД — `lifepodr`.
Создание контейнеров описывается `tests/Dockerfile` и настройками стенда.
Порты привязаны только к loopback: 8080 и 23307. Тестовый runner проверяет
имя контейнера и фиксированную папку перед сбросом тестовой копии.

`NETCAT_CHROMIUM_MODULE` — путь к `@sparticuz/chromium/build/index.js`,
`CODEX_PRIMARY_RUNTIME_NODE_MODULES` — папка Playwright. Python требуется
PyMySQL. SQL для стенда указан в `tests/run.py`; для своей машины измените путь.

Прогоны выполняются последовательно, поскольку у них общая тестовая БД:

```sh
python3 netcat-integration-v5/tests/run.py
node netcat-integration-v5/tests/smoke.mjs
python3 netcat-integration-v5/tests/run.py --previous v4
python3 netcat-integration-v5/tests/run.py --previous v3 --rolled-back
```

Каждый сценарий проходит браузерную установку, очистку, редактирование,
отправку форм, полное восстановление и повторную установку. `verify-restore.py`
сравнивает значения всех колонок прикладных таблиц и SHA-256 восстановленных
файлов. Динамические данные административной авторизации не считаются
содержимым сайта. Снимки для сравнения создаются только вне репозитория.

`prepare.py` создаёт временные `_life_auth.php` и `_life_state.php` только
на локальном стенде. Их нельзя загружать на хостинг. Они не входят в ZIP.
Рабочий хостинг для тестов не использовался.
