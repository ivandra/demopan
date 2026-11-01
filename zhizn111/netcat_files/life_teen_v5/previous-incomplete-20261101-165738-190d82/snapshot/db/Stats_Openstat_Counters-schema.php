<?php
return array (
  'sql' => 'CREATE TABLE `Stats_Openstat_Counters` (
  `Counter_Id` int(11) NOT NULL AUTO_INCREMENT,
  `Openstat_Counter_Id` int(11) DEFAULT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `Rating` tinyint(4) DEFAULT NULL,
  `Advert` tinyint(4) DEFAULT NULL,
  `Color` int(11) DEFAULT NULL,
  `Size` int(11) DEFAULT NULL,
  `TrackLinks` char(7) DEFAULT NULL,
  `Counter_Code` text,
  `User_Counter_Code` tinyint(4) DEFAULT \'0\',
  `Catalogue_Id` int(11) NOT NULL DEFAULT \'0\',
  `LastUpdated` datetime DEFAULT NULL,
  `Created` datetime NOT NULL,
  PRIMARY KEY (`Counter_Id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
