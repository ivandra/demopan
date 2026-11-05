<?php
return array (
  'sql' => 'CREATE TABLE `Module_Catalog` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Module_ID` int(11) NOT NULL,
  `Catalogue_ID` int(11) NOT NULL,
  `Inside_Admin` int(4) NOT NULL,
  `Checked` int(4) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Module_ID` (`Module_ID`),
  KEY `Catalogue_ID` (`Catalogue_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
