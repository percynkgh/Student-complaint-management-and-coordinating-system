-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 11:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_complaint_system`
--
CREATE DATABASE IF NOT EXISTS `student_complaint_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `student_complaint_system`;

DELIMITER $$
--
-- Functions
--
DROP FUNCTION IF EXISTS `LEVENSHTEIN`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `LEVENSHTEIN` (`s1` VARCHAR(255), `s2` VARCHAR(255)) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
    DECLARE cv0, cv1 VARBINARY(256);
    
    SET s1_len = CHAR_LENGTH(s1), s2_len = CHAR_LENGTH(s2), cv1 = 0x00, j = 1, i = 1, c = 0;
    
    IF s1 = s2 THEN
        RETURN 0;
    ELSEIF s1_len = 0 THEN
        RETURN s2_len;
    ELSEIF s2_len = 0 THEN
        RETURN s1_len;
    ELSE
        SET cv0 = 0x00;
        WHILE j <= s2_len DO
            SET cv0 = CONCAT(cv0, UNHEX(HEX(j))), j = j + 1;
        END WHILE;
        
        WHILE i <= s1_len DO
            SET s1 = s1, i = i + 1;
        END WHILE;
    END IF;
    
    RETURN s1_len;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT IGNORE INTO `admin_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 2, 'create_user', 'Created new student user: Percy Kongwie (ID: 17)', '::1', '2026-03-16 21:27:57'),
(2, 2, 'create_user', 'Created new student user: Percy Kongwie (ID: 20)', '::1', '2026-03-16 21:51:45'),
(3, 2, 'create_user', 'Created new staff user: Percy Kongwie (ID: 21)', '::1', '2026-03-16 21:55:47');

-- --------------------------------------------------------

--
-- Table structure for table `batch_members`
--

DROP TABLE IF EXISTS `batch_members`;
CREATE TABLE IF NOT EXISTS `batch_members` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `endorsement_status` enum('pending','confirmed','denied') DEFAULT 'pending',
  `endorsed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `unique_member` (`complaint_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_members`
--

INSERT IGNORE INTO `batch_members` (`batch_id`, `complaint_id`, `user_id`, `endorsement_status`, `endorsed_at`, `created_at`) VALUES
(2, 37, 1, 'confirmed', '2026-03-15 14:16:32', '2026-03-15 12:52:56'),
(3, 41, 1, 'confirmed', '2026-03-15 14:22:54', '2026-03-15 14:16:56'),
(4, 42, 1, 'confirmed', '2026-03-15 14:22:51', '2026-03-15 14:17:49'),
(5, 43, 1, 'confirmed', '2026-03-15 20:18:01', '2026-03-15 20:15:49'),
(6, 47, 1, 'confirmed', '2026-03-16 13:00:20', '2026-03-16 12:57:41'),
(7, 48, 1, 'confirmed', '2026-03-16 13:11:53', '2026-03-16 13:05:54'),
(8, 49, 1, 'pending', NULL, '2026-03-16 13:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `chat_attachments`
--

DROP TABLE IF EXISTS `chat_attachments`;
CREATE TABLE IF NOT EXISTS `chat_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_read_receipts`
--

DROP TABLE IF EXISTS `chat_read_receipts`;
CREATE TABLE IF NOT EXISTS `chat_read_receipts` (
  `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `unique_read` (`message_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing`
--

DROP TABLE IF EXISTS `chat_typing`;
CREATE TABLE IF NOT EXISTS `chat_typing` (
  `typing_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_typing` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`typing_id`),
  UNIQUE KEY `unique_typing` (`complaint_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_typing`
--

INSERT IGNORE INTO `chat_typing` (`typing_id`, `complaint_id`, `user_id`, `is_typing`, `updated_at`) VALUES
(1, 37, 5, 0, '2026-03-15 19:28:14');

-- --------------------------------------------------------

--
-- Table structure for table `class_rep_notifications`
--

DROP TABLE IF EXISTS `class_rep_notifications`;
CREATE TABLE IF NOT EXISTS `class_rep_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `idx_user_read` (`user_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('submitted','under_review','in_progress','resolved','closed','rejected') DEFAULT 'submitted',
  `department_assigned` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `assignment_status` enum('unassigned','assigned','awaiting_confirmation','resolved') NOT NULL DEFAULT 'unassigned',
  `location_building` varchar(100) DEFAULT NULL,
  `location_room` varchar(50) DEFAULT NULL,
  `location_details` text DEFAULT NULL,
  `is_duplicate` tinyint(1) DEFAULT 0,
  `duplicate_of` int(11) DEFAULT NULL,
  `upvote_count` int(11) DEFAULT 0,
  `affected_count` int(11) DEFAULT 1,
  `is_batch_complaint` tinyint(1) DEFAULT 0,
  `batch_token` varchar(100) DEFAULT NULL,
  `attachment_path` text DEFAULT NULL,
  `estimated_resolution_date` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`complaint_id`),
  UNIQUE KEY `complaint_number` (`complaint_number`),
  KEY `user_id` (`user_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_department` (`department_assigned`),
  KEY `idx_complaints_status` (`status`),
  KEY `idx_complaints_priority` (`priority`),
  KEY `idx_complaints_department` (`department_assigned`),
  KEY `idx_complaints_created` (`created_at`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT IGNORE INTO `complaints` (`complaint_id`, `complaint_number`, `user_id`, `category_id`, `title`, `description`, `priority`, `status`, `department_assigned`, `assigned_to`, `location_building`, `location_room`, `location_details`, `is_duplicate`, `duplicate_of`, `upvote_count`, `affected_count`, `is_batch_complaint`, `batch_token`, `attachment_path`, `estimated_resolution_date`, `resolved_at`, `created_at`, `updated_at`) VALUES
(30, 'CMP-2026-0004', 1, 3, 'AC', 'ffdsDSD', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 12:31:34', '2026-03-15 11:50:31', '2026-03-16 12:31:34'),
(37, 'CMP-2026-0011', 10, 1, 'wifi router', 'needs urgent', 'urgent', 'resolved', 'Information Technology', NULL, '', '', NULL, 0, NULL, 0, 1, 1, '8ecdac874b5fabc2d42233f2ef97a9ad', NULL, '2026-03-15 21:34:56', '2026-03-16 12:31:20', '2026-03-15 12:52:56', '2026-03-16 20:44:01'),
(41, 'CMP-2026-0014', 10, 1, 'game room', 'fgfsg', 'low', 'resolved', 'Information Technology', NULL, 'fsgfss', 'svd', '', 0, NULL, 0, 1, 1, '013b44d267e46ce1caff167a58312d7f', NULL, '2026-03-15 14:46:56', '2026-03-16 12:31:40', '2026-03-15 14:16:56', '2026-03-16 12:31:40'),
(42, 'CMP-2026-0015', 10, 1, 'game room', 'fgfsg', 'low', 'resolved', 'Information Technology', NULL, 'fsgfss', 'svd', '', 0, NULL, 0, 1, 1, '47e0a4f6dcd78a4e70980ab643029566', NULL, '2026-03-15 23:57:49', '2026-03-16 12:31:44', '2026-03-15 14:17:49', '2026-03-16 19:34:23'),
(43, 'CMP-2026-0009', 10, 1, 'dffs', 'afaddad', 'low', 'resolved', 'Information Technology', NULL, 'sdvd', 'xcxvc', '', 0, NULL, 0, 1, 1, '3002ba50b3ebf85d23cec1114160a441', NULL, '2026-03-15 20:45:49', '2026-03-16 12:31:54', '2026-03-15 20:15:49', '2026-03-16 12:31:54'),
(44, 'CMP-2026-0006', 1, 3, 'FEES', 'payed fees but cant register', 'low', 'resolved', 'Finance', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 12:25:21', '2026-03-16 12:20:28', '2026-03-16 12:25:21'),
(45, 'CMP-2026-0007', 1, 2, 'water on the floor', 'water on the class room floor', 'low', 'resolved', 'Maintenance', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 12:27:45', '2026-03-16 12:21:26', '2026-03-16 12:27:45'),
(46, 'CMP-2026-0008', 1, 4, 'name change', 'i want ot correct', 'low', 'resolved', 'Student Affairs', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 12:29:33', '2026-03-16 12:22:11', '2026-03-16 12:29:33'),
(47, 'CMP-2026-0010', 10, 1, 'fbgfssvv', 'svvdsds', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 1, 'c8fa91c15476558f2fe737878465a692', NULL, '2026-03-16 21:39:41', '2026-03-16 12:59:20', '2026-03-16 12:57:41', '2026-03-16 20:49:20'),
(48, 'CMP-2026-0012', 10, 1, 'mmadflvaf', 'svk;&#039;vk&#039;;v&#039;;lvs&#039;vvf', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 1, '0a7d5619ffb963bec5967dbf26fe38c2', NULL, '2026-03-16 22:45:54', '2026-03-16 13:11:22', '2026-03-16 13:05:54', '2026-03-16 19:34:15'),
(49, 'CMP-2026-0013', 10, 1, 'xvvsvsf', 'hiiiiiiiiiiiiiiiiiii', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 1, 'f7cede996639446948fbbeee4ffab0bb', NULL, '2026-03-16 23:11:45', '2026-03-16 13:44:08', '2026-03-16 13:31:45', '2026-03-16 19:10:13'),
(50, 'CMP-2026-0016', 15, 2, 'HII', 'BROKEN PROJECTOR', 'medium', 'resolved', 'Maintenance', NULL, 'L;MDLA', '', ';M;CM;AD', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 14:36:09', '2026-03-16 14:23:27', '2026-03-16 14:36:09'),
(51, 'CMP-2026-0017', 15, 1, 'projetpr', 'givedc;c', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 14:27:49', '2026-03-16 14:27:30', '2026-03-16 14:27:49'),
(52, 'CMP-2026-0018', 16, 3, 'HJFIJHS', 'KJDK[', 'low', 'resolved', 'Finance', NULL, 'JCJP', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 14:38:35', '2026-03-16 14:38:11', '2026-03-16 14:38:35'),
(53, 'CMP-2026-0019', 1, 1, 'WIFI connection issues', 'my pc wont connect to the wifi', 'medium', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 1, 1, 0, NULL, NULL, '2026-03-17 00:38:41', '2026-03-16 19:08:27', '2026-03-16 14:58:41', '2026-03-16 20:34:21'),
(54, 'CMP-2026-0020', 1, 4, 'bullying', 'being bullied urgent', 'urgent', 'resolved', 'Student Affairs', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 15:04:10', '2026-03-16 15:03:29', '2026-03-16 15:04:10'),
(55, 'CMP-2026-0021', 1, 4, 'class schedule', 'my class schedule', 'low', 'resolved', 'Registry', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 19:00:24', '2026-03-16 18:59:55', '2026-03-16 19:00:24'),
(56, 'CMP-2026-0022', 10, 4, 'MIssing books', 'some books in the library', 'low', 'resolved', 'Student Affairs', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 19:36:26', '2026-03-16 19:20:27', '2026-03-16 19:36:26'),
(57, 'CMP-2026-0023', 10, 3, 'fees issues', 'can not pay my fee', 'medium', 'resolved', 'Finance', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, NULL, '2026-03-16 20:27:14', '2026-03-16 20:26:53', '2026-03-16 20:27:14'),
(58, 'CMP-2026-0024', 1, 1, 'wifi router', 'cant find wife router', 'low', 'resolved', 'Information Technology', NULL, '', '', '', 0, NULL, 0, 1, 0, NULL, NULL, '2026-03-17 05:17:39', '2026-03-16 20:36:13', '2026-03-16 20:35:39', '2026-03-16 20:36:34'),
(59, 'CMP-2026-0025', 1, 1, 'wifi problem', 'my pc cant connect', 'medium', 'resolved', 'Information Technology', NULL, 'vhkj', 'kjbb', '', 0, NULL, 0, 1, 0, NULL, NULL, '2026-03-17 05:40:24', '2026-03-16 21:47:34', '2026-03-16 21:45:51', '2026-03-16 21:48:03');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

DROP TABLE IF EXISTS `complaint_attachments`;
CREATE TABLE IF NOT EXISTS `complaint_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `complaint_id` (`complaint_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_attachments`
--

INSERT IGNORE INTO `complaint_attachments` (`attachment_id`, `complaint_id`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(9, 55, '1773687595_69b8532b82430.jpg', 'uploads/complaints/55/1773687595_69b8532b82430.jpg', 89373, 'jpg', '2026-03-16 18:59:55'),
(10, 59, '1773697551_69b87a0f9a1a5.jpg', 'uploads/complaints/59/1773697551_69b87a0f9a1a5.jpg', 104069, 'jpg', '2026-03-16 21:45:51');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_evidence`
-- Before/after resolution photos and supporting evidence uploaded by
-- department staff while working an internally-assigned complaint.
--

DROP TABLE IF EXISTS `complaint_evidence`;
CREATE TABLE IF NOT EXISTS `complaint_evidence` (
  `evidence_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `evidence_type` enum('before','after','general') NOT NULL DEFAULT 'general',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`evidence_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_categories`
--

DROP TABLE IF EXISTS `complaint_categories`;
CREATE TABLE IF NOT EXISTS `complaint_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_department` varchar(100) DEFAULT NULL,
  `avg_resolution_time` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_categories`
--

INSERT IGNORE INTO `complaint_categories` (`category_id`, `category_name`, `description`, `default_department`, `avg_resolution_time`, `created_at`) VALUES
(1, 'IT Issues', 'Computer, internet, software, printer problems', 'Information Technology', 0, '2026-03-13 19:29:49'),
(2, 'Maintenance', 'Building, furniture, electricity, plumbing issues', 'Maintenance', 0, '2026-03-13 19:29:49'),
(3, 'Financial', 'Fee payments, refunds, financial aid', 'Finance', 0, '2026-03-13 19:29:49'),
(4, 'Academic', 'Registration, transcripts, course issues', 'Registry', 0, '2026-03-13 19:29:49'),
(5, 'Facilities', 'Classrooms, labs, library, sports facilities', 'Maintenance', 0, '2026-03-13 19:29:49'),
(6, 'Security', 'Campus security, safety concerns', 'Student Affairs', 0, '2026-03-13 19:29:49'),
(7, 'Harassment', 'Bullying, discrimination, harassment reports', 'Student Affairs', 0, '2026-03-13 19:29:49'),
(8, 'Transport', 'Shuttle, parking, transport services', 'Maintenance', 0, '2026-03-13 19:29:49');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_ratings`
--

DROP TABLE IF EXISTS `complaint_ratings`;
CREATE TABLE IF NOT EXISTS `complaint_ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `speed_rating` int(11) NOT NULL CHECK (`speed_rating` between 1 and 5),
  `communication_rating` int(11) NOT NULL CHECK (`communication_rating` between 1 and 5),
  `effectiveness_rating` int(11) NOT NULL CHECK (`effectiveness_rating` between 1 and 5),
  `overall_rating` decimal(3,2) GENERATED ALWAYS AS ((`speed_rating` + `communication_rating` + `effectiveness_rating`) / 3) STORED,
  `feedback` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`),
  UNIQUE KEY `unique_rating` (`complaint_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_ratings`
--

INSERT IGNORE INTO `complaint_ratings` (`rating_id`, `complaint_id`, `user_id`, `speed_rating`, `communication_rating`, `effectiveness_rating`, `feedback`, `is_public`, `created_at`) VALUES
(3, 45, 1, 5, 4, 5, '', 1, '2026-03-16 12:28:57'),
(4, 44, 1, 5, 5, 5, '', 1, '2026-03-16 12:29:04'),
(5, 56, 10, 4, 4, 4, '', 1, '2026-03-16 19:52:40'),
(6, 49, 10, 4, 5, 4, '', 1, '2026-03-16 19:58:05'),
(7, 48, 10, 5, 5, 5, '', 1, '2026-03-16 19:58:26'),
(8, 47, 10, 5, 5, 5, '', 1, '2026-03-16 19:59:32'),
(9, 43, 10, 5, 5, 5, '', 1, '2026-03-16 20:00:37'),
(10, 42, 10, 5, 5, 5, '', 1, '2026-03-16 20:01:29'),
(11, 41, 10, 5, 5, 5, '', 1, '2026-03-16 20:01:41'),
(12, 37, 10, 5, 5, 5, '', 1, '2026-03-16 20:01:49'),
(13, 57, 10, 5, 3, 4, '', 1, '2026-03-16 20:27:56'),
(14, 58, 1, 5, 5, 5, '', 1, '2026-03-16 20:36:42'),
(15, 55, 1, 5, 5, 5, '', 1, '2026-03-16 20:36:47'),
(16, 54, 1, 5, 5, 5, '', 1, '2026-03-16 20:36:53'),
(17, 53, 1, 5, 5, 5, '', 1, '2026-03-16 20:36:59'),
(18, 46, 1, 5, 5, 5, '', 1, '2026-03-16 20:37:04'),
(19, 30, 1, 5, 5, 5, '', 1, '2026-03-16 20:37:10'),
(20, 59, 1, 5, 5, 5, '', 1, '2026-03-16 21:48:16');

--
-- Triggers `complaint_ratings`
--
DROP TRIGGER IF EXISTS `update_department_ratings`;
DELIMITER $$
CREATE TRIGGER `update_department_ratings` AFTER INSERT ON `complaint_ratings` FOR EACH ROW BEGIN
    DECLARE dept_name VARCHAR(100);
    
    -- Get department of the complaint
    SELECT department_assigned INTO dept_name 
    FROM complaints WHERE complaint_id = NEW.complaint_id;
    
    IF dept_name IS NOT NULL THEN
        INSERT INTO department_ratings (department, avg_speed, avg_communication, avg_effectiveness, avg_overall, total_ratings)
        SELECT 
            dept_name,
            AVG(speed_rating) as speed,
            AVG(communication_rating) as comm,
            AVG(effectiveness_rating) as eff,
            AVG(overall_rating) as overall,
            COUNT(*) as total
        FROM complaint_ratings r
        JOIN complaints c ON r.complaint_id = c.complaint_id
        WHERE c.department_assigned = dept_name
        ON DUPLICATE KEY UPDATE
            avg_speed = VALUES(avg_speed),
            avg_communication = VALUES(avg_communication),
            avg_effectiveness = VALUES(avg_effectiveness),
            avg_overall = VALUES(avg_overall),
            total_ratings = VALUES(total_ratings);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_status_history`
--

DROP TABLE IF EXISTS `complaint_status_history`;
CREATE TABLE IF NOT EXISTS `complaint_status_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `time_spent` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `changed_by` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_timeline`
--

DROP TABLE IF EXISTS `complaint_timeline`;
CREATE TABLE IF NOT EXISTS `complaint_timeline` (
  `timeline_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `is_auto_update` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`timeline_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_timeline`
--

INSERT IGNORE INTO `complaint_timeline` (`timeline_id`, `complaint_id`, `status`, `description`, `updated_by`, `is_auto_update`, `created_at`) VALUES
(42, 30, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-15 11:50:31'),
(45, 30, 'in_progress', 'We are waiting for necessary parts or approvals to proceed.', 7, 0, '2026-03-15 11:55:18'),
(51, 37, 'submitted', 'Batch complaint submitted for 1 students', 10, 0, '2026-03-15 12:52:56'),
(69, 30, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-15 14:41:07'),
(71, 30, 'in_progress', 'HOW are you', 5, 0, '2026-03-15 20:07:08'),
(73, 41, 'under_review', 'Your complaint has been received and is being reviewed by our team.', 5, 0, '2026-03-15 20:17:41'),
(74, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 20:42:22'),
(75, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 20:48:35'),
(76, 41, 'meeting_scheduled', 'Meeting scheduled: Discussion re: game room on Mar 16, 10:00 AM', 5, 0, '2026-03-15 20:59:15'),
(77, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 21:10:29'),
(78, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 21:11:28'),
(79, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 21:18:13'),
(80, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 21:18:33'),
(81, 43, 'meeting_scheduled', 'Meeting scheduled: Discussion re: dffs on Mar 16, 10:00 AM', 5, 0, '2026-03-15 21:18:56'),
(82, 44, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 12:20:28'),
(83, 45, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 12:21:26'),
(84, 46, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 12:22:11'),
(85, 44, 'resolved', 'The issue has been fixed and verified by our team.', 7, 0, '2026-03-16 12:25:21'),
(86, 45, 'resolved', 'The issue has been fixed and verified by our team.', 6, 0, '2026-03-16 12:27:45'),
(87, 46, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 9, 0, '2026-03-16 12:29:33'),
(88, 37, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 12:31:20'),
(89, 30, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 5, 0, '2026-03-16 12:31:34'),
(90, 41, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 5, 0, '2026-03-16 12:31:40'),
(91, 42, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 5, 0, '2026-03-16 12:31:44'),
(92, 43, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 5, 0, '2026-03-16 12:31:54'),
(94, 47, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 12:59:20'),
(96, 48, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 13:11:22'),
(98, 49, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 13:34:33'),
(99, 49, 'resolved', 'This complaint has been closed. Thank you for your patience.', 5, 0, '2026-03-16 13:38:43'),
(100, 49, 'resolved', 'This complaint has been closed. Thank you for your patience.', 5, 0, '2026-03-16 13:44:08'),
(101, 50, 'submitted', 'Complaint submitted successfully', 15, 0, '2026-03-16 14:23:27'),
(102, 50, 'resolved', 'The issue has been fixed and verified by our team.', 6, 0, '2026-03-16 14:24:16'),
(103, 51, 'submitted', 'Complaint submitted successfully', 15, 0, '2026-03-16 14:27:30'),
(104, 51, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 14:27:49'),
(105, 50, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 6, 0, '2026-03-16 14:36:09'),
(106, 52, 'submitted', 'Complaint submitted successfully', 16, 0, '2026-03-16 14:38:11'),
(107, 52, 'resolved', 'The issue has been fixed and verified by our team.', 7, 0, '2026-03-16 14:38:35'),
(108, 53, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 14:58:41'),
(109, 54, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 15:03:29'),
(110, 54, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 9, 0, '2026-03-16 15:04:07'),
(111, 54, 'resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', 9, 0, '2026-03-16 15:04:10'),
(112, 55, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 18:59:55'),
(113, 55, 'resolved', 'The issue has been fixed and verified by our team.', 8, 0, '2026-03-16 19:00:24'),
(114, 53, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 19:08:27'),
(115, 56, 'submitted', 'Complaint submitted successfully', 10, 0, '2026-03-16 19:20:27'),
(116, 56, 'resolved', 'This complaint has been closed. Thank you for your patience.', 9, 0, '2026-03-16 19:36:26'),
(117, 57, 'submitted', 'Complaint submitted successfully', 10, 0, '2026-03-16 20:26:53'),
(118, 57, 'resolved', 'The issue has been fixed and verified by our team.', 7, 0, '2026-03-16 20:27:14'),
(119, 58, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 20:35:39'),
(120, 58, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 20:36:13'),
(121, 59, 'submitted', 'Complaint submitted successfully', 1, 0, '2026-03-16 21:45:51'),
(122, 59, 'resolved', 'The issue has been fixed and verified by our team.', 5, 0, '2026-03-16 21:47:34');

-- --------------------------------------------------------

--
-- Table structure for table `complaint_upvotes`
--

DROP TABLE IF EXISTS `complaint_upvotes`;
CREATE TABLE IF NOT EXISTS `complaint_upvotes` (
  `upvote_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`upvote_id`),
  UNIQUE KEY `unique_upvote` (`complaint_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_upvotes`
--

INSERT IGNORE INTO `complaint_upvotes` (`upvote_id`, `complaint_id`, `user_id`, `created_at`) VALUES
(4, 53, 1, '2026-03-16 14:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `duration_years` int(11) DEFAULT 4,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`course_id`),
  KEY `idx_department` (`department`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT IGNORE INTO `courses` (`course_id`, `course_name`, `department`, `duration_years`, `created_at`) VALUES
(1, 'Computer Science', 'Computer Science', 4, '2026-03-16 14:08:42'),
(2, 'Information Technology', 'Computer Science', 4, '2026-03-16 14:08:42'),
(3, 'Software Engineering', 'Computer Science', 4, '2026-03-16 14:08:42'),
(4, 'Data Science', 'Computer Science', 4, '2026-03-16 14:08:42'),
(5, 'Cybersecurity', 'Computer Science', 4, '2026-03-16 14:08:42'),
(6, 'Mechanical Engineering', 'Engineering', 5, '2026-03-16 14:08:42'),
(7, 'Electrical Engineering', 'Engineering', 5, '2026-03-16 14:08:42'),
(8, 'Civil Engineering', 'Engineering', 5, '2026-03-16 14:08:42'),
(9, 'Chemical Engineering', 'Engineering', 5, '2026-03-16 14:08:42'),
(10, 'Business Administration', 'Business Administration', 4, '2026-03-16 14:08:42'),
(11, 'Accounting', 'Business Administration', 4, '2026-03-16 14:08:42'),
(12, 'Marketing', 'Business Administration', 4, '2026-03-16 14:08:42'),
(13, 'Finance', 'Business Administration', 4, '2026-03-16 14:08:42'),
(14, 'Medicine', 'Medicine', 6, '2026-03-16 14:08:42'),
(15, 'Nursing', 'Medicine', 4, '2026-03-16 14:08:42'),
(16, 'Pharmacy', 'Medicine', 5, '2026-03-16 14:08:42'),
(17, 'Law', 'Law', 4, '2026-03-16 14:08:42'),
(18, 'English Literature', 'Arts and Humanities', 4, '2026-03-16 14:08:42'),
(19, 'History', 'Arts and Humanities', 4, '2026-03-16 14:08:42'),
(20, 'Philosophy', 'Arts and Humanities', 4, '2026-03-16 14:08:42');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `dept_id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) NOT NULL,
  `dept_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `staff_count` int(11) DEFAULT 0,
  `avg_response_time` decimal(5,2) DEFAULT 0.00,
  `satisfaction_score` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('academic','operational') DEFAULT 'operational',
  PRIMARY KEY (`dept_id`),
  UNIQUE KEY `dept_name` (`dept_name`),
  UNIQUE KEY `dept_code` (`dept_code`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT IGNORE INTO `departments` (`dept_id`, `dept_name`, `dept_code`, `description`, `staff_count`, `avg_response_time`, `satisfaction_score`, `created_at`, `type`) VALUES
(1, 'Information Technology', 'IT', 'Handles IT-related issues', 0, 0.00, 0.00, '2026-03-13 18:59:08', 'operational'),
(2, 'Maintenance', 'MNT', 'Handles physical infrastructure issues', 0, 0.00, 0.00, '2026-03-13 18:59:08', 'operational'),
(3, 'Finance', 'FIN', 'Handles financial and fee-related issues', 0, 0.00, 0.00, '2026-03-13 18:59:08', 'operational'),
(4, 'Registry', 'REG', 'Handles academic and administrative issues', 0, 0.00, 0.00, '2026-03-13 18:59:08', 'operational'),
(5, 'Student Affairs', 'SA', 'Handles student welfare issues', 0, 0.00, 0.00, '2026-03-13 18:59:08', 'operational'),
(6, 'Computer Science', '', 'Department of Computer Science', 0, 0.00, 0.00, '2026-03-16 14:13:32', 'academic'),
(85, 'Engineering', 'ENG', 'Department of Engineering', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(86, 'Business Administration', 'BUS', 'Department of Business Administration', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(87, 'Medicine', 'MED', 'Department of Medicine', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(88, 'Law', 'LAW', 'Department of Law', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(89, 'Arts and Humanities', 'ART', 'Department of Arts and Humanities', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(90, 'Social Sciences', 'SOC', 'Department of Social Sciences', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(91, 'Education', 'EDU', 'Department of Education', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(92, 'Nursing', 'NRS', 'Department of Nursing', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(93, 'Pharmacy', 'PHR', 'Department of Pharmacy', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(94, 'Mathematics', 'MATH', 'Department of Mathematics', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(95, 'Physics', 'PHY', 'Department of Physics', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(96, 'Chemistry', 'CHEM', 'Department of Chemistry', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic'),
(97, 'Biology', 'BIO', 'Department of Biology', 0, 0.00, 0.00, '2026-03-16 14:20:59', 'academic');

-- --------------------------------------------------------

--
-- Table structure for table `department_metrics`
--

DROP TABLE IF EXISTS `department_metrics`;
CREATE TABLE IF NOT EXISTS `department_metrics` (
  `metric_id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `complaints_received` int(11) DEFAULT 0,
  `complaints_resolved` int(11) DEFAULT 0,
  `avg_resolution_time` decimal(10,2) DEFAULT NULL,
  `satisfaction_score` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`metric_id`),
  UNIQUE KEY `unique_daily` (`department`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_metrics`
--

INSERT IGNORE INTO `department_metrics` (`metric_id`, `department`, `date`, `complaints_received`, `complaints_resolved`, `avg_resolution_time`, `satisfaction_score`, `created_at`) VALUES
(1, 'Information Technology', '2026-03-13', 0, 5, NULL, NULL, '2026-03-13 19:55:57'),
(6, 'Information Technology', '2026-03-15', 0, 17, NULL, NULL, '2026-03-15 11:47:53'),
(23, 'Finance', '2026-03-16', 0, 3, NULL, NULL, '2026-03-16 12:25:21'),
(24, 'Maintenance', '2026-03-16', 0, 3, NULL, NULL, '2026-03-16 12:27:45'),
(25, 'Student Affairs', '2026-03-16', 0, 4, NULL, NULL, '2026-03-16 12:29:33'),
(26, 'Information Technology', '2026-03-16', 0, 14, NULL, NULL, '2026-03-16 12:31:20'),
(42, 'Registry', '2026-03-16', 0, 1, NULL, NULL, '2026-03-16 19:00:24');

-- --------------------------------------------------------

--
-- Table structure for table `department_ratings`
--

DROP TABLE IF EXISTS `department_ratings`;
CREATE TABLE IF NOT EXISTS `department_ratings` (
  `dept_rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL,
  `avg_speed` decimal(3,2) DEFAULT 0.00,
  `avg_communication` decimal(3,2) DEFAULT 0.00,
  `avg_effectiveness` decimal(3,2) DEFAULT 0.00,
  `avg_overall` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`dept_rating_id`),
  UNIQUE KEY `unique_dept` (`department`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_ratings`
--

INSERT IGNORE INTO `department_ratings` (`dept_rating_id`, `department`, `avg_speed`, `avg_communication`, `avg_effectiveness`, `avg_overall`, `total_ratings`, `last_updated`) VALUES
(1, 'Finance', 5.00, 4.00, 4.50, 4.50, 2, '2026-03-16 20:27:56'),
(2, 'Information Technology', 4.91, 5.00, 4.91, 4.94, 11, '2026-03-16 21:48:16'),
(3, 'Maintenance', 5.00, 4.00, 5.00, 4.67, 1, '2026-03-16 20:18:07'),
(4, 'Student Affairs', 4.67, 4.67, 4.67, 4.67, 3, '2026-03-16 20:37:04'),
(12, 'Registry', 5.00, 5.00, 5.00, 5.00, 1, '2026-03-16 20:36:47');

-- --------------------------------------------------------

--
-- Table structure for table `direct_messages`
--

DROP TABLE IF EXISTS `direct_messages`;
CREATE TABLE IF NOT EXISTS `direct_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direct_messages`
--

INSERT IGNORE INTO `direct_messages` (`message_id`, `sender_id`, `receiver_id`, `message`, `is_read`, `read_at`, `created_at`) VALUES
(1, 5, 14, 'Hello g', 1, '2026-03-15 19:55:49', '2026-03-15 19:46:42'),
(2, 14, 5, 'HU', 1, '2026-03-15 19:55:06', '2026-03-15 19:47:46'),
(3, 5, 14, 'sup', 1, '2026-03-15 19:55:49', '2026-03-15 19:55:13'),
(4, 14, 5, 'EVERTHING COOL', 1, '2026-03-15 19:56:32', '2026-03-15 19:56:02'),
(5, 14, 5, 'SUP', 1, '2026-03-15 19:56:32', '2026-03-15 19:56:11'),
(6, 2, 14, 'hey nigga', 1, '2026-03-15 20:19:32', '2026-03-15 20:19:18'),
(7, 14, 2, 'sup', 1, '2026-03-15 20:19:52', '2026-03-15 20:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `direct_message_attachments`
--

DROP TABLE IF EXISTS `direct_message_attachments`;
CREATE TABLE IF NOT EXISTS `direct_message_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `direct_typing`
--

DROP TABLE IF EXISTS `direct_typing`;
CREATE TABLE IF NOT EXISTS `direct_typing` (
  `typing_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `is_typing` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`typing_id`),
  UNIQUE KEY `unique_typing` (`sender_id`,`receiver_id`),
  KEY `receiver_id` (`receiver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `direct_typing`
--

INSERT IGNORE INTO `direct_typing` (`typing_id`, `sender_id`, `receiver_id`, `is_typing`, `updated_at`) VALUES
(1, 5, 14, 0, '2026-03-15 19:46:42'),
(13, 14, 5, 0, '2026-03-15 19:47:45');

-- --------------------------------------------------------

--
-- Table structure for table `duplicate_log`
--

DROP TABLE IF EXISTS `duplicate_log`;
CREATE TABLE IF NOT EXISTS `duplicate_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `new_complaint_id` int(11) DEFAULT NULL,
  `existing_complaint_id` int(11) DEFAULT NULL,
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `matched_keywords` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escalation_rules`
--

DROP TABLE IF EXISTS `escalation_rules`;
CREATE TABLE IF NOT EXISTS `escalation_rules` (
  `rule_id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(50) NOT NULL,
  `hours_threshold` int(11) NOT NULL,
  `new_priority` varchar(20) DEFAULT NULL,
  `notify_role` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rule_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `escalation_rules`
--

INSERT IGNORE INTO `escalation_rules` (`rule_id`, `status`, `hours_threshold`, `new_priority`, `notify_role`, `is_active`, `created_at`) VALUES
(1, 'submitted', 24, 'high', 'admin', 1, '2026-03-13 19:48:30'),
(2, 'under_review', 48, 'high', 'admin', 1, '2026-03-13 19:48:30'),
(3, 'in_progress', 72, 'urgent', 'dean', 1, '2026-03-13 19:48:30'),
(4, 'submitted', 24, 'high', 'admin', 1, '2026-03-15 13:23:52'),
(5, 'under_review', 48, 'high', 'admin', 1, '2026-03-15 13:23:52'),
(6, 'in_progress', 72, 'urgent', 'dean', 1, '2026-03-15 13:23:52');

-- --------------------------------------------------------

--
-- Table structure for table `historical_analysis`
--

DROP TABLE IF EXISTS `historical_analysis`;
CREATE TABLE IF NOT EXISTS `historical_analysis` (
  `analysis_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `avg_resolution_time` decimal(10,2) DEFAULT NULL,
  `min_resolution_time` decimal(10,2) DEFAULT NULL,
  `max_resolution_time` decimal(10,2) DEFAULT NULL,
  `sample_size` int(11) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`analysis_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`attempt_id`)
) ENGINE=InnoDB AUTO_INCREMENT=272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT IGNORE INTO `login_attempts` (`attempt_id`, `student_id`, `ip_address`, `attempt_time`, `success`) VALUES
(1, '4211234421', '::1', '2026-03-13 19:01:36', 1),
(8, 'ADMIN001', '::1', '2026-03-13 19:16:08', 0),
(9, 'ADMIN001', '::1', '2026-03-13 19:17:23', 1),
(10, 'DEAN001', '::1', '2026-03-13 19:18:03', 1),
(11, 'STAFFIT01', '::1', '2026-03-13 19:18:43', 1),
(12, 'CLASS001', '::1', '2026-03-13 19:19:11', 1),
(13, 'ADMIN001', '::1', '2026-03-13 19:19:44', 1),
(14, '4211234421', '::1', '2026-03-13 19:20:00', 1),
(15, 'STU001', '::1', '2026-03-13 19:21:55', 1),
(16, '4211234421', '::1', '2026-03-13 19:27:26', 1),
(17, 'CLASS001', '::1', '2026-03-13 19:38:57', 0),
(18, 'CLASS001', '::1', '2026-03-13 19:39:38', 1),
(19, '4211234421', '::1', '2026-03-13 19:44:25', 1),
(20, '4211234421', '::1', '2026-03-13 19:45:52', 1),
(21, 'STAFFIT01', '::1', '2026-03-13 19:46:28', 1),
(22, '4211234421', '::1', '2026-03-13 19:50:41', 1),
(23, 'STU001', '::1', '2026-03-13 19:51:12', 1),
(24, 'STAFFFIN01', '::1', '2026-03-13 19:52:59', 1),
(25, 'CLASS001', '::1', '2026-03-13 19:53:24', 1),
(26, 'STAFFIT01', '::1', '2026-03-13 19:54:48', 1),
(27, 'CLASS001', '::1', '2026-03-13 19:55:18', 1),
(28, 'STAFFIT01', '::1', '2026-03-13 19:55:49', 1),
(29, 'CLASS001', '::1', '2026-03-13 19:56:03', 1),
(30, 'CLASS001', '::1', '2026-03-13 19:57:25', 1),
(31, '4211234422', '::1', '2026-03-13 19:59:10', 1),
(32, 'CLASS001', '::1', '2026-03-13 19:59:20', 1),
(33, '4211234421', '::1', '2026-03-13 20:01:53', 1),
(34, '4211234421', '::1', '2026-03-13 20:04:52', 1),
(35, '4211234421', '::1', '2026-03-13 20:17:27', 1),
(36, 'STAFFIT01', '::1', '2026-03-13 20:27:12', 1),
(37, '4211234421', '::1', '2026-03-13 20:29:55', 1),
(38, 'STAFFIT01', '::1', '2026-03-13 20:31:57', 1),
(39, '4211234421', '::1', '2026-03-13 20:32:40', 1),
(40, 'STAFFIT01', '::1', '2026-03-13 20:34:35', 0),
(41, 'STAFFIT01', '::1', '2026-03-13 20:34:46', 1),
(42, '4211234421', '::1', '2026-03-13 20:45:00', 1),
(43, '4211234421', '::1', '2026-03-13 20:51:55', 1),
(44, 'STAFFIT01', '::1', '2026-03-13 20:55:04', 1),
(45, '4211234421', '::1', '2026-03-13 20:55:49', 1),
(46, '4211234421', '::1', '2026-03-13 21:19:16', 0),
(47, '4211234421', '::1', '2026-03-13 21:19:23', 1),
(48, '4211234422', '::1', '2026-03-13 21:39:39', 1),
(49, 'STAFFIT01', '::1', '2026-03-13 21:42:34', 1),
(50, '4211234421', '::1', '2026-03-13 21:43:13', 1),
(51, 'STAFFIT01', '::1', '2026-03-13 21:43:59', 1),
(52, '4211234421', '::1', '2026-03-13 21:51:31', 1),
(53, 'STAFFIT01', '::1', '2026-03-13 21:52:27', 1),
(54, '4211234421', '::1', '2026-03-13 21:54:01', 1),
(55, 'STAFFIT01', '::1', '2026-03-13 21:56:11', 1),
(56, '4211234421', '::1', '2026-03-13 21:58:35', 1),
(57, 'STAFFIT01', '::1', '2026-03-13 21:59:39', 1),
(58, '4211234421', '::1', '2026-03-15 11:46:26', 1),
(59, 'STAFFIT01', '::1', '2026-03-15 11:47:06', 0),
(60, 'STAFFIT01', '::1', '2026-03-15 11:47:18', 1),
(61, '4211234421', '::1', '2026-03-15 11:49:05', 1),
(62, '4211234421', '::1', '2026-03-15 11:49:37', 1),
(63, 'STAFFIT01', '::1', '2026-03-15 11:51:18', 1),
(64, '4211234421', '::1', '2026-03-15 11:52:14', 1),
(65, 'STAFFFIN01', '::1', '2026-03-15 11:54:48', 1),
(66, '4211234421', '::1', '2026-03-15 11:55:37', 1),
(67, '4211234421', '::1', '2026-03-15 12:13:36', 1),
(68, 'STAFFIT01', '::1', '2026-03-15 12:25:32', 1),
(69, '4211234421', '::1', '2026-03-15 12:25:55', 1),
(70, 'STAFFIT01', '::1', '2026-03-15 12:26:47', 1),
(71, '4211234421', '::1', '2026-03-15 12:27:56', 1),
(72, 'STAFFIT01', '::1', '2026-03-15 12:28:47', 1),
(73, '4211234421', '::1', '2026-03-15 12:39:06', 0),
(74, '4211234421', '::1', '2026-03-15 12:39:14', 1),
(75, 'STAFFIT01', '::1', '2026-03-15 12:42:11', 1),
(76, '4211234421', '::1', '2026-03-15 12:44:15', 1),
(77, 'STAFFIT01', '::1', '2026-03-15 12:47:52', 1),
(78, 'ADMIN001', '::1', '2026-03-15 12:49:02', 1),
(79, 'DEAN001', '::1', '2026-03-15 12:50:55', 1),
(80, 'CLASS001', '::1', '2026-03-15 12:52:09', 1),
(81, 'STAFFIT01', '::1', '2026-03-15 12:53:09', 1),
(82, 'ADMIN001', '::1', '2026-03-15 12:55:22', 1),
(83, 'ADMIN001', '::1', '2026-03-15 12:57:56', 1),
(84, 'STAFFIT01', '::1', '2026-03-15 13:00:26', 1),
(85, 'ADMIN001', '::1', '2026-03-15 13:01:02', 1),
(86, '4211234421', '::1', '2026-03-15 13:13:36', 1),
(87, 'ADMIN001', '::1', '2026-03-15 13:14:44', 1),
(88, '4211234421', '::1', '2026-03-15 13:18:13', 1),
(89, '4211234421', '::1', '2026-03-15 13:26:36', 1),
(90, 'ADMIN001', '::1', '2026-03-15 13:33:03', 1),
(91, '4211234421', '::1', '2026-03-15 13:35:55', 1),
(92, 'STAFFIT01', '::1', '2026-03-15 13:36:54', 1),
(93, '4211234421', '::1', '2026-03-15 13:37:33', 1),
(94, '4211234421', '::1', '2026-03-15 13:43:05', 1),
(95, 'STAFFIT01', '::1', '2026-03-15 13:46:13', 1),
(96, '4211234421', '::1', '2026-03-15 13:47:03', 1),
(97, 'STAFFIT01', '::1', '2026-03-15 13:48:30', 1),
(98, '4211234421', '::1', '2026-03-15 13:49:12', 1),
(99, 'STAFFIT01', '::1', '2026-03-15 14:13:50', 1),
(100, 'CLASS001', '::1', '2026-03-15 14:16:26', 1),
(101, '4211234421', '::1', '2026-03-15 14:23:17', 1),
(102, 'STAFFIT01', '::1', '2026-03-15 14:24:48', 1),
(103, '4211234421', '::1', '2026-03-15 14:30:09', 1),
(104, 'ADMIN001', '::1', '2026-03-15 14:31:23', 1),
(105, 'STAFFFIN01', '::1', '2026-03-15 14:40:41', 1),
(106, 'STAFFIT01', '::1', '2026-03-15 14:40:53', 1),
(107, 'ADMIN001', '::1', '2026-03-15 14:42:15', 1),
(108, 'STAFFIT01', '::1', '2026-03-15 14:43:02', 1),
(109, 'ADMIN001', '::1', '2026-03-15 14:43:18', 1),
(110, 'STAFFIT01', '::1', '2026-03-15 17:45:17', 1),
(111, 'STAFFFIN01', '::1', '2026-03-15 19:28:53', 1),
(112, 'ADMIN001', '::1', '2026-03-15 19:29:29', 0),
(113, 'ADMIN001', '::1', '2026-03-15 19:29:35', 1),
(114, 'STAFF2', '::1', '2026-03-15 19:33:32', 1),
(115, 'STAFFIT01', '::1', '2026-03-15 19:46:30', 1),
(116, 'STAFF2', '::1', '2026-03-15 19:47:16', 0),
(117, 'STAFF2', '::1', '2026-03-15 19:47:25', 1),
(118, 'STAFFIT01', '::1', '2026-03-15 19:53:35', 1),
(119, 'STAFF2', '::1', '2026-03-15 19:55:44', 1),
(120, 'STAFFIT01', '::1', '2026-03-15 19:56:29', 1),
(121, 'ADMIN001', '::1', '2026-03-15 20:02:44', 1),
(122, 'STAFFIT01', '::1', '2026-03-15 20:06:41', 1),
(123, '4211234421', '::1', '2026-03-15 20:07:45', 1),
(124, 'STAFF2', '::1', '2026-03-15 20:09:40', 1),
(125, 'ADMIN001', '::1', '2026-03-15 20:10:02', 1),
(126, 'CLASS001', '::1', '2026-03-15 20:12:51', 1),
(127, 'STAFFIT01', '::1', '2026-03-15 20:17:20', 1),
(128, 'CLASS001', '::1', '2026-03-15 20:17:56', 1),
(129, 'ADMIN001', '::1', '2026-03-15 20:19:00', 1),
(130, 'STAFF2', '::1', '2026-03-15 20:19:30', 1),
(131, 'ADMIN001', '::1', '2026-03-15 20:19:47', 1),
(132, 'STAFFIT01', '::1', '2026-03-15 20:23:23', 0),
(133, 'STAFFIT01', '::1', '2026-03-15 20:23:29', 1),
(134, 'ADMIN001', '::1', '2026-03-15 20:45:36', 1),
(135, 'STAFFIT01', '::1', '2026-03-15 20:48:15', 1),
(136, '4211234421', '::1', '2026-03-15 21:29:48', 1),
(137, 'STAFFIT01', '::1', '2026-03-15 21:30:22', 1),
(138, 'STAFFIT01', '127.0.0.1', '2026-03-16 10:06:23', 1),
(139, 'ADMIN001', '::1', '2026-03-16 10:47:37', 0),
(140, 'STAFF2', '::1', '2026-03-16 10:47:50', 1),
(141, '4211234421', '::1', '2026-03-16 11:05:35', 1),
(142, 'DEAN001', '::1', '2026-03-16 11:47:49', 0),
(143, 'DEAN001', '::1', '2026-03-16 11:48:49', 1),
(144, 'DEAN001', '::1', '2026-03-16 11:52:45', 0),
(145, 'DEAN001', '::1', '2026-03-16 11:52:54', 1),
(146, 'DEAN001', '::1', '2026-03-16 11:54:38', 0),
(147, 'DEAN001', '::1', '2026-03-16 11:54:46', 1),
(148, 'ADMIN001', '::1', '2026-03-16 11:56:06', 1),
(149, 'DEAN001', '::1', '2026-03-16 11:56:26', 1),
(150, '4211234421', '::1', '2026-03-16 12:19:02', 1),
(151, 'ADMIN001', '::1', '2026-03-16 12:22:27', 1),
(152, 'DEAN001', '::1', '2026-03-16 12:23:15', 1),
(153, 'STAFFFIN01', '::1', '2026-03-16 12:24:48', 1),
(154, 'DEAN001', '::1', '2026-03-16 12:26:08', 1),
(155, 'ADMIN001', '::1', '2026-03-16 12:26:35', 1),
(156, 'STAFFMNT01', '::1', '2026-03-16 12:27:38', 1),
(157, 'STAFFREG01', '::1', '2026-03-16 12:28:15', 1),
(158, '4211234421', '::1', '2026-03-16 12:28:35', 1),
(159, 'STAFFSA01', '::1', '2026-03-16 12:29:28', 1),
(160, 'DEAN001', '::1', '2026-03-16 12:29:45', 1),
(161, 'ADMIN001', '::1', '2026-03-16 12:30:38', 1),
(162, 'STAFFIT01', '::1', '2026-03-16 12:31:12', 1),
(163, 'DEAN001', '::1', '2026-03-16 12:32:15', 0),
(164, 'DEAN001', '::1', '2026-03-16 12:32:21', 1),
(165, 'ADMIN001', '::1', '2026-03-16 12:35:56', 1),
(166, '4211234421', '::1', '2026-03-16 12:54:26', 1),
(167, 'CLASS001', '::1', '2026-03-16 12:57:16', 1),
(168, '4211234421', '::1', '2026-03-16 12:58:47', 1),
(169, 'STAFFIT01', '::1', '2026-03-16 12:59:08', 1),
(170, '4211234421', '::1', '2026-03-16 12:59:41', 1),
(171, 'CLASS001', '::1', '2026-03-16 13:00:13', 1),
(172, '4211234421', '::1', '2026-03-16 13:00:41', 1),
(173, 'CLASS001', '::1', '2026-03-16 13:05:25', 1),
(174, '4211234421', '::1', '2026-03-16 13:07:40', 1),
(175, 'STAFFIT01', '::1', '2026-03-16 13:11:10', 1),
(176, 'CLASS001', '::1', '2026-03-16 13:11:39', 0),
(177, 'CLASS001', '::1', '2026-03-16 13:11:45', 1),
(178, '4211234421', '::1', '2026-03-16 13:12:07', 1),
(179, 'CLASS001', '::1', '2026-03-16 13:18:03', 1),
(180, '4211234421', '::1', '2026-03-16 13:34:00', 1),
(181, 'STAFFIT01', '::1', '2026-03-16 13:34:26', 1),
(182, 'CLASS001', '::1', '2026-03-16 13:34:48', 1),
(183, '4211234421', '::1', '2026-03-16 13:35:12', 1),
(184, 'CLASS001', '::1', '2026-03-16 13:38:17', 1),
(185, 'STAFFIT01', '::1', '2026-03-16 13:38:34', 1),
(186, '4211234421', '::1', '2026-03-16 13:38:56', 1),
(187, 'CLASS001', '::1', '2026-03-16 13:39:13', 1),
(188, 'STAFFIT01', '::1', '2026-03-16 13:44:01', 1),
(189, 'CLASS001', '::1', '2026-03-16 13:44:22', 1),
(190, '4211234421', '::1', '2026-03-16 13:44:50', 1),
(191, 'NURSE02', '::1', '2026-03-16 14:22:22', 1),
(192, 'STAFFMNT01', '::1', '2026-03-16 14:24:07', 1),
(193, 'NURSE02', '::1', '2026-03-16 14:24:33', 1),
(194, 'STAFFIT01', '::1', '2026-03-16 14:27:41', 1),
(195, 'NURSE02', '::1', '2026-03-16 14:28:00', 1),
(196, 'STAFFMNT01', '::1', '2026-03-16 14:35:53', 0),
(197, 'STAFFMNT01', '::1', '2026-03-16 14:36:02', 1),
(198, 'NURSE02', '::1', '2026-03-16 14:36:25', 1),
(199, 'COMP02', '::1', '2026-03-16 14:37:50', 1),
(200, 'STAFFFIN01', '::1', '2026-03-16 14:38:27', 1),
(201, 'COMP02', '::1', '2026-03-16 14:38:51', 1),
(202, '4211234421', '::1', '2026-03-16 14:41:18', 1),
(203, '4211234421', '::1', '2026-03-16 14:46:29', 0),
(204, '4211234421', '::1', '2026-03-16 14:46:38', 1),
(205, '4211234421', '::1', '2026-03-16 14:50:23', 1),
(206, 'CLASS001', '::1', '2026-03-16 14:50:36', 1),
(207, 'DEAN001', '::1', '2026-03-16 14:50:55', 1),
(208, 'ADMIN001', '::1', '2026-03-16 14:51:31', 0),
(209, 'ADMIN001', '::1', '2026-03-16 14:51:38', 1),
(210, 'ADMIN001', '::1', '2026-03-16 14:54:32', 0),
(211, 'ADMIN001', '::1', '2026-03-16 14:54:38', 1),
(212, '4211234421', '::1', '2026-03-16 14:55:19', 0),
(213, '4211234421', '::1', '2026-03-16 14:55:27', 1),
(214, 'STAFFSA01', '::1', '2026-03-16 15:04:01', 1),
(215, '4211234421', '::1', '2026-03-16 15:04:23', 1),
(216, 'NURSE02', '::1', '2026-03-16 18:47:35', 0),
(222, 'ADMIN001', '::1', '2026-03-16 18:52:43', 1),
(223, '4211234421', '::1', '2026-03-16 18:53:04', 0),
(225, '4211234421', '::1', '2026-03-16 18:53:19', 1),
(230, 'STAFFIT01', '::1', '2026-03-16 18:57:06', 1),
(231, 'DEAN001', '::1', '2026-03-16 18:57:35', 0),
(232, 'DEAN001', '::1', '2026-03-16 18:58:00', 1),
(233, '4211234421', '::1', '2026-03-16 18:59:16', 1),
(234, 'STAFFREG01', '::1', '2026-03-16 19:00:16', 1),
(235, '4211234421', '::1', '2026-03-16 19:00:35', 1),
(236, 'DEAN001', '::1', '2026-03-16 19:01:05', 1),
(237, 'ADMIN001', '::1', '2026-03-16 19:01:40', 1),
(238, '4211234421', '::1', '2026-03-16 19:03:47', 1),
(239, 'STAFFREG01', '::1', '2026-03-16 19:04:22', 1),
(240, '4211234421', '::1', '2026-03-16 19:07:14', 1),
(241, 'STAFFIT01', '::1', '2026-03-16 19:08:03', 0),
(242, 'STAFFIT01', '::1', '2026-03-16 19:08:08', 1),
(243, '4211234421', '::1', '2026-03-16 19:08:51', 1),
(244, 'CLASS001', '::1', '2026-03-16 19:09:39', 0),
(245, 'CLASS001', '::1', '2026-03-16 19:09:46', 1),
(246, 'CLASS001', '::1', '2026-03-16 19:13:27', 0),
(247, 'CLASS001', '::1', '2026-03-16 19:13:33', 1),
(248, 'CLASS001', '::1', '2026-03-16 19:28:28', 1),
(249, 'STAFFSA01', '::1', '2026-03-16 19:36:16', 1),
(250, '4211234421', '::1', '2026-03-16 19:36:38', 0),
(251, '4211234421', '::1', '2026-03-16 19:36:48', 1),
(252, 'CLASS001', '::1', '2026-03-16 19:37:06', 1),
(253, 'STAFFFIN01', '::1', '2026-03-16 20:27:08', 1),
(254, '4211234421', '::1', '2026-03-16 20:27:26', 1),
(255, 'CLASS001', '::1', '2026-03-16 20:27:40', 1),
(256, '4211234421', '::1', '2026-03-16 20:33:16', 1),
(257, 'STAFFIT01', '::1', '2026-03-16 20:35:47', 1),
(258, '4211234421', '::1', '2026-03-16 20:36:26', 1),
(259, 'CLASS001', '::1', '2026-03-16 20:43:41', 1),
(260, 'STAFFIT01', '::1', '2026-03-16 20:43:58', 1),
(261, 'CLASS001', '::1', '2026-03-16 20:48:08', 1),
(262, 'ADMIN001', '::1', '2026-03-16 20:51:07', 0),
(263, 'ADMIN001', '::1', '2026-03-16 20:51:13', 1),
(264, '4211234421', '::1', '2026-03-16 21:43:30', 1),
(265, 'STAFFIT01', '::1', '2026-03-16 21:46:18', 1),
(266, 'ADMIN001', '::1', '2026-03-16 21:50:45', 1),
(267, 'ADMIN001', '::1', '2026-03-16 21:52:25', 0),
(268, 'ADMIN001', '::1', '2026-03-16 21:52:31', 1),
(269, 'NURSE04', '::1', '2026-03-16 21:54:26', 1),
(270, 'ADMIN001', '::1', '2026-03-16 21:54:41', 1),
(271, 'STAFFIT99', '::1', '2026-03-16 21:56:04', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `complaint_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'chat',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `idx_user_read` (`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT IGNORE INTO `notifications` (`notification_id`, `user_id`, `complaint_id`, `message_id`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 37, 1, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:20:31'),
(2, 3, 37, 1, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:20:31'),
(3, 4, 37, 1, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:20:31'),
(4, 2, 37, 2, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(5, 3, 37, 2, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(6, 4, 37, 2, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(7, 2, 37, 3, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(8, 3, 37, 3, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(9, 4, 37, 3, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:25:05'),
(10, 2, 37, 4, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:28:15'),
(11, 3, 37, 4, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:28:15'),
(12, 4, 37, 4, 'New message on complaint #CMP-2026-0011', 'chat', 0, '2026-03-15 19:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT IGNORE INTO `password_resets` (`reset_id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(4, 1, '59e5472977a4c21615946515cd74293c8a2abb757c39dd13e5ab0a2df54a4922', '2026-03-16 16:45:45', 1, '2026-03-16 14:45:45'),
(5, 2, '4d37e0d2d6ba0745407300ff2f6858f9906d67f29d6a4bb1e4e0b3f5bbabf4d4', '2026-03-16 16:54:03', 1, '2026-03-16 14:54:03');

-- --------------------------------------------------------

--
-- Table structure for table `resolution_patterns`
--

DROP TABLE IF EXISTS `resolution_patterns`;
CREATE TABLE IF NOT EXISTS `resolution_patterns` (
  `pattern_id` int(11) NOT NULL AUTO_INCREMENT,
  `keywords` text DEFAULT NULL,
  `suggested_department` varchar(100) DEFAULT NULL,
  `avg_resolution_time` decimal(10,2) DEFAULT NULL,
  `success_rate` decimal(5,2) DEFAULT NULL,
  `times_used` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pattern_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_chat_messages`
--

DROP TABLE IF EXISTS `staff_chat_messages`;
CREATE TABLE IF NOT EXISTS `staff_chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_complaint` (`complaint_id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_chat_messages`
--

INSERT IGNORE INTO `staff_chat_messages` (`message_id`, `complaint_id`, `sender_id`, `message`, `is_read`, `created_at`) VALUES
(1, 37, 5, 'hi', 0, '2026-03-15 19:20:31'),
(2, 37, 5, 'higbefs', 0, '2026-03-15 19:25:05'),
(3, 37, 5, 'higbefs', 0, '2026-03-15 19:25:05'),
(4, 37, 5, 'hi', 0, '2026-03-15 19:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `status_templates`
--

DROP TABLE IF EXISTS `status_templates`;
CREATE TABLE IF NOT EXISTS `status_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status_templates`
--

INSERT IGNORE INTO `status_templates` (`template_id`, `status`, `title`, `description`, `department`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'under_review', 'Complaint Received', 'Your complaint has been received and is being reviewed by our team.', NULL, 1, 1, '2026-03-13 19:48:30'),
(2, 'under_review', 'Initial Assessment', 'We are assessing your complaint to determine the best course of action.', NULL, 1, 2, '2026-03-13 19:48:30'),
(3, 'in_progress', 'Assigned to Team', 'Your complaint has been assigned to our team for resolution.', NULL, 1, 3, '2026-03-13 19:48:30'),
(4, 'in_progress', 'Work in Progress', 'Our team is currently working on resolving your complaint.', NULL, 1, 4, '2026-03-13 19:48:30'),
(5, 'in_progress', 'Awaiting Parts/Approval', 'We are waiting for necessary parts or approvals to proceed.', NULL, 1, 5, '2026-03-13 19:48:30'),
(6, 'resolved', 'Issue Resolved', 'Your complaint has been resolved. Please confirm if you are satisfied.', NULL, 1, 6, '2026-03-13 19:48:30'),
(7, 'resolved', 'Fixed and Verified', 'The issue has been fixed and verified by our team.', NULL, 1, 7, '2026-03-13 19:48:30'),
(8, 'closed', 'Complaint Closed', 'This complaint has been closed. Thank you for your patience.', NULL, 1, 8, '2026-03-13 19:48:30');

-- --------------------------------------------------------

--
-- Table structure for table `student_notifications`
--

DROP TABLE IF EXISTS `student_notifications`;
CREATE TABLE IF NOT EXISTS `student_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'batch_update',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `idx_user_read` (`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_notifications`
--

INSERT IGNORE INTO `student_notifications` (`notification_id`, `user_id`, `complaint_id`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 48, 'A new batch complaint #CMP-2026-0012 has been submitted that affects you. Please endorse it.', 'batch_update', 1, '2026-03-16 13:06:47'),
(2, 1, 48, 'Your batch complaint #CMP-2026-0012 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 13:11:26'),
(3, 1, 49, 'A new batch complaint #CMP-2026-0013 has been submitted that affects you. Please endorse it.', 'batch_update', 1, '2026-03-16 13:32:38'),
(4, 1, 49, 'Your batch complaint #CMP-2026-0013 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 13:34:37'),
(5, 1, 49, 'Your batch complaint #CMP-2026-0013 status changed to: Resolved - This complaint has been closed. Thank you for your patience.', 'batch_update', 1, '2026-03-16 13:38:47'),
(6, 1, 49, 'Your batch complaint #CMP-2026-0013 status changed to: Resolved - This complaint has been closed. Thank you for your patience.', 'batch_update', 1, '2026-03-16 13:44:12'),
(7, 10, 49, 'Your batch complaint #CMP-2026-0013 status was updated by staff: Resolved - This complaint has been closed. Thank you for your patience.', 'batch_update', 1, '2026-03-16 13:44:12'),
(8, 15, 50, 'Your complaint #CMP-2026-0016 status changed to: Resolved - Your complaint has been resolved. Please confirm if you are satisfied.', 'batch_update', 1, '2026-03-16 14:36:11'),
(9, 16, 52, 'Your complaint #CMP-2026-0018 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 0, '2026-03-16 14:38:38'),
(10, 1, 54, 'Your complaint #CMP-2026-0020 status changed to: Resolved - Your complaint has been resolved. Please confirm if you are satisfied.', 'batch_update', 1, '2026-03-16 15:04:09'),
(11, 1, 54, 'Your complaint #CMP-2026-0020 status changed to: Resolved - Your complaint has been resolved. Please confirm if you are satisfied.', 'batch_update', 1, '2026-03-16 15:04:12'),
(12, 1, 55, 'Your complaint #CMP-2026-0021 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 19:00:26'),
(13, 1, 53, 'Your complaint #CMP-2026-0019 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 19:08:29'),
(14, 10, 56, 'Your complaint #CMP-2026-0022 status changed to: Resolved - This complaint has been closed. Thank you for your patience.', 'batch_update', 1, '2026-03-16 19:36:28'),
(15, 10, 57, 'Your complaint #CMP-2026-0023 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 20:27:16'),
(16, 1, 58, 'Your complaint #CMP-2026-0024 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 20:36:15'),
(17, 1, 59, 'Your complaint #CMP-2026-0025 status changed to: Resolved - The issue has been fixed and verified by our team.', 'batch_update', 1, '2026-03-16 21:47:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('student','class_rep','staff','supervisor','admin','dean','principal') DEFAULT 'student',
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `class_code` varchar(50) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `batch_year` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT IGNORE INTO `users` (`user_id`, `student_id`, `email`, `password`, `full_name`, `role`, `department`, `course`, `class_code`, `year_of_study`, `batch_year`, `phone`, `profile_pic`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '4211234421', 'kongwiepercy9@gmail.com', '$2y$10$jBIB82mr4zp/rxVFEthud.mszdQZVGKiwkAXI/8JMsRa1Apa2NbSa', 'Percy Kongweeee', 'student', 'Computer Science', 'bit', NULL, 3, NULL, '0550710451', NULL, 1, '2026-03-13 19:01:23', '2026-03-16 18:54:49'),
(2, 'ADMIN001', 'admin@university.edu', '$2y$10$nGeZ5P5V8bL3aiM6VPnk/eWb83VRhW1VoruXX9Xw6N9OqqD5DAn2i', 'System Administrator', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:41', '2026-03-16 14:54:21'),
(3, 'DEAN001', 'dean@university.edu', '$2y$10$YBERmIazrA4LZPbQkBD8tei6It9wlhQXrsTbnPnx1giUsOqV5EZDu', 'Professor John Smith', 'dean', 'Engineering', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:41', '2026-03-13 19:15:41'),
(4, 'PRINCIPAL001', 'principal@university.edu', '$2y$10$DRG1DkMuvqQYSclUjTYcUemKmXlZHZo1uEqfslJHSVq0p06yWjQWK', 'Dr. Sarah Johnson', 'principal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:41', '2026-03-13 19:15:41'),
(5, 'STAFFIT01', 'it.staff@university.edu', '$2y$10$mCBmSHcZ.4VV.BKD7U9k6eAO3dc9K.OQMmSt/Kk8dlPQRJ6DwUr.q', 'James Wilson', 'staff', 'Information Technology', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:41', '2026-03-16 18:55:50'),
(6, 'STAFFMNT01', 'maintenance@university.edu', '$2y$10$qfkPzt2BlTKzltJg77ksVeVwiQt5TbxqHCUh/nIQtxwwpJ7LsFKoy', 'Robert Brown', 'staff', 'Maintenance', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:41', '2026-03-13 19:15:41'),
(7, 'STAFFFIN01', 'finance@university.edu', '$2y$10$/IL4Ea/SZCNQcYQ2d4PaveFB7JJvzbdUHYTYIqW38kVlTW0FjAatC', 'Elizabeth Davis', 'staff', 'Finance', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:42', '2026-03-13 19:15:42'),
(8, 'STAFFREG01', 'registry@university.edu', '$2y$10$v1ADqgEN.lw5ydMyRN8g5.Qs/PV95EyPcxa3MwCa4k1MvbAwNNQr6', 'Michael Taylor', 'staff', 'Registry', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:42', '2026-03-13 19:15:42'),
(9, 'STAFFSA01', 'student.affairs@university.edu', '$2y$10$wQJtBsvaSewiOboU1rohIeNhpmVLgrKiq3TwvabsFz3B5KrFGVig2', 'Patricia Martinez', 'staff', 'Student Affairs', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:42', '2026-03-13 19:15:42'),
(10, 'CLASS001', 'rep.cs@university.edu', '$2y$10$wUQ0nDVekaUJiMZxVLc2bOwkNjorskqc/W7xyRaDAuKeZ0AmAzpUa', 'David Lee', 'class_rep', 'Computer Science', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:42', '2026-03-16 19:13:17'),
(11, 'CLASS002', 'rep.eng@university.edu', '$2y$10$CV9jfpwE/ixyqd6PQLtgc.9SWVPCJiVfPVE1sz4P/1UeaMIjyFQXW', 'Lisa Wang', 'class_rep', 'Engineering', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 19:15:42', '2026-03-13 19:15:42'),
(13, '4211234422', 'kongweperc@gmail.com', '$2y$10$klTV7Pl7X9Sz4Eew8iWgS.BcbQz2IXspggFPFXa9v4iP4wJibD.nG', 'Percy Kongwie', 'student', 'Maintenance', 'svsvsv', NULL, 3, NULL, '0550710451', NULL, 1, '2026-03-13 19:58:54', '2026-03-13 19:58:54'),
(14, 'STAFF2', 'kongrcy9@gmail.com', '$2y$10$FQ42N114Mkm4a9j.AFi./u3YG1XZvzuM9UkuckBvJdOuM8GXFZ4PO', 'Percy Kongwie', 'staff', 'Information Technology', '', NULL, 0, NULL, '0550710451', NULL, 1, '2026-03-15 19:31:40', '2026-03-15 19:31:40'),
(15, 'NURSE02', 'kowiepercy9@gmail.com', '$2y$10$691lnuA7C7dI86ARwMuR3.sEptCyu1iLhfyrXl1D5drktDlrUOvli', 'MAY', 'student', 'Nursing', 'Bse', NULL, 1, NULL, '0550710451', NULL, 1, '2026-03-16 14:22:08', '2026-03-16 14:22:08'),
(16, 'COMP02', 'ngwepercy9@gmail.com', '$2y$10$QEnSjiP8XxqIf6j3rjsL8urPeYotIWyHGpINvJHe8SKkoahBLvbrm', 'JAY', 'student', 'Engineering', 'Com engeneering', NULL, 2, NULL, '0550710451', NULL, 1, '2026-03-16 14:37:36', '2026-03-16 14:37:36'),
(17, '', 'kongweperc@l.com', '$2y$10$ThZKsTrz4HPXl/azo4mCgemX3.O7a6zZNYKYzE.1EoImiox84JTpW', 'Percy Kongwie', 'student', NULL, '', NULL, 1, NULL, '0550710451', NULL, 1, '2026-03-16 21:27:57', '2026-03-16 21:27:57'),
(20, 'NURSE04', 'kongwepe@gmail.com', '$2y$10$YO5Vl/CyDNiA56pRAACDdu.bzTnbAMKA8LBwgdx2M2BFN/jcrSTJu', 'Percy Kongwie', 'student', NULL, 'Com engeneering', NULL, 1, NULL, '0550710451', NULL, 1, '2026-03-16 21:51:45', '2026-03-16 21:53:13'),
(21, 'STAFFIT99', 'cy9@gmail.com', '$2y$10$9RIjJ9BZNlevfeGFAobJKOF23/fOD4OkAZoo0GNW8enZY7o8WdGmK', 'Percy Kongwie', 'staff', 'Information Technology', NULL, NULL, NULL, NULL, '0550710451', NULL, 1, '2026-03-16 21:55:47', '2026-03-16 21:55:47');

-- --------------------------------------------------------

--
-- Table structure for table `user_conversations`
--

DROP TABLE IF EXISTS `user_conversations`;
CREATE TABLE IF NOT EXISTS `user_conversations` (
  `conversation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`conversation_id`),
  UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  KEY `user2_id` (`user2_id`),
  KEY `last_message_id` (`last_message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_conversations`
--

INSERT IGNORE INTO `user_conversations` (`conversation_id`, `user1_id`, `user2_id`, `last_message_id`, `updated_at`) VALUES
(1, 5, 14, 1, '2026-03-15 19:46:42'),
(2, 14, 5, 2, '2026-03-15 19:47:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT IGNORE INTO `user_sessions` (`session_id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `is_active`) VALUES
(1, 1, 'mv58q1c1l1t5kde9lof2j6jngg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:01:36', '2026-03-13 19:01:36', 1),
(2, 1, '3slb3bflapvk5edfjme6ber26e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:02:57', '2026-03-13 19:05:03', 0),
(3, 2, 'eailtsp9u5qt50h2r8jbv8ik2u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:17:23', '2026-03-13 19:17:38', 0),
(4, 3, 'g9j92gmin2nkfuq3pnkpf9i11i', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:18:03', '2026-03-13 19:18:03', 1),
(5, 5, 'of7flv0ad0j4v1uf88hc46r2s5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:18:43', '2026-03-13 19:18:53', 0),
(6, 10, 'bjcne2l0spr0va2fc0pcvorsha', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:19:11', '2026-03-13 19:19:28', 0),
(7, 2, '66udosla10nlnovedlm2h9l5e4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:19:44', '2026-03-13 19:19:51', 0),
(8, 1, 'trpdqhmru1q11vuncv1l5gvfpc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:20:00', '2026-03-13 19:20:05', 0),
(10, 1, '5kuhjbt7flqq0dumju3uq54tjl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:27:26', '2026-03-13 19:38:11', 0),
(11, 10, '1f7p46v7p0hiqjsif1v9cp5nb9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:39:38', '2026-03-13 19:44:16', 0),
(12, 1, '3ppshtb80g9bgtejcsmold568n', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:44:25', '2026-03-13 19:44:35', 0),
(13, 1, 'qed3uitud42i23lpu0tislhhn8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:45:52', '2026-03-13 19:46:14', 0),
(14, 5, 'uu9mecks6dn5hsdmj36hks2vs8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:46:28', '2026-03-13 19:50:33', 0),
(15, 1, '1s6725kqfllkpo875i9s2563eg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:50:41', '2026-03-13 19:51:00', 0),
(17, 7, 'fdhl6b9631d3qf2q2aumb0ia7p', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:52:59', '2026-03-13 19:53:11', 0),
(18, 10, 'hmnnj48auuqcupk55tdfmi76pu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:53:24', '2026-03-13 19:54:38', 0),
(19, 5, '8dele2hde78nm55mik0doe7qq5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:54:48', '2026-03-13 19:55:09', 0),
(20, 10, 'g0lgbsqcv7bk1aqnpfut1s7iek', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:55:18', '2026-03-13 19:55:26', 0),
(21, 5, 'a1ais38fsqh6cr4h2bdeviia95', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:55:49', '2026-03-13 19:55:58', 0),
(22, 10, 'mnlh38bv5cs7k9va0kgva4ls6s', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:56:03', '2026-03-13 19:57:16', 0),
(23, 10, 'pb8ce499n443m88d9gr94iu59m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:57:25', '2026-03-13 19:57:55', 0),
(24, 13, 'a2cdo9mmem2ignolgidnrtb3s3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:59:10', '2026-03-13 19:59:15', 0),
(25, 10, '2g3e2u0nom82ic5q9j43k7vk7j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:59:20', '2026-03-13 20:00:03', 0),
(26, 1, 'e69jggevne7qvkbgh31j6vubbf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:01:53', '2026-03-13 20:01:59', 0),
(27, 1, 'lfm9142tujrego9gkafe0jp90d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:04:52', '2026-03-13 20:17:19', 0),
(28, 1, 'uhj9vb39te6tmdsdb9e9dt86kg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:17:27', '2026-03-13 20:26:57', 0),
(29, 5, '632733gbcu6titig20g6go9mjm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:27:12', '2026-03-13 20:29:42', 0),
(30, 1, 'croc22aetgge8t6sa0gekia6u0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:29:55', '2026-03-13 20:31:43', 0),
(31, 5, '0q4jmctp0fsh9a10rbtsocjt4t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:31:57', '2026-03-13 20:32:30', 0),
(32, 1, 'l3795e8i7liulohcovod8vnqka', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:32:40', '2026-03-13 20:34:18', 0),
(33, 5, '0tgjv72267kbl8prop6mrel09r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:34:46', '2026-03-13 20:34:46', 1),
(34, 1, 's1arod2ijvfqt0a5iu8h1eoli9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:45:00', '2026-03-13 20:51:48', 0),
(35, 1, 'hr4jluv0fnffdtqe82q6lq2tdk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:51:55', '2026-03-13 20:54:55', 0),
(36, 5, 'o6bmf3tk9dns5s370tu7h23kgj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:55:04', '2026-03-13 20:55:41', 0),
(37, 1, 'c6jqerss03f01sfb8o8olsa1a5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:55:49', '2026-03-13 21:19:04', 0),
(38, 1, 'nc4436sosv8es81ph64lcdhi8a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-03-13 21:19:23', '2026-03-13 21:39:30', 0),
(39, 13, 'lnf6e794touaq540kanll4sq9s', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-03-13 21:39:39', '2026-03-13 21:42:24', 0),
(40, 5, 'utf4q29di2ds9f8vl4vvugh75r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:42:34', '2026-03-13 21:43:07', 0),
(41, 1, '0fu80etp7vijeer1p7op6pt0jb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:43:13', '2026-03-13 21:43:48', 0),
(42, 5, 'rmstpkrfbua5r0fk10cgtc7tst', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:43:59', '2026-03-13 21:43:59', 1),
(43, 1, 'k48iopi4hvm2r7p733ntm881f8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:51:31', '2026-03-13 21:52:16', 0),
(44, 5, '2e55okdf7gu5av70fklpp67sr3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:52:27', '2026-03-13 21:53:53', 0),
(45, 1, 's13dfndmct4a61500jk4tlhf2c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:54:01', '2026-03-13 21:56:02', 0),
(46, 5, 'lvvlqk13lr7gq89bv6u7pbgup1', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-03-13 21:56:11', '2026-03-13 21:58:28', 0),
(47, 1, 'gn31gsh4gs89nhqgkvrc04u35c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:58:35', '2026-03-13 21:59:05', 0),
(48, 5, '54amdqroc60h0jl0lgc5b1gnf4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 21:59:39', '2026-03-13 21:59:39', 1),
(49, 1, 'dgsmu2mhc91pghkr6vq6qr05fr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:46:26', '2026-03-15 11:46:44', 0),
(50, 5, 'hks1cfc2jni6dkqs78s09frggi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:47:18', '2026-03-15 11:48:47', 0),
(51, 1, 'a0636pesnbg2q17b2rgvmrm45l', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:49:05', '2026-03-15 11:49:22', 0),
(52, 1, 'bf2mh3lkoa55gbtcomes22ug67', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:49:37', '2026-03-15 11:51:05', 0),
(53, 5, 'tpntrc8dnt2ts3d2l8194cjr32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:51:18', '2026-03-15 11:51:53', 0),
(54, 1, 'vh4ovlpk1eg2hlmfmepplhbdnn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:52:14', '2026-03-15 11:52:29', 0),
(55, 7, 'dgjmf7hk4jb8vsmilfcq44e2o5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:54:48', '2026-03-15 11:55:26', 0),
(56, 1, 'ot43g1pp2553m7332gukce3uhq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:55:37', '2026-03-15 11:55:50', 0),
(57, 1, '0qsrab5rm91jfuc37nq7o4ps2d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:13:36', '2026-03-15 12:25:22', 0),
(58, 5, 'tdn6ubfl7l28h0leoi7k63c1lt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:25:32', '2026-03-15 12:25:44', 0),
(59, 1, 'a979vqfatovv46s2s9vqt8sikh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:25:55', '2026-03-15 12:25:55', 1),
(60, 5, 't13adksmmlc7ji72rsjq9087j1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:26:47', '2026-03-15 12:26:47', 1),
(61, 1, 'g337b5ncjpp7fag495qlo7hqaf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:27:56', '2026-03-15 12:28:33', 0),
(62, 5, 'jpq5ig0mjpru38u1p7ou9fdl07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:28:47', '2026-03-15 12:38:57', 0),
(63, 1, '1t3et39us56u63mvjclg9ebbi4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:39:14', '2026-03-15 12:42:03', 0),
(64, 5, '7emoieaiobckk7hf773r6a715g', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:42:11', '2026-03-15 12:44:07', 0),
(65, 1, '2r901ffqvfuees7gdk19t3qrnc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:44:15', '2026-03-15 12:47:41', 0),
(66, 5, 't3ajm8p8o5hkn57g57g4mkarmv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:47:52', '2026-03-15 12:48:18', 0),
(67, 2, 'o4itn38fkteqf7do9l7fjqvap8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:49:02', '2026-03-15 12:50:12', 0),
(68, 3, 'qgagb2mjt63n6arf9cb9nq2suh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:50:55', '2026-03-15 12:51:46', 0),
(69, 10, 'eslofceshg2a4mmm2t27ivhhmv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:52:09', '2026-03-15 12:53:02', 0),
(70, 5, 'v0lbbst2vbjbtr2vetnugjoejr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:53:09', '2026-03-15 12:53:47', 0),
(71, 2, 'tvsutcrb621k1d7mghc7rmgscd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:55:22', '2026-03-15 12:56:35', 0),
(72, 2, '3tp0dpvs3nkthb1dna7jks97p9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:57:56', '2026-03-15 13:00:09', 0),
(73, 5, 'krl5vg6co9bu8t82gb6ml6rg28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:00:26', '2026-03-15 13:00:53', 0),
(74, 2, 'v95jisa3i1621897ifgjapfj7o', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:01:02', '2026-03-15 13:13:30', 0),
(75, 1, 'g2fd8fiq84a30qvqg79tjj49tv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:13:36', '2026-03-15 13:14:35', 0),
(76, 2, 'q9gv05tilc2s14hibng0avjkhh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:14:44', '2026-03-15 13:18:07', 0),
(77, 1, '2k32gpbdvt5hn1slo5hk00ne22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:18:13', '2026-03-15 13:18:13', 1),
(78, 1, 'gfeh0gip467ufs7gvr3jh4vnsp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:26:36', '2026-03-15 13:26:36', 1),
(79, 2, '2aeetdt7is89o1llrscs3lnbjb', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-03-15 13:33:03', '2026-03-15 13:35:48', 0),
(80, 1, 'iub1fb5e0pmee3iq254nean01r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:35:55', '2026-03-15 13:36:44', 0),
(81, 5, 'j94lmkp3rvepn57jkj82fpsote', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:36:54', '2026-03-15 13:37:26', 0),
(82, 1, 'naib88mr0v830s95j2vcio8aie', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:37:33', '2026-03-15 13:37:33', 1),
(83, 1, 'aameo3qa8nup4c8e4o9hs4hos4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:43:05', '2026-03-15 13:46:05', 0),
(84, 5, 'g0niq0p7ap1noo7bbmbb92pgjl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:46:13', '2026-03-15 13:46:58', 0),
(85, 1, 'uf11kqeq3riag0so3r8kj770o9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:47:03', '2026-03-15 13:48:16', 0),
(86, 5, 'ov7dkj9q5j1t4s9623646gce5c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:48:30', '2026-03-15 13:49:05', 0),
(87, 1, 'pg8qhb87fdimft4u0qi4dh0tsg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:49:12', '2026-03-15 13:56:45', 0),
(88, 5, 'uv5v04fjr16jlq8tbprb0gqjm0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:13:50', '2026-03-15 14:16:17', 0),
(89, 10, '0skb38166gp2e61mju2n3l513r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:16:26', '2026-03-15 14:23:07', 0),
(90, 1, 'kaabsobdcovkee6m6rbv971bda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:23:17', '2026-03-15 14:24:40', 0),
(91, 5, 'dubhr01f272lmmsqnjo07qk3sj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:24:48', '2026-03-15 14:30:04', 0),
(92, 1, 'r7rur12k41cltl7vkh35bdsh0j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:30:09', '2026-03-15 14:31:13', 0),
(93, 2, 'ihh56bobklo72jn7sk2rnggtj4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:31:23', '2026-03-15 14:40:21', 0),
(94, 7, 'cn6m94t1d0stb9rfolah3dn4ga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:40:41', '2026-03-15 14:40:46', 0),
(95, 5, '72osqls9pk34m83g76in3oaj2u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:40:53', '2026-03-15 14:42:08', 0),
(96, 2, 'du6cc1p9s4f97h6dpcr8c3g00b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:42:15', '2026-03-15 14:42:48', 0),
(97, 5, 'vm02h8n15sl2ukorh4o37q6kq6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:43:02', '2026-03-15 14:43:08', 0),
(98, 2, 'r5bqvr9pf15phl25n25li977kg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:43:18', '2026-03-15 17:45:08', 0),
(99, 5, 'o72j51im4gt0lsmr6qq1l4v8rg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 17:45:17', '2026-03-15 19:28:23', 0),
(100, 7, '47s8j4jgfb9crtfoomgpcg3fsq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:28:53', '2026-03-15 19:29:13', 0),
(101, 2, 'm7o2cqbk40n7b6rajl4lmnabqq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:29:35', '2026-03-15 19:33:14', 0),
(102, 14, 'h2rgeq9nek1fgvsec9jupfdi7u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:33:32', '2026-03-15 19:46:16', 0),
(103, 5, 'r60du25d39s05m3ahhfrjupk85', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:46:30', '2026-03-15 19:47:00', 0),
(104, 14, '30kpea16or4itt7g453peceil5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:47:25', '2026-03-15 19:53:26', 0),
(105, 5, '3kpuqv5htujva6p1vmnpjufv23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:53:35', '2026-03-15 19:55:28', 0),
(106, 14, 'm7762jdgfda8o9rne76a5ugee1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:55:44', '2026-03-15 19:56:16', 0),
(107, 5, 'p570o58ps1sgnas48i63ve0utd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 19:56:29', '2026-03-15 20:00:00', 0),
(108, 2, '3aai1rer59gf8isoufe24p91tn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:02:44', '2026-03-15 20:06:34', 0),
(109, 5, '27u96vl9uumg8i60qaj3vtbke5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:06:41', '2026-03-15 20:07:40', 0),
(110, 1, 'a8huaqbtbet3sd2b7bo8k5mmm0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:07:45', '2026-03-15 20:09:03', 0),
(111, 14, '2eunpgep3196nj4kk00b6vtmgc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:09:40', '2026-03-15 20:09:52', 0),
(112, 2, 'lu8j4ht1qrndsa8v0l71bcn1j8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:10:02', '2026-03-15 20:12:43', 0),
(113, 10, '9dt44ptluhs4smoogj0mqaplt6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:12:51', '2026-03-15 20:17:11', 0),
(114, 5, '05jd9voq52d4jqc63be1vmf0da', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:17:20', '2026-03-15 20:17:48', 0),
(115, 10, 'd9dt3o5ouq2albbboceep1rr3j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:17:56', '2026-03-15 20:18:52', 0),
(116, 2, 'p66vcca0hevv1fs72c8afmatle', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:19:00', '2026-03-15 20:19:21', 0),
(117, 14, 'cg0h4nt7bpkls2jtuds9clvq7f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:19:30', '2026-03-15 20:19:41', 0),
(118, 2, 'utj98kqf5osgr46eln6189gvnq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:19:47', '2026-03-15 20:20:34', 0),
(119, 5, 'nb79sh5eufr8u0puktcuqc5dr5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:23:29', '2026-03-15 20:45:28', 0),
(120, 2, '528kfo9r5jsfpqcqlfl3ugtorm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:45:36', '2026-03-15 20:48:10', 0),
(121, 5, 't5flcl70mucpb25eiiu7u1965e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 20:48:15', '2026-03-15 20:48:15', 1),
(122, 1, '9hsj39n4l43t23c87mhhmir6sm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 21:29:48', '2026-03-15 21:30:14', 0),
(123, 5, 'gho28r4d5ks4249ekjmjc2kqb8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 21:30:22', '2026-03-15 21:30:22', 1),
(124, 5, 'hbos6nls0ul08as8dpik0khg4q', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 10:06:23', '2026-03-16 10:47:28', 0),
(125, 14, 'fj0u95pfkjn69o9gobpoti6mdp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 10:47:50', '2026-03-16 11:00:59', 0),
(126, 1, 'boufkb52kihmsd2bsl117d4tms', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:05:35', '2026-03-16 11:47:32', 0),
(127, 3, 'qmo5q6rbu01g8bpof5es95997e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:48:49', '2026-03-16 11:48:49', 1),
(128, 3, 'sbhoaspfrljmsh52d153hmkq8e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:52:54', '2026-03-16 11:52:54', 1),
(129, 3, '33sogiuc5sd780imt0hp9eb1vt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:54:46', '2026-03-16 11:55:57', 0),
(130, 2, 'rvq0birg3vqfkv4v6at0i35l09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:56:06', '2026-03-16 11:56:10', 0),
(131, 3, 'nu94ajgm060f91snpibumrlpro', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 11:56:26', '2026-03-16 12:18:56', 0),
(132, 1, 'cf59kjf3i939mucn7i0bs27uq5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:19:02', '2026-03-16 12:22:15', 0),
(133, 2, 'f2d1g2sk4sdbkbu5ma65cnif7n', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:22:27', '2026-03-16 12:22:59', 0),
(134, 3, '301106i474unugmo70dosn1jac', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:23:15', '2026-03-16 12:24:22', 0),
(135, 7, '2p8v4lv1vv74k2ose6o5obege6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:24:48', '2026-03-16 12:25:30', 0),
(136, 3, 't27bj62ao0hf5d0dqje0vpj2hb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:26:08', '2026-03-16 12:26:22', 0),
(137, 2, '00qu1k99peh7k00ase0ji1tnt8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:26:35', '2026-03-16 12:26:52', 0),
(138, 6, 'j9ddfujqd32o18bj0lm64hh0pj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:27:38', '2026-03-16 12:27:50', 0),
(139, 8, '04dlaor6dikc5fi970f0cbhq7b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:28:15', '2026-03-16 12:28:22', 0),
(140, 1, 'nt8mhvg1hofalfcgno0pb0fn2t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:28:35', '2026-03-16 12:29:16', 0),
(141, 9, 'jchlom6tlod2iop3i01pvk1kas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:29:28', '2026-03-16 12:29:37', 0),
(142, 3, 'e8mpjitg4aoimsjr7utuuisor6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:29:45', '2026-03-16 12:30:31', 0),
(143, 2, 'tgdcai3tlvpnmbq45dk3nf7n8o', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:30:38', '2026-03-16 12:31:04', 0),
(144, 5, 'utgjguk9gb6b96ms88rrh0g4nr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:31:12', '2026-03-16 12:32:07', 0),
(145, 3, 'vtpg80iu1r5r5e66e3ri0c1qbk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:32:21', '2026-03-16 12:35:49', 0),
(146, 2, 'v2km459n8eimf8o90uhvp5jo8e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:35:56', '2026-03-16 12:54:18', 0),
(147, 1, '5dk5ula2g052a03h8bnoj7t335', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:54:26', '2026-03-16 12:57:03', 0),
(148, 10, 'mdg1toldgqdnhlq73c1sdhgfom', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:57:16', '2026-03-16 12:58:41', 0),
(149, 1, '4okbo3tpl52ru8a7avq4dfi1gg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:58:47', '2026-03-16 12:59:00', 0),
(150, 5, 'akqte9l8au989d0e07i6r7hlj1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:59:08', '2026-03-16 12:59:26', 0),
(151, 1, '5e3sob3kbu82c12a1p180og6q0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:59:41', '2026-03-16 13:00:01', 0),
(152, 10, 'c5qf5edmg7t2rfg908eh3p978c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:00:13', '2026-03-16 13:00:36', 0),
(153, 1, '7431uijsmv1qtk2h0pji3vg439', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:00:41', '2026-03-16 13:05:10', 0),
(154, 10, 'rbpph4bffn94cfdcg5t0e11dj1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:05:25', '2026-03-16 13:07:33', 0),
(155, 1, 'if1n22d0r6n7nacjkjlteikamh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:07:40', '2026-03-16 13:11:02', 0),
(156, 5, '6h6v5j7r1qd3e7pd1pr6fgp6ct', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:11:10', '2026-03-16 13:11:29', 0),
(157, 10, '0d6515b91e1dklt8u8kulj24to', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:11:45', '2026-03-16 13:12:00', 0),
(158, 1, 'ov5up0rcgfski7ldcv628nuuqj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:12:07', '2026-03-16 13:17:54', 0),
(159, 10, 'co8q64balgk8k269b17sd0je9e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:18:03', '2026-03-16 13:33:54', 0),
(160, 1, '1aovdt45mtcofb2i5s9vtj18j0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:34:00', '2026-03-16 13:34:16', 0),
(161, 5, 'c1ad8ab0sneaat74j980vkpql6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:34:26', '2026-03-16 13:34:39', 0),
(162, 10, 'ghl0puqb8en7pm4vs4jbgqc5eq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:34:48', '2026-03-16 13:35:06', 0),
(163, 1, 'pu3mfo8iir6kcpm8pnh76237gu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:35:12', '2026-03-16 13:35:24', 0),
(164, 10, 'cee53om2bkln2og7o61d0da46a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:38:17', '2026-03-16 13:38:22', 0),
(165, 5, 'd2bs870ksrfb28nqi4ddiuuvrn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:38:34', '2026-03-16 13:38:50', 0),
(166, 1, 'lud9kaaku2rnmph9a91a1m9g2b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:38:56', '2026-03-16 13:39:04', 0),
(167, 10, 'co58ed675uuf6k8025k3bc4gkn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:39:13', '2026-03-16 13:43:55', 0),
(168, 5, 'nquvbp9cd8c77mpbkdu43mn97e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:44:01', '2026-03-16 13:44:14', 0),
(169, 10, '640ebeflmihgdgjrdhg7iniqao', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:44:22', '2026-03-16 13:44:44', 0),
(170, 1, '4q1463n3p3sv71e2g70pifl1r5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 13:44:50', '2026-03-16 13:44:59', 0),
(171, 15, 'llojvp16c5qduh7fu70845br0v', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:22:22', '2026-03-16 14:23:34', 0),
(172, 6, '4317ilstuid9hm1q7lmuo382h9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:24:07', '2026-03-16 14:24:25', 0),
(173, 15, 'v6e47k5ucog8qftr4u3qk2rdfq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:24:33', '2026-03-16 14:27:34', 0),
(174, 5, '8s3ssrqm15rkhksuulde1e0ps0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:27:41', '2026-03-16 14:27:53', 0),
(175, 15, 'lt7lcq9rj49cgluiveq171skvk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:28:00', '2026-03-16 14:35:36', 0),
(176, 6, 'nmj103fr270n7o8oq6ivvlp7rk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:36:02', '2026-03-16 14:36:13', 0),
(177, 15, 'p8ac19fh9uhlt7iq6f71aupfl9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:36:25', '2026-03-16 14:36:42', 0),
(178, 16, 'jfh4u04bjrfojtoodihcq1n6t7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:37:49', '2026-03-16 14:38:14', 0),
(179, 7, 'f100o0o2elq4dfuqit4njj9aa7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:38:27', '2026-03-16 14:38:40', 0),
(180, 16, '9k99oiuqlfdik5r7ebg6jgsm3t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:38:51', '2026-03-16 14:38:56', 0),
(181, 1, '3srgqt94552gkktuvsec0okjmd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:41:18', '2026-03-16 14:42:27', 0),
(182, 1, '3plvqq5mjl7tcfdc9k1lbrs33r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:46:38', '2026-03-16 14:46:41', 0),
(183, 1, 'u0ch8n5irqhvftmr230sjlibj8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:50:23', '2026-03-16 14:50:25', 0),
(184, 10, 'aq5tm640sf7v1mvmhbr1kc3qa0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:50:36', '2026-03-16 14:50:41', 0),
(185, 3, 'odm2j62q2akr2no4tc61ejbt8d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:50:55', '2026-03-16 14:51:19', 0),
(186, 2, 'm7tgbvmngpld755mj0575mjsil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:51:38', '2026-03-16 14:53:25', 0),
(187, 2, 'f2pnaq508ub7084qjookmv1i4q', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:54:38', '2026-03-16 14:54:42', 0),
(188, 1, '89ci9n6j7cae1s6nop1vhssod7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 14:55:27', '2026-03-16 15:03:42', 0),
(189, 9, '8k78oekhfieafofc0487b80voh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 15:04:01', '2026-03-16 15:04:14', 0),
(190, 1, '88neeof3t6h8vq4uaq747j875d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 15:04:23', '2026-03-16 15:04:23', 1),
(191, 15, 'oe81rkd4ailimad75mu8t3ijfv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:47:43', '2026-03-16 18:47:45', 0),
(192, 2, '2svgq6g440rai5dt8i7tmr7a2e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:52:43', '2026-03-16 18:52:54', 0),
(193, 1, 'mts21kk6fa4ildthbugrtj7jnp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:53:19', '2026-03-16 18:55:01', 0),
(194, 1, '41kivla3uvi7ug4hk63vdjddah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:55:16', '2026-03-16 18:55:19', 0),
(195, 5, 'asl64kcvter1djoe4fadifma28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:55:28', '2026-03-16 18:55:53', 0),
(196, 5, 'v7cek78sscama2mqp99mq743in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:57:06', '2026-03-16 18:57:10', 0),
(197, 3, '97ib8o6tdmtc8i0428i69722in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:58:00', '2026-03-16 18:58:44', 0),
(198, 1, '5653jehk6j0dbf0e7i7hul75b4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 18:59:16', '2026-03-16 18:59:58', 0),
(199, 8, 'pr6jc8vollv6rkdmrvqi9erobt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:00:16', '2026-03-16 19:00:27', 0),
(200, 1, 'a61hhmoe6vnjagh4hn8hvt23bf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:00:35', '2026-03-16 19:00:47', 0),
(201, 3, '0dn06bkr7iga9mmhprdmlh9efs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:01:05', '2026-03-16 19:01:34', 0),
(202, 2, 'p91mkdia276c2ce1j1ommcq0pj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:01:40', '2026-03-16 19:03:19', 0),
(203, 1, '08alfhp5ukh4mno04prevrkbab', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:03:47', '2026-03-16 19:04:04', 0),
(204, 8, 'scolqc0kiurvjto6sv3p3q076r', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:04:22', '2026-03-16 19:04:36', 0),
(205, 1, 'g6370qmvimj6qdvpplj8phnr34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:07:14', '2026-03-16 19:07:49', 0),
(206, 5, 'rn0dg2c32k65uu3egjupvdl4in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:08:08', '2026-03-16 19:08:38', 0),
(207, 1, 'e6m2k83nb13duq3cagu5m7tu9m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:08:51', '2026-03-16 19:08:57', 0),
(208, 10, '3n7cdfpn6fo5rj1vbmsfkf024a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:09:46', '2026-03-16 19:13:20', 0),
(209, 10, 'e53iek0bhhvopi11smi12uv9pm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:13:33', '2026-03-16 19:28:20', 0),
(210, 10, 'ihp63mnc26n4v42jvvedv8596u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:28:28', '2026-03-16 19:36:04', 0),
(211, 9, 'pog65d0ki75g81nn7mub1pi915', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:36:16', '2026-03-16 19:36:29', 0),
(212, 1, 'uals53i40hpl45n3lm9s1mod72', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:36:48', '2026-03-16 19:36:53', 0),
(213, 10, 't5ifkmv9ufm5d38a5hkogn3agj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 19:37:06', '2026-03-16 20:26:56', 0),
(214, 7, 'cl317jtnq0soft2ja55pqhofi9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:27:08', '2026-03-16 20:27:17', 0),
(215, 1, '1c6oogvlk38m9rq9ontfcu2kbu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:27:26', '2026-03-16 20:27:31', 0),
(216, 10, 'cn669kgn0et3vcbu53p0s4urjb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:27:40', '2026-03-16 20:33:08', 0),
(217, 1, 'ia7dshcjv07pi9h2ggl2pb95hn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:33:16', '2026-03-16 20:35:41', 0),
(218, 5, 'gooteuimg3j7sog1fojek895eb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:35:47', '2026-03-16 20:36:17', 0),
(219, 1, 'on3ig4j9t2qp4ktjhccrhr4jma', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:36:26', '2026-03-16 20:43:34', 0),
(220, 10, 'hiv526r5lkeb6is70j5rs9h71h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:43:41', '2026-03-16 20:43:47', 0),
(221, 5, 'b4bsn4hq3id5vs48avln5sfesh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:43:58', '2026-03-16 20:48:01', 0),
(222, 10, 'b4tlsa1vlukpg76erucit2i1rd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:48:08', '2026-03-16 20:50:59', 0),
(223, 2, 'nqgrhecc3os1iqhb76k82orrc3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 20:51:13', '2026-03-16 20:51:13', 1),
(224, 1, 'bkf1lr5kkq51dj06oprvs7gc64', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:43:29', '2026-03-16 21:43:29', 1),
(225, 5, '1jropjgjbp7b0goivt4t65p40m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:46:18', '2026-03-16 21:50:32', 0),
(226, 2, 'v00cuf2puetmmfn7bpbebc4u78', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:50:45', '2026-03-16 21:51:52', 0),
(227, 2, 'bt8golr1plb3ge7qdjckho4sd5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:52:31', '2026-03-16 21:53:19', 0),
(228, 20, '5ti31i8j9ks549udgffvti1cel', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:54:26', '2026-03-16 21:54:31', 0),
(229, 2, '9t1rdrvfploq5ehvu8uqk3dgpn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:54:41', '2026-03-16 21:55:49', 0),
(230, 21, '3rdji5509jrqplo05u3b09r8j9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 21:56:04', '2026-03-16 21:56:10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `webrtc_chat`
--

DROP TABLE IF EXISTS `webrtc_chat`;
CREATE TABLE IF NOT EXISTS `webrtc_chat` (
  `chat_id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chat_id`),
  KEY `meeting_id` (`meeting_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `webrtc_meetings`
--

DROP TABLE IF EXISTS `webrtc_meetings`;
CREATE TABLE IF NOT EXISTS `webrtc_meetings` (
  `meeting_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` varchar(100) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `meeting_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`meeting_id`),
  UNIQUE KEY `room_id` (`room_id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_room` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `webrtc_meetings`
--

INSERT IGNORE INTO `webrtc_meetings` (`meeting_id`, `room_id`, `complaint_id`, `created_by`, `meeting_name`, `is_active`, `started_at`, `ended_at`, `created_at`) VALUES
(1, 'meet_69b7d7e37e070_f6315bf886507d42', 37, 5, 'Meeting for Complaint #37', 0, '2026-03-16 10:13:55', '2026-03-16 10:14:24', '2026-03-16 10:13:55'),
(2, 'meet_69b7d877e2c46_b73bfa711134f142', 37, 5, 'Meeting for Complaint #37', 0, '2026-03-16 10:16:23', '2026-03-16 10:16:26', '2026-03-16 10:16:23'),
(3, 'meet_69b7d87e9472b_9d77e9366be5a0f5', 37, 5, 'Meeting for Complaint #37', 0, '2026-03-16 10:16:30', '2026-03-16 10:24:51', '2026-03-16 10:16:30'),
(4, 'meet_69b7da8d6d32d_d2694fdde129858f', 30, 5, 'Meeting for Complaint #30', 0, '2026-03-16 10:25:17', '2026-03-16 10:26:05', '2026-03-16 10:25:17'),
(7, 'meet_69b7dd679e83f_cc8f201ebca285b1', 43, 5, 'Meeting for Complaint #43', 0, '2026-03-16 10:37:27', '2026-03-16 10:37:48', '2026-03-16 10:37:27'),
(8, 'meet_69b7df16725c5_a1a639fea584ad18', 37, 5, 'Meeting for Complaint #37', 0, '2026-03-16 10:44:38', '2026-03-16 10:46:47', '2026-03-16 10:44:38'),
(9, 'meet_69b7dfda38dd1_a2d29e272d14b5dc', 30, 14, 'Meeting for Complaint #30', 0, '2026-03-16 10:47:54', '2026-03-16 10:48:26', '2026-03-16 10:47:54'),
(10, 'meet_69b7e07d8650a_2a9cffe87c0ef7ad', 43, 14, 'Meeting for Complaint #43', 0, '2026-03-16 10:50:37', '2026-03-16 10:50:42', '2026-03-16 10:50:37'),
(11, 'meet_69b7e099cfd15_341218eef67c8ea4', 43, 14, 'Meeting for Complaint #43', 0, '2026-03-16 10:51:05', '2026-03-16 10:51:08', '2026-03-16 10:51:05'),
(12, 'meet_69b7e16255655_bdf1424056aaf4f9', 37, 14, 'Meeting for Complaint #37', 0, '2026-03-16 10:54:26', '2026-03-16 10:54:55', '2026-03-16 10:54:26'),
(13, 'meet_69b7e182e54f7_c9a96738e7ecdf84', 37, 14, 'Meeting for Complaint #37', 0, '2026-03-16 10:54:58', '2026-03-16 10:57:18', '2026-03-16 10:54:58'),
(14, 'meet_69b7e20e2ea67_ce9e38f5ed17f159', 37, 14, 'Meeting for Complaint #37', 0, '2026-03-16 10:57:18', '2026-03-16 10:57:21', '2026-03-16 10:57:18'),
(15, 'meet_69b7e21433bee_a5f84b72baf7a6c3', 42, 14, 'Meeting for Complaint #42', 0, '2026-03-16 10:57:24', '2026-03-16 10:57:30', '2026-03-16 10:57:24'),
(16, 'meet_69b7e223692a6_f0109940dfcc1d78', 42, 14, 'Meeting for Complaint #42', 0, '2026-03-16 10:57:39', '2026-03-16 10:57:41', '2026-03-16 10:57:39'),
(17, 'meet_69b7e2cfcb864_2f8c3520478b6635', 42, 14, 'Meeting for Complaint #42', 0, '2026-03-16 11:00:31', '2026-03-16 11:00:33', '2026-03-16 11:00:31'),
(18, 'meet_69b7e2d59a351_689a2ec2b5bac2b3', 30, 14, 'Meeting for Complaint #30', 0, '2026-03-16 11:00:37', '2026-03-16 11:00:48', '2026-03-16 11:00:37'),
(19, 'meet_69b7f69566d96_21cca9001341ebc0', 44, 7, 'Meeting for Complaint #44', 0, '2026-03-16 12:24:53', '2026-03-16 12:25:11', '2026-03-16 12:24:53'),
(20, 'meet_69b7f6a7e09c4_6cb06ccc6440e2ad', 44, 7, 'Meeting for Complaint #44', 0, '2026-03-16 12:25:11', '2026-03-16 12:25:16', '2026-03-16 12:25:11');

-- --------------------------------------------------------

--
-- Table structure for table `webrtc_participants`
--

DROP TABLE IF EXISTS `webrtc_participants`;
CREATE TABLE IF NOT EXISTS `webrtc_participants` (
  `participant_id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `is_video_enabled` tinyint(1) DEFAULT 1,
  `is_audio_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`participant_id`),
  UNIQUE KEY `unique_participant` (`meeting_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `webrtc_participants`
--

INSERT IGNORE INTO `webrtc_participants` (`participant_id`, `meeting_id`, `user_id`, `joined_at`, `left_at`, `is_video_enabled`, `is_audio_enabled`) VALUES
(1, 1, 5, '2026-03-16 10:13:55', '2026-03-16 10:14:24', 1, 1),
(2, 2, 5, '2026-03-16 10:16:23', '2026-03-16 10:16:26', 1, 1),
(3, 3, 5, '2026-03-16 10:16:30', '2026-03-16 10:24:51', 1, 1),
(4, 4, 5, '2026-03-16 10:25:17', '2026-03-16 10:26:05', 1, 1),
(7, 7, 5, '2026-03-16 10:37:27', '2026-03-16 10:37:48', 1, 1),
(8, 8, 5, '2026-03-16 10:46:42', '2026-03-16 10:46:47', 1, 1),
(16, 9, 14, '2026-03-16 10:47:54', '2026-03-16 10:48:26', 1, 1),
(17, 10, 14, '2026-03-16 10:50:37', '2026-03-16 10:50:42', 1, 1),
(18, 11, 14, '2026-03-16 10:51:05', '2026-03-16 10:51:08', 1, 1),
(19, 12, 14, '2026-03-16 10:54:26', '2026-03-16 10:54:55', 1, 1),
(20, 13, 14, '2026-03-16 10:54:58', '2026-03-16 10:57:18', 1, 1),
(21, 14, 14, '2026-03-16 10:57:18', '2026-03-16 10:57:21', 1, 1),
(22, 15, 14, '2026-03-16 10:57:24', '2026-03-16 10:57:30', 1, 1),
(23, 16, 14, '2026-03-16 10:57:39', '2026-03-16 10:57:41', 1, 1),
(24, 17, 14, '2026-03-16 11:00:31', '2026-03-16 11:00:33', 1, 1),
(25, 18, 14, '2026-03-16 11:00:37', '2026-03-16 11:00:48', 1, 1),
(26, 19, 7, '2026-03-16 12:24:53', '2026-03-16 12:25:11', 1, 1),
(27, 20, 7, '2026-03-16 12:25:11', '2026-03-16 12:25:16', 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints` ADD FULLTEXT KEY `ft_title_description` (`title`,`description`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_members`
--
ALTER TABLE `batch_members`
  ADD CONSTRAINT `batch_members_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_attachments`
--
ALTER TABLE `chat_attachments`
  ADD CONSTRAINT `chat_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `staff_chat_messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_read_receipts`
--
ALTER TABLE `chat_read_receipts`
  ADD CONSTRAINT `chat_read_receipts_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `staff_chat_messages` (`message_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_read_receipts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_typing`
--
ALTER TABLE `chat_typing`
  ADD CONSTRAINT `chat_typing_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_typing_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `class_rep_notifications`
--
ALTER TABLE `class_rep_notifications`
  ADD CONSTRAINT `class_rep_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_rep_notifications_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`),
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `complaint_attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_ratings`
--
ALTER TABLE `complaint_ratings`
  ADD CONSTRAINT `complaint_ratings_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_ratings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_status_history`
--
ALTER TABLE `complaint_status_history`
  ADD CONSTRAINT `complaint_status_history_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `complaint_timeline`
--
ALTER TABLE `complaint_timeline`
  ADD CONSTRAINT `complaint_timeline_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_timeline_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `complaint_upvotes`
--
ALTER TABLE `complaint_upvotes`
  ADD CONSTRAINT `complaint_upvotes_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_upvotes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `direct_messages`
--
ALTER TABLE `direct_messages`
  ADD CONSTRAINT `direct_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `direct_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `direct_message_attachments`
--
ALTER TABLE `direct_message_attachments`
  ADD CONSTRAINT `direct_message_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `direct_messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `direct_typing`
--
ALTER TABLE `direct_typing`
  ADD CONSTRAINT `direct_typing_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `direct_typing_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `historical_analysis`
--
ALTER TABLE `historical_analysis`
  ADD CONSTRAINT `historical_analysis_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_chat_messages`
--
ALTER TABLE `staff_chat_messages`
  ADD CONSTRAINT `staff_chat_messages_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD CONSTRAINT `student_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_notifications_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_conversations`
--
ALTER TABLE `user_conversations`
  ADD CONSTRAINT `user_conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_conversations_ibfk_3` FOREIGN KEY (`last_message_id`) REFERENCES `direct_messages` (`message_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `webrtc_chat`
--
ALTER TABLE `webrtc_chat`
  ADD CONSTRAINT `webrtc_chat_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `webrtc_meetings` (`meeting_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `webrtc_chat_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `webrtc_meetings`
--
ALTER TABLE `webrtc_meetings`
  ADD CONSTRAINT `webrtc_meetings_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `webrtc_meetings_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `webrtc_participants`
--
ALTER TABLE `webrtc_participants`
  ADD CONSTRAINT `webrtc_participants_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `webrtc_meetings` (`meeting_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `webrtc_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
