<?php
return array (
  'sql' => 'CREATE TABLE `Module` (
  `Module_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Module_Name` varchar(255) NOT NULL DEFAULT \'\',
  `Keyword` varchar(255) NOT NULL DEFAULT \'\',
  `Description` text NOT NULL,
  `Parameters` text NOT NULL,
  `Example_URL` varchar(255) NOT NULL DEFAULT \'\',
  `Help_URL` varchar(255) NOT NULL DEFAULT \'\',
  `Installed` tinyint(4) NOT NULL DEFAULT \'0\',
  `Number` varchar(16) NOT NULL DEFAULT \'\',
  `Inside_Admin` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `PerSitePermissions` tinyint(3) unsigned NOT NULL DEFAULT \'0\',
  `Checked` tinyint(4) unsigned NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`Module_ID`),
  KEY `Checked` (`Checked`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8',
);
