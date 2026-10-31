<?php
return array (
  'sql' => 'CREATE TABLE `Cache_Settings` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Audit_Begin` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `Audit_Time` smallint(6) unsigned NOT NULL DEFAULT \'0\',
  `Status_list` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Quota_list` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Overdraft_list` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Status_full` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Quota_full` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Overdraft_full` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Status_browse` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Quota_browse` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Overdraft_browse` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Status_calendar` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Quota_calendar` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Overdraft_calendar` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Status_function` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `Quota_function` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Overdraft_function` tinyint(4) unsigned NOT NULL DEFAULT \'0\',
  `IO_Interface` varchar(255) DEFAULT \'file\',
  `Memcached_Host` varchar(255) DEFAULT \'localhost\',
  `Memcached_Port` int(11) DEFAULT \'11211\',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Catalogue_ID` (`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
