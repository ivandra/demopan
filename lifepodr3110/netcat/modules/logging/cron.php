<?php

/**
 * Cron-задача модуля «Логирование» (раз в сутки, CronTasks):
 * очистка журнала выбора в окне cookie-согласия по сроку хранения сайта,
 * записи удалённых сайтов, устаревшие счётчики лимитов.
 *
 * Работает независимо от того, включён ли сейчас журнал на сайте: записи,
 * накопленные до выключения, тоже удаляются по последнему сохранённому сроку.
 *
 * @see nc_cookie_consent_log::cleanup()
 * @see docs/proposals/cookie-consent-log.md
 */

require_once __DIR__ . '/../../require/cron_api.inc.php';

@set_time_limit(120);
ignore_user_abort(true);

require_once __DIR__ . '/nc_cookie_consent_log.class.php';

$result = nc_cookie_consent_log::cleanup();

echo 'Cookie consent log: sites ' . $result['sites']
    . ', deleted ' . $result['deleted']
    . ', rate rows deleted ' . $result['rate_deleted']
    . ($result['complete'] ? '' : ' (incomplete, will continue next run)')
    . "\n";
