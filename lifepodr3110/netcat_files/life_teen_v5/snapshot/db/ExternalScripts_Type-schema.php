<?php
return array (
  'sql' => 'CREATE TABLE `ExternalScripts_Type` (
  `SnippetType_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `SnippetType_Name` varchar(100) NOT NULL,
  `SnippetType_Class` varchar(100) NOT NULL,
  `SnippetType_Icon` varchar(100) DEFAULT NULL,
  `Priority` int(11) DEFAULT \'100\',
  `Enabled` tinyint(1) DEFAULT \'1\',
  PRIMARY KEY (`SnippetType_ID`),
  UNIQUE KEY `idx_class` (`SnippetType_Class`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8',
);
