-- phpMyAdmin SQL Dump
-- version 2.11.8.1deb5+lenny3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 24, 2010 at 05:29 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.6-1+lenny4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `scas`
--

-- --------------------------------------------------------


CREATE DATABASE IF NOT EXISTS `scas`;
USE scas;

-- --------------------------------------------------------

--
-- Table structure for table `locum_availability`
--

CREATE TABLE IF NOT EXISTS `locum_availability` (
  `bnum` int(12) unsigned NOT NULL,
  `available` text,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`bnum`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `locum_avail_ages`
--

CREATE TABLE IF NOT EXISTS `locum_avail_ages` (
  `bnum` int(12) NOT NULL,
  `age` char(12) NOT NULL,
  `count_avail` int(6) NOT NULL default '0',
  `count_total` int(6) NOT NULL default '0',
  `timestamp` datetime NOT NULL,
  KEY `bnum` (`bnum`,`age`,`count_avail`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `locum_avail_branches`
--

CREATE TABLE IF NOT EXISTS `locum_avail_branches` (
  `bnum` int(12) NOT NULL,
  `branch` char(12) NOT NULL,
  `count_avail` int(6) NOT NULL default '0',
  `count_total` int(6) NOT NULL default '0',
  `timestamp` datetime NOT NULL,
  KEY `bnum` (`bnum`,`branch`,`count_avail`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `locum_bib_items`
--

CREATE TABLE IF NOT EXISTS `locum_bib_items` (
  `bnum` int(12) NOT NULL,
  `author` char(254) default NULL,
  `addl_author` mediumtext,
  `title` varchar(512) NOT NULL,
  `title_medium` char(64) default NULL,
  `edition` char(64) default NULL,
  `series` char(254) default NULL,
  `callnum` char(48) default NULL,
  `pub_info` char(254) default NULL,
  `pub_year` smallint(4) default NULL,
  `stdnum` char(32) default NULL,
  `upc` bigint(20) unsigned zerofill NOT NULL,
  `lccn` char(32) default NULL,
  `descr` mediumtext,
  `notes` mediumtext,
  `subjects` mediumtext,
  `lang` char(12) default NULL,
  `loc_code` char(7) NOT NULL,
  `mat_code` char(7) NOT NULL,
  `cover_img` char(254) default NULL,
  `download_link` text,
  `modified` datetime NOT NULL,
  `bib_created` date NOT NULL,
  `bib_lastupdate` date NOT NULL,
  `bib_prevupdate` date NOT NULL,
  `bib_revs` int(4) NOT NULL,
  `active` enum('0','1') NOT NULL default '1',
  PRIMARY KEY  (`bnum`),
  KEY `modified` (`modified`),
  KEY `mat_code` (`mat_code`),
  KEY `pub_year` (`pub_year`),
  KEY `active` (`active`),
  KEY `bib_lastupdate` (`bib_lastupdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Item table for Bib records';

-- --------------------------------------------------------

--
-- Table structure for table `locum_bib_items_subject`
--

CREATE TABLE IF NOT EXISTS `locum_bib_items_subject` (
  `bnum` int(12) NOT NULL,
  `subjects` char(254) NOT NULL,
  KEY `bnum` (`bnum`),
  KEY `subjects` (`subjects`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Table for bibliographic subject headings';

-- --------------------------------------------------------

--
-- Table structure for table `locum_holds_count`
--

CREATE TABLE IF NOT EXISTS `locum_holds_count` (
  `bnum` int(12) NOT NULL,
  `hold_count_week` int(6) NOT NULL default '0',
  `hold_count_month` int(6) NOT NULL default '0',
  `hold_count_year` int(6) NOT NULL default '0',
  `hold_count_total` int(6) NOT NULL default '0',
  PRIMARY KEY  (`bnum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `locum_holds_placed`
--

CREATE TABLE IF NOT EXISTS `locum_holds_placed` (
  `bnum` int(12) NOT NULL,
  `hold_date` date NOT NULL,
  KEY `bnum` (`bnum`,`hold_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `locum_syndetics_links`
--

CREATE TABLE IF NOT EXISTS `locum_syndetics_links` (
  `isbn` char(32) NOT NULL,
  `links` char(254) NOT NULL,
  `updated` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`isbn`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Caches Syndetics content availability';