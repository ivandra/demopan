<?php
return array (
  'sql' => 'CREATE TABLE `Search_BrokenLink` (
  `URL` text NOT NULL,
  `Referrer_URL` text NOT NULL,
  `Referrer_Document_ID` int(11) DEFAULT NULL,
  `ToDelete` tinyint(4) DEFAULT \'0\',
  KEY `URL` (`URL`(100)),
  KEY `Referrer_URL` (`Referrer_URL`(100))
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
