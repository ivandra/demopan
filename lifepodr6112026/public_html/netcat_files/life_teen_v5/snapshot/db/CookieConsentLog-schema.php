<?php
return array (
  'sql' => 'CREATE TABLE `CookieConsentLog` (
  `CookieConsentLog_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Consent_UID` char(36) NOT NULL,
  `Decision` enum(\'accept\',\'reject\',\'partial\') NOT NULL,
  `Categories` varchar(100) NOT NULL,
  `Mode` varchar(16) NOT NULL,
  `Text_Hash` char(40) DEFAULT NULL,
  `Page_URL` varchar(255) DEFAULT NULL,
  `IP` varchar(45) DEFAULT NULL,
  `IP_Key` varchar(45) DEFAULT NULL,
  `User_Agent` varchar(256) DEFAULT NULL,
  `Created` datetime NOT NULL,
  PRIMARY KEY (`CookieConsentLog_ID`),
  KEY `Consent_UID` (`Consent_UID`),
  KEY `Created` (`Created`),
  KEY `Site_Created` (`Catalogue_ID`,`Created`),
  KEY `Site_IP` (`Catalogue_ID`,`IP`),
  KEY `IP` (`IP`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
