<?php
return array (
  'sql' => 'CREATE TABLE `Comments_Rules` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Subdivision_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Access_ID` int(1) unsigned NOT NULL DEFAULT \'0\',
  `Edit_Rule` enum(\'disable\',\'enable\',\'unreplied\') NOT NULL DEFAULT \'disable\',
  `Delete_Rule` enum(\'disable\',\'enable\',\'unreplied\') NOT NULL DEFAULT \'disable\',
  `Message_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
