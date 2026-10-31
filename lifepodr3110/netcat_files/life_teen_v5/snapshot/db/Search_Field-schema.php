<?php
return array (
  'sql' => 'CREATE TABLE `Search_Field` (
  `Name` varchar(255) NOT NULL,
  `Weight` decimal(5,2) unsigned NOT NULL,
  `Type` tinyint(4) NOT NULL DEFAULT \'0\',
  `IsIndexed` tinyint(4) NOT NULL DEFAULT \'1\',
  `IsSearchable` tinyint(4) NOT NULL DEFAULT \'1\',
  `IsNormalized` tinyint(4) NOT NULL DEFAULT \'1\',
  `IsSortable` tinyint(4) NOT NULL DEFAULT \'0\',
  `IsStored` tinyint(4) NOT NULL DEFAULT \'0\',
  `IsRetrievable` tinyint(4) NOT NULL DEFAULT \'0\',
  `Query` text,
  `QueryScope` enum(\'document\',\'content\') DEFAULT \'document\',
  `QueryUseFirstMatched` tinyint(4) NOT NULL,
  `FilterContent` text,
  `RemoveFromParent` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`Name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
