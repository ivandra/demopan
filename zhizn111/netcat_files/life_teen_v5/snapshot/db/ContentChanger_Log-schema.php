<?php
return array (
  'sql' => 'CREATE TABLE `ContentChanger_Log` (
  `Log_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Instruction_Json` text NOT NULL,
  `Entity_Type` varchar(50) NOT NULL,
  `Action` varchar(50) NOT NULL,
  `Execution_Status` enum(\'success\',\'error\',\'skipped\',\'pending\') NOT NULL DEFAULT \'pending\',
  `Execution_Result` text,
  `Error_Message` text,
  `Search_Criteria` text,
  `Found_Entities` text,
  `Execution_Time` datetime NOT NULL,
  `Execution_Duration` decimal(10,4) DEFAULT \'0.0000\',
  `Site_ID` int(11) DEFAULT NULL,
  PRIMARY KEY (`Log_ID`),
  KEY `idx_execution_time` (`Execution_Time`),
  KEY `idx_entity_type` (`Entity_Type`),
  KEY `idx_action` (`Action`),
  KEY `idx_status` (`Execution_Status`),
  KEY `idx_site_id` (`Site_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
