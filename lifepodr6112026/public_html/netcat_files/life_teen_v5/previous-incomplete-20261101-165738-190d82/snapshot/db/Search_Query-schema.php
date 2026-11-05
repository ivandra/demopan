<?php
return array (
  'sql' => 'CREATE TABLE `Search_Query` (
  `Query_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `QueryString` varchar(255) NOT NULL,
  `Area` text,
  `Catalogue_ID` int(11) DEFAULT NULL,
  `Language` varchar(255) DEFAULT NULL,
  `ResultsCount` int(11) NOT NULL,
  `IP` int(11) DEFAULT NULL,
  `User_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Query_ID`),
  KEY `QueryString` (`QueryString`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
