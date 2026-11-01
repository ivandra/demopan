<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_TypeOfModeration` (
  `TypeOfModeration_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeOfModeration_Name` char(64) NOT NULL DEFAULT \'\',
  `TypeOfModeration_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`TypeOfModeration_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8',
);
