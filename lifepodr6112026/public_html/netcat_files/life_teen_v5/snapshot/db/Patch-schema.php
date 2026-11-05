<?php
return array (
  'sql' => 'CREATE TABLE `Patch` (
  `Patch_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Patch_Name` char(32) NOT NULL DEFAULT \'\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `Description` char(255) NOT NULL DEFAULT \'\',
  PRIMARY KEY (`Patch_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
