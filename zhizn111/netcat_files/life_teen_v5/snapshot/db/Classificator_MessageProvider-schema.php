<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_MessageProvider` (
  `MessageProvider_ID` int(11) NOT NULL AUTO_INCREMENT,
  `MessageProvider_Name` char(255) NOT NULL,
  `MessageProvider_Priority` int(11) DEFAULT \'1\',
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`MessageProvider_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8',
);
