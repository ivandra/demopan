<?php
return array (
  'sql' => 'CREATE TABLE `Filter` (
  `Filter_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Filter_Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Data_Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Settings` text NOT NULL,
  PRIMARY KEY (`Filter_ID`),
  KEY `Filter_Sub_Class_ID` (`Filter_Sub_Class_ID`),
  KEY `Data_Sub_Class_ID` (`Data_Sub_Class_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
