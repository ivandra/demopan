<?php
return array (
  'sql' => 'CREATE TABLE `Messaging_Service` (
  `Service_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Priority` int(10) DEFAULT \'1\',
  `Checked` tinyint(1) NOT NULL DEFAULT \'1\',
  `Name` varchar(256) NOT NULL,
  `Type` varchar(64) NOT NULL DEFAULT \'sms\',
  `Description` varchar(1024) DEFAULT NULL,
  `Settings` mediumblob,
  PRIMARY KEY (`Service_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
