<?php
return array (
  'sql' => 'CREATE TABLE `Auth_UserRelation` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `Related_ID` int(11) NOT NULL DEFAULT \'0\',
  `Type` int(11) NOT NULL DEFAULT \'0\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `Related_ID` (`Related_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
