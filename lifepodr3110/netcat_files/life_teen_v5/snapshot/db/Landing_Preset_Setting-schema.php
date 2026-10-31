<?php
return array (
  'sql' => 'CREATE TABLE `Landing_Preset_Setting` (
  `Landing_Preset_Setting_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) DEFAULT NULL,
  `Preset_Keyword` varchar(255) DEFAULT NULL,
  `Preset_Settings` mediumblob,
  `Infoblock_ID` int(11) unsigned NOT NULL,
  `Source_Subdivision_ID` int(11) unsigned NOT NULL,
  PRIMARY KEY (`Landing_Preset_Setting_ID`),
  KEY `Preset_Keyword` (`Preset_Keyword`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
