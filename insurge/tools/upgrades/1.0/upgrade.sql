USE scas;

ALTER TABLE `insurge_index` CHANGE `bnum` `bnum` INT( 12 ) NOT NULL;

CREATE TABLE IF NOT EXISTS `insurge_history` (
  `hist_id` int(12) NOT NULL,
  `repos_id` char(24) NOT NULL,
  `group_id` char(12) NOT NULL,
  `uid` int(10) NOT NULL,
  `bnum` int(13) NOT NULL,
  `codate` datetime NOT NULL,
  `title` text,
  `author` text,
  PRIMARY KEY  (`hist_id`),
  KEY `repos_id` (`repos_id`,`group_id`,`uid`,`bnum`,`codate`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Tracks patron check-out history';