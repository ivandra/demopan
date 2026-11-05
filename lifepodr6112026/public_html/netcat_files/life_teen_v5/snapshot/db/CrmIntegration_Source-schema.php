<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_Source` (
  `Source_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Source_Type` enum(\'requests\',\'component\',\'infoblock\') NOT NULL,
  `Source_Object_ID` int(10) unsigned DEFAULT NULL,
  `Enabled` tinyint(1) DEFAULT \'1\',
  PRIMARY KEY (`Source_ID`),
  KEY `idx_catalogue` (`Catalogue_ID`,`Source_Type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
