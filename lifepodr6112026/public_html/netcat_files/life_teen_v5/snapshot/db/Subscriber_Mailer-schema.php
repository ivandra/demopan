<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_Mailer` (
  `Mailer_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` text,
  `Sub_Class_ID` int(11) DEFAULT \'0\',
  `Access` int(11) DEFAULT NULL,
  `Type` int(11) DEFAULT \'1\',
  `Active` int(11) DEFAULT \'1\',
  `InStat` int(11) DEFAULT \'0\',
  `Header` text,
  `Footer` text,
  `Record` text,
  `SubscribeCond` text,
  `SendCond` text,
  `SubscribeAction` text,
  `Period` int(11) DEFAULT NULL,
  `Subject` text,
  `HTML` int(1) NOT NULL DEFAULT \'0\',
  `ActionType` int(11) NOT NULL DEFAULT \'0\',
  `MailCount` int(11) NOT NULL DEFAULT \'0\',
  `LastSend` timestamp NULL DEFAULT NULL,
  `PeriodMask` varchar(256) DEFAULT NULL,
  `Class_ID` int(11) DEFAULT \'0\',
  PRIMARY KEY (`Mailer_ID`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
