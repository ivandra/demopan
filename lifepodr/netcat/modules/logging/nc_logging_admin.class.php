<?php

class nc_logging_admin {

    protected $db;
    protected $MODULE_VARS;
    protected $UI_CONFIG;

    public function __construct() {
        // system superior object
        $nc_core = nc_Core::get_object();

        // global variables
        global $UI_CONFIG;

        // global variables to internal
        $this->UI_CONFIG = $UI_CONFIG;

        // system db object
        if (is_object($nc_core->db)) $this->db = $nc_core->db;

        $this->MODULE_VARS = &$nc_core->modules->get_vars("logging");
    }

    /**
     *
     */
    public function show_logging_data($curPos = 0, $show_filter=true) {
        // system superior object
        $nc_core = nc_Core::get_object();

        // validate
        $curPos = (int)$curPos;
        $sort = $nc_core->input->fetch_get('sort');
        $sortDir = $nc_core->input->fetch_get('sortDir');
        $sortViewType = $nc_core->input->fetch_get('sortViewType');
        $sortViewEssence = $nc_core->input->fetch_get('sortViewEssence');
        $filterViewUserID = ($nc_core->input->fetch_get('filterViewUserID') != null ? (int)$nc_core->input->fetch_get('filterViewUserID') : null);
        $filterViewIP = $nc_core->input->fetch_get_text('filterViewIP');

        $filter_from_d = $nc_core->input->fetch_get_text('filter_from_d');
        $filter_from_m = $nc_core->input->fetch_get_text('filter_from_m');
        $filter_from_y = $nc_core->input->fetch_get_text('filter_from_y');
        $filter_to_d = $nc_core->input->fetch_get_text('filter_to_d');
        $filter_to_m = $nc_core->input->fetch_get_text('filter_to_m');
        $filter_to_y = $nc_core->input->fetch_get_text('filter_to_y');

        $current_url = $nc_core->url->get_parsed_url("path");

        $maxRows = $this->MODULE_VARS['ROWS_PER_PAGE'] ?: 25;
        $range = $this->MODULE_VARS['PAGES_NUMBER'] ?: 10;

        $allowedActions = array("add", "update", "drop", "check", "uncheck", "authorize");
        $allowedEssences = array("Catalogue", "Subdivision", "SubClass", "Class", "ClassTemplate", "Message", "SystemTable", "Template", "User", "Module", "Comment", "Field", "Classificator", "ClassificatorItem", "TemplatePartial", "ExternalScript", "ExternalScriptProvider");

        $allowedUser = (array)$this->db->get_results("SELECT l.`User_ID`, u.`".$nc_core->AUTHORIZE_BY."` AS Login
                                                FROM `Logging` AS l
                                                LEFT JOIN `User` AS u ON l.`User_ID` = u.`User_ID`
                                                WHERE l.`User_ID` > '0'
                                                GROUP BY l.`User_ID`
                                                ORDER BY Login", ARRAY_A);

        if ($show_filter)
        {
            if ($nc_core->modules->get_by_keyword('calendar', 0)) {
                echo nc_set_calendar(0);
            }

            echo "<div style='display:none; z-index:10000' id='nc_calendar_popup_filter_from_d'></div>\n".
            "<div style='display:none; z-index:10000' id='nc_calendar_popup_filter_to_d'></div>\n";

            echo "<form method='get' id='SortData' action='".$current_url."' style='padding:0; margin:0;'>\n".
            "<input type='hidden' name='phase' value='1'>\n".
            "<br /><fieldset>\n".
            "<legend>\n".
            "".NETCAT_MODULE_LOGGING_SHOW_PARAMETERS."\n".
            "</legend>\n".
            "<div style='margin:10px 0; _padding:0;'>\n";

            echo "<table class='nc-table nc--small' width='100%'>".
            "<col style='width:15%' /><col style='width:35%' /><col style='width:15%' /><col style='width:35%' />".
            "<tr>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_SHOW_SORT_BY."</td>".
            "<td>".
            "<select name='sort' style='width:100%'>
            <option value='date'".($sort == "date" || !$sort ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_SORT_BY_DATE."</option>
            <option value='event'".($sort == "event" ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_SORT_BY_EVENT."</option>
            <option value='user'".($sort == "user" ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_SORT_BY_USER."</option>
            </select>".
            "</td>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_SHOW_SORT_DIR."</td>".
            "<td >".
            "<select name='sortDir' style='width:100%'>
            <option value='desc'".($sortDir == "desc" || !$sortDir ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_SORT_DIR_DESC."</option>
            <option value='asc'".($sortDir == "asc" ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_SORT_DIR_ASC."</option>
            </select>".
            "</td>".
            "</tr>".
            "<tr>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_SHOW_ONLY_ACTION."</td>".
            "<td>".
            "<select name='sortViewType' style='width:100%'>
            <option value='0'".(!$sortViewType ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_ONLY_ACTION_NONE."</option>";
            foreach ($allowedActions as $row) {
                echo "<option value='".$row."'".($sortViewType == $row ? " selected" : "").">".constant("NETCAT_MODULE_LOGGING_EVENT_TYPE_".strtoupper($row))."</option>";
            }
            echo "</select>".
            "</td>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_SHOW_ONLY_ESSENCE."</td>".
            "<td>".
            "<select name='sortViewEssence' style='width:100%'>
            <option value='0'".(!$sortViewEssence ? " selected" : "").">".NETCAT_MODULE_LOGGING_SHOW_ONLY_ESSENCE_NONE."</option>";
            foreach ($allowedEssences as $row) {
                echo "<option value='".$row."'".($sortViewEssence == $row ? " selected" : "").">".constant("NETCAT_MODULE_LOGGING_EVENT_ESSENCE_".strtoupper($row))."</option>";
            }
            echo "</select>".
            "</td>".
            "</tr>".
            "<tr>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_SHOW_USER."</td>".
            "<td>".
            "<select name='filterViewUserID' style='width:100%'>".
            "<option value=''".(!isset($filterViewUserID) ? " selected" : "").">".NETCAT_MODULE_LOGGING_ALL_USERS."</option>".
            "<option value='0'".(isset($filterViewUserID) ? " selected" : "").">".NETCAT_MODULE_LOGGING_DATA_SYSTEM."</option>";
            foreach ($allowedUser as $row) {
                echo "<option value='".$row['User_ID']."'".($filterViewUserID == $row['User_ID'] ? " selected" : "").">".$row['Login']."</option>";
            }
            echo "</select>".
            "</td>".
            "<td nowrap>" . NETCAT_MODULE_LOGGING_DATA_IP . "</td>".
            "<td>".
            nc_admin_input_simple("filterViewIP", $filterViewIP, 0, 'width:100%;').
            "</td>".
            "</tr>".
            "<tr>".
            "<td nowrap>".NETCAT_MODULE_LOGGING_OVER_PERIOD."</td>".
            "<td colspan='3'>".            
            "<div class='nc-form'>".
            "<input name='filter_from_d' value='" . $filter_from_d . "' type='text' class='nc--mini' /> . ".
            "<input name='filter_from_m' value='" . $filter_from_m . "' type='text' class='nc--mini' /> . ".
            "<input name='filter_from_y' value='" . $filter_from_y . "' type='text' class='nc--small' /> ".
            "<span style='position:relative;padding-right:15px'>".
            "<button id='nc_calendar_popup_img_filter_from_d' class='nc-btn nc--light' onclick=\"nc_calendar_popup('filter_from_d', 'filter_from_m', 'filter_from_y'); return false\"><i class='nc-icon nc--calendar'></i></button>".
            "</span>".
            "<input name='filter_to_d' value='" . $filter_to_d . "' type='text' class='nc--mini' /> . ".
            "<input name='filter_to_m' value='" . $filter_to_m . "' type='text' class='nc--mini' /> . ".
            "<input name='filter_to_y' value='" . $filter_to_y . "' type='text' class='nc--small' /> ".
            "<span style='position:relative'>".
            "<button id='nc_calendar_popup_img_filter_to_d' class='nc-btn nc--light' onclick=\"nc_calendar_popup('filter_to_d', 'filter_to_m', 'filter_to_y'); return false\"><i class='nc-icon nc--calendar'></i></button>".
            "</span>".
            "</div>".
            "</td>".
            "</tr>".
            "</table>";

            echo "</div>\n".
            "</fieldset>\n".
            "</form>\n";
        }


        switch ($sort) {
            case "event":
                $sort_field = "l.`Event`";
                break;
            case "user":
                $sort_field = "u.`Email`";
                break;
            default:
                $sort_field = "l.`Date`";
        }

        switch ($sortDir) {
            case "asc":
                $sort_dir_field = "ASC";
                break;
            default:
                $sort_dir_field = "DESC";
        }

        $query_where_arr = array();

        if ($sortViewType && in_array($sortViewType, $allowedActions)) {
            $query_where_arr[] = "l.`Event` REGEXP '^".$sortViewType."'";
        }

        if ($sortViewEssence && in_array($sortViewEssence, $allowedEssences)) {
            $query_where_arr[] = "l.`Event` REGEXP '^(add|update|drop|check|uncheck|authorize)".$sortViewEssence."$'";
        }

        if (isset($filterViewUserID)) {
            $query_where_arr[] = "l.`User_ID` = '" . $filterViewUserID . "'";
        }

        if ($filterViewIP) {
            $query_where_arr[] =
                "(l.`IP` LIKE '" . $this->db->escape($filterViewIP) . "%' OR " .
                "l.`ForwardedForIP` LIKE '" . $this->db->escape($filterViewIP) . "%')";
        }

        if ($filter_from_d && $filter_from_m && $filter_from_y) {
            $date_from = new DateTime();
            if ($date_from->setDate($filter_from_y, $filter_from_m, $filter_from_d)) {
                $query_where_arr[] = "l.`Date` >= '" . $date_from->format('Y-m-d') . " 00:00:00'";
            }
        }

        if ($filter_to_d && $filter_to_m && $filter_to_y) {
            $date_to = new DateTime();
            if ($date_to->setDate($filter_to_y, $filter_to_m, $filter_to_d)) {
                $query_where_arr[] = "l.`Date` <= '" . $date_to->format('Y-m-d') . " 23:59:59'";
            }
        }

        $data = $this->db->get_results(
            "SELECT SQL_CALC_FOUND_ROWS l.*, 
                    u.`$nc_core->AUTHORIZE_BY` AS Login
               FROM `Logging` AS l
                    LEFT JOIN `User` AS u ON l.`User_ID` = u.`User_ID`
               " . (!empty($query_where_arr) ? "WHERE " . join(" AND ", $query_where_arr) : "") . "
             ORDER BY $sort_field $sort_dir_field, l.`ID` $sort_dir_field
              LIMIT " . (int)$curPos . ", " . (int)$maxRows,
            ARRAY_A
        );

        if (!empty($data)) {

            echo "<table class='nc-table'>
      <col width='3%'/><col/><col width='60%'/><col width='8%'/><col width='8%'/>
        <tr>
          <th nowrap class='nc-text-center'>".NETCAT_MODULE_LOGGING_DATA_ID."</th>
          <th nowrap>".NETCAT_MODULE_LOGGING_DATA_EVENT."</th>
          <th nowrap>".NETCAT_MODULE_LOGGING_DATA_INFO."</th>
          <th nowrap>".NETCAT_MODULE_LOGGING_DATA_DATE."</th>
          <th nowrap>".NETCAT_MODULE_LOGGING_DATA_IP."</th>
          <th nowrap class='nc-text-center'>".NETCAT_MODULE_LOGGING_DATA_USER."</th>
        </tr>";

            $totRows = $this->db->get_var("SELECT FOUND_ROWS()");

            foreach ($data as $row) {
                // determine event name
                preg_match("/^(add|update|drop|check|uncheck|authorize)/is", $row['Event'], $matches);

                // default color
                $color = "#FFFFFF";

                // set colors
                if (!empty($matches) && isset($matches[1])) {
                    switch ($matches[1]) {
                        case "add":
                            $color = "nc--green";
                            $tcolor = "nc-text-green";
                            break;
                        case "update":
                        case "check":
                        case "uncheck":
                            $color = "nc--yellow";
                            $tcolor = "nc-text-yellow";
                            break;
                        case "drop":
                            $color = "nc--red";
                            $tcolor = "nc-text-red";
                            break;
                        case "authorize":
                            $color = "nc--blue";
                            $tcolor = "nc-text-blue";
                            break;
                    }
                }

                // row element
                echo
                    "<tr>" .
                    "<td class='nc-text-center " . $color . "'>" . $row['ID'] . "</td>" .
                    "<td nowrap>" . $nc_core->event->event_name($row['Event']) . "</td>" .
                    "<td>" . $row['Info'] . "</td>" .
                    "<td nowrap>" . $row['Date'] . "</td>" .
                    "<td nowrap>" . htmlspecialchars($row['IP'] . ($row['ForwardedForIP'] ? " ($row[ForwardedForIP])" : '')) . "</td>" .
                    "<td class='nc-text-center'>" .
                    ($row['User_ID']
                        ? "<a target='_top' href='$nc_core->ADMIN_PATH#user.edit($row[User_ID])'>" . $row['Login'] . "</a>"
                        : NETCAT_MODULE_LOGGING_DATA_SYSTEM
                    ) .
                    "</td>" .
                    "</tr>";

            }

            echo "</table>";
        } else {
            $totRows = 0;
        }


        $env['maxRows'] = $maxRows;
        $env['totRows'] = $totRows;
        $env['curPos'] = $curPos;
        $env['LocalQuery'] = $current_url;

        // DEPRECATED
        global $browse_msg;

        $browse_msg['prefix'] = "";
        $browse_msg['suffix'] = "";
        $browse_msg['active'] = "<b>%PAGE</b>";
        $browse_msg['unactive'] = "<a href=%URL>%PAGE</a>";
        $browse_msg['divider'] = " | ";

        $last_pos = ( ceil($totRows / $maxRows) * $maxRows - $maxRows );

        require_once($nc_core->INCLUDE_FOLDER."s_class.inc.php");
        // pagination data
        $pagination = browse_messages($env, $range);

        if ($pagination) {
            // pagination prefix
            echo "<div style='margin:10px 0'>";

            // first link
            if ($curPos) {
                echo "<a href='".$env['LocalQuery'].(strstr($env['LocalQuery'], "?") ? "&" : "?")."curPos=0'>&laquo;&laquo;</a>&nbsp;&nbsp;";
            }

            // pagination
            echo $pagination;

            // last link
            if ($curPos != $last_pos && ( ($range + 1) * $maxRows ) < $totRows) {
                echo "&nbsp;&nbsp;<a href='".$env['LocalQuery'].(strstr($env['LocalQuery'], "?") ? "&" : "?")."curPos=".$last_pos."'>&raquo;&raquo;</a>";
            }

            // pagination suffix
            echo "</div>";
        }

        // clear form
        echo "<form method='post' id='ClearData' action='".$current_url."' style='padding:0; margin:0;'>\n".
        "<input type='hidden' name='phase' value='2'>\n".
        "</form>\n";

        // admin buttons
        $this->UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "align" => "left",
            "caption" => NETCAT_MODULE_LOGGING_BUTTON_CLEAR,
            "action" => "mainView.submitIframeForm('ClearData')",
            "red_border" => true,
        );

        // admin buttons
        $this->UI_CONFIG->actionButtons[] = array(
                "id" => "submit",
                "caption" => NETCAT_MODULE_LOGGING_BUTTON_SORT,
                "action" => "mainView.submitIframeForm('SortData')"
        );
    }


    /**
     * MySQL datetime/date → российский формат ДД.ММ.ГГГГ[ ЧЧ:ММ]. Возвращает готовую
     * к выводу строку (только цифры/разделители — экранирование не требуется).
     */
    protected static function fmt_dt($dt) {
        $dt = (string)$dt;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $dt, $m)) {
            return $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dt, $d)) {
            return $d[3] . '.' . $d[2] . '.' . $d[1];
        }
        return htmlspecialchars($dt);
    }

    /**
     * Субъект согласия: «Аноним» либо «Пользователь #N» со ссылкой на карточку
     * пользователя в админке (target=_top → навигация в родительском фрейме SPA).
     */
    protected function subject_html($row) {
        if ($row['Subject_Type'] !== 'user') {
            return NETCAT_MODULE_LOGGING_CONSENT_SUBJECT_ANON;
        }
        $uid = (int)$row['User_ID'];
        if (!$uid) {
            return NETCAT_MODULE_LOGGING_CONSENT_SUBJECT_USER;
        }
        $nc_core = nc_Core::get_object();
        return NETCAT_MODULE_LOGGING_CONSENT_SUBJECT_USER .
            " <a target='_top' href='" . htmlspecialchars($nc_core->ADMIN_PATH) . "#user.edit(" . $uid . ")'>#" . $uid . "</a>";
    }

    /**
     * Дата из фильтра (ДД.ММ.ГГГГ от datepicker, либо ГГГГ-ММ-ДД) → нормализованная
     * ГГГГ-ММ-ДД для SQL. Невалидное/пустое → ''.
     */
    protected static function parse_date($s) {
        $s = trim((string)$s);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) {
            return $s;
        }
        return '';
    }

    /**
     * Баннер health-сигнала журнала согласий (метки в настройках модуля).
     */
    protected function consent_health_banner() {
        $nc_core = nc_Core::get_object();
        // метки пишутся в глобальном scope (catalogue_id=0) — читаем тем же scope;
        // get_settings при отсутствии настроек модуля возвращает array() — приводим к строке.
        $get = function ($key) use ($nc_core) {
            $v = $nc_core->get_settings($key, 'logging', false, 0);
            return is_string($v) ? $v : '';
        };
        $last_error = $get('consent_log_last_error');
        $last_success = $get('consent_log_last_success');
        $error_count = (int)$get('consent_log_error_count');

        if ($error_count > 0 && (!$last_success || substr($last_error, 0, 19) >= substr($last_success, 0, 19))) {
            nc_print_status(
                sprintf(NETCAT_MODULE_LOGGING_CONSENT_HEALTH_WARN, htmlspecialchars($last_error), $error_count),
                "error"
            );
        }
        if ($last_success) {
            echo "<div style='margin:12px 0 10px;color:#888'>" .
                 sprintf(NETCAT_MODULE_LOGGING_CONSENT_LAST_SUCCESS, self::fmt_dt($last_success)) .
                 "</div>";
        }
    }

    /**
     * Список журнала согласий (только чтение) с фильтром и пагинацией.
     */
    public function show_consent_data($curPos = 0) {
        $nc_core = nc_Core::get_object();
        $curPos = (int)$curPos;

        $this->consent_health_banner();

        $search = (string)$nc_core->input->fetch_get_text('q');
        $f_name = (string)$nc_core->input->fetch_get_text('f_name');
        $f_action = (string)$nc_core->input->fetch_get_text('f_action');
        $f_action = in_array($f_action, array('grant', 'withdraw'), true) ? $f_action : '';
        $date_from_raw = (string)$nc_core->input->fetch_get_text('date_from');
        $date_to_raw   = (string)$nc_core->input->fetch_get_text('date_to');
        $date_from = self::parse_date($date_from_raw);
        $date_to   = self::parse_date($date_to_raw);

        $current_url = $nc_core->url->get_parsed_url("path");
        $maxRows = $this->MODULE_VARS['ROWS_PER_PAGE'] ?: 25;
        $range = $this->MODULE_VARS['PAGES_NUMBER'] ?: 10;

        // нормализованные значения дат в поле — в формате datepicker (ДД.ММ.ГГГГ)
        $date_from_val = $date_from !== '' ? self::fmt_dt($date_from) : htmlspecialchars($date_from_raw);
        $date_to_val   = $date_to !== '' ? self::fmt_dt($date_to) : htmlspecialchars($date_to_raw);

        // фильтр
        echo "<form method='get' id='ConsentFilter' action='" . $current_url . "' style='padding:0;margin:0'>\n" .
             "<input type='hidden' name='phase' value='10'>\n" .
             "<fieldset><legend>" . NETCAT_MODULE_LOGGING_SHOW_PARAMETERS . "</legend>\n" .
             "<table class='nc-table nc--small' width='100%'><col style='width:15%'/><col style='width:35%'/><col style='width:15%'/><col style='width:35%'/>\n" .
             "<tr><td nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_NAME . "</td>" .
             "<td><input type='text' name='f_name' style='width:100%' value='" . htmlspecialchars($f_name) . "'></td>" .
             "<td nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_SEARCH . "</td>" .
             "<td><input type='text' name='q' style='width:100%' value='" . htmlspecialchars($search) . "'></td></tr>\n" .
             "<tr><td nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_PERIOD . "</td>" .
             "<td><input type='text' name='date_from' data-provide='datepicker' autocomplete='off' style='width:45%' placeholder='" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_FROM . "' value='" . $date_from_val . "'> — " .
             "<input type='text' name='date_to' data-provide='datepicker' autocomplete='off' style='width:45%' placeholder='" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_TO . "' value='" . $date_to_val . "'></td>" .
             "<td nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_ACTION . "</td>" .
             "<td><select name='f_action' style='width:100%'>" .
             "<option value=''>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_ALL . "</option>" .
             "<option value='grant'" . ($f_action === 'grant' ? ' selected' : '') . ">" . NETCAT_MODULE_LOGGING_CONSENT_ACTION_GRANT . "</option>" .
             "<option value='withdraw'" . ($f_action === 'withdraw' ? ' selected' : '') . ">" . NETCAT_MODULE_LOGGING_CONSENT_ACTION_WITHDRAW . "</option>" .
             "</select></td></tr></table></fieldset></form>\n";

        $where = array();
        if ($search !== '') {
            $esc = $this->db->escape($search);
            $where[] = "(`Email` LIKE '%" . $esc . "%' OR `Phone` LIKE '%" . $esc . "%')";
        }
        if ($f_name !== '') {
            $where[] = "`Name` LIKE '%" . $this->db->escape($f_name) . "%'";
        }
        if ($date_from !== '') {
            $where[] = "`Created` >= '" . $date_from . " 00:00:00'";
        }
        if ($date_to !== '') {
            $where[] = "`Created` <= '" . $date_to . " 23:59:59'";
        }
        if ($f_action) {
            $where[] = "`Action` = '" . $this->db->escape($f_action) . "'";
        }

        $data = (array)$this->db->get_results(
            "SELECT SQL_CALC_FOUND_ROWS * FROM `ConsentLog`" .
            (!empty($where) ? " WHERE " . join(" AND ", $where) : "") .
            " ORDER BY `Created` DESC, `ConsentLog_ID` DESC LIMIT " . $curPos . ", " . (int)$maxRows,
            ARRAY_A
        );
        $totRows = (int)$this->db->get_var("SELECT FOUND_ROWS()");

        if (!empty($data)) {
            echo "<table class='nc-table'>\n<tr>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_DATE . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_SUBJECT . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_NAME . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_CONTACTS . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_TYPE . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_ACTION . "</th>" .
                 "<th>" . NETCAT_MODULE_LOGGING_CONSENT_COL_LABEL . "</th>" .
                 "<th nowrap>IP</th></tr>\n";
            foreach ($data as $row) {
                $rec_url = $current_url . "?phase=11&id=" . (int)$row['ConsentLog_ID'];
                $subject = $this->subject_html($row);
                $contacts = htmlspecialchars((string)$row['Email']) . ($row['Phone'] ? "<br>" . htmlspecialchars((string)$row['Phone']) : '');
                $action_label = $row['Action'] === 'withdraw' ? NETCAT_MODULE_LOGGING_CONSENT_ACTION_WITHDRAW : NETCAT_MODULE_LOGGING_CONSENT_ACTION_GRANT;
                $plain = trim(strip_tags((string)$row['Consent_Label']));
                $preview = htmlspecialchars(mb_substr($plain, 0, 90)) . (mb_strlen($plain) > 90 ? '…' : '');
                echo "<tr>" .
                     "<td nowrap><a href='" . $rec_url . "'>" . self::fmt_dt($row['Created']) . "</a></td>" .
                     "<td nowrap>" . $subject . "</td>" .
                     "<td>" . (trim((string)$row['Name']) !== '' ? htmlspecialchars((string)$row['Name']) : '—') . "</td>" .
                     "<td>" . ($contacts !== '' ? $contacts : '—') . "</td>" .
                     "<td nowrap>" . (htmlspecialchars((string)$row['Consent_Type']) ?: '—') . "</td>" .
                     "<td nowrap>" . $action_label . "</td>" .
                     "<td>" . $preview . "</td>" .
                     "<td nowrap>" . htmlspecialchars((string)$row['IP']) . "</td>" .
                     "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<div class='nc-alert nc--blue'><i class='nc-icon-l nc--status-info'></i><div class='nc-alert--message'>" . NETCAT_MODULE_LOGGING_CONSENT_EMPTY . "</div></div>";
        }

        // пагинация
        $env = array(
            'maxRows' => $maxRows,
            'totRows' => $totRows,
            'curPos'  => $curPos,
            'LocalQuery' => $current_url . "?phase=10" .
                ($search !== '' ? "&q=" . urlencode($search) : '') .
                ($f_name !== '' ? "&f_name=" . urlencode($f_name) : '') .
                ($date_from !== '' ? "&date_from=" . urlencode($date_from) : '') .
                ($date_to !== '' ? "&date_to=" . urlencode($date_to) : '') .
                ($f_action ? "&f_action=" . $f_action : ''),
        );
        global $browse_msg;
        $browse_msg['prefix'] = $browse_msg['suffix'] = '';
        $browse_msg['active'] = "<b>%PAGE</b>";
        $browse_msg['unactive'] = "<a href=%URL>%PAGE</a>";
        $browse_msg['divider'] = " | ";
        require_once($nc_core->INCLUDE_FOLDER . "s_class.inc.php");
        $pagination = browse_messages($env, $range);
        if ($pagination) {
            echo "<div style='margin:10px 0'>" . $pagination . "</div>";
        }

        $this->UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_LOGGING_BUTTON_SORT,
            "action" => "mainView.submitIframeForm('ConsentFilter')",
        );
    }

    /**
     * Страница одной записи журнала согласий (текст согласия + ?docdate-ссылки на документы).
     */
    public function show_consent_record() {
        $nc_core = nc_Core::get_object();
        $id = (int)$nc_core->input->fetch_get('id');

        $current_url = $nc_core->url->get_parsed_url("path");
        echo "<div style='margin:0 0 10px'><a href='" . $current_url . "?phase=10'>" . NETCAT_MODULE_LOGGING_CONSENT_BACK . "</a></div>";

        $row = $this->db->get_row("SELECT * FROM `ConsentLog` WHERE `ConsentLog_ID` = " . $id, ARRAY_A);
        if (!$row) {
            echo "<div class='nc-alert nc--blue'><i class='nc-icon-l nc--status-info'></i><div class='nc-alert--message'>" . NETCAT_MODULE_LOGGING_CONSENT_EMPTY . "</div></div>";
            return;
        }

        $date = substr((string)$row['Created'], 0, 10);
        $label = nc_consent_log::add_date_to_links(nc_consent_log::sanitize_label($row['Consent_Label']), $date);

        $subject = $this->subject_html($row);
        $action_label = $row['Action'] === 'withdraw' ? NETCAT_MODULE_LOGGING_CONSENT_ACTION_WITHDRAW : NETCAT_MODULE_LOGGING_CONSENT_ACTION_GRANT;
        $contacts = htmlspecialchars((string)$row['Email']) . ($row['Phone'] ? " / " . htmlspecialchars((string)$row['Phone']) : '');
        $source = htmlspecialchars(trim((string)$row['Source_Table'] . ($row['Source_Object_ID'] ? " #" . (int)$row['Source_Object_ID'] : '')));
        $source_url = '';
        if ($row['Source_URL']) {
            $u = (string)$row['Source_URL'];
            $u_dated = $u . (strpos($u, '?') !== false ? '&' : '?') . 'docdate=' . $date;
            $source_url = "<a target='_blank' href='" . htmlspecialchars($u_dated) . "'>" . htmlspecialchars($u) . "</a>";
        }

        $fields = array(
            NETCAT_MODULE_LOGGING_CONSENT_COL_DATE     => self::fmt_dt($row['Created']),
            NETCAT_MODULE_LOGGING_CONSENT_COL_ACTION   => $action_label,
            NETCAT_MODULE_LOGGING_CONSENT_COL_SUBJECT  => $subject,
            NETCAT_MODULE_LOGGING_CONSENT_COL_NAME     => (trim((string)$row['Name']) !== '' ? htmlspecialchars((string)$row['Name']) : '—'),
            NETCAT_MODULE_LOGGING_CONSENT_COL_CONTACTS => ($contacts !== '' ? $contacts : '—'),
            NETCAT_MODULE_LOGGING_CONSENT_COL_TYPE     => (htmlspecialchars((string)$row['Consent_Type']) ?: '—'),
            NETCAT_MODULE_LOGGING_CONSENT_COL_LABEL    => "<div style='padding:.6em;background:#f7f7f7;border:1px solid #eee'>" . $label . "</div>",
            NETCAT_MODULE_LOGGING_CONSENT_SOURCE_OBJECT => ($source !== '' ? $source : '—'),
            NETCAT_MODULE_LOGGING_CONSENT_SOURCE_URL   => ($source_url !== '' ? $source_url : '—'),
            "IP" => htmlspecialchars((string)$row['IP']),
            NETCAT_MODULE_LOGGING_CONSENT_USER_AGENT   => (htmlspecialchars((string)$row['User_Agent']) ?: '—'),
        );

        echo "<table class='nc-table'>\n";
        foreach ($fields as $k => $v) {
            echo "<tr><th nowrap style='text-align:left;width:25%'>" . $k . "</th><td>" . $v . "</td></tr>\n";
        }
        echo "</table>\n";

        echo $this->site_documents_html((int)$row['Catalogue_ID'], $date);
    }

    /**
     * Стандартные документы сайта (если заданы в настройках) — ссылками на редакцию,
     * действовавшую на дату согласия (?docdate). Если ни один раздел не задан — ''.
     *
     * @param int $site_id
     * @param string $date ГГГГ-ММ-ДД
     * @return string
     */
    protected function site_documents_html($site_id, $date) {
        $site_id = (int)$site_id;
        $doc_roles = array(
            'PrivacyPolicy_Sub_ID' => 'Политика обработки ПД',
            'Consent_Sub_ID'       => 'Согласие на обработку ПД',
            'Rules_Sub_ID'         => 'Правила сайта',
        );
        $cat = (array)$this->db->get_row(
            "SELECT `PrivacyPolicy_Sub_ID`, `Consent_Sub_ID`, `Rules_Sub_ID`
               FROM `Catalogue` WHERE `Catalogue_ID` = " . $site_id,
            ARRAY_A
        );
        $doc_links = array();
        foreach ($doc_roles as $field => $fallback) {
            $sub_id = (int)nc_array_value($cat, $field);
            if ($sub_id <= 0) {
                continue;
            }
            $sub_url = function_exists('nc_folder_url') ? nc_folder_url($sub_id) : '';
            if (!$sub_url) {
                continue;
            }
            $name = trim((string)$this->db->get_var("SELECT `Subdivision_Name` FROM `Subdivision` WHERE `Subdivision_ID` = " . $sub_id));
            $caption = $name !== '' ? $name : $fallback;
            $u = $sub_url . (strpos($sub_url, '?') !== false ? '&' : '?') . 'docdate=' . $date;
            $doc_links[] = "<a target='_blank' href='" . htmlspecialchars($u) . "'>" . htmlspecialchars($caption) . "</a>";
        }
        if (!$doc_links) {
            return '';
        }

        return "<p style='margin-top:12px'>" . NETCAT_MODULE_LOGGING_CONSENT_DOCS . ": " . implode(' · ', $doc_links) . "</p>";
    }

    /**
     * Подпись решения в окне cookie-согласия.
     */
    protected static function cookie_decision_label($decision) {
        switch ($decision) {
            case nc_cookie_consent_log::DECISION_ACCEPT:
                return NETCAT_MODULE_LOGGING_COOKIE_DECISION_ACCEPT;
            case nc_cookie_consent_log::DECISION_REJECT:
                return NETCAT_MODULE_LOGGING_COOKIE_DECISION_REJECT;
            default:
                return NETCAT_MODULE_LOGGING_COOKIE_DECISION_PARTIAL;
        }
    }

    /**
     * CSV категорий → подписи через запятую (necessary не показываем — есть всегда).
     */
    protected static function cookie_categories_label($csv) {
        $labels = array(
            nc_consent::CATEGORY_FUNCTIONAL => NETCAT_MODULE_LOGGING_COOKIE_CATEGORY_FUNCTIONAL,
            nc_consent::CATEGORY_ANALYTICS  => NETCAT_MODULE_LOGGING_COOKIE_CATEGORY_ANALYTICS,
            nc_consent::CATEGORY_MARKETING  => NETCAT_MODULE_LOGGING_COOKIE_CATEGORY_MARKETING,
        );
        $result = array();
        foreach (explode(',', (string)$csv) as $category) {
            if (isset($labels[$category])) {
                $result[] = $labels[$category];
            }
        }

        return $result ? implode(', ', $result) : NETCAT_MODULE_LOGGING_COOKIE_CATEGORY_NECESSARY_ONLY;
    }

    /**
     * Состояние журналирования cookie-согласий: где включено, срабатывали ли
     * лимиты, сколько записей отброшено за сутки и нет ли «тихих» сайтов
     * (включено, но записей давно нет — скорее всего окно согласия не выведено).
     */
    protected function cookie_consent_status_banner() {
        $sites = (array)$this->db->get_results(
            "SELECT c.`Catalogue_ID`, c.`Catalogue_Name`
               FROM `Settings` AS s
               JOIN `Catalogue` AS c ON c.`Catalogue_ID` = s.`Catalogue_ID`
              WHERE s.`Module` = 'system' AND s.`Key` = '" . nc_consent::SETTING_LOG . "' AND s.`Value` = '1'
              ORDER BY c.`Catalogue_ID`",
            ARRAY_A
        );

        $enabled = $silent = array();
        foreach ($sites as $site) {
            $site_id = (int)$site['Catalogue_ID'];
            if (!nc_consent::is_log_enabled($site_id)) {
                continue; // режим notice или модуль выключен для сайта
            }
            $name = htmlspecialchars((string)$site['Catalogue_Name']);
            $enabled[] = $name;

            // лимиты за последние сутки: всплеск посещаемости или массовая отправка
            $overflow = nc_cookie_consent_log::get_overflow($site_id, nc_cookie_consent_log::SETTING_OVERFLOW);
            if ($overflow !== '' && strtotime($overflow) > time() - 86400) {
                nc_print_status(
                    sprintf(NETCAT_MODULE_LOGGING_COOKIE_OVERFLOW, $name, self::fmt_dt($overflow),
                        nc_consent::log_limit(nc_consent::SETTING_LOG_LIMIT_SITE, $site_id)),
                    'error'
                );
            }
            $overflow_day = nc_cookie_consent_log::get_overflow($site_id, nc_cookie_consent_log::SETTING_OVERFLOW_DAY);
            if ($overflow_day !== '' && strtotime($overflow_day) > time() - 86400) {
                nc_print_status(
                    sprintf(NETCAT_MODULE_LOGGING_COOKIE_OVERFLOW_DAY, $name, self::fmt_dt($overflow_day),
                        nc_consent::log_limit(nc_consent::SETTING_LOG_LIMIT_DAY, $site_id)),
                    'error'
                );
            }
            $dropped_today = nc_cookie_consent_log::get_dropped($site_id);
            $dropped_yesterday = nc_cookie_consent_log::get_dropped($site_id, strtotime('-1 day'));
            if ($dropped_today || $dropped_yesterday) {
                nc_print_status(sprintf(NETCAT_MODULE_LOGGING_COOKIE_DROPPED, $name, $dropped_today, $dropped_yesterday), 'info');
            }

            $last = $this->db->get_var(
                "SELECT MAX(`Created`) FROM `" . nc_cookie_consent_log::TABLE . "` WHERE `Catalogue_ID` = " . $site_id
            );
            if (!$last || strtotime($last) < time() - 14 * 86400) {
                $silent[] = $name;
            }
        }

        if (!$enabled) {
            echo "<div class='nc-alert nc--blue'><i class='nc-icon-l nc--status-info'></i><div class='nc-alert--message'>" .
                 NETCAT_MODULE_LOGGING_COOKIE_DISABLED . "</div></div>";
            return;
        }
        if ($silent) {
            nc_print_status(sprintf(NETCAT_MODULE_LOGGING_COOKIE_SILENT, implode(', ', $silent)), 'info');
        }
        echo "<div style='margin:12px 0 10px;color:#888'>" . sprintf(NETCAT_MODULE_LOGGING_COOKIE_ENABLED_ON, implode(', ', $enabled)) . "</div>";
    }

    /**
     * Экранировать строку для LIKE (\, %, _) — результат ещё нужно пропустить через escape().
     *
     * @param string $value
     * @return string
     */
    protected static function escape_like($value) {
        return addcslashes((string)$value, '\\%_');
    }

    /**
     * Курсор постраничной навигации «Created|ID» → array(created, id) или null.
     *
     * @param string $cursor
     * @return array|null
     */
    protected static function parse_cookie_cursor($cursor) {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|(\d{1,10})$/', (string)$cursor, $m)) {
            return null;
        }

        return array($m[1], (int)$m[2]);
    }

    /**
     * Список журнала выбора в окне cookie-согласия (только чтение).
     *
     * Навигация — по курсору (Created, ID) «новее / старее» без подсчёта общего
     * числа строк: таблица растёт на каждого посетителя, COUNT/SQL_CALC_FOUND_ROWS
     * и большие OFFSET на ней дороги.
     */
    public function show_cookie_consent_data() {
        $nc_core = nc_Core::get_object();

        if (!nc_cookie_consent_log::tables_exist()) {
            nc_print_status(NETCAT_MODULE_LOGGING_COOKIE_NO_TABLE, 'error');
            return;
        }

        $this->cookie_consent_status_banner();

        $search = trim((string)$nc_core->input->fetch_get_text('q'));
        $f_decision = (string)$nc_core->input->fetch_get_text('f_decision');
        $decisions = array(
            nc_cookie_consent_log::DECISION_ACCEPT,
            nc_cookie_consent_log::DECISION_REJECT,
            nc_cookie_consent_log::DECISION_PARTIAL,
        );
        $f_decision = in_array($f_decision, $decisions, true) ? $f_decision : '';
        $f_site = (int)$nc_core->input->fetch_get('f_site');
        $date_from_raw = (string)$nc_core->input->fetch_get_text('date_from');
        $date_to_raw   = (string)$nc_core->input->fetch_get_text('date_to');
        $date_from = self::parse_date($date_from_raw);
        $date_to   = self::parse_date($date_to_raw);
        $direction = $nc_core->input->fetch_get('dir') === 'prev' ? 'prev' : 'next';
        $cursor = self::parse_cookie_cursor($nc_core->input->fetch_get('cursor'));

        $current_url = $nc_core->url->get_parsed_url("path");
        $maxRows = (int)($this->MODULE_VARS['ROWS_PER_PAGE'] ?: 25);

        $date_from_val = $date_from !== '' ? self::fmt_dt($date_from) : htmlspecialchars($date_from_raw);
        $date_to_val   = $date_to !== '' ? self::fmt_dt($date_to) : htmlspecialchars($date_to_raw);

        $decision_options = "<option value=''>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_ALL . "</option>";
        foreach ($decisions as $decision) {
            $decision_options .= "<option value='" . $decision . "'" . ($f_decision === $decision ? ' selected' : '') . ">" .
                                  self::cookie_decision_label($decision) . "</option>";
        }

        $site_names = array();
        foreach ((array)$this->db->get_results("SELECT `Catalogue_ID`, `Catalogue_Name` FROM `Catalogue` ORDER BY `Priority`, `Catalogue_ID`", ARRAY_A) as $site) {
            $site_names[(int)$site['Catalogue_ID']] = (string)$site['Catalogue_Name'];
        }
        $site_options = "<option value=''>" . NETCAT_MODULE_LOGGING_COOKIE_FILTER_ALL_SITES . "</option>";
        foreach ($site_names as $site_id => $site_name) {
            $site_options .= "<option value='" . $site_id . "'" . ($f_site === $site_id ? ' selected' : '') . ">" .
                             htmlspecialchars($site_name) . " (#" . $site_id . ")</option>";
        }

        echo "<form method='get' id='CookieConsentFilter' action='" . $current_url . "' style='padding:0;margin:0'>\n" .
             "<input type='hidden' name='phase' value='12'>\n" .
             "<fieldset><legend>" . NETCAT_MODULE_LOGGING_SHOW_PARAMETERS . "</legend>\n" .
             "<table class='nc-table nc--small' width='100%'><col style='width:15%'/><col style='width:35%'/><col style='width:15%'/><col style='width:35%'/>\n" .
             "<tr><td nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_FILTER_SEARCH . "</td>" .
             "<td><input type='text' name='q' style='width:100%' value='" . htmlspecialchars($search) . "'></td>" .
             "<td nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_COL_DECISION . "</td>" .
             "<td><select name='f_decision' style='width:100%'>" . $decision_options . "</select></td></tr>\n" .
             "<tr><td nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_PERIOD . "</td>" .
             "<td><input type='text' name='date_from' data-provide='datepicker' autocomplete='off' style='width:45%' placeholder='" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_FROM . "' value='" . $date_from_val . "'> — " .
             "<input type='text' name='date_to' data-provide='datepicker' autocomplete='off' style='width:45%' placeholder='" . NETCAT_MODULE_LOGGING_CONSENT_FILTER_TO . "' value='" . $date_to_val . "'></td>" .
             "<td nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_COL_SITE . "</td>" .
             "<td><select name='f_site' style='width:100%'>" . $site_options . "</select></td></tr></table></fieldset></form>\n";

        $where = array();
        if ($f_site) {
            $where[] = "`Catalogue_ID` = " . $f_site;
        }
        if ($search !== '') {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $search)) {
                // ID согласия — точное совпадение (индекс Consent_UID)
                $where[] = "`Consent_UID` = '" . strtolower($search) . "'";
            } else {
                // IP — по префиксу (индексы Site_IP / IP); % и _ в строке — буквально
                $where[] = "`IP` LIKE '" . $this->db->escape(self::escape_like($search)) . "%'";
            }
        }
        if ($f_decision) {
            $where[] = "`Decision` = '" . $f_decision . "'";
        }
        if ($date_from !== '') {
            $where[] = "`Created` >= '" . $date_from . " 00:00:00'";
        }
        if ($date_to !== '') {
            $where[] = "`Created` <= '" . $date_to . " 23:59:59'";
        }
        if ($cursor) {
            list($cursor_created, $cursor_id) = $cursor;
            $op = $direction === 'prev' ? '>' : '<';
            $where[] = "(`Created` " . $op . " '" . $cursor_created . "' OR (`Created` = '" . $cursor_created . "' AND `CookieConsentLog_ID` " . $op . " " . $cursor_id . "))";
        }
        $order = $direction === 'prev' ? 'ASC' : 'DESC';

        // n+1 строк — узнать, есть ли следующая страница в этом направлении
        $data = (array)$this->db->get_results(
            "SELECT * FROM `" . nc_cookie_consent_log::TABLE . "`" .
            ($where ? " WHERE " . implode(" AND ", $where) : "") .
            " ORDER BY `Created` " . $order . ", `CookieConsentLog_ID` " . $order .
            " LIMIT " . ($maxRows + 1),
            ARRAY_A
        );
        $has_more = count($data) > $maxRows;
        $data = array_slice($data, 0, $maxRows);
        if ($direction === 'prev') {
            $data = array_reverse($data);
            $has_newer = $has_more;
            $has_older = (bool)$cursor;
        } else {
            $has_newer = (bool)$cursor;
            $has_older = $has_more;
        }

        if ($data) {
            echo "<table class='nc-table'>\n<tr>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_CONSENT_COL_DATE . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_COL_DECISION . "</th>" .
                 "<th>" . NETCAT_MODULE_LOGGING_COOKIE_COL_CATEGORIES . "</th>" .
                 "<th nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_COL_UID . "</th>" .
                 "<th nowrap>IP</th>" .
                 ($f_site ? "" : "<th nowrap>" . NETCAT_MODULE_LOGGING_COOKIE_COL_SITE . "</th>") .
                 "<th>" . NETCAT_MODULE_LOGGING_COOKIE_COL_PAGE . "</th></tr>\n";
            foreach ($data as $row) {
                $rec_url = $current_url . "?phase=13&id=" . (int)$row['CookieConsentLog_ID'];
                $row_site_id = (int)$row['Catalogue_ID'];
                echo "<tr>" .
                     "<td nowrap><a href='" . $rec_url . "'>" . self::fmt_dt($row['Created']) . "</a></td>" .
                     "<td nowrap>" . self::cookie_decision_label($row['Decision']) . "</td>" .
                     "<td>" . self::cookie_categories_label($row['Categories']) . "</td>" .
                     "<td nowrap><code>" . htmlspecialchars((string)$row['Consent_UID']) . "</code></td>" .
                     "<td nowrap>" . htmlspecialchars((string)$row['IP']) . "</td>" .
                     ($f_site ? "" : "<td>" . (isset($site_names[$row_site_id]) ? htmlspecialchars($site_names[$row_site_id]) : '#' . $row_site_id) . "</td>") .
                     "<td>" . (trim((string)$row['Page_URL']) !== '' ? htmlspecialchars((string)$row['Page_URL']) : '—') . "</td>" .
                     "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<div class='nc-alert nc--blue'><i class='nc-icon-l nc--status-info'></i><div class='nc-alert--message'>" . NETCAT_MODULE_LOGGING_CONSENT_EMPTY . "</div></div>";
        }

        $base_query = $current_url . "?phase=12" .
            ($search !== '' ? "&q=" . urlencode($search) : '') .
            ($f_site ? "&f_site=" . $f_site : '') .
            ($date_from !== '' ? "&date_from=" . urlencode($date_from) : '') .
            ($date_to !== '' ? "&date_to=" . urlencode($date_to) : '') .
            ($f_decision ? "&f_decision=" . $f_decision : '');
        $nav = array();
        if ($has_newer && $data) {
            $first = reset($data);
            $nav[] = "<a href='" . htmlspecialchars($base_query) . "'>" . NETCAT_MODULE_LOGGING_COOKIE_PAGE_FIRST . "</a>";
            $nav[] = "<a href='" . htmlspecialchars($base_query . "&dir=prev&cursor=" . urlencode($first['Created'] . '|' . $first['CookieConsentLog_ID'])) . "'>" . NETCAT_MODULE_LOGGING_COOKIE_PAGE_NEWER . "</a>";
        } elseif ($has_newer) {
            $nav[] = "<a href='" . htmlspecialchars($base_query) . "'>" . NETCAT_MODULE_LOGGING_COOKIE_PAGE_FIRST . "</a>";
        }
        if ($has_older && $data) {
            $last = end($data);
            $nav[] = "<a href='" . htmlspecialchars($base_query . "&dir=next&cursor=" . urlencode($last['Created'] . '|' . $last['CookieConsentLog_ID'])) . "'>" . NETCAT_MODULE_LOGGING_COOKIE_PAGE_OLDER . "</a>";
        }
        if ($nav) {
            echo "<div style='margin:10px 0'>" . implode(' | ', $nav) . "</div>";
        }

        $this->cookie_consent_purge_form($f_site, $site_names);

        echo "<div style='margin:16px 0 0;color:#888;font-size:12px'>" . NETCAT_MODULE_LOGGING_COOKIE_TRUST_NOTE . "</div>";

        $this->UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_LOGGING_BUTTON_SORT,
            "action" => "mainView.submitIframeForm('CookieConsentFilter')",
        );
    }

    /**
     * Форма «Удалить записи старше N дней» (для сайта из фильтра или всех сайтов).
     *
     * @param int $site_id
     * @param string[] $site_names
     */
    protected function cookie_consent_purge_form($site_id, array $site_names) {
        $nc_core = nc_Core::get_object();
        $current_url = $nc_core->url->get_parsed_url("path");
        $scope = $site_id
            ? (isset($site_names[$site_id]) ? $site_names[$site_id] : '#' . $site_id)
            : NETCAT_MODULE_LOGGING_COOKIE_FILTER_ALL_SITES;
        $confirm = json_encode(sprintf(NETCAT_MODULE_LOGGING_COOKIE_PURGE_CONFIRM, '%DAYS%', $scope), JSON_UNESCAPED_UNICODE);
        if (!$nc_core->NC_UNICODE || $confirm === false) {
            // в windows-1251 json_encode строку не примет — подтверждение без подстановки
            $confirm = "'" . addslashes(str_replace(array("\r", "\n"), ' ', sprintf(NETCAT_MODULE_LOGGING_COOKIE_PURGE_CONFIRM, '%DAYS%', $scope))) . "'";
        }

        echo "<form method='post' action='" . $current_url . "' style='margin:16px 0 0'" .
             " onsubmit=\"var d=this.elements['days'].value;return confirm(" . htmlspecialchars($confirm, ENT_QUOTES) . ".replace('%DAYS%', d));\">\n" .
             "<input type='hidden' name='phase' value='14'>\n" .
             "<input type='hidden' name='f_site' value='" . (int)$site_id . "'>\n" .
             $nc_core->token->get_input() . "\n" .
             "<fieldset><legend>" . NETCAT_MODULE_LOGGING_COOKIE_PURGE_TITLE . "</legend>\n" .
             htmlspecialchars($scope) . ": " . NETCAT_MODULE_LOGGING_COOKIE_PURGE_OLDER_THAN .
             " <input type='number' name='days' min='1' step='1' required style='width:80px'> " .
             NETCAT_MODULE_LOGGING_COOKIE_PURGE_DAYS .
             " <button type='submit' class='nc-btn nc--small nc--red'>" . NETCAT_MODULE_LOGGING_COOKIE_PURGE_BUTTON . "</button>" .
             "</fieldset></form>\n";
    }

    /**
     * Удалить записи журнала cookie-согласий старше N дней (phase 14, POST).
     * Право и CSRF-токен проверяются в admin.php.
     */
    public function purge_cookie_consent_data() {
        $nc_core = nc_Core::get_object();
        $days_raw = trim((string)$nc_core->input->fetch_post('days'));
        $site_id = (int)$nc_core->input->fetch_post('f_site');

        if (!ctype_digit($days_raw) || (int)$days_raw < 1 || strlen($days_raw) > 6) {
            nc_print_status(NETCAT_MODULE_LOGGING_COOKIE_PURGE_INVALID, 'error');
            return $site_id;
        }

        $result = nc_cookie_consent_log::purge($site_id, (int)$days_raw, microtime(true) + nc_cookie_consent_log::PURGE_TIME_BUDGET);
        nc_print_status(
            sprintf($result['complete'] ? NETCAT_MODULE_LOGGING_COOKIE_PURGE_DONE : NETCAT_MODULE_LOGGING_COOKIE_PURGE_PARTIAL, $result['deleted']),
            $result['complete'] ? 'ok' : 'info'
        );

        return $site_id;
    }

    /**
     * Карточка записи журнала cookie-согласий: все поля, текст уведомления,
     * документы сайта на дату выбора и история того же ID согласия.
     */
    public function show_cookie_consent_record() {
        $nc_core = nc_Core::get_object();
        $id = (int)$nc_core->input->fetch_get('id');

        $current_url = $nc_core->url->get_parsed_url("path");
        echo "<div style='margin:0 0 10px'><a href='" . $current_url . "?phase=12'>" . NETCAT_MODULE_LOGGING_CONSENT_BACK . "</a></div>";

        $row = nc_cookie_consent_log::tables_exist()
            ? $this->db->get_row("SELECT * FROM `" . nc_cookie_consent_log::TABLE . "` WHERE `CookieConsentLog_ID` = " . $id, ARRAY_A)
            : null;
        if (!$row) {
            echo "<div class='nc-alert nc--blue'><i class='nc-icon-l nc--status-info'></i><div class='nc-alert--message'>" . NETCAT_MODULE_LOGGING_CONSENT_EMPTY . "</div></div>";
            return;
        }

        $date = substr((string)$row['Created'], 0, 10);
        $text = nc_cookie_consent_log::get_text($row['Text_Hash']);
        $text_html = $text !== null && $text !== ''
            ? "<div style='padding:.6em;background:#f7f7f7;border:1px solid #eee'>" .
              nc_consent_log::add_date_to_links(nc_consent_log::sanitize_label($text), $date) . "</div>"
            : "<span style='color:#888'>" . NETCAT_MODULE_LOGGING_COOKIE_TEXT_UNKNOWN . "</span>";
        $modes = array(
            nc_consent::MODE_ACCEPT_ALL => NETCAT_MODULE_LOGGING_COOKIE_MODE_ACCEPTALL,
            nc_consent::MODE_CATEGORIES => NETCAT_MODULE_LOGGING_COOKIE_MODE_CATEGORIES,
        );
        $site_name = (string)$this->db->get_var("SELECT `Catalogue_Name` FROM `Catalogue` WHERE `Catalogue_ID` = " . (int)$row['Catalogue_ID']);
        $page = '—';
        $u = trim((string)$row['Page_URL']);
        if ($u !== '') {
            $page = htmlspecialchars($u);
            // ссылка — абсолютная, на домен сайта записи (админка может быть открыта
            // на другом домене); путь, похожий на ссылку на другой хост, — без ссылки
            if ($site_name !== '' && $u[0] === '/' && !preg_match('~^/[/\\\\]~', $u)) {
                $u_dated = $nc_core->catalogue->get_url_by_id((int)$row['Catalogue_ID']) . $u
                    . (strpos($u, '?') !== false ? '&' : '?') . 'docdate=' . $date;
                $page = "<a target='_blank' href='" . htmlspecialchars($u_dated) . "'>" . htmlspecialchars($u) . "</a>";
            }
        }

        $fields = array(
            NETCAT_MODULE_LOGGING_CONSENT_COL_DATE      => self::fmt_dt($row['Created']),
            NETCAT_MODULE_LOGGING_COOKIE_COL_DECISION   => self::cookie_decision_label($row['Decision']),
            NETCAT_MODULE_LOGGING_COOKIE_COL_CATEGORIES => self::cookie_categories_label($row['Categories']),
            NETCAT_MODULE_LOGGING_COOKIE_COL_UID        => "<code>" . htmlspecialchars((string)$row['Consent_UID']) . "</code>",
            NETCAT_MODULE_LOGGING_COOKIE_COL_MODE       => nc_array_value($modes, $row['Mode'], htmlspecialchars((string)$row['Mode'])),
            NETCAT_MODULE_LOGGING_COOKIE_COL_TEXT       => $text_html,
            NETCAT_MODULE_LOGGING_COOKIE_COL_SITE       => ($site_name !== '' ? htmlspecialchars($site_name) : '#' . (int)$row['Catalogue_ID']),
            NETCAT_MODULE_LOGGING_COOKIE_COL_PAGE       => $page,
            "IP"                                        => htmlspecialchars((string)$row['IP']),
            NETCAT_MODULE_LOGGING_CONSENT_USER_AGENT    => (htmlspecialchars((string)$row['User_Agent']) ?: '—'),
        );

        echo "<table class='nc-table'>\n";
        foreach ($fields as $k => $v) {
            echo "<tr><th nowrap style='text-align:left;width:25%'>" . $k . "</th><td>" . $v . "</td></tr>\n";
        }
        echo "</table>\n";

        echo $this->site_documents_html((int)$row['Catalogue_ID'], $date);

        // история выбора в этом браузере (тот же ID согласия)
        $history = (array)$this->db->get_results(
            "SELECT `CookieConsentLog_ID`, `Created`, `Decision`, `Categories`
               FROM `" . nc_cookie_consent_log::TABLE . "`
              WHERE `Consent_UID` = '" . $this->db->escape($row['Consent_UID']) . "'
              ORDER BY `Created`, `CookieConsentLog_ID`
              LIMIT 100",
            ARRAY_A
        );
        if (count($history) > 1) {
            echo "<h3 style='margin-top:16px'>" . NETCAT_MODULE_LOGGING_COOKIE_HISTORY . "</h3>\n<table class='nc-table'>\n";
            foreach ($history as $h) {
                $is_current = (int)$h['CookieConsentLog_ID'] === (int)$row['CookieConsentLog_ID'];
                $date_html = $is_current
                    ? "<b>" . self::fmt_dt($h['Created']) . "</b>"
                    : "<a href='" . $current_url . "?phase=13&id=" . (int)$h['CookieConsentLog_ID'] . "'>" . self::fmt_dt($h['Created']) . "</a>";
                echo "<tr><td nowrap>" . $date_html . "</td>" .
                     "<td nowrap>" . self::cookie_decision_label($h['Decision']) . "</td>" .
                     "<td>" . self::cookie_categories_label($h['Categories']) . "</td></tr>\n";
            }
            echo "</table>\n";
        }
    }

    /**
     *
     */
    public function clear_logging_dialog() {
        // system superior object
        $nc_core = nc_Core::get_object();

        $current_url = $nc_core->url->get_parsed_url("path");

        // clear notice
        nc_print_status(NETCAT_MODULE_LOGGING_NOTICE_CLEAR, "info");

        // clear form
        echo "<form method='post' id='CancelForm' action='".$current_url."' style='padding:0; margin:0;'>\n".
        "<input type='hidden' name='phase' value='1'>\n".
        "</form>\n";

        // clear form
        echo "<form method='post' id='ClearForm' action='".$current_url."' style='padding:0; margin:0;'>\n".
        "<input type='hidden' name='phase' value='3'>\n".
        "</form>\n";

        // admin buttons
        $this->UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "align" => "left",
            "caption" => NETCAT_MODULE_LOGGING_BUTTON_CANCEL,
            "action" => "mainView.submitIframeForm('CancelForm')",
        );

        // admin buttons
        $this->UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_LOGGING_BUTTON_CLEAR,
            "action" => "mainView.submitIframeForm('ClearForm')",
            "red_border" => true,
        );
    }

    /**
     *
     */
    public function clear_logging_data() {
        // clear logging data
        $this->db->query("TRUNCATE TABLE `Logging`");
    }

}
?>
