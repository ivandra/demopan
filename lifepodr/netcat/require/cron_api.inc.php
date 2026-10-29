<?php

/* Cron API
 *
 * Данный файл должен подключаться для каждого cron задания, перед его выполнением
 * Данный файл инициализирует контекст работы с системой Netcat и осуществляет проверку соответствия переданного get параметра cron_key
 */

$NETCAT_FOLDER = realpath(__DIR__ . "/../../");

require_once($NETCAT_FOLDER . "/vars.inc.php");
require_once($ROOT_FOLDER . "connect_io.php");

if (!isset($nc_core)) {
    $nc_core = nc_core::get_object();
}

$system_key = $nc_core->get_settings("SecretKey");
$request_key = $nc_core->input->fetch_get_post("cron_key") ?: $nc_core->input->fetch_get_post("secret_key");

if ($system_key === "" || $system_key !== $request_key) {
    die("Access denied");
}

$nc_core->modules->load_env();
