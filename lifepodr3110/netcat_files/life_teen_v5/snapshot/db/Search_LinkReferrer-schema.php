<?php
return array (
  'sql' => 'CREATE TABLE `Search_LinkReferrer` (
  `Source_Document_ID` int(11) DEFAULT NULL,
  `Source_Link_ID` int(11) NOT NULL,
  `Target_Link_ID` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
