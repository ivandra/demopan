<?php
return array (
  'sql' => 'CREATE TABLE `Settings` (
  `Settings_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Key` varchar(255) NOT NULL,
  `Value` text NOT NULL,
  `Module` varchar(255) NOT NULL DEFAULT \'system\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Settings_ID`),
  KEY `Catalogue_ID` (`Catalogue_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=211 DEFAULT CHARSET=utf8',
);
