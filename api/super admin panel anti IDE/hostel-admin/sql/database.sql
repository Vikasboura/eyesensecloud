-- Database Schema for Hostel Management System (Super Admin Panel)
-- Production Level PDO & Core PHP Architecture

CREATE DATABASE IF NOT EXISTS `hostel_admin_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hostel_admin_db`;

-- --------------------------------------------------------
-- Table structure for table `admins`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin','Warden') NOT NULL DEFAULT 'Super Admin',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `hostels`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `hostels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_name` varchar(255) NOT NULL,
  `hostel_code` varchar(50) NOT NULL UNIQUE,
  `hostel_type` enum('Boys','Girls','Co-Ed','PG','Staff') NOT NULL,
  `description` text DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'India',
  `pincode` varchar(20) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `hostel_image` varchar(255) DEFAULT NULL,
  `hostel_rules` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `rooms`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_type` enum('Single','Double','Triple','Dormitory') NOT NULL DEFAULT 'Double',
  `capacity` int(11) NOT NULL DEFAULT 2,
  `rent_per_month` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Available','Full','Under Maintenance') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `fk_rooms_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `wardens`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `wardens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `joining_date` date NOT NULL,
  `status` enum('Active','On Leave','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `fk_wardens_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `students`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL UNIQUE,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `phone` varchar(20) NOT NULL,
  `guardian_name` varchar(150) NOT NULL,
  `guardian_phone` varchar(20) NOT NULL,
  `admission_date` date NOT NULL,
  `status` enum('Active','Vacated') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `fk_students_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `system_settings`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


