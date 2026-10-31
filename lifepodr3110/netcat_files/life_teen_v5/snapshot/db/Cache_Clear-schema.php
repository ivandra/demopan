<?php
return array (
  'sql' => 'CREATE TABLE `Cache_Clear` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Subdivision_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Essence` varchar(16) NOT NULL DEFAULT \'\',
  `Efficiency` float unsigned NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
