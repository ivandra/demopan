<?php
return array (
  'sql' => 'CREATE TABLE `Version_File` (
  `Version_File_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Version_ID` int(11) unsigned NOT NULL,
  `Field_Name` varchar(255) NOT NULL,
  `File_Path` text,
  `File_Hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`Version_File_ID`),
  KEY `Version_ID` (`Version_ID`),
  KEY `File_Hash` (`File_Hash`)
) ENGINE=MyISAM AUTO_INCREMENT=80 DEFAULT CHARSET=utf8',
);
