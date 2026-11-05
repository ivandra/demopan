<?php

/**
 * Журнал выбора в окне cookie-согласия — таблицы CookieConsentLog (записи),
 * CookieConsentText (справочник версий текста уведомления) и CookieConsentRate
 * (счётчики лимитов).
 *
 * Append-only: только INSERT и удаление по сроку хранения (cron.php модуля,
 * раз в сутки) или вручную из админки. Не бросает исключений. Включается настройкой сайта (nc_consent::is_log_enabled()),
 * данные приходят из фронтового рантайма nc-consent.js через точку приёма
 * cookie_consent.php.
 *
 * @see docs/proposals/cookie-consent-log.md
 */
class nc_cookie_consent_log {

    const TABLE = 'CookieConsentLog';
    const TEXT_TABLE = 'CookieConsentText';
    const RATE_TABLE = 'CookieConsentRate';

    const DECISION_ACCEPT  = 'accept';    // выданы все необязательные категории
    const DECISION_REJECT  = 'reject';    // только necessary
    const DECISION_PARTIAL = 'partial';   // часть категорий

    /**
     * Защита от массовой отправки. Отличить поддельную запись от настоящей
     * анонимная точка приёма не может — ограничиваем количество и объём:
     * лимит в минуту с одного адреса (IPv6 — с подсети /64), лимиты в минуту
     * и в сутки на сайт (значения — настройки сайта, nc_consent::log_limit()),
     * в справочник попадают только тексты, отрисованные сервером (подпись
     * окна или совпадение с настройками сайта).
     *
     * Счётчики — строки CookieConsentRate, увеличиваются атомарно
     * (INSERT … ON DUPLICATE KEY UPDATE), так что параллельные запросы не
     * проходят лимит «все разом». Служебные ключи начинаются с '#' — адрес
     * так начинаться не может. Period — номер минуты от начала эпохи; для
     * суточных счётчиков — номер минуты начала суток (время сервера).
     */
    const RATE_KEY_SITE_MINUTE = '#site';     // записей в минуту на сайт
    const RATE_KEY_SITE_DAY    = '#day';      // записей в сутки на сайт
    const RATE_KEY_DROPPED     = '#dropped';  // отброшено по лимитам за сутки
    /** Сколько хранить счётчики: минутные — 1 час, суточные — 3 суток (для «вчера» в админке). */
    const RATE_MINUTE_KEEP = 60;
    const RATE_DAY_KEEP = 4320;

    /** Настройки модуля (per-site): время последнего срабатывания лимита в минуту / в сутки на сайт. */
    const SETTING_OVERFLOW = 'cookie_consent_log_overflow';
    const SETTING_OVERFLOW_DAY = 'cookie_consent_log_overflow_day';
    /** Тот же ID и те же категории в течение N секунд — повтор, не пишем. */
    const DEDUP_SECONDS = 10;
    /** Удаление по сроку хранения — порциями по N строк. */
    const PURGE_BATCH = 10000;
    /** Сколько секунд очистка может работать за один запуск (остальное — в следующий). */
    const PURGE_TIME_BUDGET = 50;
    /** Максимальная длина текста уведомления; длиннее — запись без текста. */
    const TEXT_MAX_LENGTH = 5000;

    /** Коды результата log_choice() — они же HTTP-статусы точки приёма. */
    const RESULT_OK = 204;
    const RESULT_INVALID = 400;
    const RESULT_DISABLED = 404;
    const RESULT_RATE_LIMITED = 429;
    const RESULT_ERROR = 500;

    /**
     * Записать выбор посетителя.
     *
     * @param array $data  uid, categories (CSV), mode, text, sig (подпись текста), url;
     *                     site_id / ip / user_agent / created — по умолчанию из окружения.
     * @return int один из RESULT_*
     */
    public static function log_choice(array $data) {
        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        $site_id = !empty($data['site_id']) ? (int)$data['site_id'] : (int)$nc_core->catalogue->id();
        if (!$site_id || !nc_consent::is_log_enabled($site_id) || !self::tables_exist()) {
            return self::RESULT_DISABLED;
        }

        $uid = strtolower(trim((string)nc_array_value($data, 'uid')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uid)) {
            return self::RESULT_INVALID;
        }

        $categories = self::normalize_categories((string)nc_array_value($data, 'categories'));
        if ($categories === null) {
            return self::RESULT_INVALID;
        }

        $mode = (string)nc_array_value($data, 'mode');
        if (!in_array($mode, array(nc_consent::MODE_ACCEPT_ALL, nc_consent::MODE_CATEGORIES), true)) {
            return self::RESULT_INVALID;
        }

        // текст длиннее допустимого не отменяет запись — пишем её без текста
        $text = trim((string)nc_array_value($data, 'text'));
        if (mb_strlen($text) > self::TEXT_MAX_LENGTH) {
            $text = '';
        }

        $server = $_SERVER;
        $ip = self::cut(isset($data['ip']) ? $data['ip'] : nc_array_value($server, 'REMOTE_ADDR'), 45);
        $ip_key = self::ip_key($ip);
        $user_agent = self::cut(isset($data['user_agent']) ? $data['user_agent'] : nc_array_value($server, 'HTTP_USER_AGENT'), 256);
        $page = self::page_path((string)nc_array_value($data, 'url'));
        $now = !empty($data['created']) ? strtotime((string)$data['created']) : false;
        if (!$now) {
            $now = time();
        }
        $created = date('Y-m-d H:i:s', $now);

        if (!self::check_limits($site_id, $ip_key, $now)) {
            return self::RESULT_RATE_LIMITED;
        }

        $categories_csv = implode(',', $categories);

        // повтор того же выбора (двойной клик, повторная отправка) — считаем записанным
        $duplicate = $db->get_var(
            "SELECT `CookieConsentLog_ID` FROM `" . self::TABLE . "`
              WHERE `Consent_UID` = '" . $uid . "'
                AND `Catalogue_ID` = " . $site_id . "
                AND `Categories` = '" . $db->escape($categories_csv) . "'
                AND `Created` > '" . date('Y-m-d H:i:s', $now - self::DEDUP_SECONDS) . "'
              LIMIT 1"
        );
        if ($duplicate) {
            return self::RESULT_OK;
        }

        $text_hash = self::resolve_text_hash($text, (string)nc_array_value($data, 'sig'), $site_id, $created);

        $db->query(
            "INSERT INTO `" . self::TABLE . "` SET
                `Catalogue_ID` = " . $site_id . ",
                `Consent_UID` = '" . $uid . "',
                `Decision` = '" . self::decision($categories) . "',
                `Categories` = '" . $db->escape($categories_csv) . "',
                `Mode` = '" . $db->escape($mode) . "',
                `Text_Hash` = " . ($text_hash === null ? 'NULL' : "'" . $text_hash . "'") . ",
                `Page_URL` = '" . $db->escape($page) . "',
                `IP` = '" . $db->escape($ip) . "',
                `IP_Key` = '" . $db->escape($ip_key) . "',
                `User_Agent` = '" . $db->escape($user_agent) . "',
                `Created` = '" . $db->escape($created) . "'"
        );
        if ($db->is_error || !$db->insert_id) {
            error_log('nc_cookie_consent_log: INSERT failed (errno ' . $db->errno . ')');
            return self::RESULT_ERROR;
        }

        // очистка по сроку хранения — не здесь, а по расписанию (cron.php модуля)
        return self::RESULT_OK;
    }

    /**
     * Проверить лимиты и учесть запрос в счётчиках. Порядок: адрес → сайт в
     * минуту → сайт в сутки; запрос, отклонённый раньше, в следующие счётчики
     * не попадает. Отклонённые считаются в RATE_KEY_DROPPED.
     *
     * @param int $site_id
     * @param string $ip_key
     * @param int $now unix time
     * @return bool true — можно писать
     */
    protected static function check_limits($site_id, $ip_key, $now) {
        $minute = self::minute_period($now);
        $day = self::day_period($now);

        $rejected_by = null;
        if (self::hit($site_id, $ip_key, $minute) > nc_consent::log_limit(nc_consent::SETTING_LOG_LIMIT_IP, $site_id)) {
            $rejected_by = nc_consent::SETTING_LOG_LIMIT_IP;
        } elseif (self::hit($site_id, self::RATE_KEY_SITE_MINUTE, $minute) > nc_consent::log_limit(nc_consent::SETTING_LOG_LIMIT_SITE, $site_id)) {
            $rejected_by = nc_consent::SETTING_LOG_LIMIT_SITE;
            self::register_overflow($site_id, self::SETTING_OVERFLOW, $now);
        } elseif (self::hit($site_id, self::RATE_KEY_SITE_DAY, $day) > nc_consent::log_limit(nc_consent::SETTING_LOG_LIMIT_DAY, $site_id)) {
            $rejected_by = nc_consent::SETTING_LOG_LIMIT_DAY;
            self::register_overflow($site_id, self::SETTING_OVERFLOW_DAY, $now);
        }

        if ($rejected_by === null) {
            return true;
        }
        self::hit($site_id, self::RATE_KEY_DROPPED, $day);

        return false;
    }

    /**
     * Атомарно увеличить счётчик и вернуть его новое значение. LAST_INSERT_ID(expr)
     * хранится в соединении — значение ровно то, что записал этот запрос, а не
     * прочитанное позже (после инкрементов параллельных запросов).
     *
     * При ошибке БД возвращает 0 (запрос не ограничивается): таблица проверена
     * заранее (tables_exist()), а сбой БД помешает и самой записи.
     *
     * @param int $site_id
     * @param string $key адрес (ip_key()) или RATE_KEY_*
     * @param int $period
     * @return int
     */
    protected static function hit($site_id, $key, $period) {
        $db = nc_core::get_object()->db;
        $db->query(
            "INSERT INTO `" . self::RATE_TABLE . "` (`Catalogue_ID`, `IP_Key`, `Period`, `Hits`)
             VALUES (" . (int)$site_id . ", '" . $db->escape((string)$key) . "', " . (int)$period . ", LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE `Hits` = LAST_INSERT_ID(`Hits` + 1)"
        );
        if ($db->is_error) {
            error_log('nc_cookie_consent_log: rate counter failed (errno ' . $db->errno . ')');
            return 0;
        }

        return (int)$db->get_var("SELECT LAST_INSERT_ID()");
    }

    /**
     * Значение счётчика без изменения (0 — нет строки).
     *
     * @param int $site_id
     * @param string $key
     * @param int $period
     * @return int
     */
    public static function get_counter($site_id, $key, $period) {
        if (!self::tables_exist()) {
            return 0;
        }
        $db = nc_core::get_object()->db;

        return (int)$db->get_var(
            "SELECT `Hits` FROM `" . self::RATE_TABLE . "`
              WHERE `Catalogue_ID` = " . (int)$site_id . "
                AND `IP_Key` = '" . $db->escape((string)$key) . "'
                AND `Period` = " . (int)$period
        );
    }

    /**
     * Отброшено по лимитам за сутки, в которые входит момент $time.
     *
     * @param int $site_id
     * @param int|null $time
     * @return int
     */
    public static function get_dropped($site_id, $time = null) {
        return self::get_counter($site_id, self::RATE_KEY_DROPPED, self::day_period($time === null ? time() : $time));
    }

    /**
     * Номер минуты (Period минутных счётчиков).
     * @param int $time
     * @return int
     */
    public static function minute_period($time) {
        return (int)floor($time / 60);
    }

    /**
     * Номер минуты начала суток по времени сервера (Period суточных счётчиков).
     * @param int $time
     * @return int
     */
    public static function day_period($time) {
        return self::minute_period(mktime(0, 0, 0, (int)date('n', $time), (int)date('j', $time), (int)date('Y', $time)));
    }

    /**
     * Удалить записи сайта старше $days дней порциями (DELETE … LIMIT в цикле).
     *
     * @param int $site_id 0 — все сайты
     * @param int|null $days null — все записи сайта (сайт удалён)
     * @param float|null $deadline microtime(true), после которого остановиться
     * @return array('deleted' => int, 'complete' => bool)
     */
    public static function purge($site_id, $days, $deadline = null) {
        $result = array('deleted' => 0, 'complete' => true);
        if (!self::tables_exist()) {
            return $result;
        }
        $db = nc_core::get_object()->db;

        $where = array();
        if ($site_id) {
            $where[] = "`Catalogue_ID` = " . (int)$site_id;
        }
        if ($days !== null) {
            $where[] = "`Created` < '" . date('Y-m-d H:i:s', time() - (int)$days * 86400) . "'";
        }
        if (!$where) {
            return $result;
        }

        do {
            $affected = $db->query(
                "DELETE FROM `" . self::TABLE . "` WHERE " . implode(' AND ', $where) . " LIMIT " . self::PURGE_BATCH
            );
            if ($affected === false) {
                $result['complete'] = false;
                break;
            }
            $result['deleted'] += (int)$affected;
            if ($affected >= self::PURGE_BATCH && $deadline !== null && microtime(true) > $deadline) {
                $result['complete'] = false;
                break;
            }
        } while ($affected >= self::PURGE_BATCH);

        return $result;
    }

    /**
     * Очистка по расписанию (cron.php модуля, раз в сутки):
     * - по всем сайтам, у которых есть записи, — независимо от того, включён ли
     *   сейчас журнал: последний сохранённый срок хранения сайта (0 — не удалять);
     * - записи удалённых сайтов — целиком;
     * - устаревшие строки счётчиков лимитов.
     *
     * @param int $time_budget секунд
     * @return array('sites' => int, 'deleted' => int, 'rate_deleted' => int, 'complete' => bool)
     */
    public static function cleanup($time_budget = self::PURGE_TIME_BUDGET) {
        $result = array('sites' => 0, 'deleted' => 0, 'rate_deleted' => 0, 'complete' => true);
        if (!self::tables_exist()) {
            return $result;
        }
        $nc_core = nc_core::get_object();
        $db = $nc_core->db;
        $deadline = microtime(true) + $time_budget;

        $site_ids = array_map('intval', (array)$db->get_col("SELECT DISTINCT `Catalogue_ID` FROM `" . self::TABLE . "`"));
        $existing = array_map('intval', (array)$db->get_col("SELECT `Catalogue_ID` FROM `Catalogue`"));

        foreach ($site_ids as $site_id) {
            if (microtime(true) > $deadline) {
                $result['complete'] = false;
                break;
            }
            if (in_array($site_id, $existing, true)) {
                $days = nc_consent::log_ttl($site_id);
                if (!$days) {
                    continue; // «не удалять»
                }
            } else {
                $days = null; // сайт удалён
            }
            $purged = self::purge($site_id, $days, $deadline);
            $result['sites']++;
            $result['deleted'] += $purged['deleted'];
            if (!$purged['complete']) {
                $result['complete'] = false;
                break;
            }
        }

        $now_minute = self::minute_period(time());
        $rate_conditions = array(
            "`IP_Key` NOT LIKE '#%' AND `Period` < " . ($now_minute - self::RATE_MINUTE_KEEP),
            "`IP_Key` LIKE '#%' AND `Period` < " . ($now_minute - self::RATE_DAY_KEEP),
        );
        foreach ($rate_conditions as $condition) {
            do {
                $affected = $db->query("DELETE FROM `" . self::RATE_TABLE . "` WHERE " . $condition . " LIMIT " . self::PURGE_BATCH);
                $result['rate_deleted'] += (int)$affected;
            } while ($affected >= self::PURGE_BATCH && microtime(true) <= $deadline);
        }

        return $result;
    }

    /**
     * Решение по набору выданных категорий.
     *
     * @param string[] $categories
     * @return string один из DECISION_*
     */
    public static function decision(array $categories) {
        $gated = nc_consent::gated_categories();
        $granted = array_intersect($gated, $categories);
        if (!$granted) {
            return self::DECISION_REJECT;
        }

        return count($granted) === count($gated) ? self::DECISION_ACCEPT : self::DECISION_PARTIAL;
    }

    /**
     * Текст уведомления по хэшу (из справочника версий).
     *
     * @param string $hash
     * @return string|null
     */
    public static function get_text($hash) {
        if (!preg_match('/^[0-9a-f]{40}$/', (string)$hash)) {
            return null;
        }
        $text = nc_core::get_object()->db->get_var(
            "SELECT `Text` FROM `" . self::TEXT_TABLE . "` WHERE `Text_Hash` = '" . $hash . "'"
        );

        return $text === null ? null : (string)$text;
    }

    /**
     * Есть ли все таблицы журнала (патч мог быть ещё не применён).
     * @return bool
     */
    public static function tables_exist() {
        static $exists = null;
        if ($exists === null) {
            $tables = (array)nc_core::get_object()->db->get_col("SHOW TABLES LIKE 'CookieConsent%'");
            $exists = !array_diff(array(self::TABLE, self::TEXT_TABLE, self::RATE_TABLE), $tables);
        }

        return $exists;
    }

    /**
     * CSV категорий → нормализованный список (necessary всегда есть, порядок
     * как в nc_consent::categories()). Неизвестная категория → null.
     *
     * @param string $csv
     * @return string[]|null
     */
    protected static function normalize_categories($csv) {
        $items = array_filter(array_map('trim', explode(',', $csv)), 'strlen');
        $known = nc_consent::categories();
        if (array_diff($items, $known)) {
            return null;
        }
        $items[] = nc_consent::CATEGORY_NECESSARY;

        return array_values(array_intersect($known, $items));
    }

    /**
     * Время последнего срабатывания лимита на сайт ('' — не было).
     *
     * @param int $site_id
     * @param string $setting SETTING_OVERFLOW (в минуту) или SETTING_OVERFLOW_DAY (в сутки)
     * @return string Y-m-d H:i:s
     */
    public static function get_overflow($site_id, $setting = self::SETTING_OVERFLOW) {
        $value = nc_core::get_object()->get_settings($setting, 'logging', false, (int)$site_id);
        return is_string($value) ? $value : '';
    }

    /**
     * Отметить срабатывание лимита на сайт — не чаще раза в минуту, чтобы при
     * массовой отправке не писать настройку на каждый запрос.
     *
     * @param int $site_id
     * @param string $setting SETTING_OVERFLOW / SETTING_OVERFLOW_DAY
     * @param int $now
     */
    protected static function register_overflow($site_id, $setting, $now) {
        $last = self::get_overflow($site_id, $setting);
        if ($last === '' || strtotime($last) < $now - 60) {
            nc_core::get_object()->set_settings($setting, date('Y-m-d H:i:s', $now), 'logging', (int)$site_id);
        }
    }

    /**
     * Ключ адреса для лимита частоты: IPv4 — сам адрес, IPv6 — подсеть /64
     * (провайдер обычно выдаёт абоненту целую /64, лимит по точному адресу обходится).
     *
     * @param string $ip
     * @return string
     */
    protected static function ip_key($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = bin2hex(substr(inet_pton($ip), 0, 8));
            return implode(':', str_split($hex, 4)) . '::/64';
        }

        return $ip;
    }

    /**
     * Страница выбора — только путь, без параметров (метки, идентификаторы в
     * query не нужны журналу и раздувают запись).
     *
     * @param string $url
     * @return string
     */
    protected static function page_path($url) {
        $path = (string)parse_url($url, PHP_URL_PATH);
        // только путь от корня сайта: «//host» и «/\host» браузер понимает как
        // ссылку на другой хост — такие значения не храним
        if ($path === '' || $path[0] !== '/' || (isset($path[1]) && ($path[1] === '/' || $path[1] === '\\'))) {
            return '';
        }

        return self::cut($path, 255);
    }

    /**
     * Хэш версии текста уведомления или null. В справочник попадают только
     * тексты, которые отрисовал сервер: с верной подписью окна
     * (nc_consent::text_signature() — виджет, откуда бы ни пришёл текст) либо
     * совпавшие с текстами, настроенными на сайте (known_texts() — для окон без
     * подписи, напр. сервис-компонента). Уже известная версия принимается и
     * после смены текста — страницы из кеша ещё показывают старую.
     * Произвольный текст из запроса не сохраняется.
     *
     * @param string $text
     * @param string $signature
     * @param int $site_id
     * @param string $created
     * @return string|null
     */
    protected static function resolve_text_hash($text, $signature, $site_id, $created) {
        if ($text === '') {
            return null;
        }
        $db = nc_core::get_object()->db;
        $hash = sha1($text);
        if ($db->get_var("SELECT 1 FROM `" . self::TEXT_TABLE . "` WHERE `Text_Hash` = '" . $hash . "'")) {
            return $hash;
        }
        $expected = nc_consent::text_signature($text, $site_id);
        $signed = $expected !== '' && $signature !== '' && hash_equals($expected, $signature);
        if (!$signed && !in_array($text, self::known_texts($site_id), true)) {
            return null;
        }
        $db->query(
            "INSERT IGNORE INTO `" . self::TEXT_TABLE . "` SET
                `Text_Hash` = '" . $hash . "',
                `Text` = '" . $db->escape($text) . "',
                `Created` = '" . $db->escape($created) . "'"
        );

        return $hash;
    }

    /**
     * Тексты окна согласия, настроенные на сайте: тексты по умолчанию для
     * режимов, поле Text виджета netcat_cookie_consent (значение по умолчанию и
     * экземпляры) и настройка Text инфоблоков сервис-компонента
     * netcat_service_cookie_consent (включая .old-версии и шаблоны).
     *
     * Текст, переданный в виджет из кода макета
     * (widget->generate('netcat_cookie_consent', array('Text' => …))), здесь не
     * виден — его подтверждает подпись окна (см. resolve_text_hash()).
     *
     * @param int $site_id
     * @return string[]
     */
    protected static function known_texts($site_id) {
        static $cache = array();
        $site_id = (int)$site_id;
        if (isset($cache[$site_id])) {
            return $cache[$site_id];
        }

        $nc_core = nc_core::get_object();
        $db = $nc_core->db;
        $texts = array();

        foreach (array(nc_consent::MODE_ACCEPT_ALL, nc_consent::MODE_CATEGORIES, nc_consent::MODE_NOTICE) as $mode) {
            $default = nc_consent::default_banner_text($mode);
            $texts[] = $default;
            if (!$nc_core->NC_UNICODE) {
                $texts[] = $nc_core->utf8->utf2win($default);
            }
        }

        $widget_field_id = (int)$db->get_var(
            "SELECT f.`Field_ID`
               FROM `Field` AS f
               JOIN `Widget_Class` AS wc ON wc.`Widget_Class_ID` = f.`Widget_Class_ID`
              WHERE wc.`Keyword` = 'netcat_cookie_consent' AND f.`Field_Name` = 'Text'"
        );
        if ($widget_field_id) {
            $texts[] = (string)$db->get_var("SELECT `DefaultState` FROM `Field` WHERE `Field_ID` = " . $widget_field_id);
            $texts = array_merge($texts, (array)$db->get_col(
                "SELECT `Value` FROM `Widget_Field` WHERE `Field_ID` = " . $widget_field_id
            ));
        }

        $infoblock_ids = (array)$db->get_col(
            "SELECT sc.`Sub_Class_ID`
               FROM `Sub_Class` AS sc
               JOIN `Class` AS c ON c.`Class_ID` = sc.`Class_ID`
               LEFT JOIN `Class` AS parent ON parent.`Class_ID` = c.`ClassTemplate`
              WHERE sc.`Catalogue_ID` = " . $site_id . "
                AND (c.`Keyword` LIKE 'netcat\\_service\\_cookie\\_consent%'
                     OR parent.`Keyword` LIKE 'netcat\\_service\\_cookie\\_consent%')"
        );
        foreach ($infoblock_ids as $infoblock_id) {
            try {
                $settings = $nc_core->sub_class->get_by_id((int)$infoblock_id, 'Sub_Class_Settings');
            } catch (Exception $e) {
                continue;
            }
            $texts[] = (string)nc_array_value((array)$settings, 'Text');
        }

        $cache[$site_id] = array_values(array_unique(array_filter(array_map('trim', $texts), 'strlen')));

        return $cache[$site_id];
    }

    /**
     * Усечь строку до $max символов (UTF-8 безопасно).
     */
    protected static function cut($value, $max) {
        return mb_substr((string)$value, 0, $max);
    }
}
