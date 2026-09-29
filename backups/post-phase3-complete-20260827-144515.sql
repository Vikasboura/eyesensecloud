-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: localhost    Database: eyesense_licenses
-- ------------------------------------------------------
-- Server version	8.0.40

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_requests`
--

DROP TABLE IF EXISTS `access_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `request_type` enum('FACE','VEHICLE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `snapshot_b64` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','APPROVED','DENIED','IGNORED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `duration_minutes` int DEFAULT '60',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_status` (`client_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `access_requests`
--

LOCK TABLES `access_requests` WRITE;
/*!40000 ALTER TABLE `access_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `access_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alert_logs`
--

DROP TABLE IF EXISTS `alert_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alert_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_level` enum('INFO','WARNING','CRITICAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'INFO',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `channel` enum('SYSTEM','EMAIL','SMS','WHATSAPP','MQTT','UI') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'SYSTEM',
  `status` enum('SENT','FAILED','PENDING') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'SENT',
  `error_detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alert_logs`
--

LOCK TABLES `alert_logs` WRITE;
/*!40000 ALTER TABLE `alert_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `alert_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actor_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bulk_detection_alerts`
--

DROP TABLE IF EXISTS `bulk_detection_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulk_detection_alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_timestamp` datetime NOT NULL,
  `unknown_count` int NOT NULL DEFAULT '0',
  `alert_sent` tinyint(1) NOT NULL DEFAULT '0',
  `cooldown_expires_at` datetime DEFAULT NULL,
  `snapshot_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_recipients` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_camera_ts` (`client_id`,`camera_id`,`detection_timestamp`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bulk_detection_alerts`
--

LOCK TABLES `bulk_detection_alerts` WRITE;
/*!40000 ALTER TABLE `bulk_detection_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bulk_detection_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `camera_assignment`
--

DROP TABLE IF EXISTS `camera_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_assignment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_user_cam` (`client_id`,`user_id`,`camera_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `camera_assignment`
--

LOCK TABLES `camera_assignment` WRITE;
/*!40000 ALTER TABLE `camera_assignment` DISABLE KEYS */;
/*!40000 ALTER TABLE `camera_assignment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cameras`
--

DROP TABLE IF EXISTS `cameras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cameras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `premises_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_camid` (`client_id`,`camera_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cameras`
--

LOCK TABLES `cameras` WRITE;
/*!40000 ALTER TABLE `cameras` DISABLE KEYS */;
/*!40000 ALTER TABLE `cameras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sender_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiver_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` enum('TEXT','IMAGE','PDF','AUDIO','VIDEO','FILE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'TEXT',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `machine_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `sa_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `hostel_management_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (4,'IND-2026-QS5LQ','Abhijit','Santra','abhijitsantra325@gmail.com','9339293101','','Abhijit01',0,'2026-08-12 10:49:26'),(5,'IND-2026-SA6HW','Jethalal','Gada','abhijitsantra1005@gmail.com','+919339293101','','Jethalal01',0,'2026-08-26 17:03:08');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_summary`
--

DROP TABLE IF EXISTS `daily_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_summary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `summary_date` date DEFAULT NULL,
  `total_events` int DEFAULT '0',
  `total_faces` int DEFAULT '0',
  `total_known_faces` int DEFAULT '0',
  `total_unknown_faces` int DEFAULT '0',
  `face_detection_failures` int DEFAULT '0',
  `face_recognition_failures` int DEFAULT '0',
  `total_vehicles` int DEFAULT '0',
  `total_known_vehicles` int DEFAULT '0',
  `total_unknown_vehicles` int DEFAULT '0',
  `vehicle_detection_failures` int DEFAULT '0',
  `plate_read_failures` int DEFAULT '0',
  `total_snapshots` int DEFAULT '0',
  `snapshot_failures` int DEFAULT '0',
  `total_videos` int DEFAULT '0',
  `video_failures` int DEFAULT '0',
  `model_errors` int DEFAULT '0',
  `camera_errors` int DEFAULT '0',
  `database_errors` int DEFAULT '0',
  `server_errors` int DEFAULT '0',
  `app_crash_count` int DEFAULT '0',
  `alerts_sent` int DEFAULT '0',
  `alert_failures` int DEFAULT '0',
  `avg_processing_time_ms` int DEFAULT NULL,
  `max_processing_time_ms` int DEFAULT NULL,
  `min_processing_time_ms` int DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_faces_success` int DEFAULT '0',
  `total_faces_failed` int DEFAULT '0',
  `total_authorized_faces` int DEFAULT '0',
  `total_unauthorized_faces` int DEFAULT '0',
  `total_vehicles_success` int DEFAULT '0',
  `total_vehicles_failed` int DEFAULT '0',
  `total_authorized_vehicles` int DEFAULT '0',
  `total_unauthorized_vehicles` int DEFAULT '0',
  `peak_activity_hour` tinyint DEFAULT NULL,
  `error_face_detection` int DEFAULT '0',
  `error_face_recognition` int DEFAULT '0',
  `error_vehicle_detection` int DEFAULT '0',
  `error_plate_ocr` int DEFAULT '0',
  `error_camera_failure` int DEFAULT '0',
  `error_model_failure` int DEFAULT '0',
  `error_system_failure` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_date` (`client_id`,`summary_date`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=548 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_summary`
--

LOCK TABLES `daily_summary` WRITE;
/*!40000 ALTER TABLE `daily_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `global_user_id` bigint DEFAULT NULL,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `government_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_level` enum('L0','L1','L2','L3','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'L1',
  `status` enum('active','inactive','suspended','terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `face_registered` tinyint(1) DEFAULT '0',
  `face_embedding_count` int DEFAULT '0',
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_seen_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `valid_until` datetime DEFAULT NULL,
  `parent_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hierarchy_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_permissions` json DEFAULT NULL,
  `plotno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latitude` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_maplink` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `alt_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `plot_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_filled_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_empid` (`client_id`,`employee_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (63,1,'IND-2026-QS5LQ','Abhijit01','Abhijit Santra','abhijitsantra325@gmail.com','9339293101','superadmin',NULL,NULL,NULL,NULL,'ADMIN','active',NULL,NULL,0,0,'INDSAC-ADMIN',NULL,NULL,'2026-08-12 16:19:26','2026-08-27 13:31:23',NULL,0,NULL,NULL,NULL,'pbkdf2:sha256:600000$rPlh6Ia3VVfQTKTK$d8cd622aec609f214e3f92415b1c7e46524b5655888f034f689ccf90f364302b',NULL,'2-X','Bengaluru','13.108277','77.577087','https://www.google.com/maps?q=13.108277,77.577087','9876543210','Indian','owner','direct'),(64,2,'IND-2026-SA6HW','Jethalal01','Jethalal Gada','abhijitsantra1005@gmail.com','+919339293101','superadmin',NULL,NULL,NULL,NULL,'ADMIN','active',NULL,NULL,0,0,'INDSAC-ADMIN',NULL,NULL,'2026-08-26 22:33:09','2026-08-27 13:31:23',NULL,0,NULL,NULL,NULL,'pbkdf2:sha256:600000$TNkagIrODGs3o5Z3$bbbc95421b822784b8f5798f766ce7cac51c5d858f6ecbc017309ff028d3132e',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `face_embeddings`
--

DROP TABLE IF EXISTS `face_embeddings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `face_embeddings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `faiss_index` int NOT NULL,
  `embedding_model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'buffalo_l',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `embedding_blob` blob,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_empid` (`client_id`,`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `face_embeddings`
--

LOCK TABLES `face_embeddings` WRITE;
/*!40000 ALTER TABLE `face_embeddings` DISABLE KEYS */;
/*!40000 ALTER TABLE `face_embeddings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `face_logs`
--

DROP TABLE IF EXISTS `face_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `face_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_source` enum('AUTO','MANUAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'AUTO',
  `detection_type` enum('FACE','PERSON') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'FACE',
  `person_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recognized` tinyint(1) DEFAULT '0',
  `authorized` tinyint(1) DEFAULT '0',
  `confidence` float DEFAULT '0',
  `embedding_distance` float DEFAULT NULL,
  `bbox` json DEFAULT NULL,
  `detection_success` tinyint(1) DEFAULT '1',
  `recognition_success` tinyint(1) DEFAULT '1',
  `detection_error_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recognition_error_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `model_version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_time_ms` int DEFAULT NULL,
  `image_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `snapshot_id` int DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_ts` (`client_id`,`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=3618 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `face_logs`
--

LOCK TABLES `face_logs` WRITE;
/*!40000 ALTER TABLE `face_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `face_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_documents`
--

DROP TABLE IF EXISTS `guest_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `identity_id` int DEFAULT NULL,
  `guest_profile_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_documents`
--

LOCK TABLES `guest_documents` WRITE;
/*!40000 ALTER TABLE `guest_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `guest_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_identities`
--

DROP TABLE IF EXISTS `guest_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest_identities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name_lower` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_identities`
--

LOCK TABLES `guest_identities` WRITE;
/*!40000 ALTER TABLE `guest_identities` DISABLE KEYS */;
/*!40000 ALTER TABLE `guest_identities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_visits`
--

DROP TABLE IF EXISTS `guest_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest_visits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `government_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_profile_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_request_id` int DEFAULT NULL,
  `identity_id` int DEFAULT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `valid_until` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `status` enum('ACTIVE','EXPIRED','REVOKED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVE',
  `premises_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_status` (`client_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_visits`
--

LOCK TABLES `guest_visits` WRITE;
/*!40000 ALTER TABLE `guest_visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `guest_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guests`
--

DROP TABLE IF EXISTS `guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approved_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `valid_until` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guests`
--

LOCK TABLES `guests` WRITE;
/*!40000 ALTER TABLE `guests` DISABLE KEYS */;
/*!40000 ALTER TABLE `guests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `licenses`
--

DROP TABLE IF EXISTS `licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `licenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `auto_renew` tinyint(1) DEFAULT '0',
  `sa_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sa_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sa_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'completed',
  `amount` decimal(10,2) DEFAULT '0.00',
  `license_key_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_machine_id` (`machine_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `licenses`
--

LOCK TABLES `licenses` WRITE;
/*!40000 ALTER TABLE `licenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `licenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_bills`
--

DROP TABLE IF EXISTS `maintenance_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member_id` int NOT NULL,
  `billing_month` date NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `previous_due` decimal(10,2) DEFAULT '0.00',
  `total_due` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('PENDING','UPLOADED','VERIFIED','REJECTED','OVERDUE','PARTIAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `overdue_flagged_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Late penalty applied when PARTIAL goes OVERDUE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_month` (`member_id`,`billing_month`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_status` (`client_id`,`status`),
  KEY `idx_month` (`client_id`,`billing_month`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_bills`
--

LOCK TABLES `maintenance_bills` WRITE;
/*!40000 ALTER TABLE `maintenance_bills` DISABLE KEYS */;
INSERT INTO `maintenance_bills` VALUES (36,'IND-2026-QS5LQ',6,'2026-08-01',10000.00,0.00,10000.00,'2026-08-05','OVERDUE',NULL,'2026-08-26 14:36:59',0.00);
/*!40000 ALTER TABLE `maintenance_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_receipts`
--

DROP TABLE IF EXISTS `maintenance_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bill_id` int NOT NULL,
  `member_id` int NOT NULL,
  `uploaded_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('PDF','IMAGE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_kb` int DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_mode` enum('Cash','UPI','Bank Transfer','Cheque','QR Code','Other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `payment_date` date NOT NULL,
  `reference_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `review_status` enum('PENDING','VERIFIED','REJECTED','SUPERSEDED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `reviewed_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notification_sent` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_num` (`client_id`,`receipt_number`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_bill` (`bill_id`),
  KEY `idx_member` (`client_id`,`member_id`),
  KEY `idx_review` (`client_id`,`review_status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_receipts`
--

LOCK TABLES `maintenance_receipts` WRITE;
/*!40000 ALTER TABLE `maintenance_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_settings`
--

DROP TABLE IF EXISTS `maintenance_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `society_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Our Society',
  `default_amount` decimal(10,2) DEFAULT '0.00',
  `default_due_day` tinyint DEFAULT '10',
  `admin_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cron_last_run` datetime DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `landmark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pincode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes_partial` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes_exact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes_overdue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes_overpaid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `penalty_amount` decimal(10,2) DEFAULT '0.00',
  `penalty_days` int DEFAULT '0',
  `notify_email` tinyint(1) DEFAULT '1',
  `notify_sms` tinyint(1) DEFAULT '0',
  `notify_whatsapp` tinyint(1) DEFAULT '0',
  `admin_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_upi_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_bank_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_ifsc` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sqft_rate` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Rs per sqft',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_settings`
--

LOCK TABLES `maintenance_settings` WRITE;
/*!40000 ALTER TABLE `maintenance_settings` DISABLE KEYS */;
INSERT INTO `maintenance_settings` VALUES (18,'IND-2026-QS5LQ','Our Society',0.00,10,NULL,NULL,'2026-08-26 18:34:32','2026-08-27 00:04:32',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,0,1,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000),(23,'IND-2026-SA6HW','Gokul Dham Society',0.00,10,NULL,'INDSAC-ADMIN','2026-08-26 17:03:09',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,0,1,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000);
/*!40000 ALTER TABLE `maintenance_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_registration_requests`
--

DROP TABLE IF EXISTS `member_registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_registration_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `monthly_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `due_day` tinyint NOT NULL DEFAULT '10',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','APPROVED','REJECTED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `reject_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviewed_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `plot_size_sqft` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_status` (`client_id`,`status`),
  KEY `idx_login` (`client_id`,`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_registration_requests`
--

LOCK TABLES `member_registration_requests` WRITE;
/*!40000 ALTER TABLE `member_registration_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `member_registration_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_otps`
--

DROP TABLE IF EXISTS `password_reset_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`client_id`,`employee_id`,`otp`,`used`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_otps`
--

LOCK TABLES `password_reset_otps` WRITE;
/*!40000 ALTER TABLE `password_reset_otps` DISABLE KEYS */;
INSERT INTO `password_reset_otps` VALUES (4,'IND-2026-QS5LQ','Abhijit01','abhijitsantra325@gmail.com','465079','2026-08-17 12:02:36','2026-08-17 12:07:36',1),(5,'IND-2026-QS5LQ','Abhijit01','abhijitsantra325@gmail.com','611606','2026-08-25 12:59:36','2026-08-25 13:04:36',1);
/*!40000 ALTER TABLE `password_reset_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `days` int NOT NULL DEFAULT '30',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `camera_limit` int DEFAULT '0' COMMENT '0 = unlimited',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_code` (`plan_code`),
  KEY `idx_plan_code` (`plan_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` VALUES (6,'PRO_3M','Pro 3 months',90,9999.00,2,1,'2026-08-13 09:42:23','2026-08-13 09:42:23');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_audit_logs`
--

DROP TABLE IF EXISTS `portal_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_audit_logs`
--

LOCK TABLES `portal_audit_logs` WRITE;
/*!40000 ALTER TABLE `portal_audit_logs` DISABLE KEYS */;
INSERT INTO `portal_audit_logs` VALUES (211,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-17 12:03:40'),(212,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-17 20:33:41'),(213,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-18 10:19:07'),(214,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-18 15:23:23'),(215,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-18 23:07:41'),(216,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-19 10:55:42'),(217,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-25 13:00:45'),(218,'IND-2026-QS5LQ','Abhijit01','CREATE_SELF_MEMBER_RECORD','Admin created own member record: flat=A-10, sqft=1200','2026-08-25 19:59:40'),(219,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-26 20:06:59'),(220,'IND-2026-QS5LQ','Abhijit01','LOGIN','Portal login successful','2026-08-27 13:33:47'),(221,NULL,'Abhijit01','SOCIETY_CREATED','{\"society_id\":1,\"society_code\":\"SOC-2026-478B83\",\"society_name\":\"Default Key Case One 20260827\"}','2026-08-27 14:32:01'),(222,NULL,'Abhijit01','SOCIETY_CREATED','{\"society_id\":2,\"society_code\":\"SOC-2026-E3910E\",\"society_name\":\"Default Key Case Two 20260827\"}','2026-08-27 14:32:02'),(223,'IND-2026-BSS4A','SA-D2026BSS4A','SOCIETY_SWITCHED','{\"from_client_id\":\"IND-2026-QS5LQ\",\"to_society_id\":2,\"to_client_id\":\"IND-2026-BSS4A\"}','2026-08-27 14:32:02'),(224,'IND-2026-BSS4A','SA-D2026BSS4A','SOCIETY_SWITCHED','{\"from_client_id\":\"IND-2026-QS5LQ\",\"to_society_id\":2,\"to_client_id\":\"IND-2026-BSS4A\"}','2026-08-27 14:32:02');
/*!40000 ALTER TABLE `portal_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_notifications`
--

DROP TABLE IF EXISTS `portal_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `recipient_employee_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFO',
  `target_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_recipient` (`client_id`,`recipient_employee_id`),
  KEY `idx_client_unread` (`client_id`,`recipient_employee_id`,`is_read`,`deleted_at`),
  KEY `idx_client_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_notifications`
--

LOCK TABLES `portal_notifications` WRITE;
/*!40000 ALTER TABLE `portal_notifications` DISABLE KEYS */;
INSERT INTO `portal_notifications` VALUES (1,'IND-2026-QS5LQ','Abhijit01','Bill Generated - August 2026','Your maintenance bill of Rs. 10,000.00 is due by 05 Aug 2026.','INFO','my_dues.php','{\"bill_id\":36,\"event\":\"bill_generated\",\"billing_month\":\"2026-08-01\"}',1,'2026-08-26 20:07:28','2026-08-26 20:06:59',NULL),(2,'IND-2026-QS5LQ','Abhijit01','Bill Overdue - August 2026','Your maintenance bill of Rs. 10,000.00 was due on 05 Aug 2026.','ERROR','my_dues.php','{\"bill_id\":36,\"event\":\"bill_overdue\",\"billing_month\":\"2026-08-01\"}',1,'2026-08-26 20:07:28','2026-08-26 20:07:02',NULL);
/*!40000 ALTER TABLE `portal_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_retention_settings`
--

DROP TABLE IF EXISTS `portal_retention_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_retention_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshots_days` int DEFAULT '90',
  `videos_days` int DEFAULT '30',
  `logs_days` int DEFAULT '180',
  `audit_days` int DEFAULT '365',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_retention_settings`
--

LOCK TABLES `portal_retention_settings` WRITE;
/*!40000 ALTER TABLE `portal_retention_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `portal_retention_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_role_permissions`
--

DROP TABLE IF EXISTS `portal_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_perm` (`client_id`,`role`,`permission`)
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_role_permissions`
--

LOCK TABLES `portal_role_permissions` WRITE;
/*!40000 ALTER TABLE `portal_role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `portal_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_users`
--

DROP TABLE IF EXISTS `portal_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('guard','manager','admin','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_access` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`client_id`,`username`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_users`
--

LOCK TABLES `portal_users` WRITE;
/*!40000 ALTER TABLE `portal_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `portal_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `premises`
--

DROP TABLE IF EXISTS `premises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `premises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_name` (`client_id`,`name`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `premises`
--

LOCK TABLES `premises` WRITE;
/*!40000 ALTER TABLE `premises` DISABLE KEYS */;
/*!40000 ALTER TABLE `premises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registered_vehicles`
--

DROP TABLE IF EXISTS `registered_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registered_vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plate_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` enum('car','truck','motorbike','bus','bicycle','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'car',
  `owner_type` enum('EMPLOYEE','GUEST','VIP') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'EMPLOYEE',
  `owner_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_plate` (`client_id`,`plate_number`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registered_vehicles`
--

LOCK TABLES `registered_vehicles` WRITE;
/*!40000 ALTER TABLE `registered_vehicles` DISABLE KEYS */;
/*!40000 ALTER TABLE `registered_vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `default_permissions` json NOT NULL,
  `allowed_child_roles` json NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_rank` int DEFAULT '100',
  PRIMARY KEY (`client_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `snapshot_logs`
--

DROP TABLE IF EXISTS `snapshot_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `snapshot_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confidence` float DEFAULT '0',
  `bbox` json DEFAULT NULL,
  `snapshot_type` enum('CROP','FULL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'CROP',
  `resolution` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saved_success` tinyint(1) DEFAULT '1',
  `save_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4426 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `snapshot_logs`
--

LOCK TABLES `snapshot_logs` WRITE;
/*!40000 ALTER TABLE `snapshot_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `snapshot_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `society`
--

DROP TABLE IF EXISTS `society`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `society` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legacy_client_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `society_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `society_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `society_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pincode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_towers` int unsigned DEFAULT NULL,
  `number_of_flats` int unsigned DEFAULT NULL,
  `number_of_cameras` int unsigned DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_society_code` (`society_code`),
  UNIQUE KEY `uq_society_name_city` (`society_name`,`city`),
  UNIQUE KEY `uq_society_legacy_client` (`legacy_client_id`),
  KEY `idx_society_status` (`status`),
  KEY `idx_society_city` (`city`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `society`
--

LOCK TABLES `society` WRITE;
/*!40000 ALTER TABLE `society` DISABLE KEYS */;
/*!40000 ALTER TABLE `society` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `society_members`
--

DROP TABLE IF EXISTS `society_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `society_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `due_day` tinyint NOT NULL DEFAULT '10',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `plot_size_sqft` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code_client` (`client_id`,`member_code`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_emp` (`client_id`,`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `society_members`
--

LOCK TABLES `society_members` WRITE;
/*!40000 ALTER TABLE `society_members` DISABLE KEYS */;
INSERT INTO `society_members` VALUES (6,'IND-2026-QS5LQ','MBR-0001','Abhijit Santra','A-10','abhijitsantra325@gmail.com','9339293101','Abhijit01',10000.00,5,'active',NULL,'Abhijit Santra','2026-08-25 14:29:40','2026-08-25 14:29:40',NULL);
/*!40000 ALTER TABLE `society_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `society_settings`
--

DROP TABLE IF EXISTS `society_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `society_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `society_id` bigint unsigned NOT NULL,
  `logo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_color` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `welcome_message` text COLLATE utf8mb4_unicode_ci,
  `emergency_contacts` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_society_settings_society` (`society_id`),
  CONSTRAINT `fk_society_settings_society` FOREIGN KEY (`society_id`) REFERENCES `society` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `society_settings`
--

LOCK TABLES `society_settings` WRITE;
/*!40000 ALTER TABLE `society_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `society_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'INFO',
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'SYSTEM',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=38509 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`client_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_allowed` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_user_perm` (`client_id`,`user_id`,`permission_name`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_preferences` (
  `client_id` varchar(50) NOT NULL,
  `user_id` int NOT NULL,
  `theme` varchar(10) DEFAULT 'light',
  PRIMARY KEY (`client_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES ('IND-2026-QS5LQ',63,'dark');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_society_mapping`
--

DROP TABLE IF EXISTS `user_society_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_society_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `society_id` bigint unsigned NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `default_user_key` bigint DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_society` (`user_id`,`society_id`),
  UNIQUE KEY `uq_user_default_society` (`default_user_key`),
  KEY `idx_mapping_user` (`user_id`),
  KEY `idx_mapping_society` (`society_id`),
  CONSTRAINT `fk_mapping_society` FOREIGN KEY (`society_id`) REFERENCES `society` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mapping_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_society_mapping`
--

LOCK TABLES `user_society_mapping` WRITE;
/*!40000 ALTER TABLE `user_society_mapping` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_society_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Abhijit Santra','abhijitsantra325@gmail.com','9339293101','pbkdf2:sha256:600000$rPlh6Ia3VVfQTKTK$d8cd622aec609f214e3f92415b1c7e46524b5655888f034f689ccf90f364302b','ACTIVE','2026-08-27 08:01:23','2026-08-27 08:01:23'),(2,'Jethalal Gada','abhijitsantra1005@gmail.com','+919339293101','pbkdf2:sha256:600000$TNkagIrODGs3o5Z3$bbbc95421b822784b8f5798f766ce7cac51c5d858f6ecbc017309ff028d3132e','ACTIVE','2026-08-27 08:01:23','2026-08-27 08:01:23');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_logs`
--

DROP TABLE IF EXISTS `vehicle_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_source` enum('AUTO','MANUAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'AUTO',
  `vehicle_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detected` tinyint(1) DEFAULT '1',
  `plate_read_success` tinyint(1) DEFAULT '1',
  `authorized` tinyint(1) DEFAULT '0',
  `confidence` float DEFAULT '0',
  `plate_confidence` float DEFAULT NULL,
  `bbox` json DEFAULT NULL,
  `entry_type` enum('ENTRY','EXIT','UNKNOWN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ENTRY',
  `detection_error_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_error_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `model_version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_engine` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_time_ms` int DEFAULT NULL,
  `image_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `snapshot_id` int DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_ts` (`client_id`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_logs`
--

LOCK TABLES `vehicle_logs` WRITE;
/*!40000 ALTER TABLE `vehicle_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_logs`
--

DROP TABLE IF EXISTS `video_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `video_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `video_path` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `frame_count` int DEFAULT '0',
  `duration_seconds` float DEFAULT NULL,
  `recording_started_at` datetime DEFAULT NULL,
  `recording_ended_at` datetime DEFAULT NULL,
  `success` tinyint(1) DEFAULT '1',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_logs`
--

LOCK TABLES `video_logs` WRITE;
/*!40000 ALTER TABLE `video_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'eyesense_licenses'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-27 14:45:16
