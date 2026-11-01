<?php
return array (
  'sql' => 'CREATE TABLE `Search_Extension` (
  `Rule_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ExtensionInterface` varchar(255) NOT NULL,
  `ExtensionClass` varchar(255) NOT NULL,
  `SearchProvider` varchar(255) DEFAULT NULL,
  `Action` varchar(255) DEFAULT NULL,
  `Language` varchar(255) DEFAULT NULL,
  `ContentType` varchar(255) DEFAULT NULL,
  `Priority` tinyint(5) unsigned NOT NULL DEFAULT \'127\',
  `Checked` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`Rule_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8',
);
