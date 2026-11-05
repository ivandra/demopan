<?php
return array (
  'sql' => 'CREATE TABLE `Comments_Count` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Sub_Class_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(11) NOT NULL DEFAULT \'0\',
  `CountComments` int(11) NOT NULL DEFAULT \'0\',
  `CountReplies` int(11) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
