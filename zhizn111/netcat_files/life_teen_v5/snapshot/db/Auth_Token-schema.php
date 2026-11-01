<?php
return array (
  'sql' => 'CREATE TABLE `Auth_Token` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `Login` text,
  `PublicKey` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
