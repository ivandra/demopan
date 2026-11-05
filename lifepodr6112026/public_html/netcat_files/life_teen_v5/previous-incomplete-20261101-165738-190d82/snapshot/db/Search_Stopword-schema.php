<?php
return array (
  'sql' => 'CREATE TABLE `Search_Stopword` (
  `Word_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Language` varchar(10) NOT NULL,
  `Word` varchar(255) NOT NULL,
  PRIMARY KEY (`Word_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=983 DEFAULT CHARSET=utf8',
);
