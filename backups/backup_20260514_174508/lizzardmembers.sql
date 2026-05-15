-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: lizzardmembers
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `entity_type` enum('member','tour') NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `entity_label` varchar(255) NOT NULL,
  `changes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'Koczur Richard','create','tour',3,'Teszt túra — Magyarország','[{\"k\":\"Ország\",\"v\":\"Magyarország\"},{\"k\":\"Elnevezés\",\"v\":\"Teszt túra\"},{\"k\":\"Dátum\",\"v\":\"2025-01-01\"},{\"k\":\"Pontérték\",\"v\":\"15\"},{\"k\":\"Résztvevők száma\",\"v\":\"1\"}]','2026-05-13 16:35:57'),(2,1,'Koczur Richard','update','member',4,'Teszt Elek','[{\"k\":\"Utolsó díjfizetés\",\"f\":\"2018-01-01\",\"t\":\"2026-01-01\"}]','2026-05-13 16:36:15'),(3,1,'Koczur Richard','update','tour',3,'Teszt túra — Magyarország','[{\"k\":\"Távolság (km)\",\"f\":\"44.0\",\"t\":\"44\"},{\"k\":\"Hozzáadott résztvevők\",\"f\":\"—\",\"t\":\"Koczur Richard\"}]','2026-05-13 16:47:01'),(4,1,'Koczur Richard','update','member',2,'Bojtor Gergely','[{\"k\":\"Fiókzárolás\",\"f\":\"Zárolt\",\"t\":\"Feloldva\"}]','2026-05-13 19:10:37');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_blocks`
--

DROP TABLE IF EXISTS `ip_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_blocks` (
  `ip` varchar(45) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_blocks`
--

LOCK TABLES `ip_blocks` WRITE;
/*!40000 ALTER TABLE `ip_blocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tour_members`
--

DROP TABLE IF EXISTS `tour_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tour_members` (
  `tour_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tour_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tour_members_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tour_members`
--

LOCK TABLES `tour_members` WRITE;
/*!40000 ALTER TABLE `tour_members` DISABLE KEYS */;
INSERT INTO `tour_members` VALUES (1,2,'2026-05-07 12:08:58'),(1,3,'2026-05-07 12:08:58'),(2,2,'2026-05-07 12:10:13'),(3,1,'2026-05-13 16:47:01'),(3,2,'2026-05-13 16:47:01');
/*!40000 ALTER TABLE `tour_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tours`
--

DROP TABLE IF EXISTS `tours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tours` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `tour_date` date DEFAULT NULL,
  `days` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `accommodation` varchar(100) DEFAULT NULL,
  `total_km` decimal(8,1) DEFAULT NULL,
  `total_elevation` int(10) unsigned DEFAULT NULL,
  `participant_count` smallint(5) unsigned DEFAULT NULL,
  `points` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tours`
--

LOCK TABLES `tours` WRITE;
/*!40000 ALTER TABLE `tours` DISABLE KEYS */;
INSERT INTO `tours` VALUES (1,'Tara Nemzeti Park','Szerbia','Tara Nemzeti Park','2026-05-01',4,'Hotel',28.0,1220,NULL,18,'2026-05-07 06:17:56','2026-05-07 06:28:23'),(2,'Dalmáciai kanyonok','Horvátország','Velebit','2026-04-15',4,'Apartman',39.0,2111,NULL,20,'2026-05-07 12:10:13','2026-05-07 12:10:13'),(3,'Teszt túra','Magyarország','Pilis','2025-01-01',1,'Sátor',44.0,1555,NULL,15,'2026-05-13 16:35:57','2026-05-13 16:35:57');
/*!40000 ALTER TABLE `tours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `dateofbirth` date DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `tshirt_size` enum('XS','S','M','L','XL','XXL','XXXL') DEFAULT NULL,
  `emergency_name` varchar(100) DEFAULT NULL,
  `emergency_relation` enum('szülő','gyermek','testvér','egyéb') DEFAULT NULL,
  `emergency_phone` varchar(30) DEFAULT NULL,
  `member_since` date DEFAULT NULL,
  `last_payment` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `points` int(10) unsigned NOT NULL DEFAULT 0,
  `level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'rkoczur','rkoczur@gmail.com','$2y$10$iAQsJ4TAV6oUGgfAdC7H5OAZXgJVaWVM6FrQkggobKHObiu.Un31O','admin','Richard','Koczur',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-30',NULL,'avatar_1_1778699686.png',15,2,1,0,NULL,'2026-04-30 15:24:05','2026-05-13 19:14:46'),(2,'gbojtor','teszt@bojtor.hu','$2y$10$VTecHGsV16FtSpRPP1JyaOTM9DIrT24eLJC8ZWQaok63oHhqrAQBS','user','Gergely','Bojtor','1972-01-02',NULL,NULL,NULL,NULL,'L',NULL,NULL,NULL,'2007-10-21','2026-01-02',NULL,53,4,1,0,NULL,'2026-05-07 06:34:06','2026-05-13 19:10:37'),(3,'akisko','akisko@akiskol.hu','$2y$10$cb.LBJyCCEJIVzYYY9sUm.peZnSZpOycQkQjiTYi9hEFtVgxtzHue','user','Alíz','Kiskó','1985-02-11',NULL,NULL,NULL,NULL,'S',NULL,NULL,NULL,'2017-08-09','2025-01-01',NULL,18,2,1,0,NULL,'2026-05-07 12:08:47','2026-05-13 09:33:43'),(4,'telek','tesztelek@tesztelek.hu','$2y$10$XvMf0BYTRngIOrg3We/Dv.oH2PIj50b5CzdicZi6lHK/ZkPdcT/SS','user','Elek','Teszt','1988-01-01',NULL,NULL,NULL,NULL,'S',NULL,NULL,NULL,'2017-01-01','2026-01-01',NULL,0,1,1,0,NULL,'2026-05-13 09:33:19','2026-05-13 16:36:15');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-14 17:50:41
