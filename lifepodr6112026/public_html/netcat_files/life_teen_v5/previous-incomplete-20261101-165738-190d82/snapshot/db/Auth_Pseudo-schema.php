<?php
return array (
  'sql' => 'CREATE TABLE `Auth_Pseudo` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `Subdivision_ID` int(11) NOT NULL,
  `IP` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`,`Subdivision_ID`),
  KEY `Subdivision_ID` (`Subdivision_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
