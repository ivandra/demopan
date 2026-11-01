<?php
return array (
  'sql' => 'CREATE TABLE `Template` (
  `Template_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Keyword` varchar(64) NOT NULL DEFAULT \'\',
  `Description` varchar(64) NOT NULL DEFAULT \'\',
  `Parent_Template_ID` int(11) NOT NULL DEFAULT \'0\',
  `Settings` text NOT NULL,
  `CustomSettings` text,
  `Header` text NOT NULL,
  `Footer` text NOT NULL,
  `File_Mode` tinyint(1) NOT NULL DEFAULT \'0\',
  `File_Path` varchar(255) DEFAULT NULL,
  `File_Hash` char(32) NOT NULL DEFAULT \'\',
  `Priority` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Template_ID`),
  KEY `Keyword` (`Keyword`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 PACK_KEYS=0',
);
