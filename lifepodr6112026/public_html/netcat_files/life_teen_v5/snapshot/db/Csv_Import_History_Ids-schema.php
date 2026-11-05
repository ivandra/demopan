<?php
return array (
  'sql' => 'CREATE TABLE `Csv_Import_History_Ids` (
  `History_ID` int(11) NOT NULL DEFAULT \'0\',
  `Message_ID` int(10) unsigned NOT NULL DEFAULT \'0\',
  KEY `History_ID` (`History_ID`),
  KEY `Message_ID` (`Message_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
