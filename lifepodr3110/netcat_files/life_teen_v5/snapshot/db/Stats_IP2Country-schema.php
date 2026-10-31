<?php
return array (
  'sql' => 'CREATE TABLE `Stats_IP2Country` (
  `IP_Net` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `IP_Range_Start` int(10) unsigned NOT NULL DEFAULT \'0\',
  `IP_Range_End` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Country` char(2) DEFAULT NULL,
  KEY `IP_Net` (`IP_Net`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
