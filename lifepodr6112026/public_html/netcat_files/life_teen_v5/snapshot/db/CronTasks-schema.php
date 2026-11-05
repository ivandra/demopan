<?php
return array (
  'sql' => 'CREATE TABLE `CronTasks` (
  `Cron_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Cron_Minutes` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Cron_Hours` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Cron_Days` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Cron_Months` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Cron_Weekdays` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `Cron_Script_URL` varchar(255) NOT NULL DEFAULT \'\',
  `Cron_Launch` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`Cron_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8',
);
