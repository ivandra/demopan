<?php
return array (
  'sql' => 'CREATE TABLE `ExternalScripts_Snippet` (
  `Snippet_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `SnippetType_ID` int(10) unsigned DEFAULT NULL,
  `Name` varchar(255) NOT NULL,
  `Settings` mediumtext,
  `Conditions` mediumtext,
  `Priority` int(11) DEFAULT \'500\',
  `Enabled` tinyint(1) DEFAULT \'1\',
  `Created` timestamp NULL DEFAULT NULL,
  `Updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Snippet_ID`),
  KEY `idx_catalogue_enabled` (`Catalogue_ID`,`Enabled`),
  KEY `idx_priority` (`Priority`),
  KEY `fk_snippet_type` (`SnippetType_ID`),
  CONSTRAINT `fk_snippet_type` FOREIGN KEY (`SnippetType_ID`) REFERENCES `ExternalScripts_Type` (`SnippetType_ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
