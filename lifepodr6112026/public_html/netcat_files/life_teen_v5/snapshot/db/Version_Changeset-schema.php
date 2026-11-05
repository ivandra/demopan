<?php
return array (
  'sql' => 'CREATE TABLE `Version_Changeset` (
  `Version_Changeset_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ChangeCount` smallint(5) unsigned DEFAULT \'0\',
  `Description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Version_Changeset_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8',
);
