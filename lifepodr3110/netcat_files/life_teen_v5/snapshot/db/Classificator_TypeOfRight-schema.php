<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_TypeOfRight` (
  `TypeOfRight_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeOfRight_Name` char(64) NOT NULL DEFAULT \'\',
  `TypeOfRight_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`TypeOfRight_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8',
);
