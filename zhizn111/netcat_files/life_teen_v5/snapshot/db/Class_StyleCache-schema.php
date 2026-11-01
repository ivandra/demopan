<?php
return array (
  'sql' => 'CREATE TABLE `Class_StyleCache` (
  `Catalogue_ID` int(11) NOT NULL,
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Class_Template_ID` int(11) NOT NULL,
  `LastUpdate` int(11) unsigned NOT NULL DEFAULT \'0\',
  `BlockSelector` varchar(255) NOT NULL DEFAULT \'\',
  `ContainerBreakpoints` varchar(255) NOT NULL DEFAULT \'\',
  `ListObjectBreakpoints` varchar(255) NOT NULL DEFAULT \'\',
  UNIQUE KEY `Class_StyleCache` (`Catalogue_ID`,`Class_ID`,`Class_Template_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
