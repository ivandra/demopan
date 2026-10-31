<?php
return array (
  'sql' => 'CREATE TABLE `User_Group` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `PermissionGroup_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `PermissionGroup_ID` (`PermissionGroup_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
