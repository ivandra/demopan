<?php
return array (
  'sql' => 'CREATE TABLE `Logging` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Event` varchar(32) NOT NULL,
  `Date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `User_ID` int(10) unsigned NOT NULL,
  `IP` char(15) DEFAULT NULL,
  `ForwardedForIP` char(255) DEFAULT NULL,
  `Args` blob,
  `Info` text NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8',
);
