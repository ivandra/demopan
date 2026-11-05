<?php
return array (
  'sql' => 'CREATE TABLE `Search_Schedule` (
  `Schedule_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Type` tinyint(4) NOT NULL,
  `AreaString` text NOT NULL,
  `StartTime` int(11) NOT NULL,
  PRIMARY KEY (`Schedule_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
