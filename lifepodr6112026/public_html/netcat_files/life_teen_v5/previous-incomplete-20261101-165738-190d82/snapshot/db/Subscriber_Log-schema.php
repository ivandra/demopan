<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_Log` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Mailer_ID` int(11) DEFAULT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `ActionTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ActionType` enum(\'mail\',\'subscribe\',\'unsubscribe\',\'confirm\') DEFAULT \'mail\',
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Mailer_ID_2` (`Mailer_ID`,`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
