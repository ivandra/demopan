<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_SendQueue` (
  `Queue_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Log_ID` int(10) unsigned NOT NULL,
  `Scheduled_At` datetime NOT NULL,
  PRIMARY KEY (`Queue_ID`),
  KEY `idx_scheduled` (`Scheduled_At`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
