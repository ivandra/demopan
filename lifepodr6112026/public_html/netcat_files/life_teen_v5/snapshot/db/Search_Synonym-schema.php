<?php
return array (
  'sql' => 'CREATE TABLE `Search_Synonym` (
  `Synonyms_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Language` varchar(10) NOT NULL,
  `Words` text NOT NULL,
  PRIMARY KEY (`Synonyms_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
