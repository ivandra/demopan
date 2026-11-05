<?php
return array (
  'sql' => 'CREATE TABLE `Widget_Field` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Widget_ID` int(11) NOT NULL DEFAULT \'0\',
  `Field_ID` int(11) NOT NULL DEFAULT \'0\',
  `Value` text,
  PRIMARY KEY (`ID`),
  KEY `Widget_ID` (`Widget_ID`),
  KEY `Field_ID` (`Field_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
