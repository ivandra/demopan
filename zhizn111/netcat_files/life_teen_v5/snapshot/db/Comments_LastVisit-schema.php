<?php
return array (
  'sql' => 'CREATE TABLE `Comments_LastVisit` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) NOT NULL DEFAULT \'0\',
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `CommentsLastVisit` (`User_ID`,`Class_ID`,`Message_ID`),
  KEY `Class_ID` (`Class_ID`),
  KEY `Message_ID` (`Message_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
