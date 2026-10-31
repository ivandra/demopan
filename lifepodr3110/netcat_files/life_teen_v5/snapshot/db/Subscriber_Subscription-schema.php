<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_Subscription` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) DEFAULT NULL,
  `Mailer_ID` int(11) DEFAULT NULL,
  `Status` enum(\'on\',\'off\',\'wait\') NOT NULL DEFAULT \'on\',
  `Period` int(11) NOT NULL,
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `LastSend` timestamp NULL DEFAULT \'0000-00-00 00:00:00\',
  `Hash` varchar(255) NOT NULL,
  `Count` int(11) DEFAULT NULL,
  `NextSend` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Mailer_ID` (`Mailer_ID`),
  KEY `User_ID_2` (`User_ID`,`Mailer_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
