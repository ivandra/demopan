<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_ContactType` (
  `ContactType_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ContactType_Name` char(255) NOT NULL DEFAULT \'\',
  `ContactType_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`ContactType_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8',
);
