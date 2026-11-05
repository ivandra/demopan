<?php
return array (
  'sql' => 'CREATE TABLE `Permission` (
  `Permission_ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `AdminType` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `PermissionSet` int(11) NOT NULL DEFAULT \'0\',
  `PermissionGroup_ID` int(11) NOT NULL DEFAULT \'0\',
  `PermissionBegin` datetime DEFAULT NULL,
  `PermissionEnd` datetime DEFAULT NULL,
  PRIMARY KEY (`Permission_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `AdminType` (`AdminType`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
