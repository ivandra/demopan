<?php
return array (
  'sql' => 'CREATE TABLE `Search_Document` (
  `Document_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Catalogue_ID` int(11) NOT NULL,
  `Subdivision_ID` int(11) DEFAULT NULL,
  `Ancestors` text,
  `Path` text NOT NULL,
  `Title` text NOT NULL,
  `Content` mediumtext,
  `Meta` mediumtext,
  `ContentType` varchar(255) NOT NULL,
  `Language` varchar(255) NOT NULL,
  `IncludeInSitemap` tinyint(4) NOT NULL DEFAULT \'0\',
  `SitemapChangefreq` enum(\'\',\'always\',\'hourly\',\'daily\',\'weekly\',\'monthly\',\'yearly\',\'never\') DEFAULT NULL,
  `SitemapPriority` decimal(2,1) unsigned DEFAULT NULL,
  `Hash` char(40) DEFAULT NULL,
  `ToDelete` tinyint(4) NOT NULL DEFAULT \'0\',
  `LastModified` timestamp NULL DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Document_ID`),
  KEY `Sitemap` (`Catalogue_ID`,`IncludeInSitemap`),
  KEY `Title` (`Title`(256))
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
