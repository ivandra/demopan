<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_SubscriberPeriod` (
  `SubscriberPeriod_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SubscriberPeriod_Name` char(255) NOT NULL DEFAULT \'\',
  `SubscriberPeriod_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`SubscriberPeriod_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8',
);
