<?php
return array (
  'sql' => 'CREATE TABLE `Block_Widget` (
  `Block_Widget_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) NOT NULL,
  `Priority` int(11) NOT NULL,
  `Checked` smallint(6) NOT NULL DEFAULT \'1\',
  `Widget_ID` int(11) NOT NULL,
  `Widget_Class_ID` int(11) NOT NULL,
  `Block_Key` varchar(64) NOT NULL DEFAULT \'\',
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Widget_Settings` text,
  PRIMARY KEY (`Block_Widget_ID`),
  KEY `Catalogue_ID` (`Catalogue_ID`),
  KEY `Block_Key` (`Block_Key`),
  KEY `Widget_ID` (`Widget_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
