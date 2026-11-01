<?php
return array (
  'sql' => 'CREATE TABLE `Stats_OS` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `OS` char(32) NOT NULL DEFAULT \'\',
  `Visitors` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`OS`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
