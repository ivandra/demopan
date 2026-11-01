<?php
return array (
  'sql' => 'CREATE TABLE `Search_Index_LastRank` (
  `Time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `QueryHash` char(40) NOT NULL,
  `Offset` int(11) unsigned NOT NULL,
  `Rank` int(11) unsigned NOT NULL,
  UNIQUE KEY `QueryHash` (`QueryHash`,`Offset`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
