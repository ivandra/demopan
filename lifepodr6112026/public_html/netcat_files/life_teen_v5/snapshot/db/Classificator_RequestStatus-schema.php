<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_RequestStatus` (
  `RequestStatus_ID` int(11) NOT NULL AUTO_INCREMENT,
  `RequestStatus_Name` char(255) NOT NULL DEFAULT \'\',
  `RequestStatus_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`RequestStatus_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8',
);
