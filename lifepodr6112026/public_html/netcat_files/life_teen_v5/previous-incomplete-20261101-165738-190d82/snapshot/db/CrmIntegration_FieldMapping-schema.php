<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_FieldMapping` (
  `Mapping_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Source_Type` enum(\'requests\',\'component\',\'custom\') NOT NULL,
  `Source_ID` int(10) unsigned DEFAULT NULL,
  `Netcat_Field` varchar(100) NOT NULL,
  `Crm_Field` varchar(100) NOT NULL,
  `Transform` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`Mapping_ID`),
  KEY `idx_mapping` (`Catalogue_ID`,`Provider_ID`,`Source_Type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
