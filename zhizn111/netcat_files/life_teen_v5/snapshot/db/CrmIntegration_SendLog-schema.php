<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_SendLog` (
  `Log_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Source_Type` varchar(50) NOT NULL,
  `Source_Object_ID` int(10) unsigned NOT NULL,
  `Component_ID` int(10) unsigned DEFAULT NULL,
  `Status` tinyint(4) NOT NULL,
  `External_ID` varchar(255) DEFAULT NULL,
  `Retry_Count` tinyint(4) DEFAULT \'0\',
  `Request_Data` json DEFAULT NULL,
  `Response_Data` json DEFAULT NULL,
  `Error_Message` text,
  `Created` datetime DEFAULT CURRENT_TIMESTAMP,
  `Updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Log_ID`),
  KEY `idx_status` (`Catalogue_ID`,`Status`),
  KEY `idx_created` (`Created`),
  KEY `idx_source` (`Source_Object_ID`,`Component_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
