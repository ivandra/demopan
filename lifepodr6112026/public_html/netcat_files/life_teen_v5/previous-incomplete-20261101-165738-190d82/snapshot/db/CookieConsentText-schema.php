<?php
return array (
  'sql' => 'CREATE TABLE `CookieConsentText` (
  `Text_Hash` char(40) NOT NULL,
  `Text` text NOT NULL,
  `Created` datetime NOT NULL,
  PRIMARY KEY (`Text_Hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);
