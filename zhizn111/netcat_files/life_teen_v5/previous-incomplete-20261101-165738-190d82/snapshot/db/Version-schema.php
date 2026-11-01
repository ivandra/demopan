<?php
return array (
  'sql' => 'CREATE TABLE `Version` (
  `Version_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Version_Changeset_ID` int(11) unsigned NOT NULL,
  `Previous_Version_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Restored_Version_ID` int(11) unsigned NOT NULL DEFAULT \'0\',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Action` enum(\'initial\',\'created\',\'enabled\',\'disabled\',\'updated\',\'deleted\') NOT NULL,
  `Entity` enum(\'site\',\'subdivision\',\'infoblock\',\'object\') NOT NULL,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Subdivision_ID` int(11) NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) NOT NULL DEFAULT \'0\',
  `IsActual` tinyint(1) DEFAULT \'0\',
  `Snapshot` mediumblob NOT NULL,
  PRIMARY KEY (`Version_ID`),
  KEY `Version_Changeset_ID` (`Version_Changeset_ID`),
  KEY `Previous_Version_ID` (`Previous_Version_ID`),
  KEY `Entity_Catalogue_ID` (`Entity`,`Catalogue_ID`),
  KEY `Entity_Subdivision_ID` (`Entity`,`Subdivision_ID`),
  KEY `Entity_Sub_Class_ID` (`Entity`,`Sub_Class_ID`),
  KEY `Entity_Sub_Class_ID_Message_ID` (`Entity`,`Sub_Class_ID`,`Message_ID`),
  KEY `Entity_Class_ID_Message_ID` (`Entity`,`Class_ID`,`Message_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=570 DEFAULT CHARSET=utf8',
);
