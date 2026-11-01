<?php
return array (
  'sql' => 'CREATE TABLE `Search_Index_Field` (
  `Field_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Weight` decimal(5,2) NOT NULL,
  `Type` tinyint(4) NOT NULL,
  `IsSortable` tinyint(4) NOT NULL DEFAULT \'0\',
  `IsStored` tinyint(4) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Field_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
