<?php
return array (
  'sql' => 'CREATE TABLE `Template_Partial` (
  `Template_Partial_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Template_ID` int(11) NOT NULL,
  `Keyword` varchar(64) NOT NULL,
  `Description` varchar(255) NOT NULL DEFAULT \'\',
  `EnableAsyncLoad` tinyint(1) unsigned DEFAULT \'0\',
  PRIMARY KEY (`Template_Partial_ID`),
  UNIQUE KEY `Keyword` (`Template_ID`,`Keyword`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
