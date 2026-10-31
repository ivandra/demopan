<?php
return array (
  'sql' => 'CREATE TABLE `Version_ContentHistory` (
  `Version_ContentHistory_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Version_ID` int(11) unsigned NOT NULL,
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Subdivision_ID` int(11) NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) NOT NULL DEFAULT \'0\',
  `State` enum(\'previous\',\'actual\',\'next\') DEFAULT NULL,
  PRIMARY KEY (`Version_ContentHistory_ID`),
  KEY `Version_ID` (`Version_ID`),
  KEY `Location` (`Catalogue_ID`,`Subdivision_ID`,`Sub_Class_ID`,`Message_ID`,`State`),
  KEY `Subdivision_ID` (`Subdivision_ID`,`State`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`,`State`),
  KEY `Message` (`Sub_Class_ID`,`Message_ID`,`State`)
) ENGINE=MyISAM AUTO_INCREMENT=570 DEFAULT CHARSET=utf8',
);
