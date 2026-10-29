<?php

/* $Id: function.inc.php 6207 2012-02-10 10:14:50Z denis $ */
if (!class_exists("nc_System")) die("Unable to load file.");
// get global value (for admin mode)
global $MODULE_FOLDER, $SUB_FOLDER, $HTTP_ROOT_PATH;

// include need classes
include_once nc_module_folder('logging') . 'nc_logging.class.php';
include_once nc_module_folder('logging') . 'nc_consent_log.class.php';
include_once nc_module_folder('logging') . 'nc_cookie_consent_log.class.php';

// instaninate object
nc_logging::get_object();

// Журнал согласий 152-ФЗ: автоматическая регистрация согласий по полям формата
// 'agreement' убрана намеренно — нельзя надёжно угадать состав произвольной формы.
// Логирование выполняется явно на стороне того, кто знает структуру формы:
//   - заказ      → modules/netshop/function.inc.php (EVENT_AFTER_ORDER_CREATED)
//   - регистрация→ modules/auth/function.inc.php (AFTER_USER_CREATED) + api/auth.php
//   - заявки     → modules/requests/function.inc.php (nc_requests::log_form_consent)
//   - кастомные формы → вызов nc_consent_log::log_consent() в действии после
//     добавления компонента (см. docs/proposals/152fz_consent_log.md).