<?php
return array (
  'sql' => 'CREATE TABLE `Routing_Route` (
  `Route_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Site_ID` int(10) unsigned NOT NULL,
  `Description` text,
  `AllowGET` tinyint(1) NOT NULL DEFAULT \'1\',
  `AllowPOST` tinyint(1) NOT NULL DEFAULT \'1\',
  `IsBuiltin` tinyint(1) NOT NULL DEFAULT \'1\',
  `Pattern` text NOT NULL,
  `CompiledPattern` mediumblob,
  `ResourceType` varchar(255) NOT NULL,
  `ResourceParameters` blob,
  `QueryVariables` mediumblob,
  `QueryVariablesRequiredForCanonical` tinyint(3) unsigned NOT NULL DEFAULT \'1\',
  `Priority` int(10) unsigned NOT NULL DEFAULT \'0\',
  `Enabled` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`Route_ID`),
  KEY `SiteRoutes` (`Site_ID`,`Enabled`,`Priority`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
