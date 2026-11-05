<?php
return array (
  'sql' => 'CREATE TABLE `Widget` (
  `Widget_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Widget_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Name` varchar(255) NOT NULL,
  `Keyword` varchar(255) NOT NULL,
  `Result` text NOT NULL,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `LastUser_ID` int(11) NOT NULL DEFAULT \'0\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Widget_ID`),
  KEY `Widget_Class_ID` (`Widget_Class_ID`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
