<?php
return array (
  'sql' => 'CREATE TABLE `Documentation` (
  `Documentation_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Type` enum(\'function\',\'variable\',\'macros\',\'method\',\'static\') NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Signature` text NOT NULL,
  `ShortHelp` text NOT NULL,
  `FullHelp` text NOT NULL,
  `Module_ID` int(11) DEFAULT NULL,
  `Parent` varchar(255) NOT NULL,
  `Areas` varchar(255) NOT NULL,
  PRIMARY KEY (`Documentation_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=185 DEFAULT CHARSET=utf8',
);
