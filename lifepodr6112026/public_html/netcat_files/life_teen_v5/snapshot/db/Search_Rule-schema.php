<?php
return array (
  'sql' => 'CREATE TABLE `Search_Rule` (
  `Rule_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Catalogue_ID` int(11) DEFAULT NULL,
  `AreaString` text,
  `Interval` smallint(6) DEFAULT NULL,
  `IntervalType` varchar(255) NOT NULL,
  `Hour` tinyint(4) DEFAULT NULL,
  `Minute` tinyint(4) DEFAULT NULL,
  `LastStartTime` int(11) DEFAULT NULL,
  `LastFinishTime` int(11) DEFAULT NULL,
  `LastResult` blob NOT NULL,
  PRIMARY KEY (`Rule_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
