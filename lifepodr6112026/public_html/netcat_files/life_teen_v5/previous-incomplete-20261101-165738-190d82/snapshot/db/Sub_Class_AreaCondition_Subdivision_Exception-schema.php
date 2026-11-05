<?php
return array (
  'sql' => 'CREATE TABLE `Sub_Class_AreaCondition_Subdivision_Exception` (
  `Condition_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Sub_Class_ID` int(11) NOT NULL,
  `Subdivision_ID` int(11) NOT NULL,
  `IncludeChildren` tinyint(1) unsigned DEFAULT \'0\',
  PRIMARY KEY (`Condition_ID`),
  UNIQUE KEY `Sub_Class_ID` (`Sub_Class_ID`,`Subdivision_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
