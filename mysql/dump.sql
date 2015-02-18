-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Machine: localhost
-- Genereertijd: 16 dec 2014 om 02:16
-- Serverversie: 5.5.40-0ubuntu0.14.04.1
-- PHP-versie: 5.5.9-1ubuntu4.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Databank: `catlab_accounts`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `neuron_users`
--

CREATE TABLE IF NOT EXISTS `neuron_users` (
  `u_id` int(11) NOT NULL AUTO_INCREMENT,
  `u_email` varchar(255) DEFAULT NULL,
  `u_username` varchar(50) DEFAULT NULL,
  `u_password` varchar(255) DEFAULT NULL,
  `u_resetPassword` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`u_id`),
  UNIQUE KEY `u_email` (`u_email`),
  KEY `u_username` (`u_username`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;
