<?php
return array (
  'sql' => 'CREATE TABLE `Class_Multipurpose_Template_Cache` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Class_ID` int(11) NOT NULL,
  `Compatible_Class_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Compatibility` (`Class_ID`,`Compatible_Class_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=5435 DEFAULT CHARSET=utf8',
);
