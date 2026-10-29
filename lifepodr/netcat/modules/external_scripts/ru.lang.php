<?php

// Описание модуля
const NETCAT_MODULE_EXTSCRIPTS = 'Внешние скрипты';
const NETCAT_MODULE_EXTSCRIPTS_DESCRIPTION = 'Управление внешними скриптами (счётчики, аналитика, виджеты)';

// Общие
const NETCAT_MODULE_EXTSCRIPTS_ADD = 'Добавить скрипт';
const NETCAT_MODULE_EXTSCRIPTS_EDIT = 'Редактировать';
const NETCAT_MODULE_EXTSCRIPTS_DELETE = 'Удалить';
const NETCAT_MODULE_EXTSCRIPTS_SAVE = 'Сохранить';
const NETCAT_MODULE_EXTSCRIPTS_CANCEL = 'Отмена';
const NETCAT_MODULE_EXTSCRIPTS_ENABLED = 'Включён';
const NETCAT_MODULE_EXTSCRIPTS_DISABLED = 'Отключён';
const NETCAT_MODULE_EXTSCRIPTS_NAME = 'Название';
const NETCAT_MODULE_EXTSCRIPTS_TYPE = 'Тип скрипта';
const NETCAT_MODULE_EXTSCRIPTS_PRIORITY = 'Приоритет';
const NETCAT_MODULE_EXTSCRIPTS_STATUS = 'Статус';
const NETCAT_MODULE_EXTSCRIPTS_ACTIONS = 'Действия';
const NETCAT_MODULE_EXTSCRIPTS_SETTINGS = 'Настройки';
const NETCAT_MODULE_EXTSCRIPTS_CONDITIONS = 'Условия показа';
const NETCAT_MODULE_EXTSCRIPTS_PREVIEW = 'Предпросмотр кода';

// Типы сниппетов
const NETCAT_MODULE_EXTSCRIPTS_TYPE_YANDEX_METRIKA = 'Яндекс.Метрика';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_GTM = 'Google Tag Manager';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_GA4 = 'Google Analytics 4';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_JIVO = 'Jivo';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CARROT = 'Carrot Quest';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_VK_PIXEL = 'VK Pixel';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CALLTOUCH = 'Calltouch';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CUSTOM = 'Произвольный код';

// Общие настройки аналитики
const NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS = 'Отправка событий';
const NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS_DESC = 'Включить отправку событий из форм и других элементов с атрибутами data-analytics-*';

// Яндекс.Метрика
const NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID = 'ID счётчика';
const NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID_DESC = 'Числовой идентификатор счётчика из Яндекс.Метрики';
const NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR = 'Вебвизор';
const NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR_DESC = 'Запись действий посетителей';
const NETCAT_MODULE_EXTSCRIPTS_YM_CLICKMAP = 'Карта кликов';
const NETCAT_MODULE_EXTSCRIPTS_YM_TRACKLINKS = 'Отслеживание ссылок';
const NETCAT_MODULE_EXTSCRIPTS_YM_ACCURATE_BOUNCE = 'Точный показатель отказов';
const NETCAT_MODULE_EXTSCRIPTS_YM_TRACKHASH = 'Отслеживание хеша в URL';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE = 'Электронная коммерция';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_DESC = 'Включить передачу данных электронной коммерции';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_CONTAINER = 'Имя контейнера данных';

// Google Tag Manager
const NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID = 'Container ID';
const NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID_DESC = 'Идентификатор контейнера в формате GTM-XXXXXXX';

// Google Analytics
const NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID = 'Measurement ID';
const NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID_DESC = 'Администратор → Потоки данных → выбрать поток → Measurement ID (формат G-XXXXXXXXXX)';

// Jivo
const NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID = 'Widget ID';
const NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID_DESC = 'Идентификатор виджета из личного кабинета Jivo';

// Carrot Quest
const NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID = 'App ID';
const NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID_DESC = 'Идентификатор приложения из личного кабинета Carrot Quest';

// VK Pixel
const NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID = 'Pixel ID';
const NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID_DESC = 'Идентификатор пикселя VK';

// Calltouch
const NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID = 'ID скрипта';
const NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID_DESC = 'Идентификатор из личного кабинета Calltouch (mod_id)';

// Произвольный код
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START = 'Код после &lt;head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START_DESC = 'HTML/JS код, который будет вставлен сразу после открывающего тега &lt;head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END = 'Код перед &lt;/head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END_DESC = 'HTML/JS код, который будет вставлен перед закрывающим тегом &lt;/head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START = 'Код после &lt;body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START_DESC = 'HTML/JS код, который будет вставлен сразу после открывающего тега &lt;body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END = 'Код перед &lt;/body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END_DESC = 'HTML/JS код, который будет вставлен перед закрывающим тегом &lt;/body&gt;';

// Условия показа
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_ALL = 'Все страницы';
const NETCAT_MODULE_EXTSCRIPTS_SCOPE_SOME = 'Некоторые страницы';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE = 'Только выбранные разделы';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_EXCLUDE = 'Все, кроме выбранных разделов';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_SELECT = 'Выбрать разделы';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE_CHILDREN = 'Включая дочерние разделы';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_SELECTED = 'Выбрано разделов';

// Ошибки
const NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED = 'ID счётчика обязателен';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_NUMERIC = 'ID счётчика должен быть числом';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_REQUIRED = 'Container ID обязателен';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_FORMAT = 'Container ID должен быть в формате GTM-XXXXXXX';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CUSTOM_EMPTY = 'Необходимо заполнить хотя бы одно поле с кодом';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_NAME_REQUIRED = 'Название обязательно';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_TYPE_REQUIRED = 'Выберите тип скрипта';

// Сообщения
const NETCAT_MODULE_EXTSCRIPTS_MSG_SAVED = 'Скрипт сохранён';
const NETCAT_MODULE_EXTSCRIPTS_MSG_DELETED = 'Скрипт удалён';
const NETCAT_MODULE_EXTSCRIPTS_MSG_ENABLED = 'Скрипт включён';
const NETCAT_MODULE_EXTSCRIPTS_MSG_DISABLED = 'Скрипт отключён';
const NETCAT_MODULE_EXTSCRIPTS_MSG_ORDER_SAVED = 'Порядок сохранён';
const NETCAT_MODULE_EXTSCRIPTS_MSG_NO_SCRIPTS = 'Нет добавленных скриптов';
const NETCAT_MODULE_EXTSCRIPTS_MSG_SELECT_SITE = 'Выберите сайт для управления скриптами';
const NETCAT_MODULE_EXTSCRIPTS_MSG_NO_SITES = 'Нет доступных сайтов';
const NETCAT_MODULE_EXTSCRIPTS_SITE = 'Сайт';
const NETCAT_MODULE_EXTSCRIPTS_SCRIPTS_COUNT = 'Скриптов';

// Подтверждения
const NETCAT_MODULE_EXTSCRIPTS_CONFIRM_DELETE = 'Вы уверены, что хотите удалить этот скрипт?';

// Подсказки
const NETCAT_MODULE_EXTSCRIPTS_HINT_DRAG = 'Перетащите для изменения порядка';
const NETCAT_MODULE_EXTSCRIPTS_HINT_PRIORITY = 'Чем меньше значение, тем раньше будет вставлен скрипт';

// Позиции вставки (для предпросмотра)
const NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_START = 'После &lt;head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_END = 'Перед &lt;/head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_BODY_START = 'После &lt;body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_BODY_END = 'Перед &lt;/body&gt;';

// Админка
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_TITLE = 'Внешние скрипты';
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_ADD_TITLE = 'Добавить скрипт';
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_EDIT_TITLE = 'Редактировать скрипт';
const NETCAT_MODULE_EXTSCRIPTS_SELECT_TYPE = 'Выберите тип скрипта';

// Категория cookie (согласие)
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY = 'Категория cookie';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY_HINT = 'Укажите категорию, если скрипт устанавливает cookie или передаёт данные посетителя третьим сторонам (аналитика, реклама, чаты): в режиме блокировки он не выполнится, пока посетитель не даст согласие. Если скрипт этого не делает — выберите «Не собирает данные», он будет работать всегда.';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_DEFAULT = '(по умолчанию для типа скрипта)';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_NONE = 'Не устанавливает cookie и не передаёт данные (не блокировать)';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_NECESSARY = 'Необходимые';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_FUNCTIONAL = 'Функциональные';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_ANALYTICS = 'Аналитические';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_MARKETING = 'Маркетинговые';

// Строка режима окна согласия на странице модуля
const NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_LINE = 'Режим окна cookie-согласия (при наличии окна на сайте)';
const NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_CONFIGURE = 'настроить';

// Вкладки
const NETCAT_MODULE_EXTSCRIPTS_TAB_SNIPPETS = 'Встраиваемые скрипты';
const NETCAT_MODULE_EXTSCRIPTS_TAB_PROVIDERS = 'Автоблокировка скриптов в контенте';

// Реестр провайдеров (152-ФЗ: карты/видео — авто-блокировка по домену до согласия)
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_INTRO = 'Сторонние виджеты (карты, видео и т.п.), которые блокируются до получения cookie-согласия посетителя — независимо от того, как они попали на страницу (штатный виджет, кастомный компонент, вставка в тексте). «Выключить» — запись остаётся в списке, но провайдер больше не блокируется.';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD = 'Добавить провайдера';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD_TITLE = 'Добавить провайдера';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_EDIT_TITLE = 'Редактировать провайдера';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_MSG_EMPTY = 'Нет добавленных провайдеров';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_LABEL = 'Название';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN = 'Домен / паттерн';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN_HINT = 'Подстрока домена (например, vkvideo.ru) или регулярное выражение без ограничителей, если включена галка «Регулярное выражение» (например, (?:api-maps|maps)\.yandex\.ru). Ищется в src тега и в коде встроенных скриптов.';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_IS_REGEX = 'Регулярное выражение';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CATEGORY = 'Категория cookie';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CONFIRM_DELETE = 'Вы уверены, что хотите удалить этого провайдера?';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ERROR_REGEX = 'Регулярное выражение некорректно — проверьте синтаксис';
