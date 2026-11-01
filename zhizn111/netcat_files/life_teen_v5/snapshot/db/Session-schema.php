<?php
return array (
  'sql' => 'CREATE TABLE `Session` (
  `Session_ID` char(32) NOT NULL DEFAULT \'\',
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `SessionStart` int(11) NOT NULL DEFAULT \'0\',
  `SessionTime` int(11) NOT NULL DEFAULT \'0\',
  `UserIP` bigint(11) NOT NULL DEFAULT \'0\',
  `LoginSave` tinyint(1) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  `Passed2FA` int(11) NOT NULL DEFAULT \'0\',
  `AuthVariant` int(11) DEFAULT \'1\',
  PRIMARY KEY (`Session_ID`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
