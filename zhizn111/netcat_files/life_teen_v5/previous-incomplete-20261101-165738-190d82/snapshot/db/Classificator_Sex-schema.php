<?php
return array (
  'sql' => 'CREATE TABLE `Classificator_Sex` (
  `Sex_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Sex_Name` char(255) NOT NULL DEFAULT \'\',
  `Sex_Priority` int(11) DEFAULT NULL,
  `Value` text,
  `Checked` int(1) DEFAULT \'1\',
  `CustomSettings` mediumtext,
  PRIMARY KEY (`Sex_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8',
);
