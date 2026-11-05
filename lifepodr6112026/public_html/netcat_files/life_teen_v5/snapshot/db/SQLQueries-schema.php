<?php
return array (
  'sql' => 'CREATE TABLE `SQLQueries` (
  `SQL_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SQL_text` text,
  UNIQUE KEY `SQL_ID` (`SQL_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
