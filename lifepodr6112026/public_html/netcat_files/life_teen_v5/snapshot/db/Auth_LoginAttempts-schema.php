<?php
return array (
  'sql' => 'CREATE TABLE `Auth_LoginAttempts` (
  `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IP_Address` varchar(45) NOT NULL,
  `Login` varchar(255) DEFAULT NULL,
  `Attempted` datetime DEFAULT CURRENT_TIMESTAMP,
  `Success` tinyint(1) DEFAULT \'0\',
  PRIMARY KEY (`Attempt_ID`),
  KEY `idx_ip_time` (`IP_Address`,`Attempted`),
  KEY `idx_login_time` (`Login`,`Attempted`),
  KEY `idx_attempted` (`Attempted`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4',
);
