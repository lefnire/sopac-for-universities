USE scas;

DROP TABLE IF EXISTS `locum_availability`;
CREATE TABLE IF NOT EXISTS `locum_availability` (
  `bnum` int(12) unsigned NOT NULL,
  `available` text,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`bnum`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `locum_avail_branches`;
CREATE TABLE IF NOT EXISTS `locum_avail_branches` (
  `bnum` int(12) NOT NULL,
  `branch` char(12) NOT NULL,
  `count_avail` int(6) NOT NULL default '0',
  `count_total` int(6) NOT NULL default '0',
  `timestamp` datetime NOT NULL,
  KEY `bnum` (`bnum`,`branch`,`count_avail`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `locum_avail_ages`;
CREATE TABLE IF NOT EXISTS `locum_avail_ages` (
  `bnum` int(12) NOT NULL,
  `age` char(12) NOT NULL,
  `count_avail` int(6) NOT NULL default '0',
  `count_total` int(6) NOT NULL default '0',
  `timestamp` datetime NOT NULL,
  KEY `bnum` (`bnum`,`age`,`count_avail`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `locum_syndetics_links` (
  `isbn` char(32) NOT NULL,
  `links` char(254) NOT NULL,
  `updated` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`isbn`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Caches Syndetics content availability';

ALTER TABLE `locum_bib_items` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `locum_bib_items_subject` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `locum_facet_heap` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `locum_holds_count` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `locum_holds_placed` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE `locum_bib_items` ADD `upc` BIGINT UNSIGNED ZEROFILL NOT NULL AFTER `stdnum`;
ALTER TABLE `locum_bib_items` ADD `download_link` TEXT NULL AFTER `cover_img`;

ALTER TABLE `insurge_index` CHANGE `bnum` `bnum` INT( 12 ) NOT NULL;
ALTER TABLE `locum_bib_items` CHANGE `bnum` `bnum` INT( 12 ) NOT NULL;
ALTER TABLE `locum_bib_items_subject` CHANGE `bnum` `bnum` INT( 12 ) NOT NULL;
ALTER TABLE `locum_facet_heap` CHANGE `bnum` `bnum` INT( 12 ) NOT NULL;
