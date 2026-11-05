<?php
return array (
  'sql' => 'CREATE TABLE `ExternalScripts_Provider` (
  `Provider_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Label` varchar(255) NOT NULL,
  `Pattern` varchar(255) NOT NULL,
  `IsRegex` tinyint(1) NOT NULL DEFAULT \'0\',
  `Category` varchar(20) NOT NULL DEFAULT \'functional\',
  `Priority` int(11) NOT NULL DEFAULT \'100\',
  `Enabled` tinyint(1) NOT NULL DEFAULT \'1\',
  `Created` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`Provider_ID`),
  UNIQUE KEY `idx_pattern` (`Pattern`),
  KEY `idx_enabled_priority` (`Enabled`,`Priority`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8',
);
