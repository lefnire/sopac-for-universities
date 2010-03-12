-- phpMyAdmin SQL Dump
-- version 2.11.8.1deb5+lenny3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 26, 2010 at 05:02 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.6-1+lenny4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

CREATE DATABASE IF NOT EXISTS `scas`;
USE scas;

--
-- Database: `scas`
--

-- --------------------------------------------------------

--
-- Table structure for table `insurge_history`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `insurge_index`
--

CREATE TABLE IF NOT EXISTS `insurge_index` (
  `bnum` int(12) NOT NULL,
  `rating_idx` int(8) NOT NULL default '0',
  `tag_idx` text,
  `review_idx` text,
  PRIMARY KEY  (`bnum`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `insurge_ratings`
--

CREATE TABLE IF NOT EXISTS `insurge_ratings` (
  `rate_id` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `rating` float NOT NULL,
  `rate_date` datetime NOT NULL,
  PRIMARY KEY  (`rate_id`),
  KEY `repos_id` (`repos_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `rating` (`rating`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `insurge_reviews`
--

CREATE TABLE IF NOT EXISTS `insurge_reviews` (
  `rev_id` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `rev_title` char(254) NOT NULL,
  `rev_body` text NOT NULL,
  `rev_last_update` timestamp NOT NULL default '0000-00-00 00:00:00' on update CURRENT_TIMESTAMP,
  `rev_create_date` datetime NOT NULL,
  PRIMARY KEY  (`rev_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `repos_id` (`repos_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `insurge_tags`
--

CREATE TABLE IF NOT EXISTS `insurge_tags` (
  `tid` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `tag` char(254) NOT NULL,
  `tag_date` datetime NOT NULL,
  PRIMARY KEY  (`tid`),
  KEY `repos_id` (`repos_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `tag` (`tag`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
