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
-- Table structure for table `locum_bib_items_university`
--

CREATE TABLE IF NOT EXISTS `locum_bib_items_university` (
  `bnum` int(12) NOT NULL,
  `holdings` mediumtext,
  `continues` mediumtext,
  `link` mediumtext,
  `alt_title` mediumtext,
  `related_work` mediumtext,
  `local_note` mediumtext,
  KEY `bnum` (`bnum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Table for additional university information for bib items';