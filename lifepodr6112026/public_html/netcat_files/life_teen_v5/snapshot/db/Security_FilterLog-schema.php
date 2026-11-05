<?php
return array (
  'sql' => 'CREATE TABLE `Security_FilterLog` (
  `Security_FilterLog_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Hash` char(32) DEFAULT NULL,
  `FilterType` varchar(255) DEFAULT NULL,
  `CheckType` varchar(255) DEFAULT NULL,
  `Catalogue_ID` int(11) NOT NULL,
  `URL` text,
  `Referer` text,
  `PostData` text,
  `Backtrace` text,
  `IP` char(15) DEFAULT NULL,
  `ForwardedForIP` varchar(255) DEFAULT NULL,
  `CheckedString` text,
  `ValueSource` varchar(255) DEFAULT NULL,
  `Value` text,
  `EmailAlertSent` tinyint(4) DEFAULT \'0\',
  `SmsAlertSent` tinyint(1) DEFAULT \'0\',
  PRIMARY KEY (`Security_FilterLog_ID`),
  KEY `Hash` (`Hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
