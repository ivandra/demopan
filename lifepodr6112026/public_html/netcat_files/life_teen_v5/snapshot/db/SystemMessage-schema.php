<?php
return array (
  'sql' => 'CREATE TABLE `SystemMessage` (
  `SystemMessage_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Description` varchar(255) NOT NULL DEFAULT \'\',
  `Checked` tinyint(4) NOT NULL DEFAULT \'0\',
  `Message` text NOT NULL,
  PRIMARY KEY (`SystemMessage_ID`),
  KEY `Checked` (`Checked`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
