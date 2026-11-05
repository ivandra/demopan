<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_Provider` (
  `Provider_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Provider_Name` varchar(100) NOT NULL,
  `Provider_Class` varchar(100) NOT NULL,
  `Provider_Icon` varchar(100) DEFAULT NULL,
  `Priority` int(11) DEFAULT \'100\',
  `Enabled` tinyint(1) DEFAULT \'1\',
  PRIMARY KEY (`Provider_ID`),
  UNIQUE KEY `idx_class` (`Provider_Class`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8',
);
