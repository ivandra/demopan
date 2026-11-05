<?php
return array (
  'sql' => 'CREATE TABLE `Landing_Page` (
  `Landing_Page_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Preset_Keyword` varchar(255) DEFAULT NULL,
  `Site_ID` int(11) unsigned NOT NULL,
  `Subdivision_ID` int(11) unsigned NOT NULL,
  `Source_Component_ID` int(11) unsigned DEFAULT NULL,
  `Source_Object_ID` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`Landing_Page_ID`),
  KEY `Site_ID` (`Site_ID`),
  KEY `Preset_Keyword` (`Preset_Keyword`),
  KEY `Subdivision_ID` (`Subdivision_ID`),
  KEY `Source` (`Source_Component_ID`,`Source_Object_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
