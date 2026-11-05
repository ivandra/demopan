<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_TypeOfData` (
  `TypeOfData_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeOfData_Name` char(64) NOT NULL DEFAULT \'\',
  `TypeOfData_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`TypeOfData_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8',
);
