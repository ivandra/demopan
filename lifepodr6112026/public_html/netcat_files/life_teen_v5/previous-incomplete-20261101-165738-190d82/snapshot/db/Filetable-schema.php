<?php
return array (
  'sql' => 'CREATE TABLE `Filetable` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Real_Name` text NOT NULL,
  `Virt_Name` char(32) NOT NULL DEFAULT \'\',
  `File_Path` char(64) NOT NULL DEFAULT \'\',
  `File_Type` char(64) DEFAULT NULL,
  `File_Size` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Message_ID` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Field_ID` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Content_Disposition` int(1) DEFAULT \'0\',
  `Download` int(11) NOT NULL DEFAULT \'0\',
  `Deleted` int(1) DEFAULT \'0\',
  PRIMARY KEY (`ID`),
  KEY `File_Path` (`File_Path`),
  KEY `Field_ID` (`Field_ID`),
  KEY `Message_ID_2` (`Message_ID`,`Field_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
