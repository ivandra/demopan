<?php
return array (
  'sql' => 'CREATE TABLE `Search_Log` (
  `Log_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Type` varchar(255) NOT NULL,
  `Message` mediumtext NOT NULL,
  PRIMARY KEY (`Log_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
