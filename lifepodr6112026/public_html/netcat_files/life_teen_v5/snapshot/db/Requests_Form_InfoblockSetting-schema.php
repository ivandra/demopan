<?php
return array (
  'sql' => 'CREATE TABLE `Requests_Form_InfoblockSetting` (
  `Requests_Form_InfoblockSetting_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Infoblock_ID` int(11) unsigned NOT NULL,
  `FormType` varchar(255) NOT NULL DEFAULT \'default\',
  `OpenPopupButton_Text` text,
  `OpenPopupButton_Type` varchar(255) DEFAULT NULL,
  `OpenPopupButton_BackgroundColor` varchar(255) DEFAULT NULL,
  `OpenPopupButton_ShowPrice` tinyint(1) DEFAULT \'0\',
  `EmbeddedForm_ComponentTemplate_ID` int(11) unsigned DEFAULT \'0\',
  `EmbeddedForm_Header` text,
  `EmbeddedForm_TextAfterHeader` mediumtext,
  `EmbeddedForm_SubmitButton_Text` text,
  `EmbeddedForm_SubmitButton_BackgroundColor` varchar(255) DEFAULT NULL,
  `EmbeddedForm_SubmitButton_Type` varchar(255) DEFAULT NULL,
  `EmbeddedForm_SubmitButton_ShowPrice` tinyint(1) DEFAULT \'0\',
  `Infoblock_OpenPopupButton_AnalyticsCategories` text,
  `Infoblock_OpenPopupButton_AnalyticsLabels` text,
  `Infoblock_SubmitButton_AnalyticsCategories` text,
  `Infoblock_SubmitButton_AnalyticsLabels` text,
  PRIMARY KEY (`Requests_Form_InfoblockSetting_ID`),
  UNIQUE KEY `Form` (`Infoblock_ID`,`FormType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
