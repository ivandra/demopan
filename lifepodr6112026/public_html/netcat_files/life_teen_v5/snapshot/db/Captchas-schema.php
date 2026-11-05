<?php
return array (
  'sql' => 'CREATE TABLE `Captchas` (
  `Captcha_Hash` varchar(32) NOT NULL DEFAULT \'\',
  `Captcha_Code` varchar(255) NOT NULL DEFAULT \'\',
  `Captcha_Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Captcha_Hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
