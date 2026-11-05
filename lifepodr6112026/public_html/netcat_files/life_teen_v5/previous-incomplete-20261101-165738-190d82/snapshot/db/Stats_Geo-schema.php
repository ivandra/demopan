<?php
return array (
  'sql' => 'CREATE TABLE `Stats_Geo` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `Country` char(2) NOT NULL DEFAULT \'\',
  `Hosts` int(10) unsigned DEFAULT NULL,
  `Hits` int(11) NOT NULL DEFAULT \'0\',
  `Visitors` int(10) unsigned DEFAULT NULL,
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`Country`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
