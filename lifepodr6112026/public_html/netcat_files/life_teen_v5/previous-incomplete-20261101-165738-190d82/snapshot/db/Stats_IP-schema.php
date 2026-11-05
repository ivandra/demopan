<?php
return array (
  'sql' => 'CREATE TABLE `Stats_IP` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `IP` char(15) NOT NULL DEFAULT \'\',
  `Hits` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`IP`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
