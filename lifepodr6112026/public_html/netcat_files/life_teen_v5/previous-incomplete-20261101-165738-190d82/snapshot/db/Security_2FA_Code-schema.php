<?php
return array (
  'sql' => 'CREATE TABLE `Security_2FA_Code` (
  `Security_2FA_Code_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) unsigned NOT NULL,
  `Code` varchar(255) DEFAULT NULL,
  `Created` int(11) unsigned NOT NULL,
  `Purpose` varchar(255) DEFAULT NULL,
  `LastAttempt` int(11) unsigned DEFAULT \'0\',
  `AttemptCount` int(11) NOT NULL DEFAULT \'0\',
  `IsSent` tinyint(1) DEFAULT \'1\',
  `Hint` mediumtext,
  PRIMARY KEY (`Security_2FA_Code_ID`),
  UNIQUE KEY `User_ID` (`User_ID`,`Purpose`),
  KEY `Created` (`Created`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
