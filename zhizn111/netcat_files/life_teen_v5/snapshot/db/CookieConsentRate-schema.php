<?php
return array (
  'sql' => 'CREATE TABLE `CookieConsentRate` (
  `Catalogue_ID` int(11) NOT NULL,
  `IP_Key` varchar(45) NOT NULL,
  `Period` int(10) unsigned NOT NULL,
  `Hits` int(10) unsigned NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`Catalogue_ID`,`IP_Key`,`Period`),
  KEY `Period` (`Period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
