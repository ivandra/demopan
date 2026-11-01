<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_UserMessage` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `Sub_Class_ID` int(11) NOT NULL,
  `Message_ID` int(11) NOT NULL,
  `Mailer_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
