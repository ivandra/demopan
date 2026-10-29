<?php

/**
 * Журнал согласий 152-ФЗ — таблица ConsentLog.
 *
 * Append-only фасад: только INSERT (UPDATE/DELETE не делаем). Метод не бросает
 * исключений и не прерывает пользовательский сценарий — при недоступности БД/таблицы
 * пишет error_log() и health-сигнал, возвращая пустой результат.
 *
 * Публичный API для стандартных компонентов (заявка, заказ, регистрация) и любых
 * кастомных форм. Текст галочки (label_html) формирует и регенерирует на сервере
 * вызывающий контроллер — из POST берётся только факт отметки, не текст.
 *
 * @see docs/proposals/152fz_consent_log.md
 */
class nc_consent_log {

    const TABLE = 'ConsentLog';

    /**
     * Зарегистрировать факт(ы) согласия/отзыва.
     *
     * @param array $common   Общие поля события:
     *      site_id, user_id, name, email, phone, source_table, source_object_id,
     *      source_infoblock_id, source_url (по умолч. REQUEST_URI),
     *      ip / user_agent (по умолч. из $_SERVER), created (по умолч. now).
     * @param array $consents Массив согласий, каждый элемент:
     *      ['label_html' => '...', 'type' => 'pd'|'rules'|..., 'action' => 'grant'|'withdraw'].
     *      Каждый элемент → отдельная запись.
     * @return int[] Массив ConsentLog_ID (пустой при ошибке/недоступности).
     */
    public static function log_consent(array $common, array $consents) {
        if (!$consents) {
            return array();
        }

        $nc_core = nc_core::get_object();
        $db = $nc_core->db;

        // мягкая зависимость: таблица должна существовать
        if (!$db->get_var("SHOW TABLES LIKE '" . self::TABLE . "'")) {
            return array();
        }

        $server = $_SERVER;

        $site_id = isset($common['site_id']) && $common['site_id']
            ? (int)$common['site_id']
            : (int)$nc_core->catalogue->get_current('Catalogue_ID');

        $user_id  = !empty($common['user_id']) ? (int)$common['user_id'] : null;
        $name     = isset($common['name']) && trim((string)$common['name']) !== '' ? self::cut(trim((string)$common['name']), 255) : null;
        // имя не передано, но субъект — зарегистрированный пользователь: берём User.Name
        // (централизованно, чтобы вызывающим не дублировать SELECT)
        if ($name === null && $user_id) {
            $u = trim((string)$db->get_var("SELECT `Name` FROM `User` WHERE `User_ID` = " . $user_id));
            if ($u !== '') {
                $name = self::cut($u, 255);
            }
        }
        $email    = isset($common['email']) && $common['email'] !== '' ? self::cut($common['email'], 255) : null;
        $phone    = isset($common['phone']) && $common['phone'] !== '' ? self::cut($common['phone'], 32) : null;

        $source_table        = isset($common['source_table']) && $common['source_table'] !== '' ? self::cut($common['source_table'], 64) : null;
        $source_object_id    = !empty($common['source_object_id']) ? (int)$common['source_object_id'] : null;
        $source_infoblock_id = !empty($common['source_infoblock_id']) ? (int)$common['source_infoblock_id'] : null;

        $source_url = isset($common['source_url']) ? (string)$common['source_url'] : (string)nc_array_value($server, 'REQUEST_URI');
        $source_url = self::cut($source_url, 500);

        $ip = isset($common['ip']) ? (string)$common['ip'] : (string)nc_array_value($server, 'REMOTE_ADDR');
        $ip = self::cut($ip, 45);

        $user_agent = isset($common['user_agent']) ? (string)$common['user_agent'] : (string)nc_array_value($server, 'HTTP_USER_AGENT');
        $user_agent = self::cut($user_agent, 512);

        $created = !empty($common['created']) ? (string)$common['created'] : date('Y-m-d H:i:s');

        $subject_type = $user_id ? 'user' : 'anonymous';

        $ids = array();
        foreach ($consents as $consent) {
            $label = isset($consent['label_html']) ? trim((string)$consent['label_html']) : '';
            if ($label === '') {
                continue; // без текста согласия запись не имеет смысла
            }
            $type   = isset($consent['type']) && $consent['type'] !== '' ? self::cut($consent['type'], 32) : null;
            $action = (isset($consent['action']) && $consent['action'] === 'withdraw') ? 'withdraw' : 'grant';

            $set =
                "`Catalogue_ID` = " . $site_id . ", " .
                "`Subject_Type` = '" . $subject_type . "', " .
                "`User_ID` = " . ($user_id === null ? 'NULL' : $user_id) . ", " .
                "`Name` = " . self::sql_str($db, $name) . ", " .
                "`Email` = " . self::sql_str($db, $email) . ", " .
                "`Phone` = " . self::sql_str($db, $phone) . ", " .
                "`Consent_Label` = '" . $db->escape($label) . "', " .
                "`Consent_Type` = " . self::sql_str($db, $type) . ", " .
                "`Action` = '" . $action . "', " .
                "`Source_Table` = " . self::sql_str($db, $source_table) . ", " .
                "`Source_Object_ID` = " . ($source_object_id === null ? 'NULL' : $source_object_id) . ", " .
                "`Source_Infoblock_ID` = " . ($source_infoblock_id === null ? 'NULL' : $source_infoblock_id) . ", " .
                "`Source_URL` = '" . $db->escape($source_url) . "', " .
                "`IP` = '" . $db->escape($ip) . "', " .
                "`User_Agent` = '" . $db->escape($user_agent) . "', " .
                "`Created` = '" . $db->escape($created) . "'";

            $db->query("INSERT INTO `" . self::TABLE . "` SET " . $set);

            if ($db->is_error) {
                self::register_error('INSERT failed (errno ' . $db->errno . ')');
                continue; // не прерываем — пробуем остальные согласия
            }

            $new_id = (int)$db->insert_id;
            if ($new_id) {
                $ids[] = $new_id;
            }
        }

        if ($ids) {
            self::register_success();
        }

        return $ids;
    }

    /**
     * Строгий allowlist-санитайзер текста согласия для вывода в админке (анти stored-XSS).
     * Оставляет только a/b/i/strong/span/br, срезает обработчики событий on*= и
     * javascript:-ссылки. Текст обычно из настроек/шаблона, но кастомная форма
     * может подсунуть произвольный HTML — поэтому чистим на выводе.
     *
     * @param string $html
     * @return string
     */
    public static function sanitize_label($html) {
        $html = strip_tags((string)$html, '<a><b><i><strong><span><br>');
        // обработчики событий on...="..." / on...='...' / on...=value
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        // javascript:-ссылки в href
        $html = preg_replace('/(href\s*=\s*)("|\')?\s*javascript:[^"\'>\s]*/i', '$1$2#', $html);
        return $html;
    }

    /**
     * Подставляет ?docdate=ГГГГ-ММ-ДД во все ссылки внутри текста согласия — чтобы из
     * админки открыть документ в редакции, действовавшей на момент согласия (если в
     * разделе стоит компонент «Документ с временными версиями»). Ничего не угадываем:
     * просто дописываем параметр в любые href, какие есть.
     *
     * @param string $html  уже санитизированный HTML
     * @param string $date  дата в формате ГГГГ-ММ-ДД
     * @return string
     */
    public static function add_date_to_links($html, $date) {
        $date = preg_replace('/[^0-9-]/', '', (string)$date);
        if ($date === '') {
            return $html;
        }
        return preg_replace_callback(
            '/href\s*=\s*"([^"]*)"/i',
            function ($m) use ($date) {
                $url = $m[1];
                if ($url === '' || $url[0] === '#' || stripos($url, 'javascript:') === 0 || stripos($url, 'mailto:') === 0) {
                    return $m[0];
                }
                $glue = strpos($url, '?') !== false ? '&' : '?';
                // не дублируем docdate= (имя совпадает с GET-параметром docver-компонента,
                // НЕ `date` — иначе сработал бы штатный date-архив списка объектов netcat)
                if (preg_match('/[?&]docdate=/', $url)) {
                    return $m[0];
                }
                return 'href="' . $url . $glue . 'docdate=' . $date . '"';
            },
            $html
        );
    }

    /**
     * Усечь строку до $max символов (UTF-8 безопасно).
     */
    protected static function cut($value, $max) {
        return mb_substr((string)$value, 0, $max);
    }

    /**
     * NULL или экранированный строковый литерал для SQL.
     */
    protected static function sql_str($db, $value) {
        return $value === null ? 'NULL' : "'" . $db->escape($value) . "'";
    }

    /**
     * Health-сигнал: момент последней успешной записи.
     */
    protected static function register_success() {
        $nc_core = nc_core::get_object();
        $nc_core->set_settings('consent_log_last_success', date('Y-m-d H:i:s'), 'logging', 0);
    }

    /**
     * Health-сигнал об ошибке записи: error_log() + счётчик/метка в настройках модуля.
     * Сам по себе не критичен — на исключения health-механизма не реагируем.
     */
    protected static function register_error($message) {
        error_log('nc_consent_log: ' . $message);
        $nc_core = nc_core::get_object();
        $count = (int)$nc_core->get_settings('consent_log_error_count', 'logging') + 1;
        $nc_core->set_settings('consent_log_error_count', $count, 'logging', 0);
        $nc_core->set_settings('consent_log_last_error', date('Y-m-d H:i:s') . ' ' . self::cut($message, 200), 'logging', 0);
    }
}
