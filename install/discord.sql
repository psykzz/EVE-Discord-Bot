SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `authUsers` (
  `id` int(15) NOT NULL AUTO_INCREMENT,
  `eveName` varchar(365) COLLATE utf8_unicode_ci NOT NULL,
  `characterID` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `discordID` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `role` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `active` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
  `addedOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `characterID` (`characterID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=62 ;

CREATE TABLE IF NOT EXISTS `pendingUsers` (
  `id` int(56) NOT NULL AUTO_INCREMENT,
  `characterID` varchar(128) NOT NULL,
  `corporationID` varchar(128) NOT NULL,
  `allianceID` varchar(128) NOT NULL,
  `groups` varchar(128) NOT NULL,
  `authString` varchar(128) NOT NULL,
  `active` varchar(128) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=73 ;

CREATE TABLE IF NOT EXISTS `shipFits` (
  `id` int(25) NOT NULL AUTO_INCREMENT,
  `fitName` varchar(65) COLLATE utf8_unicode_ci NOT NULL,
  `fit` varchar(1800) COLLATE utf8_unicode_ci NOT NULL,
  `fitAuthor` varchar(65) COLLATE utf8_unicode_ci NOT NULL,
  `dateAdded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=23 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
