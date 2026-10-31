<?php
return array (
  'sql' => 'CREATE TABLE `Redirect` (
  `Redirect_ID` int(11) NOT NULL AUTO_INCREMENT,
  `OldURL` varchar(255) NOT NULL DEFAULT \'\',
  `NewURL` varchar(255) NOT NULL DEFAULT \'\',
  `Header` int(3) DEFAULT \'301\',
  `Group_ID` int(11) DEFAULT \'1\',
  `Checked` int(3) DEFAULT \'1\',
  PRIMARY KEY (`Redirect_ID`),
  UNIQUE KEY `OldURL` (`OldURL`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8',
);
