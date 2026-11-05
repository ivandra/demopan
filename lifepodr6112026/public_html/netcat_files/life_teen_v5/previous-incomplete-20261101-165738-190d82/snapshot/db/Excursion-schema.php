<?php
return array (
  'sql' => 'CREATE TABLE `Excursion` (
  `User_ID` int(11) NOT NULL,
  `ShowNext` int(11) NOT NULL DEFAULT \'1\',
  UNIQUE KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
