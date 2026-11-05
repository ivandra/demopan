<?php
return array (
  'sql' => 'CREATE TABLE `Search_Link` (
  `Link_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Hash` binary(20) NOT NULL,
  `URL` text NOT NULL,
  `Processed` tinyint(4) NOT NULL,
  `Broken` tinyint(4) NOT NULL,
  PRIMARY KEY (`Link_ID`),
  UNIQUE KEY `Hash` (`Hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
