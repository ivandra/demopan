<?php
return array (
  'sql' => 'CREATE TABLE `Multifield` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Field_ID` int(10) unsigned NOT NULL,
  `Message_ID` int(10) unsigned NOT NULL,
  `Priority` int(10) unsigned DEFAULT NULL,
  `Name` text,
  `Size` int(10) unsigned NOT NULL,
  `Path` text,
  `Preview` text,
  PRIMARY KEY (`ID`),
  KEY `ixField_ID` (`Field_ID`),
  KEY `ixMessage_ID` (`Message_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8',
);
