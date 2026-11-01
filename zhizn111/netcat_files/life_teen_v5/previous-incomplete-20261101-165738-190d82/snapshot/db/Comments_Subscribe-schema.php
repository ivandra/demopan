<?php
return array (
  'sql' => 'CREATE TABLE `Comments_Subscribe` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `Sub_Class_ID` int(11) NOT NULL,
  `Message_ID` int(11) NOT NULL,
  `Comment_ID` int(11) NOT NULL,
  `Subdivision_ID` int(11) NOT NULL DEFAULT \'0\',
  `Catalogue_ID` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`ID`),
  KEY `User_ID` (`User_ID`,`Sub_Class_ID`,`Message_ID`,`Comment_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
