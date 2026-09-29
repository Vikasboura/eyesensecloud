-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: eyesense_licenses
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_requests`
--
/* Eyesense Version - 1.0.0 */


CREATE TABLE IF NOT EXISTS `access_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `request_type` enum('FACE','VEHICLE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_path` longtext COLLATE utf8mb4_unicode_ci,
  `snapshot_b64` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','APPROVED','DENIED','IGNORED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `duration_minutes` int DEFAULT '60',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_status` (`client_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `alert_logs`
--

CREATE TABLE IF NOT EXISTS `alert_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_level` enum('INFO','WARNING','CRITICAL') COLLATE utf8mb4_unicode_ci DEFAULT 'INFO',
  `message` text COLLATE utf8mb4_unicode_ci,
  `channel` enum('SYSTEM','EMAIL','SMS','WHATSAPP','MQTT','UI') COLLATE utf8mb4_unicode_ci DEFAULT 'SYSTEM',
  `status` enum('SENT','FAILED','PENDING') COLLATE utf8mb4_unicode_ci DEFAULT 'SENT',
  `error_detail` text COLLATE utf8mb4_unicode_ci,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `audit_logs`
--

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actor_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `camera_assignment`
--

CREATE TABLE IF NOT EXISTS `camera_assignment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_user_cam` (`client_id`,`user_id`,`camera_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cameras`
--

CREATE TABLE IF NOT EXISTS `cameras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `premises_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_camid` (`client_id`,`camera_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `chat_messages`
--

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sender_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiver_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` enum('TEXT','IMAGE','PDF','AUDIO','VIDEO','FILE') COLLATE utf8mb4_unicode_ci DEFAULT 'TEXT',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `clients`
-- version - 1.0.1 (Aman sen)

CREATE TABLE IF NOT EXISTS `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `machine_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `sa_username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `hostel_management_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `daily_summary`
--

CREATE TABLE IF NOT EXISTS `daily_summary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

--
-- Table structure for table `employees`
--

CREATE TABLE IF NOT EXISTS `employees` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `global_user_id` bigint DEFAULT NULL,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `government_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_level` enum('L0','L1','L2','L3','ADMIN') COLLATE utf8mb4_unicode_ci DEFAULT 'L1',
  `status` enum('active','inactive','suspended','terminated') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `face_registered` tinyint(1) DEFAULT '0',
  `face_embedding_count` int DEFAULT '0',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_seen_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `valid_until` datetime DEFAULT NULL,
  `parent_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hierarchy_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_permissions` json DEFAULT NULL,
  `plotno` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_address` text COLLATE utf8mb4_unicode_ci,
  `latitude` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_maplink` text COLLATE utf8mb4_unicode_ci,
  `alt_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_notes` text COLLATE utf8mb4_unicode_ci,
  `plot_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plot_filled_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_empid` (`client_id`,`employee_id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `face_embeddings`
--

CREATE TABLE IF NOT EXISTS `face_embeddings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `faiss_index` int NOT NULL,
  `embedding_model` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'buffalo_l',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `embedding_blob` blob,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_empid` (`client_id`,`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `face_logs`
--

CREATE TABLE IF NOT EXISTS `face_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_source` enum('AUTO','MANUAL') COLLATE utf8mb4_unicode_ci DEFAULT 'AUTO',
  `detection_type` enum('FACE','PERSON') COLLATE utf8mb4_unicode_ci DEFAULT 'FACE',
  `person_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recognized` tinyint(1) DEFAULT '0',
  `authorized` tinyint(1) DEFAULT '0',
  `confidence` float DEFAULT '0',
  `embedding_distance` float DEFAULT NULL,
  `bbox` json DEFAULT NULL,
  `detection_success` tinyint(1) DEFAULT '1',
  `recognition_success` tinyint(1) DEFAULT '1',
  `detection_error_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recognition_error_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `model_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_time_ms` int DEFAULT NULL,
  `image_path` longtext COLLATE utf8mb4_unicode_ci,
  `snapshot_id` int DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_ts` (`client_id`,`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=3618 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `guest_documents`
--

CREATE TABLE IF NOT EXISTS `guest_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `identity_id` int DEFAULT NULL,
  `guest_profile_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_data` longtext COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `guest_identities`
--

CREATE TABLE IF NOT EXISTS `guest_identities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name_lower` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `guest_visits`
--

CREATE TABLE IF NOT EXISTS `guest_visits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `government_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_profile_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_request_id` int DEFAULT NULL,
  `identity_id` int DEFAULT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `valid_until` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `status` enum('ACTIVE','EXPIRED','REVOKED') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVE',
  `premises_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_status` (`client_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `guests`
--

CREATE TABLE IF NOT EXISTS `guests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approved_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `valid_until` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `licenses`
--

CREATE TABLE IF NOT EXISTS `licenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `auto_renew` tinyint(1) DEFAULT '0',
  `sa_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sa_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sa_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'completed',
  `amount` decimal(10,2) DEFAULT '0.00',
  `license_key_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_machine_id` (`machine_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `maintenance_bills`
--

CREATE TABLE IF NOT EXISTS `maintenance_bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member_id` int NOT NULL,
  `billing_month` date NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `previous_due` decimal(10,2) DEFAULT '0.00',
  `total_due` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('PENDING','UPLOADED','VERIFIED','REJECTED','OVERDUE','PARTIAL') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `overdue_flagged_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Late penalty applied when PARTIAL goes OVERDUE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_month` (`member_id`,`billing_month`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_status` (`client_id`,`status`),
  KEY `idx_month` (`client_id`,`billing_month`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `maintenance_receipts`
--

CREATE TABLE IF NOT EXISTS `maintenance_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bill_id` int NOT NULL,
  `member_id` int NOT NULL,
  `uploaded_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('PDF','IMAGE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_kb` int DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_mode` enum('Cash','UPI','Bank Transfer','Cheque','QR Code','Other') COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `payment_date` date NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `review_status` enum('PENDING','VERIFIED','REJECTED','SUPERSEDED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `reviewed_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `notification_sent` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_num` (`client_id`,`receipt_number`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_bill` (`bill_id`),
  KEY `idx_member` (`client_id`,`member_id`),
  KEY `idx_review` (`client_id`,`review_status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `maintenance_settings`
--

CREATE TABLE IF NOT EXISTS `maintenance_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `society_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'Our Society',
  `default_amount` decimal(10,2) DEFAULT '0.00',
  `default_due_day` tinyint DEFAULT '10',
  `admin_emails` text COLLATE utf8mb4_unicode_ci,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cron_last_run` datetime DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `landmark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pincode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes_partial` text COLLATE utf8mb4_unicode_ci,
  `notes_exact` text COLLATE utf8mb4_unicode_ci,
  `notes_overdue` text COLLATE utf8mb4_unicode_ci,
  `notes_overpaid` text COLLATE utf8mb4_unicode_ci,
  `penalty_amount` decimal(10,2) DEFAULT '0.00',
  `penalty_days` int DEFAULT '0',
  `notify_email` tinyint(1) DEFAULT '1',
  `notify_sms` tinyint(1) DEFAULT '0',
  `notify_whatsapp` tinyint(1) DEFAULT '0',
  `admin_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_upi_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_bank_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_ifsc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_instructions` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `member_registration_requests`
--

CREATE TABLE IF NOT EXISTS `member_registration_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flat_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monthly_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `due_day` tinyint NOT NULL DEFAULT '10',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `reject_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_status` (`client_id`,`status`),
  KEY `idx_login` (`client_id`,`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `password_reset_otps`
--

CREATE TABLE IF NOT EXISTS `password_reset_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`client_id`,`employee_id`,`otp`,`used`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `plans`
--

CREATE TABLE IF NOT EXISTS `plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `portal_audit_logs`
--

CREATE TABLE IF NOT EXISTS `portal_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `portal_retention_settings`
--

CREATE TABLE IF NOT EXISTS `portal_retention_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshots_days` int DEFAULT '90',
  `videos_days` int DEFAULT '30',
  `logs_days` int DEFAULT '180',
  `audit_days` int DEFAULT '365',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `portal_role_permissions`
--

CREATE TABLE IF NOT EXISTS `portal_role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_perm` (`client_id`,`role`,`permission`)
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `portal_users`
--

CREATE TABLE IF NOT EXISTS `portal_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('guard','manager','admin','superadmin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_access` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`client_id`,`username`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `premises`
--

CREATE TABLE IF NOT EXISTS `premises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_name` (`client_id`,`name`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `registered_vehicles`
--

CREATE TABLE IF NOT EXISTS `registered_vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plate_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` enum('car','truck','motorbike','bus','bicycle','other') COLLATE utf8mb4_unicode_ci DEFAULT 'car',
  `owner_type` enum('EMPLOYEE','GUEST','VIP') COLLATE utf8mb4_unicode_ci DEFAULT 'EMPLOYEE',
  `owner_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_plate` (`client_id`,`plate_number`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `default_permissions` json NOT NULL,
  `allowed_child_roles` json NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_rank` int DEFAULT '100',
  PRIMARY KEY (`client_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `snapshot_logs`
--

CREATE TABLE IF NOT EXISTS `snapshot_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` longtext COLLATE utf8mb4_unicode_ci,
  `confidence` float DEFAULT '0',
  `bbox` json DEFAULT NULL,
  `snapshot_type` enum('CROP','FULL') COLLATE utf8mb4_unicode_ci DEFAULT 'CROP',
  `resolution` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saved_success` tinyint(1) DEFAULT '1',
  `save_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4426 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `society_members`
--

CREATE TABLE IF NOT EXISTS `society_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flat_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `due_day` tinyint NOT NULL DEFAULT '10',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code_client` (`client_id`,`member_code`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_emp` (`client_id`,`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `system_logs`
--

CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'INFO',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'SYSTEM',
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=38509 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `system_settings`
--

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`client_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_permissions`
--

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_allowed` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cid_user_perm` (`client_id`,`user_id`,`permission_name`),
  KEY `idx_cid` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `vehicle_logs`
--

CREATE TABLE IF NOT EXISTS `vehicle_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_source` enum('AUTO','MANUAL') COLLATE utf8mb4_unicode_ci DEFAULT 'AUTO',
  `vehicle_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detected` tinyint(1) DEFAULT '1',
  `plate_read_success` tinyint(1) DEFAULT '1',
  `authorized` tinyint(1) DEFAULT '0',
  `confidence` float DEFAULT '0',
  `plate_confidence` float DEFAULT NULL,
  `bbox` json DEFAULT NULL,
  `entry_type` enum('ENTRY','EXIT','UNKNOWN') COLLATE utf8mb4_unicode_ci DEFAULT 'ENTRY',
  `detection_error_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_error_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `model_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_engine` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_time_ms` int DEFAULT NULL,
  `image_path` longtext COLLATE utf8mb4_unicode_ci,
  `snapshot_id` int DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_ts` (`client_id`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `video_logs`
--

CREATE TABLE IF NOT EXISTS `video_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `video_path` longtext COLLATE utf8mb4_unicode_ci,
  `frame_count` int DEFAULT '0',
  `duration_seconds` float DEFAULT NULL,
  `recording_started_at` datetime DEFAULT NULL,
  `recording_ended_at` datetime DEFAULT NULL,
  `success` tinyint(1) DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`client_id`),
  KEY `idx_cid_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dump completed on 2026-05-12 20:21:05

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `society`
--

CREATE TABLE IF NOT EXISTS `society` (
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
  UNIQUE KEY `uq_society_legacy_client` (`legacy_client_id`),
  UNIQUE KEY `uq_society_code` (`society_code`),
  UNIQUE KEY `uq_society_name_city` (`society_name`,`city`),
  KEY `idx_society_status` (`status`),
  KEY `idx_society_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_society_mapping`
--

CREATE TABLE IF NOT EXISTS `user_society_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `society_id` bigint unsigned NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_society` (`user_id`,`society_id`),
  KEY `idx_mapping_user` (`user_id`),
  KEY `idx_mapping_society` (`society_id`),
  CONSTRAINT `fk_mapping_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mapping_society` FOREIGN KEY (`society_id`) REFERENCES `society` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `society_settings`
--

CREATE TABLE IF NOT EXISTS `society_settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `bulk_detection_alerts`
--
/* Created new table for bulk detection alerts for Issue number : ES-53-Bulk-Unknown-Person-Detection-&-Weapon-Detection-with-Email-Notifications */

CREATE TABLE IF NOT EXISTS `bulk_detection_alerts` (
  `id`                   int          NOT NULL AUTO_INCREMENT,
  `client_id`            varchar(64)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `camera_id`            varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `detection_timestamp`  datetime     NOT NULL,
  `unknown_count`        int          NOT NULL DEFAULT '0',
  `alert_sent`           tinyint(1)   NOT NULL DEFAULT '0',
  `cooldown_expires_at`  datetime     DEFAULT NULL,
  `snapshot_path`        longtext     COLLATE utf8mb4_unicode_ci,
  `email_recipients`     varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status`               varchar(50)  COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at`           datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cid`       (`client_id`),
  KEY `idx_camera_ts` (`client_id`,`camera_id`,`detection_timestamp`),
  KEY `idx_status`    (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Migration v5: Plot Size Billing Rate
-- Adds sqft_rate to maintenance_settings and plot_size_sqft to society_members
-- and member_registration_requests to support per-sqft maintenance calculation.
-- Feature: Plot-Size-Based-Billing (branch: 59-es-59-package-eyesense-server)
-- MySQL-safe, idempotent version (uses INFORMATION_SCHEMA checks)
-- ============================================================

DROP PROCEDURE IF EXISTS _v5_add_plot_cols;
DELIMITER $$
CREATE PROCEDURE _v5_add_plot_cols()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'sqft_rate') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `sqft_rate` DECIMAL(10,4) NOT NULL DEFAULT 0
      COMMENT 'Rs per sqft; 0 = feature disabled — monthly amount is set manually';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'society_members'
                   AND COLUMN_NAME  = 'plot_size_sqft') THEN
    ALTER TABLE `society_members`
      ADD COLUMN `plot_size_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0
      COMMENT 'Member plot area in square feet; 0 = not set';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'member_registration_requests'
                   AND COLUMN_NAME  = 'plot_size_sqft') THEN
    ALTER TABLE `member_registration_requests`
      ADD COLUMN `plot_size_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0
      COMMENT 'Plot size (sqft) submitted by member during self-registration';
  END IF;
END$$
DELIMITER ;
CALL _v5_add_plot_cols();
DROP PROCEDURE IF EXISTS _v5_add_plot_cols;

-- ============================================================
-- Migration v6: Member Complaints
-- Creates member_complaints table for complaint management feature.
-- Feature: Member-Complaint-Management (branch: 63-es-63-caps-town-requirement)
-- ============================================================
/* Created new table for member complaints for Issue number : ES-64-Member-Complaint-Management-Feature */

CREATE TABLE IF NOT EXISTS `member_complaints` (
  `id`              INT            NOT NULL AUTO_INCREMENT,
  `client_id`       VARCHAR(64)    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member_id`       INT            NOT NULL COMMENT 'society_members.id',
  `employee_id`     VARCHAR(100)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `full_name`       VARCHAR(200)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `flat_number`     VARCHAR(100)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email`           VARCHAR(200)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile`          VARCHAR(30)    COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category`        VARCHAR(100)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `subject`         VARCHAR(500)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description`     TEXT           COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority`        ENUM('LOW','MEDIUM','HIGH','URGENT') NOT NULL DEFAULT 'MEDIUM',
  `status`          ENUM('OPEN','REVIEWED','RESOLVED','REJECTED') NOT NULL DEFAULT 'OPEN',
  `admin_remarks`   TEXT           COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by`     VARCHAR(100)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at`     DATETIME       DEFAULT NULL,
  `resolved_at`     DATETIME       DEFAULT NULL,
  `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client`  (`client_id`),
  KEY `idx_member`  (`member_id`),
  KEY `idx_status`  (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Member complaints raised through the EyeSense Cloud Portal';

-- ============================================================
-- Migration v7: Society Ledger (Expense Tracking)
-- Creates society_ledger table for tracking society income and expenses.
-- Feature: Admin-Expense-Ledger-Tracking (branch: 63-es-63-caps-town-requirement)
-- ============================================================
/* Created new table for society expense ledger for Issue number : ES-65-Admin-Expense-Ledger-Tracking */

CREATE TABLE IF NOT EXISTS `society_ledger` (
  `id`                INT            NOT NULL AUTO_INCREMENT,
  `client_id`         VARCHAR(64)    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `transaction_date`  DATE           NOT NULL COMMENT 'The actual date the transaction occurred',
  `transaction_type`  ENUM('CREDIT','DEBIT') NOT NULL COMMENT 'CREDIT = Income/Inflow, DEBIT = Expense/Outflow',
  `amount`            DECIMAL(12,2)  NOT NULL,
  `party_name`        VARCHAR(255)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `work_description`  VARCHAR(500)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_info`       VARCHAR(255)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cheque details, transaction ID, reference number, etc.',
  `notes`             TEXT           COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance`           DECIMAL(12,2)  NOT NULL DEFAULT 0.00 COMMENT 'Running balance at this transaction point',
  `sort_order`        INT            NOT NULL DEFAULT 0 COMMENT 'Order within same date',
  `created_by`        VARCHAR(100)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Admin who added',
  `created_at`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by`        VARCHAR(100)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Admin who last edited',
  `updated_at`        DATETIME       DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client`       (`client_id`),
  KEY `idx_ledger_sort`  (`transaction_date`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Society income and expense ledger';

-- ============================================================
-- Migration v8: Complaint System Upgrade, Rate Map, Activity Monitor
-- Date: 2026-06-08
-- Branch: 63-es-63-caps-town-requirement
-- Features:
--   - Complaint assignments (multi-admin, email notifications)
--   - Complaint comments/chat with file attachments
--   - Complaint close/withdraw/reopen lifecycle
--   - Table-based maintenance rate map (plot_size → monthly_amount)
--   - Rate map change history (who changed what and when)
--   - Portal login session tracking with IP geolocation
--   - Attachment size limit setting per client
-- MySQL-safe, idempotent version
-- ============================================================

-- ── 1. Extend member_complaints: close/reopen lifecycle ──────────────
DROP PROCEDURE IF EXISTS _v8_patch_complaints;
DELIMITER $$
CREATE PROCEDURE _v8_patch_complaints()
BEGIN
  -- Extend status enum (safe to re-run — MODIFY is idempotent by nature)
  ALTER TABLE `member_complaints`
    MODIFY COLUMN `status`
      ENUM('OPEN','REVIEWED','RESOLVED','REJECTED','CLOSED','WITHDRAWN')
      NOT NULL DEFAULT 'OPEN'
      COMMENT 'CLOSED = admin closed, WITHDRAWN = member withdrew';

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'member_complaints'
                   AND COLUMN_NAME  = 'closed_by') THEN
    ALTER TABLE `member_complaints`
      ADD COLUMN `closed_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
      COMMENT 'employee_id who closed or withdrew the complaint';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'member_complaints'
                   AND COLUMN_NAME  = 'closed_at') THEN
    ALTER TABLE `member_complaints`
      ADD COLUMN `closed_at` DATETIME DEFAULT NULL
      COMMENT 'Timestamp when complaint was closed/withdrawn';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'member_complaints'
                   AND COLUMN_NAME  = 'reopen_count') THEN
    ALTER TABLE `member_complaints`
      ADD COLUMN `reopen_count` INT NOT NULL DEFAULT 0
      COMMENT 'Number of times this complaint has been reopened';
  END IF;
END$$
DELIMITER ;
CALL _v8_patch_complaints();
DROP PROCEDURE IF EXISTS _v8_patch_complaints;

-- ── 2. Complaint assignments: multi-admin assignment tracking ─────────
CREATE TABLE IF NOT EXISTS `complaint_assignments` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `complaint_id`  INT NOT NULL COMMENT 'member_complaints.id',
  `client_id`     VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `assigned_to`   VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'employee_id of the admin being assigned',
  `assigned_by`   VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'employee_id of the admin who made the assignment',
  `assigned_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `removed_at`    DATETIME DEFAULT NULL
    COMMENT 'Set to NOW() when admin is unassigned; NULL means still assigned',
  PRIMARY KEY (`id`),
  KEY `idx_complaint` (`complaint_id`),
  KEY `idx_assignee`  (`client_id`, `assigned_to`),
  KEY `idx_active`    (`complaint_id`, `removed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Tracks which admins are assigned to each complaint, with full history';

-- ── 3. Complaint comments: chat between member and admins ─────────────
CREATE TABLE IF NOT EXISTS `complaint_comments` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `complaint_id`    INT NOT NULL COMMENT 'member_complaints.id',
  `client_id`       VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `author_id`       VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'employee_id of the commenter',
  `author_name`     VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `author_role`     ENUM('MEMBER','ADMIN','SUPERADMIN') NOT NULL DEFAULT 'MEMBER',
  `message`         TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_complaint` (`complaint_id`),
  KEY `idx_client`    (`client_id`),
  KEY `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Chat-style comment thread on each complaint between member and admins';

-- ── 4. Complaint + comment attachments ───────────────────────────────
CREATE TABLE IF NOT EXISTS `complaint_attachments` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `complaint_id`    INT NOT NULL COMMENT 'member_complaints.id',
  `comment_id`      INT DEFAULT NULL
    COMMENT 'complaint_comments.id; NULL = attached to complaint itself',
  `client_id`       VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `file_name`       VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'Original filename as uploaded by user',
  `file_path`       VARCHAR(1000) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'Server-side relative path within uploads folder',
  `file_type`       VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'MIME type e.g. image/jpeg, video/mp4',
  `file_size_kb`    INT NOT NULL DEFAULT 0,
  `uploaded_by`     VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'employee_id who uploaded',
  `uploaded_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_complaint`  (`complaint_id`),
  KEY `idx_comment`    (`comment_id`),
  KEY `idx_client`     (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'File attachments for complaints (comment_id=NULL) and complaint comments';

-- ── 5. Maintenance rate map: plot size to monthly amount ──────────────
CREATE TABLE IF NOT EXISTS `maintenance_rate_map` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `client_id`       VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plot_size_sqft`  DECIMAL(10,2) NOT NULL
    COMMENT 'Plot size in square feet',
  `monthly_amount`  DECIMAL(10,2) NOT NULL
    COMMENT 'Monthly maintenance amount in INR for this plot size',
  `label`           VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Optional label e.g. "Small Plot", "Corner Plot"',
  `created_by`      VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by`      VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_size` (`client_id`, `plot_size_sqft`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Table-based mapping of plot size (sqft) to monthly maintenance amount';

-- ── 6. Rate map history: full audit trail of every rate change ────────
CREATE TABLE IF NOT EXISTS `maintenance_rate_map_history` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `rate_map_id`     INT NOT NULL COMMENT 'maintenance_rate_map.id',
  `client_id`       VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plot_size_sqft`  DECIMAL(10,2) NOT NULL,
  `old_amount`      DECIMAL(10,2) DEFAULT NULL
    COMMENT 'Amount before the change; NULL for initial creation',
  `new_amount`      DECIMAL(10,2) NOT NULL
    COMMENT 'Amount after the change',
  `old_label`       VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_label`       VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_type`     ENUM('CREATED','UPDATED','DELETED') NOT NULL DEFAULT 'UPDATED',
  `changed_by`      VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'employee_id of admin who made the change',
  `changed_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_map`  (`rate_map_id`),
  KEY `idx_client`    (`client_id`),
  KEY `idx_changed`   (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Full history of every change to maintenance rate map entries';

-- ── 7. Attachment size limit + IP / user-agent on audit logs ─────────
DROP PROCEDURE IF EXISTS _v8_patch_misc;
DELIMITER $$
CREATE PROCEDURE _v8_patch_misc()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'max_attachment_size_mb') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `max_attachment_size_mb` INT NOT NULL DEFAULT 5
      COMMENT 'Max file upload size (MB) for complaint and comment attachments. SA/Admin configurable.';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'portal_audit_logs'
                   AND COLUMN_NAME  = 'ip_address') THEN
    ALTER TABLE `portal_audit_logs`
      ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL
      COMMENT 'Client IP address at time of action';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'portal_audit_logs'
                   AND COLUMN_NAME  = 'user_agent') THEN
    ALTER TABLE `portal_audit_logs`
      ADD COLUMN `user_agent` VARCHAR(500) DEFAULT NULL
      COMMENT 'Browser / client user-agent string';
  END IF;
END$$
DELIMITER ;
CALL _v8_patch_misc();
DROP PROCEDURE IF EXISTS _v8_patch_misc;

-- ── 8. Portal login sessions with geo-location ────────────────────────
CREATE TABLE IF NOT EXISTS `portal_login_sessions` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `client_id`       VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `employee_id`     VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `full_name`       VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role`            VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address`      VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'IPv4 or IPv6 address',
  `user_agent`      VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city`            VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region`          VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'State or province',
  `country`         VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isp`             VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Internet Service Provider name',
  `lat`             DECIMAL(10,6) DEFAULT NULL COMMENT 'Latitude from geo-lookup',
  `lon`             DECIMAL(10,6) DEFAULT NULL COMMENT 'Longitude from geo-lookup',
  `login_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`    DATETIME DEFAULT NULL
    COMMENT 'Last heartbeat received from this browser session',
  `logout_at`       DATETIME DEFAULT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = session still active, 0 = logged out',
  PRIMARY KEY (`id`),
  KEY `idx_client`   (`client_id`),
  KEY `idx_employee` (`client_id`, `employee_id`),
  KEY `idx_login`    (`login_at`),
  KEY `idx_active`   (`client_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Every portal login tracked with IP, browser, and geo-location for activity monitoring';

-- ── 9. Portal in-app notifications ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `portal_notifications` (
  `id`                    INT NOT NULL AUTO_INCREMENT,
  `client_id`             VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `recipient_employee_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message`               TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kind`                  VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFO',
  `target_url`            VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json`          TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read`               TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`               DATETIME DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`            DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_recipient` (`client_id`, `recipient_employee_id`),
  KEY `idx_client_unread` (`client_id`, `recipient_employee_id`, `is_read`, `deleted_at`),
  KEY `idx_client_created` (`client_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Portal in-app notifications for members and admins';

-- ── 10. Portal user preferences ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `client_id` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` INT NOT NULL,
  `theme` VARCHAR(10) COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  PRIMARY KEY (`client_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Portal user preferences such as theme selection';

-- ============================================================
-- Migration v9: Tenant Management System
-- Date: 2026-06-17
-- Branch: 78-es-78-tenant-management--client-profile-module
-- Features:
--   - Full tenant record management linked to society members
--   - Tenant self-registration via signed stateless URL tokens
--   - Tenant approval workflow (Pending → Approved/Rejected → Active/Inactive)
--   - Government ID verification with optional document upload
--   - Emergency contact and occupancy tracking
--   - Per-client feature-flag toggles in maintenance_settings
-- MySQL-safe, idempotent version
-- ============================================================

-- ── 1. Main tenant records table ──────────────────────────────────────
/* Created new table for society tenant records for Issue:
   ES-78-Tenant-Management-Client-Profile-Module */

CREATE TABLE IF NOT EXISTS `society_tenants` (
  -- ── Primary identification ───────────────────────────────────────────
  `id`                        INT           NOT NULL AUTO_INCREMENT,
  `client_id`                 VARCHAR(64)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'Society client identifier (IND-YYYY-XXXXX)',
  `member_id`                 INT           NOT NULL
    COMMENT 'society_members.id — which member owns/sponsors this tenant',

  -- ── Basic personal information ───────────────────────────────────────
  `full_name`                 VARCHAR(150)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email`                     VARCHAR(150)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile`                    VARCHAR(20)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alt_mobile`                VARCHAR(20)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Alternate / secondary mobile number',
  `gender`                    ENUM('Male','Female','Other') DEFAULT NULL,
  `dob`                       DATE          DEFAULT NULL
    COMMENT 'Date of birth (optional)',

  -- ── Property / occupancy details ────────────────────────────────────
  `plot_number`               VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Plot or survey number within the society',
  `block_wing`                VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Block / wing identifier (e.g. A, East Wing)',
  `floor_number`              VARCHAR(20)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flat_number`               VARCHAR(50)   COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'Room / flat number of the tenanted unit',
  `occupancy_start`           DATE          DEFAULT NULL
    COMMENT 'Date from which the tenant started occupying the unit',
  `occupancy_end`             DATE          DEFAULT NULL
    COMMENT 'Expected / actual vacate date; NULL = indefinite',

  -- ── Government identity verification ────────────────────────────────
  `govt_id_type`              ENUM('Aadhaar','PAN','Passport','Driving License','Voter ID') DEFAULT NULL,
  `govt_id_number`            VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Masked/encrypted government ID number',
  `govt_id_doc_path`          VARCHAR(500)  COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Relative server path to uploaded ID document image/PDF',

  -- ── Permanent address ────────────────────────────────────────────────
  `permanent_address`         TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perm_city`                 VARCHAR(100)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perm_state`                VARCHAR(100)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perm_country`              VARCHAR(100)  COLLATE utf8mb4_unicode_ci DEFAULT 'India',
  `perm_pincode`              VARCHAR(10)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  -- ── Tenant-specific details ──────────────────────────────────────────
  `purpose`                   ENUM('Rental','Family Member','Student','Employee','Guest','Other') DEFAULT NULL
    COMMENT 'Reason for occupancy',
  `occupation`                VARCHAR(100)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name`              VARCHAR(150)  COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Employer / company if occupation is salaried',
  `emergency_contact_name`    VARCHAR(100)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_number`  VARCHAR(20)   COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes`                     TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Additional notes from member or admin',

  -- ── System / workflow fields ─────────────────────────────────────────
  `registration_status`       ENUM('Pending','Approved','Rejected','Active','Inactive') NOT NULL DEFAULT 'Pending'
    COMMENT 'Pending = awaiting approval, Active = approved and currently occupying',
  `registered_via`            ENUM('Manual','Link') NOT NULL DEFAULT 'Manual'
    COMMENT 'Manual = added by member/admin, Link = tenant self-registered via token URL',
  `registered_by`             VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'employee_id of the portal user who created this record',
  `approved_by`               VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'employee_id of admin who approved/rejected',
  `approved_at`               DATETIME      DEFAULT NULL,
  `reject_reason`             TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_client`   (`client_id`),
  KEY `idx_member`   (`client_id`, `member_id`),
  KEY `idx_status`   (`client_id`, `registration_status`),
  KEY `idx_created`  (`client_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'Tenant records for each society, linked to the sponsoring member';

-- ── 2. Feature-flag toggles for Tenant Management + society_members extras ──
DROP PROCEDURE IF EXISTS _v9_tenant_flags;
DELIMITER $$
CREATE PROCEDURE _v9_tenant_flags()
BEGIN
  -- maintenance_settings: tenant_* flags
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_member_can_add') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_member_can_add` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Feature flag: allow regular society members to add tenants directly';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_member_can_gen_link') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_member_can_gen_link` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Feature flag: allow members to generate a tenant self-registration token URL';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_admin_can_add') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_admin_can_add` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Feature flag: allow admins to add / edit tenant records';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_sa_can_add') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_sa_can_add` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Feature flag: allow superadmin to add / update / delete any tenant';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_doc_required') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_doc_required` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Feature flag: make government ID document upload mandatory during registration';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'maintenance_settings'
                   AND COLUMN_NAME  = 'tenant_approval_required') THEN
    ALTER TABLE `maintenance_settings`
      ADD COLUMN `tenant_approval_required` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Feature flag: require admin approval before a tenant becomes Active';
  END IF;

  -- society_members: block_wing
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'society_members'
                   AND COLUMN_NAME  = 'block_wing') THEN
    ALTER TABLE `society_members`
      ADD COLUMN `block_wing` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
      COMMENT 'Block or wing identifier within the society (e.g. A, B, East Wing)';
  END IF;

  -- society_members: floor_number
  IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'society_members'
                   AND COLUMN_NAME  = 'floor_number') THEN
    ALTER TABLE `society_members`
      ADD COLUMN `floor_number` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
      COMMENT 'Floor number of the members unit';
  END IF;
END$$
DELIMITER ;
CALL _v9_tenant_flags();
DROP PROCEDURE IF EXISTS _v9_tenant_flags;

-- ============================================================
-- Migration v10 — Tenant Portal Account Columns
-- Date    : 2026-06-24
-- Branch  : 78-es-78-tenant-management--client-profile-module
-- Purpose : Allow tenants to log in to the EyeSense self-service
--           portal (tenant_dashboard.php) using a username +
--           bcrypt password. Tenants can also authenticate using
--           their registered mobile number or email address.
--           Password is hashed via PHP password_hash(BCRYPT).
--           Login is handled in portal/login.php after the
--           existing employee lookup (fallback path).
-- MySQL-safe, idempotent version
-- ============================================================

-- ── 1. Existing deployments: add portal columns if not already present ────
DROP PROCEDURE IF EXISTS _v10_add_portal_cols;
DELIMITER $$
CREATE PROCEDURE _v10_add_portal_cols()
BEGIN
  -- portal_username
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'society_tenants'
      AND COLUMN_NAME  = 'portal_username'
  ) THEN
    ALTER TABLE society_tenants
      ADD COLUMN portal_username VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        COMMENT 'Tenant portal login username (mobile / email / custom handle) — added v10';
  END IF;

  -- portal_password_hash
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'society_tenants'
      AND COLUMN_NAME  = 'portal_password_hash'
  ) THEN
    ALTER TABLE society_tenants
      ADD COLUMN portal_password_hash VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
        COMMENT 'Tenant portal password stored as PHP bcrypt hash — added v10';
  END IF;

  -- unique constraint (client_id, portal_username)
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'society_tenants'
      AND CONSTRAINT_NAME = 'uq_tenant_portal_username'
  ) THEN
    ALTER TABLE society_tenants
      ADD CONSTRAINT uq_tenant_portal_username UNIQUE (client_id, portal_username);
  END IF;
END$$
DELIMITER ;
CALL _v10_add_portal_cols();
DROP PROCEDURE IF EXISTS _v10_add_portal_cols;

-- ============================================================
-- Demo and Subscription Requests
-- ============================================================

CREATE TABLE IF NOT EXISTS demo_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    organization VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolution TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ============================================================
-- Restore session variables
-- ============================================================
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-12 20:21:05

ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `global_user_id` BIGINT DEFAULT NULL AFTER `id`;

ALTER TABLE `employees`
  ADD COLUMN `location_source` VARCHAR(10) NOT NULL DEFAULT 'gps' COMMENT 'Source of effective coordinates: gps or manual',
  ADD COLUMN `gps_latitude` VARCHAR(60) DEFAULT NULL COMMENT 'Latest raw GPS latitude reading',
  ADD COLUMN `gps_longitude` VARCHAR(60) DEFAULT NULL COMMENT 'Latest raw GPS longitude reading',
  ADD COLUMN `manual_location_updated_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when manual location was last pinned/updated',
  ADD COLUMN `manual_location_set_by` VARCHAR(64) DEFAULT NULL COMMENT 'source:id of the actor who set the manual location (e.g. employees:63)';

-
-- ------------------------------------------------------------
-- Schema Additions for Existing Tables (from Migrations)
-- ------------------------------------------------------------
-- Add society_id to detection tables
ALTER TABLE `cameras` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `face_logs` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `vehicle_logs` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `snapshot_logs` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `video_logs` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
-- Add society fields to licenses
ALTER TABLE `licenses` ADD COLUMN `society_id` bigint unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `licenses` ADD COLUMN `legacy_client_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `society_id`;

-- Add default_user_key to user_society_mapping
ALTER TABLE `user_society_mapping` ADD COLUMN `default_user_key` varchar(64) DEFAULT NULL;
-- Add payment and tenant configuration settings to maintenance_settings
ALTER TABLE `maintenance_settings` ADD COLUMN `sqft_rate` decimal(10,4) NOT NULL DEFAULT 0;
ALTER TABLE `maintenance_settings` ADD COLUMN `max_attachment_size_mb` int NOT NULL DEFAULT 5;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_member_can_add` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_member_can_gen_link` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_admin_can_add` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_sa_can_add` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_doc_required` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `maintenance_settings` ADD COLUMN `tenant_approval_required` tinyint(1) NOT NULL DEFAULT 1;
-- Add plot size and block/wing details to society_members
ALTER TABLE `society_members` ADD COLUMN `plot_size_sqft` decimal(10,2) NOT NULL DEFAULT 0;
ALTER TABLE `society_members` ADD COLUMN `block_wing` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `society_members` ADD COLUMN `floor_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
-- Add plot size to member_registration_requests
ALTER TABLE `member_registration_requests` ADD COLUMN `plot_size_sqft` decimal(10,2) NOT NULL DEFAULT 0;
-- Add closure and reopen fields to member_complaints
ALTER TABLE `member_complaints` MODIFY COLUMN `status` enum('OPEN','REVIEWED','RESOLVED','REJECTED','CLOSED','WITHDRAWN') NOT NULL DEFAULT 'OPEN';
ALTER TABLE `member_complaints` ADD COLUMN `closed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `member_complaints` ADD COLUMN `closed_at` datetime DEFAULT NULL;
ALTER TABLE `member_complaints` ADD COLUMN `reopen_count` int NOT NULL DEFAULT 0;
-- Add client tracking columns to portal_audit_logs
ALTER TABLE `portal_audit_logs` ADD COLUMN `ip_address` varchar(45) DEFAULT NULL;
ALTER TABLE `portal_audit_logs` ADD COLUMN `user_agent` varchar(500) DEFAULT NULL;
-- Add login credentials to society_tenants
ALTER TABLE `society_tenants` ADD COLUMN `portal_username` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `society_tenants` ADD COLUMN `portal_password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
-- Dedicated Society Audit Log table
CREATE TABLE IF NOT EXISTS `society_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `society_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint DEFAULT NULL,
  `actor_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sal_society` (`society_id`),
  KEY `idx_sal_actor` (`actor_user_id`),
  KEY `idx_sal_action` (`action_type`),
  KEY `idx_sal_created` (`created_at`),
  CONSTRAINT `fk_sal_society` FOREIGN KEY (`society_id`) REFERENCES `society` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
