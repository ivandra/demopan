<?php
return array (
  'sql' => 'CREATE TABLE `CrmIntegration_ProviderCatalogue` (
  `Catalogue_ID` int(10) unsigned NOT NULL,
  `Provider_ID` int(10) unsigned NOT NULL,
  `Enabled` tinyint(1) DEFAULT \'1\',
  `Priority` int(11) DEFAULT \'100\',
  PRIMARY KEY (`Catalogue_ID`,`Provider_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
