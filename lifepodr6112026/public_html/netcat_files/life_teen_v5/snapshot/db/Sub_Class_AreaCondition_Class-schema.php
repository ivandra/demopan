<?php
return array (
  'sql' => 'CREATE TABLE `Sub_Class_AreaCondition_Class` (
  `Condition_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Sub_Class_ID` int(11) NOT NULL,
  `Class_ID` int(11) NOT NULL,
  PRIMARY KEY (`Condition_ID`),
  UNIQUE KEY `Sub_Class_ID` (`Sub_Class_ID`,`Class_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
