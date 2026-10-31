<?php
return array (
  'sql' => 'CREATE TABLE `Landing_Page_Block` (
  `Landing_Page_ID` int(11) NOT NULL,
  `Infoblock_ID` int(11) unsigned NOT NULL,
  `Block_Class_Keyword` varchar(255) DEFAULT NULL,
  UNIQUE KEY `Infoblock_ID` (`Infoblock_ID`),
  KEY `Landing_Page_ID` (`Landing_Page_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
