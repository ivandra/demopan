<?php
return array (
  'sql' => 'CREATE TABLE `Wysiwyg_Panel` (
  `Wysiwyg_Panel_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(50) NOT NULL,
  `Toolbars` longtext NOT NULL,
  `RemoveButtons` longtext NOT NULL,
  `Editor` enum(\'ckeditor\') NOT NULL DEFAULT \'ckeditor\',
  PRIMARY KEY (`Wysiwyg_Panel_ID`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8',
);
