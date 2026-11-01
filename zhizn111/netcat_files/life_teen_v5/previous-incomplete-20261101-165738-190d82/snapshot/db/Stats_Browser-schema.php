<?php
return array (
  'sql' => 'CREATE TABLE `Stats_Browser` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `Browser` char(32) NOT NULL DEFAULT \'\',
  `Visitors` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`Browser`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
