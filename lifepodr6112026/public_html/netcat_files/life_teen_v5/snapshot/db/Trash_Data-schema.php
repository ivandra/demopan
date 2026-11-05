<?php
return array (
  'sql' => 'CREATE TABLE `Trash_Data` (
  `Trash_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Type` int(3) NOT NULL,
  `Message_ID` int(11) NOT NULL,
  `Class_ID` int(11) NOT NULL,
  `Subdivision_ID` int(11) NOT NULL,
  `Sub_Class_ID` int(11) NOT NULL,
  `System_Table_ID` int(3) NOT NULL,
  `Created` datetime NOT NULL,
  `XML_Filename` char(255) DEFAULT NULL,
  `XML_Filesize` int(10) NOT NULL,
  `IP` char(15) DEFAULT NULL,
  `UserAgent` char(255) DEFAULT NULL,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Trash_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Message_ID` (`Message_ID`),
  KEY `Class_ID` (`Class_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
