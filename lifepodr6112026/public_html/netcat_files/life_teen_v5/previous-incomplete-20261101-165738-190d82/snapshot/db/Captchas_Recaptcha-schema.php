<?php
return array (
  'sql' => 'CREATE TABLE `Captchas_Recaptcha` (
  `Response` blob,
  `Result` blob,
  `Expires` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `Response` (`Response`(255))
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
