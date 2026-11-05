<?php
return array (
  'sql' => 'CREATE TABLE `Search_Task` (
  `Task_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `RunnerType` tinyint(3) unsigned DEFAULT NULL,
  `Links` mediumblob NOT NULL,
  `Area` mediumblob NOT NULL,
  `Disallowed` mediumblob,
  `Rule_ID` int(11) DEFAULT NULL,
  `StartTime` int(11) DEFAULT NULL,
  `TotalProcessed` int(11) DEFAULT \'0\',
  `TotalChecked` int(11) DEFAULT \'0\',
  `TotalNotFound` int(11) DEFAULT \'0\',
  `TotalDeleted` int(11) DEFAULT \'0\',
  `Token` int(11) NOT NULL,
  `LastActivity` int(11) DEFAULT NULL,
  `IsIdle` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Task_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
