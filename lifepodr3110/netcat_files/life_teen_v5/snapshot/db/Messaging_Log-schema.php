<?php
return array (
  'sql' => 'CREATE TABLE `Messaging_Log` (
  `Log_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Service_ID` int(10) DEFAULT \'-1\',
  `Message_ID` int(10) unsigned DEFAULT NULL,
  `Log_Message` varchar(1024) NOT NULL,
  `Message` varchar(1024) NOT NULL,
  `Recipients` varchar(512) DEFAULT NULL,
  `Messaging_Type` varchar(255) DEFAULT \'sms\',
  `Event_Type` varchar(255) DEFAULT \'system\',
  `Status` varchar(255) DEFAULT \'success\',
  `Details` mediumblob,
  PRIMARY KEY (`Log_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
