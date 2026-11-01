<?php
return array (
  'sql' => 'CREATE TABLE `Csv_Import_History` (
  `History_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `Rollbacked` int(1) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`History_ID`),
  KEY `Class_ID` (`Class_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
