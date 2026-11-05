<?php
return array (
  'sql' => 'CREATE TABLE `Classificator` (
  `Classificator_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Classificator_Name` char(32) NOT NULL DEFAULT \'\',
  `Table_Name` char(32) NOT NULL DEFAULT \'\',
  `System` smallint(6) NOT NULL DEFAULT \'0\',
  `Sort_Type` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `Sort_Direction` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `CustomSettingsTemplate` text,
  PRIMARY KEY (`Classificator_ID`),
  UNIQUE KEY `Table_Name` (`Table_Name`),
  KEY `System` (`System`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8',
);
