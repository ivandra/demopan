<?php
return array (
  'sql' => 'CREATE TABLE `Mixin_Preset` (
  `Mixin_Preset_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Mixin_Preset_Name` varchar(255) DEFAULT NULL,
  `Mixin_Settings` blob,
  `Class_Template_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Scope` enum(\'Index\',\'IndexItem\',\'Full\') NOT NULL DEFAULT \'Index\',
  PRIMARY KEY (`Mixin_Preset_ID`),
  KEY `Class_Template_ID` (`Class_Template_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8',
);
