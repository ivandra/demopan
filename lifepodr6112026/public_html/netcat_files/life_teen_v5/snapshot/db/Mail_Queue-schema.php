<?php
return array (
  'sql' => 'CREATE TABLE `Mail_Queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `create_time` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `time_to_send` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  `sent_time` datetime DEFAULT NULL,
  `id_user` bigint(20) NOT NULL DEFAULT \'0\',
  `ip` varchar(20) NOT NULL DEFAULT \'unknown\',
  `sender` varchar(50) NOT NULL DEFAULT \'\',
  `recipient` varchar(50) NOT NULL DEFAULT \'\',
  `headers` text NOT NULL,
  `body` longtext NOT NULL,
  `try_sent` tinyint(4) NOT NULL DEFAULT \'0\',
  `delete_after_send` tinyint(1) NOT NULL DEFAULT \'1\',
  PRIMARY KEY (`id`),
  KEY `time_to_send` (`time_to_send`),
  KEY `id_user` (`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8',
);
