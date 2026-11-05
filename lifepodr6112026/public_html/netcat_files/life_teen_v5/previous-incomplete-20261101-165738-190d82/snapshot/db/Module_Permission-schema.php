<?php
return array (
  'sql' => 'CREATE TABLE `Module_Permission` (
  `Module_Permission_ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `PermissionGroup_ID` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Module_Keyword` varchar(255) DEFAULT NULL,
  `PermissionType` varchar(255) DEFAULT NULL,
  `PermissionBegin` datetime DEFAULT NULL,
  `PermissionEnd` datetime DEFAULT NULL,
  PRIMARY KEY (`Module_Permission_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `PermissionGroup_ID` (`PermissionGroup_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
