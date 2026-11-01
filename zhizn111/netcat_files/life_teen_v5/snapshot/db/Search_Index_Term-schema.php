<?php
return array (
  'sql' => 'CREATE TABLE `Search_Index_Term` (
  `Term` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `Code` varbinary(255) NOT NULL,
  `Length` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`Term`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
