<?php

/**
 * Системный хелпер согласия на cookie (Cookie Consent).
 *
 * Единый источник правды о согласии для СЕРВЕРНОЙ стороны: используется
 * процессорами модулей (external_scripts, stats) и баннером согласия, не
 * создавая зависимости между ними. Парный фронтовый рантайм —
 * files/netcat_template/asset/consent/nc-consent.js (общий контракт).
 *
 * Контракт:
 *   - cookie `cookie_consent` — CSV выданных категорий, напр. "necessary,analytics";
 *     легаси-значение "accepted" трактуется как «все категории»;
 *   - режим хранится в настройке сайта `ConsentMode` (Settings, модуль system):
 *     notice | accept-all | categories. По умолчанию accept-all (блокировка до согласия);
 *   - инертный тег: <script type="text/plain" data-nc-consent="<категория>"> —
 *     браузер его не исполняет; рантайм активирует при наличии согласия.
 *
 * @see docs/proposals/cookie-consent.md
 */
class nc_consent {

    /** Имя cookie с состоянием согласия (общее с JS-рантаймом). */
    const COOKIE_NAME = 'cookie_consent';

    /** Ключ настройки сайта с режимом баннера (Settings, модуль system). */
    const SETTING_MODE = 'ConsentMode';

    /** Срок хранения выбора (дней; 0 = до конца сессии). */
    const SETTING_TTL_ACCEPT = 'ConsentTtlAccept';   // согласие
    const SETTING_TTL_REJECT = 'ConsentTtlReject';   // отказ
    const TTL_DEFAULT_ACCEPT = 180;
    const TTL_DEFAULT_REJECT = 30;

    /**
     * Журналирование выбора в окне согласия (настройки сайта, модуль system).
     * Выключено по умолчанию. @see docs/proposals/cookie-consent-log.md
     */
    const SETTING_LOG     = 'ConsentCookieLog';      // '1' — журналировать
    const SETTING_LOG_TTL = 'ConsentCookieLogTtl';   // срок хранения записей, дней; 0 — не удалять
    const LOG_TTL_DEFAULT = 365;

    /**
     * Лимиты записи в журнал (настройки сайта; пусто — значение по умолчанию).
     * Сверх лимита запись отбрасывается (см. nc_cookie_consent_log::check_limits()).
     */
    const SETTING_LOG_LIMIT_IP   = 'ConsentCookieLogIpLimit';    // в минуту с одного адреса (IPv6 — с подсети /64)
    const SETTING_LOG_LIMIT_SITE = 'ConsentCookieLogSiteLimit';  // в минуту на сайт
    const SETTING_LOG_LIMIT_DAY  = 'ConsentCookieLogDayLimit';   // в сутки на сайт
    const LOG_LIMIT_IP_DEFAULT   = 10;       // живой посетитель делает 1–3 выбора
    const LOG_LIMIT_SITE_DEFAULT = 600;
    const LOG_LIMIT_DAY_DEFAULT  = 50000;
    const LOG_LIMIT_MAX          = 100000000;

    /** Категории cookie. */
    const CATEGORY_NONE       = 'none';        // скрипт не ставит cookie и ничего не передаёт — не блокируется никогда
    const CATEGORY_NECESSARY  = 'necessary';   // строго необходимые (сессия, CSRF) — не блокируется
    const CATEGORY_FUNCTIONAL = 'functional';
    const CATEGORY_ANALYTICS  = 'analytics';
    const CATEGORY_MARKETING  = 'marketing';

    /** Режимы баннера. */
    const MODE_NOTICE     = 'notice';      // только информирование, без блокировки
    const MODE_ACCEPT_ALL = 'accept-all';  // одна кнопка, блок всего до согласия
    const MODE_CATEGORIES = 'categories';  // выбор категорий

    /**
     * Все известные категории.
     * @return string[]
     */
    public static function categories() {
        return array(
            self::CATEGORY_NECESSARY,
            self::CATEGORY_FUNCTIONAL,
            self::CATEGORY_ANALYTICS,
            self::CATEGORY_MARKETING,
        );
    }

    /**
     * Категории, на которые собирается согласие и которые блокируются до него.
     * Всё остальное (none, necessary, неизвестное) не блокируется никогда.
     *
     * @return string[]
     */
    public static function gated_categories() {
        return array(
            self::CATEGORY_FUNCTIONAL,
            self::CATEGORY_ANALYTICS,
            self::CATEGORY_MARKETING,
        );
    }

    /**
     * Подлежит ли скрипт данной категории блокировке до согласия.
     * none / necessary / неизвестное — нет.
     *
     * @param string $category
     * @return bool
     */
    public static function is_blockable($category) {
        return in_array($category, self::gated_categories(), true);
    }

    /**
     * Текущий режим согласия для сайта.
     *
     * @param int|null $site_id null — текущий сайт
     * @return string один из MODE_*
     */
    public static function get_mode($site_id = null) {
        $mode = nc_core::get_object()->get_settings(self::SETTING_MODE, 'system', false, $site_id);
        $allowed = array(self::MODE_NOTICE, self::MODE_ACCEPT_ALL, self::MODE_CATEGORIES);

        // Дефолт для сайтов без явной настройки — accept-all (блокировка до
        // согласия), как требует 152-ФЗ. Сменить на notice можно в настройках сайта.
        return in_array($mode, $allowed, true) ? $mode : self::MODE_ACCEPT_ALL;
    }

    /**
     * Включена ли блокировка до согласия (гейтинг). В режиме notice — нет.
     *
     * @param int|null $site_id
     * @return bool
     */
    public static function is_gating_enabled($site_id = null) {
        return self::get_mode($site_id) !== self::MODE_NOTICE;
    }

    /**
     * Допустимые сроки хранения выбора (дней). 0 — до конца сессии.
     * @return int[]
     */
    public static function ttl_values() {
        return array(0, 1, 7, 30, 180, 365);
    }

    /**
     * Срок хранения согласия (дней; 0 = сессия). По умолчанию 180.
     * @param int|null $site_id
     * @return int
     */
    public static function ttl_accept($site_id = null) {
        return self::read_ttl(self::SETTING_TTL_ACCEPT, self::TTL_DEFAULT_ACCEPT, $site_id);
    }

    /**
     * Срок хранения отказа (дней; 0 = сессия). По умолчанию 30.
     * @param int|null $site_id
     * @return int
     */
    public static function ttl_reject($site_id = null) {
        return self::read_ttl(self::SETTING_TTL_REJECT, self::TTL_DEFAULT_REJECT, $site_id);
    }

    /**
     * @param string $key
     * @param int $default
     * @param int|null $site_id
     * @return int
     */
    protected static function read_ttl($key, $default, $site_id) {
        $value = nc_core::get_object()->get_settings($key, 'system', false, $site_id);
        if ($value === false || $value === '' || $value === null) {
            return $default;
        }
        $value = (int)$value;

        return in_array($value, self::ttl_values(), true) ? $value : $default;
    }

    /**
     * Допустимые сроки хранения записей журнала выбора (дней). 0 — не удалять.
     * @return int[]
     */
    public static function log_ttl_values() {
        return array(90, 180, 365, 1095, 0);
    }

    /**
     * Срок хранения записей журнала выбора (дней; 0 — не удалять). По умолчанию 365.
     * @param int|null $site_id
     * @return int
     */
    public static function log_ttl($site_id = null) {
        $value = nc_core::get_object()->get_settings(self::SETTING_LOG_TTL, 'system', false, $site_id);
        if ($value === false || $value === '' || $value === null || is_array($value)) {
            return self::LOG_TTL_DEFAULT;
        }
        $value = (int)$value;

        return in_array($value, self::log_ttl_values(), true) ? $value : self::LOG_TTL_DEFAULT;
    }

    /**
     * Лимиты записи в журнал выбора: ключ настройки => значение по умолчанию.
     * @return int[]
     */
    public static function log_limit_defaults() {
        return array(
            self::SETTING_LOG_LIMIT_IP   => self::LOG_LIMIT_IP_DEFAULT,
            self::SETTING_LOG_LIMIT_SITE => self::LOG_LIMIT_SITE_DEFAULT,
            self::SETTING_LOG_LIMIT_DAY  => self::LOG_LIMIT_DAY_DEFAULT,
        );
    }

    /**
     * Значение лимита записи в журнал (SETTING_LOG_LIMIT_*). Пустая или
     * некорректная настройка — значение по умолчанию.
     *
     * @param string $key SETTING_LOG_LIMIT_*
     * @param int|null $site_id
     * @return int
     */
    public static function log_limit($key, $site_id = null) {
        $defaults = self::log_limit_defaults();
        if (!isset($defaults[$key])) {
            return 0;
        }
        $value = self::parse_log_limit(nc_core::get_object()->get_settings($key, 'system', false, $site_id));

        return is_int($value) ? $value : $defaults[$key];
    }

    /**
     * Разбор значения лимита из формы/настроек: целое от 1 до LOG_LIMIT_MAX → int,
     * пусто → '' (значение по умолчанию), иначе → null (некорректно).
     *
     * @param mixed $value
     * @return int|string|null
     */
    public static function parse_log_limit($value) {
        if ($value === null || $value === false || is_array($value)) {
            return '';
        }
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (!ctype_digit($value) || strlen($value) > 9) {
            return null;
        }
        $value = (int)$value;

        return $value >= 1 && $value <= self::LOG_LIMIT_MAX ? $value : null;
    }

    /**
     * Включена ли настройка «Записывать выбор в журнал» (без учёта зависимостей).
     * @param int|null $site_id
     * @return bool
     */
    public static function is_log_setting_on($site_id = null) {
        $value = nc_core::get_object()->get_settings(self::SETTING_LOG, 'system', false, $site_id);
        return !is_array($value) && (string)$value === '1';
    }

    /**
     * Установлен и включён ли модуль «Логирование» для сайта. Читаем БД напрямую,
     * а не nc_modules::get_by_keyword(): тот отвечает только для текущего сайта,
     * а в настройках сайта в админке редактируемый сайт может быть другим.
     *
     * @param int|null $site_id
     * @return bool
     */
    public static function is_logging_module_enabled($site_id = null) {
        static $cache = array();

        $nc_core = nc_core::get_object();
        $site_id = $site_id ? (int)$site_id : (int)$nc_core->catalogue->id();
        if (!isset($cache[$site_id])) {
            $row = $nc_core->db->get_row(
                "SELECT m.`Installed`, m.`Checked`, mc.`Checked` AS `Site_Checked`
                   FROM `Module` AS m
                   LEFT JOIN `Module_Catalog` AS mc
                          ON mc.`Module_ID` = m.`Module_ID` AND mc.`Catalogue_ID` = " . $site_id . "
                  WHERE m.`Keyword` = 'logging'",
                ARRAY_A
            );
            $checked = $row && $row['Site_Checked'] !== null ? $row['Site_Checked'] : nc_array_value($row, 'Checked');
            $cache[$site_id] = $row && $row['Installed'] && $checked;
        }

        return $cache[$site_id];
    }

    /**
     * Подпись текста окна согласия (HMAC на системном SecretKey). Окно кладёт
     * её в ncConsentConfig рядом с текстом; журнал по ней убеждается, что текст
     * отрисовал сервер, даже если текст передан в виджет из кода макета и в БД
     * его нет. Без SecretKey — '' (журнал сверит текст с настройками сайта).
     *
     * Подпись привязана к сайту: текст, подписанный на одном сайте установки,
     * не подтверждается подписью при записи в журнал другого сайта.
     *
     * @param string $text
     * @param int|null $site_id сайт окна (по умолчанию — текущий)
     * @return string
     */
    public static function text_signature($text, $site_id = null) {
        $nc_core = nc_core::get_object();
        $key = $nc_core->get_settings('SecretKey', 'system', false, 0);
        if (!is_string($key) || $key === '') {
            return '';
        }
        $site_id = $site_id ? (int)$site_id : (int)$nc_core->catalogue->id();

        return hash_hmac('sha256', 'nc_consent_text|' . $site_id . '|' . trim((string)$text), $key);
    }

    /**
     * Журналировать ли выбор посетителя: настройка включена, режим с
     * блокировкой (в notice выбора нет) и модуль «Логирование» включён.
     *
     * @param int|null $site_id
     * @return bool
     */
    public static function is_log_enabled($site_id = null) {
        // порядок важен: настройка читается из уже загруженных Settings, и при
        // выключенном журнале (по умолчанию) запроса к таблице модулей на хите нет
        return self::is_log_setting_on($site_id)
            && self::is_gating_enabled($site_id)
            && self::is_logging_module_enabled($site_id);
    }

    /**
     * Текст баннера по умолчанию для режима (когда у виджета/компонента поле
     * Text не заполнено). Единое место, чтобы виджет и сервис-компонент не
     * расходились.
     *
     * @param string|null $mode один из MODE_*
     * @return string
     */
    public static function default_banner_text($mode = null) {
        switch ($mode) {
            case self::MODE_ACCEPT_ALL:
                return 'Мы используем файлы cookie для работы сайта, аналитики и улучшения сервиса. Вы можете принять или отклонить их.';
            case self::MODE_CATEGORIES:
                return 'Мы используем файлы cookie для работы сайта, аналитики и маркетинга. Можно принять все, отклонить необязательные или настроить категории.';
            case self::MODE_NOTICE:
            default:
                return 'Мы используем файлы cookie, чтобы сайт работал корректно и был удобнее. Продолжая пользоваться сайтом, вы соглашаетесь с использованием cookie.';
        }
    }

    /**
     * Выдано ли согласие на указанную категорию.
     *
     *   - неблокируемая категория (none / necessary / неизвестное) — всегда true;
     *   - гейтинг выключен (режим notice) — всегда true;
     *   - иначе смотрим cookie с выданными категориями (легаси "accepted" = все).
     *
     * @param string $category
     * @param int|null $site_id
     * @return bool
     */
    public static function is_granted($category, $site_id = null) {
        if (!self::is_blockable($category)) {
            return true;
        }
        if (!self::is_gating_enabled($site_id)) {
            return true;
        }

        return in_array($category, self::granted_categories(), true);
    }

    /**
     * Категории, на которые посетитель дал согласие (из cookie).
     * necessary присутствует всегда.
     *
     * @return string[]
     */
    public static function granted_categories() {
        $raw = isset($_COOKIE[self::COOKIE_NAME]) ? trim($_COOKIE[self::COOKIE_NAME]) : '';

        if ($raw === '') {
            return array(self::CATEGORY_NECESSARY);
        }

        // Легаси-формат: одно значение "accepted" = согласие на всё.
        if ($raw === 'accepted') {
            return self::categories();
        }

        $list = array_filter(array_map('trim', explode(',', $raw)));
        $list = array_values(array_intersect($list, self::categories()));

        if (!in_array(self::CATEGORY_NECESSARY, $list, true)) {
            $list[] = self::CATEGORY_NECESSARY;
        }

        return $list;
    }

    /**
     * Делает блок инертным до согласия:
     *   - <script> → type="text/plain" (браузер не исполняет; inline и src);
     *   - <img>/<iframe> → src переименовывается в data-nc-src (браузер не грузит,
     *     закрывает трекинг-пиксели, в т.ч. noscript-фолбэки);
     * всем помеченным проставляется data-nc-consent="<категория>". Рантайм
     * активирует их при согласии. Для неблокируемых категорий код не меняется.
     *
     * @param string $html     HTML-код сниппета
     * @param string $category
     * @return string
     */
    public static function wrap_inert($html, $category) {
        if ($html === '' || !self::is_blockable($category)) {
            return $html;
        }

        $cat = htmlspecialchars($category, ENT_QUOTES);

        // <script> — переводим в type="text/plain" (браузер не исполняет).
        $html = preg_replace_callback(
            '/<script\b([^>]*)>/i',
            function ($m) use ($cat) {
                // Убираем существующий type="...", чтобы заменить на text/plain.
                $attrs = preg_replace('/\s+type\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $m[1]);

                return '<script type="text/plain" data-nc-consent="' . $cat . '"' . $attrs . '>';
            },
            $html
        );

        // <img>/<iframe> — переименовываем src в data-nc-src (браузер не грузит),
        // рантайм восстанавливает после согласия. Закрывает трекинг-пиксели,
        // включая noscript-фолбэки счётчиков (для no-JS — без согласия не грузятся).
        $html = preg_replace_callback(
            '/<(img|iframe)\b([^>]*)>/i',
            function ($m) use ($cat) {
                $attrs = preg_replace('/(\s)src(\s*=)/i', '${1}data-nc-src${2}', $m[2]);

                return '<' . strtolower($m[1]) . ' data-nc-consent="' . $cat . '"' . $attrs . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * Реестр известных сторонних провайдеров (карты/видео и т.п.), которых
     * нужно гейтить независимо от того, как они попали на страницу — штатный
     * виджет, кастомный компонент конструктора или вставка в WYSIWYG-контенте.
     * `pattern` ищется в src тега ИЛИ в теле инлайн-<script> (см. autodetect_wrap).
     *
     * Источник — таблица `ExternalScripts_Provider` (модуль «Внешние скрипты»,
     * экран «Авто-блокировка карт/видео»: добавить/редактировать/удалить/
     * выключить). Мягкая зависимость — нет таблицы (модуль не устанавливался/
     * старая БД до патча) → пустой реестр, автодетект просто ничего не находит.
     * Кэш на запрос — autodetect_wrap зовёт providers() на каждый совпавший тег.
     *
     * @return array<int, array{pattern: string, category: string, label: string}>
     */
    public static function providers() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = array();
        $db = nc_core::get_object()->db;
        if (!$db->get_var("SHOW TABLES LIKE 'ExternalScripts_Provider'")) {
            return $cache;
        }

        $rows = $db->get_results(
            'SELECT `Label`, `Pattern`, `IsRegex`, `Category` FROM `ExternalScripts_Provider`'
            . ' WHERE `Enabled` = 1 ORDER BY `Priority` ASC, `Provider_ID` ASC',
            ARRAY_A
        );
        if (!$rows) {
            return $cache;
        }

        foreach ($rows as $row) {
            if ($row['Pattern'] === '') {
                continue;
            }
            // IsRegex=0 — Pattern это домен/подстрока, экранируем под regex;
            // IsRegex=1 — Pattern уже тело regex (без ограничителей), задаётся
            // в админке для продвинутых случаев (проверяется при сохранении).
            $body = $row['IsRegex'] ? $row['Pattern'] : preg_quote($row['Pattern'], '#');
            $cache[] = array(
                'pattern' => '#' . $body . '#i',
                'category' => $row['Category'],
                'label' => $row['Label'],
            );
        }

        return $cache;
    }

    /**
     * Провайдер (категория + подпись), под который подпадает строка (src тега
     * или тело инлайн-скрипта), либо null, если совпадений нет.
     *
     * @param string $haystack
     * @return array{category: string, label: string}|null
     */
    protected static function match_provider($haystack) {
        if ($haystack === '') {
            return null;
        }
        foreach (self::providers() as $provider) {
            if (preg_match($provider['pattern'], $haystack)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * HTML плейсхолдера вместо блокированного виджета: текст + кнопка
     * «Изменить настройки cookie» (переоткрывает баннер согласия, см.
     * nc-consent.js/nc-consent-banner.js). Кнопка — классы баннера, чтобы
     * подхватить тот же скин/фолбэк под тему.
     *
     * @param string $label
     * @return string
     */
    protected static function placeholder_body($label) {
        return '<div class="nc-consent-placeholder__body">'
            . '<p class="nc-consent-placeholder__text">Контент «' . htmlspecialchars($label, ENT_QUOTES)
            . '» не загружен, пока вы не разрешите соответствующие cookie.</p>'
            . '<button type="button" class="nc-consent-placeholder__btn tpl-button tpl-button-secondary nc-consent__btn">'
            . 'Изменить настройки cookie</button>'
            . '</div>';
    }

    /**
     * Превращает пустой контейнер-див (напр. <div class="map-container">, куда
     * виджет потом руками рисует карту через JS-SDK) в ХОСТА плейсхолдера —
     * вместо отдельного блока рядом со скриптом (как для прочих скриптов)
     * текст+кнопка рисуются ПРЯМО ВНУТРИ этого дива. Это сохраняет его
     * исходные атрибуты (id, классы, инлайн-стили) на месте — критично, когда
     * позиция блока на странице задаётся CSS (flex order, grid-area и т.п.)
     * по этому самому классу/id: отдельный плейсхолдер-сосед эти правила не
     * подхватил бы и оказался бы в другом месте страницы (см. карты на
     * /kontakty/, где контейнер карты стоит ДО списка адресов, а сам
     * провоцирующий блокировку <script> — после). Атрибут
     * data-nc-consent-reuse="1" — маркер для рантайма: при активации див нужно
     * не удалять целиком (как синтетический wrapper для прочих скриптов), а
     * только вычистить плейсхолдер-разметку и вернуть как было.
     *
     * @param string $container_html весь тег вида "<div ...></div>" (пустой)
     * @param string $category
     * @param string $label
     * @return string
     */
    protected static function mark_as_placeholder_host($container_html, $category, $label) {
        $cat = htmlspecialchars($category, ENT_QUOTES);

        return preg_replace_callback(
            '/^(<div\b[^>]*)(>)\s*(<\/div>)$/i',
            function ($m) use ($cat, $label) {
                $attrs = $m[1];
                if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs)) {
                    $attrs = preg_replace('/\bclass\s*=\s*"([^"]*)"/i', 'class="$1 nc-consent-placeholder"', $attrs, 1);
                } else {
                    $attrs .= ' class="nc-consent-placeholder"';
                }

                return $attrs . ' data-nc-consent-placeholder="' . $cat . '" data-nc-consent-reuse="1"' . $m[2]
                    . self::placeholder_body($label) . $m[3];
            },
            $container_html,
            1
        );
    }

    /**
     * Значение атрибута тега по имени (из строки атрибутов без самого тега).
     *
     * @param string $attrs
     * @param string $name
     * @return string
     */
    protected static function extract_attr($attrs, $name) {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|(\S+))/i', $attrs, $m)) {
            return $m[2] !== '' ? $m[2] : (isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[4]) ? $m[4] : ''));
        }

        return '';
    }

    /**
     * Блокировка <script src="..."> и инлайн <script>...</script>, попавших
     * под providers() (по src либо по телу — нужно для виджетов, которые
     * подгружают сторонний SDK динамически через document.createElement
     * внутри уже исполняющегося инлайн-скрипта, напр. Яндекс.Карты).
     *
     * Для виджетов карт/видео, рисующих контент JS-SDK'ом в заранее заданный
     * <div class="...map-container...">, плейсхолдер рисуется ПРЯМО ВНУТРИ
     * этого дива (см. mark_as_placeholder_host()) — а не отдельным блоком
     * рядом со <script>: между контейнером и скриптом, который его блокирует,
     * на странице может быть произвольная вёрстка (список адресов и т.п. —
     * см. /kontakty/), так что искать просто "пустой див прямо перед
     * скриптом" ненадёжно. Вместо этого ищем БЛИЖАЙШИЙ ПРЕДШЕСТВУЮЩИЙ пустой
     * div с классом, содержащим "map-container" (общая конвенция между
     * штатными виджетами карт netcat_contact_maps/yandex и
     * netcat_contact_office — оба используют этот класс для стилизации
     * области карты), и не занятый другим скриптом. Если такого контейнера
     * нет — как раньше, отдельный сосед-плейсхолдер перед скриптом.
     *
     * @param string $html
     * @return string
     */
    protected static function autodetect_wrap_scripts($html) {
        preg_match_all(
            '/<div\b[^>]*\bclass\s*=\s*"[^"]*\bmap-container\b[^"]*"[^>]*>\s*<\/div>/i',
            $html,
            $container_m,
            PREG_OFFSET_CAPTURE
        );
        $containers = $container_m[0];

        preg_match_all(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            $html,
            $script_m,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        $replacements = array(); // offset => array(length, text)
        $used = array();         // индексы уже занятых контейнеров

        foreach ($script_m as $sm) {
            list($full, $full_offset) = $sm[0];
            $attrs = $sm[1][0];
            $body = $sm[2][0];

            if (stripos($attrs, 'data-nc-consent') !== false) {
                continue;
            }
            $src = self::extract_attr($attrs, 'src');
            $provider = self::match_provider($src !== '' ? $src : $body);
            if ($provider === null || !self::is_blockable($provider['category'])) {
                continue;
            }

            $best_idx = null;
            foreach ($containers as $idx => $c) {
                if (isset($used[$idx]) || $c[1] >= $full_offset) {
                    continue;
                }
                if ($best_idx === null || $c[1] > $containers[$best_idx][1]) {
                    $best_idx = $idx;
                }
            }

            $inert_script = self::wrap_inert($full, $provider['category']);

            if ($best_idx !== null) {
                $used[$best_idx] = true;
                list($container_text, $container_offset) = $containers[$best_idx];
                $host = self::mark_as_placeholder_host($container_text, $provider['category'], $provider['label']);
                $replacements[$container_offset] = array(strlen($container_text), $host);
                $replacements[$full_offset] = array(strlen($full), $inert_script);
            } else {
                $cat = htmlspecialchars($provider['category'], ENT_QUOTES);
                $placeholder = '<div class="nc-consent-placeholder" data-nc-consent-placeholder="' . $cat . '">'
                    . self::placeholder_body($provider['label']) . '</div>';
                $replacements[$full_offset] = array(strlen($full), $placeholder . $inert_script);
            }
        }

        krsort($replacements);
        foreach ($replacements as $offset => $r) {
            $html = substr_replace($html, $r[1], $offset, $r[0]);
        }

        return $html;
    }

    /**
     * Автоматическая блокировка известных провайдеров (carт/видео) по их
     * домену — без участия конкретного компонента/виджета. Ищет совпадения
     * с providers() в src <iframe>/<img>/<script> и в теле инлайн-<script>
     * (нужно для виджетов, которые подгружают сторонний SDK динамически,
     * напр. Яндекс.Карты создают <script src="api-maps.yandex.ru..."> через
     * document.createElement внутри уже исполняющегося инлайн-скрипта — тогда
     * блокируется весь инлайн-блок целиком).
     *
     * Уже обработанные теги (с data-nc-consent) пропускаются — идемпотентно.
     * Гейтинг согласия не проверяется здесь (как и в processor.php — рантайм
     * сам активирует при наличии согласия в cookie), проверяется только режим.
     *
     * @param string $html
     * @param int|null $site_id
     * @return string
     */
    public static function autodetect_wrap($html, $site_id = null) {
        if ($html === '' || !self::is_gating_enabled($site_id)) {
            return $html;
        }

        $html = self::autodetect_wrap_scripts($html);

        // <iframe ...>...</iframe> — парный тег: захватываем вместе с
        // закрывающим, иначе закрывающий </div> плейсхолдера окажется ВНУТРИ
        // незакрытого <iframe> (невалидный HTML).
        $wrap_pair_tag = function ($full, $attrs, $body, $closing) {
            $src = self::extract_attr($attrs, 'src');
            $provider = self::match_provider($src);
            if ($provider === null || !self::is_blockable($provider['category'])) {
                return $full;
            }

            $cat = htmlspecialchars($provider['category'], ENT_QUOTES);

            return '<div class="nc-consent-placeholder" data-nc-consent-placeholder="' . $cat . '">'
                . self::placeholder_body($provider['label'])
                . self::wrap_inert('<iframe' . $attrs . '>', $provider['category']) . $body . $closing
                . '</div>';
        };

        $html = preg_replace_callback(
            '/<iframe\b([^>]*)>(.*?)(<\/iframe>)/is',
            function ($m) use ($wrap_pair_tag) {
                if (stripos($m[1], 'data-nc-consent') !== false) {
                    return $m[0];
                }

                return $wrap_pair_tag($m[0], $m[1], $m[2], $m[3]);
            },
            $html
        );

        // <img src="..."> — самозакрывающийся (void), закрывающего тега нет.
        $html = preg_replace_callback(
            '/<img\b[^>]*>/i',
            function ($m) {
                $full = $m[0];
                if (stripos($full, 'data-nc-consent') !== false) {
                    return $full;
                }
                $src = self::extract_attr($full, 'src');
                $provider = self::match_provider($src);
                if ($provider === null || !self::is_blockable($provider['category'])) {
                    return $full;
                }

                $cat = htmlspecialchars($provider['category'], ENT_QUOTES);

                return '<div class="nc-consent-placeholder" data-nc-consent-placeholder="' . $cat . '">'
                    . self::placeholder_body($provider['label'])
                    . self::wrap_inert($full, $provider['category'])
                    . '</div>';
            },
            $html
        );

        return $html;
    }

    /**
     * Тег подключения фронтового рантайма. Гарантированно отдаётся один раз за
     * запрос (защита от дублей при нескольких потребителях) — повторные вызовы
     * возвращают пустую строку.
     *
     * При включённом журналировании выбора перед рантаймом выводится
     * window.ncConsentLog с адресом точки приёма — так журнал работает и с
     * виджетом, и с сервис-компонентом без правки их шаблонов.
     *
     * @return string
     */
    public static function runtime_script_tag() {
        if (!empty($GLOBALS['nc_consent_runtime_included'])) {
            return '';
        }
        $GLOBALS['nc_consent_runtime_included'] = true;

        $nc_core = nc_core::get_object();
        $src = $nc_core->SUB_FOLDER . $nc_core->HTTP_TEMPLATE_PATH . 'asset/consent/nc-consent.js';

        $log_config = '';
        if (self::is_log_enabled()) {
            $log_config = '<script>window.ncConsentLog = '
                . nc_array_json(array('url' => nc_module_path('logging') . 'cookie_consent.php'))
                . ';</script>';
        }

        return $log_config . '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>';
    }
}
