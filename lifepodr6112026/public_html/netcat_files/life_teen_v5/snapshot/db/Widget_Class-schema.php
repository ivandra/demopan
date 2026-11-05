<?php
return array (
  'sql' => 'CREATE TABLE `Widget_Class` (
  `Widget_Class_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Keyword` varchar(255) NOT NULL,
  `Description` text,
  `Category` text,
  `InDevelop` tinyint(4) NOT NULL DEFAULT \'0\',
  `Template` text NOT NULL,
  `Settings` text NOT NULL,
  `AddForm` text NOT NULL,
  `EditForm` text NOT NULL,
  `User_ID` int(11) NOT NULL DEFAULT \'0\',
  `LastUser_ID` int(11) NOT NULL DEFAULT \'0\',
  `Created` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `LastUpdated` timestamp NOT NULL DEFAULT \'0000-00-00 00:00:00\' ON UPDATE CURRENT_TIMESTAMP,
  `WidgetDisallow` tinyint(4) NOT NULL DEFAULT \'0\',
  `Update` int(11) NOT NULL DEFAULT \'0\',
  `File_Mode` tinyint(1) NOT NULL DEFAULT \'0\',
  `File_Path` varchar(255) DEFAULT NULL,
  `AfterSaveAction` text NOT NULL,
  `BeforeSaveAction` text NOT NULL,
  `IsStatic` tinyint(2) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`Widget_Class_ID`),
  KEY `User_ID` (`User_ID`),
  KEY `LastUser_ID` (`LastUser_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8',
);
