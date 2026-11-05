<?php
return array (
  'sql' => 'CREATE TABLE `Mail_Attachment` (
  `Mail_Attachment_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(255) NOT NULL,
  `Filename` text,
  `Extension` varchar(10) NOT NULL,
  `Content_Type` varchar(100) NOT NULL,
  `Path` text,
  PRIMARY KEY (`Mail_Attachment_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
