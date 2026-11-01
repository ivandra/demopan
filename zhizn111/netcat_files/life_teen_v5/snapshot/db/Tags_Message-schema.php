<?php
return array (
  'sql' => 'CREATE TABLE `Tags_Message` (
  `Tag_ID` int(10) NOT NULL DEFAULT \'0\',
  `Sub_Class_ID` int(10) NOT NULL DEFAULT \'0\',
  `Message_ID` int(10) NOT NULL DEFAULT \'0\',
  `Class_ID` int(11) NOT NULL DEFAULT \'0\',
  KEY `Tag_ID` (`Tag_ID`),
  KEY `Sub_Class_ID` (`Sub_Class_ID`),
  KEY `Message_ID` (`Message_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
