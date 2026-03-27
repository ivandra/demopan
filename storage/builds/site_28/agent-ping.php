<?php
// /agent-ping.php
require_once __DIR__ . '/link_agent.php';
$src = isset($_GET['from']) ? (string)$_GET['from'] : 'user';
$src = in_array($src, ['user','cron','panel'], true) ? $src : 'user';
la_sync($src);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo "ok\n";
