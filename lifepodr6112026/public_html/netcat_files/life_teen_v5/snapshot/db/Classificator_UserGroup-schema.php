<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_UserGroup` (
  `UserGroup_ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserGroup_Name` char(64) NOT NULL DEFAULT \'\',
  `UserGroup_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`UserGroup_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8',
);
