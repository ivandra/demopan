<?php
return array (
  'sql' => 'CREATE TABLE `ConsentLog` (
  `ConsentLog_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Subject_Type` enum(\'user\',\'anonymous\') NOT NULL DEFAULT \'anonymous\',
  `User_ID` int(11) DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Phone` varchar(32) DEFAULT NULL,
  `Consent_Label` text NOT NULL,
  `Consent_Type` varchar(32) DEFAULT NULL,
  `Action` enum(\'grant\',\'withdraw\') NOT NULL DEFAULT \'grant\',
  `Source_Table` varchar(64) DEFAULT NULL,
  `Source_Object_ID` int(11) DEFAULT NULL,
  `Source_Infoblock_ID` int(11) DEFAULT NULL,
  `Source_URL` varchar(500) DEFAULT NULL,
  `IP` varchar(45) DEFAULT NULL,
  `User_Agent` varchar(512) DEFAULT NULL,
  `Created` datetime NOT NULL,
  PRIMARY KEY (`ConsentLog_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Email` (`Email`),
  KEY `Created` (`Created`),
  KEY `Source` (`Source_Table`,`Source_Object_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
