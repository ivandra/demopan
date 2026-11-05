<?php
return array (
  'sql' => 'CREATE TABLE `Component_Revisions` (
  `Revision_ID` int(11) NOT NULL AUTO_INCREMENT,
  `File` varchar(255) NOT NULL DEFAULT \'\',
  `Subdivision_ID` int(11) NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  PRIMARY KEY (`Revision_ID`),
  KEY `Subdivision_ID` (`Subdivision_ID`),
  KEY `Class_ID` (`Class_ID`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`),
  KEY `Message_ID` (`Message_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
