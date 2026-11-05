<?php
return array (
  'sql' => 'CREATE TABLE `Subscriber_Prepared` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Email` text NOT NULL,
  `Subject` text NOT NULL,
  `Body` text NOT NULL,
  `SendTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `HTML` int(1) NOT NULL,
  `Files` text,
  `Mailer_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
