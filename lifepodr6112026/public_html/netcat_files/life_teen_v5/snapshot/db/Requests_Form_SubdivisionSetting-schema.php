<?php
return array (
  'sql' => 'CREATE TABLE `Requests_Form_SubdivisionSetting` (
  `Requests_Form_SubdivisionSetting_ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) unsigned NOT NULL,
  `Subdivision_ID` int(11) unsigned NOT NULL,
  `FormType` varchar(255) NOT NULL DEFAULT \'default\',
  `StandaloneForm_ComponentTemplate_ID` int(11) unsigned DEFAULT \'0\',
  `StandaloneForm_Header` text,
  `StandaloneForm_TextAfterHeader` mediumtext,
  `StandaloneForm_SubmitButton_Text` text,
  `StandaloneForm_SubmitButton_BackgroundColor` varchar(255) DEFAULT NULL,
  `StandaloneForm_SubmitButton_ShowPrice` tinyint(1) DEFAULT \'0\',
  `Subdivision_VisibleFields` mediumtext,
  `Subdivision_FieldProperties` mediumtext,
  `Subdivision_NotificationEmail` mediumtext,
  `Subdivision_OpenPopupButton_AnalyticsCategories` text,
  `Subdivision_OpenPopupButton_AnalyticsLabels` text,
  `Subdivision_SubmitButton_AnalyticsCategories` text,
  `Subdivision_SubmitButton_AnalyticsLabels` text,
  PRIMARY KEY (`Requests_Form_SubdivisionSetting_ID`),
  UNIQUE KEY `Form` (`Catalogue_ID`,`Subdivision_ID`,`FormType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
