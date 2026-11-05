<?php
return array (
  'sql' => 'CREATE TABLE `Auth_ExternalAuth` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) DEFAULT NULL,
  `Service` enum(\'openid\',\'vk\',\'fb\',\'twitter\',\'oauth\') DEFAULT NULL,
  `ExternalUser` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`),
  KEY `ExternalUser` (`ExternalUser`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
