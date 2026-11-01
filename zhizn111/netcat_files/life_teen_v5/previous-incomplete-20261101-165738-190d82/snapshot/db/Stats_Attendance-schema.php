<?php
return array (
  'sql' => 'CREATE TABLE `Stats_Attendance` (
  `Date` date NOT NULL DEFAULT \'0000-00-00\',
  `Hour` tinyint(4) NOT NULL DEFAULT \'0\',
  `Hosts` int(11) NOT NULL DEFAULT \'0\',
  `Hits` int(11) NOT NULL DEFAULT \'0\',
  `Visitors` int(11) NOT NULL DEFAULT \'0\',
  `NewHosts` int(11) NOT NULL DEFAULT \'0\',
  `NewVisitors` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Date`,`Hour`,`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
