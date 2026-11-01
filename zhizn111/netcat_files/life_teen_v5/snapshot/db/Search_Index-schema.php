<?php
return array (
  'sql' => 'CREATE TABLE `Search_Index` (
  `Document_ID` int(11) NOT NULL,
  `Content` longtext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`Document_ID`),
  FULLTEXT KEY `Content` (`Content`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
