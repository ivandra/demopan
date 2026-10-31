<?php
return array (
  'sql' => 'CREATE TABLE `Cache_Audit` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Subdivision_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Essence` varchar(16) NOT NULL DEFAULT \'\',
  `Query_String` varchar(255) NOT NULL DEFAULT \'\',
  `Unique_String` varchar(32) NOT NULL DEFAULT \'\',
  `Attempt_Write` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Attempt_Read` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Writed` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Readed` int(11) unsigned NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
