<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_Message` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Sub_Class_ID` int(11) NOT NULL,
  `Message_ID` int(11) NOT NULL,
  `Date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Rel_Sub_Class_ID` int(11) NOT NULL,
  `Rel_Message_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`),
  KEY `Rel_Sub_Class_ID` (`Rel_Sub_Class_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
