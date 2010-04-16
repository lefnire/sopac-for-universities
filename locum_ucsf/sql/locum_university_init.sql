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
  `continues` mediumtext, /*223*/
  `link` mediumtext, /*132*/
  `alt_title` mediumtext, /*507*/
  `related_work` mediumtext, /*104*/
  `local_note` mediumtext, /*303*/
  `oclc` char(12), /*9*/
  `doc_number` varchar(128), /*32*/
  `holdings` mediumtext, /*184*/
  `cont_d_by` mediumtext, /*343*/
  `__note__` mediumtext, /*184*/
  `hldgs_stat` varchar(128), /*53*/
  KEY `bnum` (`bnum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Table for additional university information for bib items';