<?php
return array (
  'sql' => 'CREATE TABLE `FieldFilter` (
  `SubClass_ID` int(11) NOT NULL DEFAULT \'0\',
  `Field_Name` char(64) NOT NULL DEFAULT \'\',
  `DoSearch` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`SubClass_ID`,`Field_Name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
