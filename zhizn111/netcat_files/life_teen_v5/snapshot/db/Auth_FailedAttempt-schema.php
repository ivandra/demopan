<?php
return array (
  'sql' => 'CREATE TABLE `Auth_FailedAttempt` (
  `RemoteHash` char(32) NOT NULL,
  `LastAttempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `AttemptCount` int(11) DEFAULT NULL,
  PRIMARY KEY (`RemoteHash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
