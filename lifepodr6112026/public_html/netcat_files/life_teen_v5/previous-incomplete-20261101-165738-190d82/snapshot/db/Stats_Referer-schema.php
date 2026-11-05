<?php
return array (
  'sql' => 'CREATE TABLE `Stats_Referer` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `Referer` char(255) NOT NULL DEFAULT \'\',
  `Hosts` int(11) NOT NULL DEFAULT \'0\',
  `Hits` int(11) NOT NULL DEFAULT \'0\',
  `Visitors` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`Referer`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
