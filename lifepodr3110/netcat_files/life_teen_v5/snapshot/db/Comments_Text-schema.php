<?php
return array (
  'sql' => 'CREATE TABLE `Comments_Text` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Parent_Comment_ID` int(11) NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) NOT NULL DEFAULT \'0\',
  `Date` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `Updated` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `Comment` text NOT NULL,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `IP` varchar(15) DEFAULT \'\',
  `Checked` tinyint(1) NOT NULL DEFAULT \'1\',
  `Guest_Email` varchar(255) DEFAULT NULL,
  `Guest_Name` varchar(255) NOT NULL,
  `Value` text,
  `Like` int(11) NOT NULL,
  `Dislike` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`,`Message_ID`,`Checked`),
  KEY `User_ID` (`User_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
