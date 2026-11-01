<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_Provider_Setting` (
  `Setting_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Param_Name` varchar(100) NOT NULL,
  `Param_Value` text,
  PRIMARY KEY (`Setting_ID`),
  UNIQUE KEY `idx_unique` (`Catalogue_ID`,`Provider_ID`,`Param_Name`),
  KEY `idx_provider` (`Provider_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
