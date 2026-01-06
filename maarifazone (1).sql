-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 27, 2025 at 04:23 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maarifazone`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` bigint UNSIGNED NOT NULL,
  `log_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint UNSIGNED DEFAULT NULL,
  `causer_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint UNSIGNED DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `properties`, `created_at`) VALUES
(1, 'login', 'User logged in', 'App\\Models\\User', 2, 'App\\Models\\User', 2, '{\"ip\": \"::1\", \"email\": \"info@wolinet.com\", \"user_agent\": \"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36\"}', '2025-07-02 08:38:21');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$12$nfcjObkzd6Zcn04qYzAGtOgRoqfMzJ3XiT8NGathjWe990.u6QC/W', '2025-07-15 17:39:48');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','normal','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('new','read','replied','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_category` (`category`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `name`, `email`, `phone`, `subject`, `category`, `message`, `priority`, `ip_address`, `user_agent`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Renatus Bernardo', 'info@wolinet.com', '747003357', 'MUTAMARK', 'course', 'nime download', 'high', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 'replied', '2025-10-26 23:47:51', '2025-10-26 23:58:44'),
(2, NULL, 'Renatus Bernardo', 'kigodimeet@gmail.com', '+255655991973', 'MUTAMARK', 'technical', 'i cant donloafds', 'high', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'closed', '2025-10-27 00:10:00', '2025-10-27 00:26:16');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `level` enum('beginner','intermediate','advanced','all') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `duration` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'English',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TZS',
  `cover_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promo_video` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visibility` enum('draft','private','published') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `total_modules` int UNSIGNED NOT NULL DEFAULT '0',
  `total_lessons` int UNSIGNED NOT NULL DEFAULT '0',
  `total_minutes` int UNSIGNED NOT NULL DEFAULT '0',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_courses_slug` (`slug`),
  KEY `idx_courses_category` (`category_id`),
  KEY `idx_courses_visibility` (`visibility`),
  KEY `fk_courses_creator` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `category_id`, `title`, `slug`, `short_description`, `description`, `level`, `duration`, `language`, `price`, `currency`, `cover_image`, `promo_video`, `visibility`, `featured`, `total_modules`, `total_lessons`, `total_minutes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, 'Jinsi ya kuweka Library kwenye Kontakt', 'jinsi-ya-kuweka-library-kwenye-kontakt', '<p>Jinsi ya kuweka Library kwenye Kontakt</p>', '<p>Jinsi ya kuweka Library kwenye Kontakt</p>', 'all', '1', 'English', 1000.00, 'TZS', 'uploads/thumbnails/692803320bbf5_Screenshot 2025-10-05 084122.png', 'https://vimeo.com/1140368733?fl=pl&fe=sh', 'published', 1, 1, 1, 0, 1, '2025-11-26 15:42:58', '2025-11-27 10:52:18');

-- --------------------------------------------------------

--
-- Table structure for table `course_categories`
--

DROP TABLE IF EXISTS `course_categories`;
CREATE TABLE IF NOT EXISTS `course_categories` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_course_categories_slug` (`slug`),
  KEY `idx_course_categories_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_categories`
--

INSERT INTO `course_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Audio Mixiing', 'audio-mixiing', '', NULL, 1, 0, '2025-11-26 21:32:56', '2025-11-26 21:32:56'),
(2, NULL, 'AuDIO Recording', 'audio-recording', '', NULL, 1, 0, '2025-11-26 21:33:46', '2025-11-26 21:33:46'),
(3, NULL, 'Mastering', 'mastering', '', NULL, 1, 0, '2025-11-26 21:34:00', '2025-11-26 21:34:00'),
(4, NULL, 'Beat making', 'beat-making', '', NULL, 1, 0, '2025-11-26 21:34:11', '2025-11-26 21:34:11');

-- --------------------------------------------------------

--
-- Table structure for table `course_category_links`
--

DROP TABLE IF EXISTS `course_category_links`;
CREATE TABLE IF NOT EXISTS `course_category_links` (
  `course_id` int NOT NULL,
  `category_id` int NOT NULL,
  PRIMARY KEY (`course_id`,`category_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_category_links`
--

INSERT INTO `course_category_links` (`course_id`, `category_id`) VALUES
(1, 3),
(145, 29);

-- --------------------------------------------------------

--
-- Table structure for table `course_requests`
--

DROP TABLE IF EXISTS `course_requests`;
CREATE TABLE IF NOT EXISTS `course_requests` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_link` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','in_review','fulfilled','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_ip_created` (`ip_address`,`created_at`),
  KEY `idx_fpr_created` (`device_fingerprint`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_requests`
--

INSERT INTO `course_requests` (`id`, `user_id`, `name`, `email`, `phone`, `course_name`, `course_link`, `category`, `details`, `ip_address`, `user_agent`, `device_fingerprint`, `status`, `created_at`, `updated_at`) VALUES
(1, 20, 'AIBI AWEEH', 'ibclassicprod@gmail.com', '+243972856288', 'AFRRO BEATS', 'http://localhost/ak23studiokit/course-details.php?slug=afro-genesis-vol-2', 'AUDIO SAMPLE', '<p>testing mitambo</p>', '197.186.16.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '30705b44487cd50d883d29bcbfec949c', 'new', '2025-10-15 15:51:30', '2025-10-15 15:51:30'),
(2, 19, 'komz', 'komanyalucas@gmail.com', '0763312251', 'kontakt', 'https://www.native-instruments.com/en/courses/komplete/samplers/kontakt-8/?srsltid=AfmBOopIlAPWFfdh2seUyuGcUV2olDBxkYySU-9Knn5kNloU8-oi-7Gm', NULL, NULL, '41.59.159.53', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:144.0) Gecko/20100101 Firefox/144.0', '72dc4c14f63e160ee60f7f76386abae1', 'new', '2025-10-15 17:09:39', '2025-10-15 17:09:39'),
(3, 19, 'komz', 'komanyalucas@gmail.com', '0763312251', 'kompa', NULL, NULL, NULL, '154.74.186.15', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:144.0) Gecko/20100101 Firefox/144.0', '72dc4c14f63e160ee60f7f76386abae1', 'new', '2025-10-27 17:45:55', '2025-10-27 17:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `downloads_log`
--

DROP TABLE IF EXISTS `downloads_log`;
CREATE TABLE IF NOT EXISTS `downloads_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `downloaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dl_order` (`order_id`)
) ENGINE=MyISAM AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `downloads_log`
--

INSERT INTO `downloads_log` (`id`, `order_id`, `downloaded_at`) VALUES
(1, 6, '2025-09-29 03:04:04'),
(2, 20, '2025-10-02 05:35:17'),
(3, 21, '2025-10-02 06:05:16'),
(4, 23, '2025-10-02 07:48:48'),
(5, 26, '2025-10-02 09:17:31'),
(6, 26, '2025-10-02 10:18:33'),
(7, 28, '2025-10-02 12:41:36'),
(8, 30, '2025-10-02 15:01:54'),
(9, 10, '2025-10-07 17:14:29'),
(10, 32, '2025-10-07 18:14:47'),
(11, 37, '2025-10-10 14:02:25'),
(12, 42, '2025-10-10 14:34:03'),
(13, 42, '2025-10-10 14:37:45'),
(14, 42, '2025-10-10 14:38:45'),
(15, 42, '2025-10-10 14:47:48'),
(16, 44, '2025-10-10 15:18:17'),
(17, 45, '2025-10-10 15:18:29'),
(18, 42, '2025-10-10 15:24:22'),
(19, 44, '2025-10-10 15:24:43'),
(20, 47, '2025-10-10 15:39:17'),
(21, 37, '2025-10-16 13:58:38'),
(22, 54, '2025-10-16 14:04:02'),
(23, 62, '2025-10-23 05:32:09'),
(24, 66, '2025-10-23 14:09:51'),
(25, 57, '2025-10-24 02:10:01'),
(26, 70, '2025-10-25 19:26:22'),
(27, 73, '2025-10-26 22:38:48'),
(28, 10, '2025-10-27 03:39:25'),
(29, 76, '2025-10-27 10:36:07'),
(30, 75, '2025-10-27 10:36:23'),
(31, 76, '2025-10-27 10:39:44'),
(32, 76, '2025-10-27 16:51:49'),
(33, 75, '2025-10-27 16:52:42'),
(34, 88, '2025-10-28 18:42:48'),
(35, 90, '2025-11-06 13:36:18'),
(36, 90, '2025-11-06 13:37:43'),
(37, 90, '2025-11-06 13:37:49'),
(38, 92, '2025-11-27 10:59:43');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `course_id` int UNSIGNED NOT NULL,
  `enrollment_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','active','completed','cancelled','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_enrollment_user_course` (`user_id`,`course_id`),
  KEY `idx_enrollment_course` (`course_id`),
  KEY `idx_enrollment_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrollment_code`, `progress`, `status`, `payment_status`, `expires_at`, `created_at`, `updated_at`, `completed_at`) VALUES
(1, 4, 1, NULL, 0.00, 'active', 'paid', NULL, '2025-11-27 13:55:33', '2025-11-27 13:55:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_payments`
--

DROP TABLE IF EXISTS `enrollment_payments`;
CREATE TABLE IF NOT EXISTS `enrollment_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` int UNSIGNED NOT NULL,
  `gateway` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pesapal',
  `reference` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TZS',
  `status` enum('pending','completed','failed','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_enrollment` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_folders`
--

DROP TABLE IF EXISTS `file_folders`;
CREATE TABLE IF NOT EXISTS `file_folders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `instructors`
--

DROP TABLE IF EXISTS `instructors`;
CREATE TABLE IF NOT EXISTS `instructors` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expertise` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `social_links` json DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_instructor_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructors`
--

INSERT INTO `instructors` (`id`, `user_id`, `name`, `bio`, `avatar`, `expertise`, `social_links`, `approved`, `created_at`, `updated_at`) VALUES
(1, 2, 'NIT STORE', 'djvjhdj', NULL, NULL, NULL, 1, '2025-11-26 15:09:44', '2025-11-26 15:09:44'),
(2, 3, 'Renatus Bernardo', 'the ticha by proffessional', NULL, NULL, NULL, 1, '2025-11-27 02:41:05', '2025-11-27 02:41:05');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_announcements`
--

DROP TABLE IF EXISTS `instructor_announcements`;
CREATE TABLE IF NOT EXISTS `instructor_announcements` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` int UNSIGNED NOT NULL,
  `instructor_id` int UNSIGNED NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_course` (`course_id`),
  KEY `fk_announcements_instructor` (`instructor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `instructor_course_assignments`
--

DROP TABLE IF EXISTS `instructor_course_assignments`;
CREATE TABLE IF NOT EXISTS `instructor_course_assignments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `instructor_id` int UNSIGNED NOT NULL,
  `course_id` int UNSIGNED NOT NULL,
  `assigned_by` int UNSIGNED DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_assignment` (`instructor_id`,`course_id`),
  KEY `idx_assignment_course` (`course_id`),
  KEY `fk_assignment_admin` (`assigned_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_course_assignments`
--

INSERT INTO `instructor_course_assignments` (`id`, `instructor_id`, `course_id`, `assigned_by`, `assigned_at`) VALUES
(1, 1, 1, 1, '2025-11-26 15:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_wallet`
--

DROP TABLE IF EXISTS `instructor_wallet`;
CREATE TABLE IF NOT EXISTS `instructor_wallet` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructor_id` int UNSIGNED NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_earned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_withdraw` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_instructor` (`instructor_id`),
  UNIQUE KEY `wallet_number` (`wallet_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instructor_wallet`
--

INSERT INTO `instructor_wallet` (`id`, `wallet_number`, `instructor_id`, `balance`, `total_earned`, `last_withdraw`, `updated_at`) VALUES
(1, 'AKW-0950-5937', 2, 50000.00, 50000.00, NULL, '2025-11-27 04:07:40');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_wallet_transactions`
--

DROP TABLE IF EXISTS `instructor_wallet_transactions`;
CREATE TABLE IF NOT EXISTS `instructor_wallet_transactions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `instructor_id` int UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iwt_instructor` (`instructor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `instructor_wallet_transactions`
--

INSERT INTO `instructor_wallet_transactions` (`id`, `instructor_id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 2, 'credit', 50000.00, 'Admin transfer', '2025-11-27 04:07:40');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_withdraw_requests`
--

DROP TABLE IF EXISTS `instructor_withdraw_requests`;
CREATE TABLE IF NOT EXISTS `instructor_withdraw_requests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `instructor_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(191) DEFAULT NULL,
  `account_number` varchar(191) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_iwr_instructor` (`instructor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ipn_logs`
--

DROP TABLE IF EXISTS `ipn_logs`;
CREATE TABLE IF NOT EXISTS `ipn_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_tracking_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` enum('video','text','pdf') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'video',
  `video_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pdf_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_number` int UNSIGNED NOT NULL DEFAULT '1',
  `is_preview` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_module` (`module_id`),
  KEY `idx_lessons_order` (`module_id`,`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `module_id`, `title`, `content_type`, `video_url`, `text_content`, `pdf_file`, `duration`, `order_number`, `is_preview`, `created_at`, `updated_at`) VALUES
(1, 1, 'get started 2', 'text', 'https://www.youtube.com/watch?v=0NX2MTMo2WsJinsi ya kuweka Library kwenye Kontakt', 'Jinsi ya kuweka Library kwenye Kontakt Jinsi ya kuweka Library kwenye Kontakt', NULL, '1', 1, 1, '2025-11-27 10:52:18', '2025-11-27 10:52:18');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_notes`
--

DROP TABLE IF EXISTS `lesson_notes`;
CREATE TABLE IF NOT EXISTS `lesson_notes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `lesson_id` int UNSIGNED NOT NULL,
  `student_id` int UNSIGNED NOT NULL,
  `note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notes_student` (`student_id`),
  KEY `fk_notes_lesson` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_progress`
--

DROP TABLE IF EXISTS `lesson_progress`;
CREATE TABLE IF NOT EXISTS `lesson_progress` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED NOT NULL,
  `status` enum('pending','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_progress` (`enrollment_id`,`lesson_id`),
  KEY `idx_progress_enrollment` (`enrollment_id`),
  KEY `fk_progress_lesson` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_outbox`
--

DROP TABLE IF EXISTS `mail_outbox`;
CREATE TABLE IF NOT EXISTS `mail_outbox` (
  `id` int NOT NULL AUTO_INCREMENT,
  `to` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  `last_error` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medias`
--

DROP TABLE IF EXISTS `medias`;
CREATE TABLE IF NOT EXISTS `medias` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `courses_id` bigint UNSIGNED NOT NULL,
  `storage_config_id` int UNSIGNED DEFAULT NULL,
  `folder_id` bigint UNSIGNED DEFAULT NULL,
  `type` enum('upload','url') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('image','zip','pdf','wav') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint UNSIGNED DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_links` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_medias_course` (`courses_id`),
  KEY `idx_medias_filetype` (`file_type`),
  KEY `idx_medias_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medias`
--

INSERT INTO `medias` (`id`, `courses_id`, `storage_config_id`, `folder_id`, `type`, `file_type`, `size`, `value`, `share_links`, `created_at`, `updated_at`, `deleted_at`) VALUES
(4, 1, NULL, NULL, 'url', 'zip', NULL, 'https://www.youtube.com/watch?v=rwzQFLttRlM', NULL, '2025-11-27 07:52:18', '2025-11-27 07:52:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `media_share_tokens`
--

DROP TABLE IF EXISTS `media_share_tokens`;
CREATE TABLE IF NOT EXISTS `media_share_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `media_id` bigint UNSIGNED NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `max_downloads` int UNSIGNED DEFAULT '10',
  `download_count` int UNSIGNED DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `media_id` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_number` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_modules_course` (`course_id`),
  KEY `idx_modules_order` (`course_id`,`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `course_id`, `title`, `summary`, `order_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'Jinsi ya kuweka Library kwenye Kontakt', NULL, 2, '2025-11-27 10:52:18', '2025-11-27 10:52:18');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `type` enum('sms','email','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `created_at`) VALUES
(1, 7, 'system', 'Order completed for Colorz Red. Your download is ready!', '2025-10-02 02:31:16'),
(2, 1, 'system', 'Order completed: Order #19 - Colorz Red', '2025-10-02 02:31:16'),
(3, 7, 'system', 'Payment completed for Colorz Red. Your download is ready!', '2025-10-02 02:31:16'),
(4, 1, 'system', 'Payment completed: Order #19 - Colorz Red (TSH 1,800)', '2025-10-02 02:31:16'),
(5, 9, 'system', 'Order completed for Mad Max. Your download is ready!', '2025-10-02 02:34:37'),
(6, 1, 'system', 'Order completed: Order #20 - Mad Max', '2025-10-02 02:34:37'),
(7, 9, 'system', 'Payment completed for Mad Max. Your download is ready!', '2025-10-02 02:34:37'),
(8, 1, 'system', 'Payment completed: Order #20 - Mad Max (TSH 4,600)', '2025-10-02 02:34:37'),
(9, 20, 'system', 'We received your request for AFRRO BEATS.', '2025-10-15 15:51:32'),
(10, 1, 'system', 'New course request: AFRRO BEATS by ibclassicprod@gmail.com', '2025-10-15 15:51:32'),
(11, 19, 'system', 'We received your request for kontakt.', '2025-10-15 17:09:42'),
(12, 1, 'system', 'New course request: kontakt by komanyalucas@gmail.com', '2025-10-15 17:09:42'),
(13, 18, 'system', 'Order completed for FL Studio Producer 25.1.6  All Plugins + FLEX Bundle. Your download is ready!', '2025-10-16 12:00:40'),
(14, 1, 'system', 'Order completed: Order #54 - FL Studio Producer 25.1.6  All Plugins + FLEX Bundle', '2025-10-16 12:00:41'),
(15, 18, 'system', 'Payment completed for FL Studio Producer 25.1.6  All Plugins + FLEX Bundle. Your download is ready!', '2025-10-16 12:00:41'),
(16, 1, 'system', 'Payment completed: Order #54 - FL Studio Producer 25.1.6  All Plugins + FLEX Bundle (TSH 5,200)', '2025-10-16 12:00:41'),
(17, 29, 'system', 'Order completed for Afro Genesis Vol 2. Your download is ready!', '2025-10-24 19:42:18'),
(18, 1, 'system', 'Order completed: Order #67 - Afro Genesis Vol 2', '2025-10-24 19:42:18'),
(19, 29, 'system', 'Payment completed for Afro Genesis Vol 2. Your download is ready!', '2025-10-24 19:42:18'),
(20, 1, 'system', 'Payment completed: Order #67 - Afro Genesis Vol 2 (TSH 3,200)', '2025-10-24 19:42:18'),
(21, 19, 'system', 'We received your request for kompa.', '2025-10-27 17:45:56'),
(22, 1, 'system', 'New course request: kompa by komanyalucas@gmail.com', '2025-10-27 17:45:56'),
(23, 37, 'system', 'Order completed for Africano - Afrobeats & Latino. Your download is ready!', '2025-11-05 20:33:58'),
(24, 1, 'system', 'Order completed: Order #90 - Africano - Afrobeats & Latino', '2025-11-05 20:33:58'),
(25, 37, 'system', 'Payment completed for Africano - Afrobeats & Latino. Your download is ready!', '2025-11-05 20:33:58'),
(26, 1, 'system', 'Payment completed: Order #90 - Africano - Afrobeats & Latino (TSH 5,500)', '2025-11-05 20:33:58'),
(27, 4, 'system', 'Order completed for . Your download is ready!', '2025-11-27 07:58:26'),
(28, 1, 'system', 'Order completed: Order #92 - ', '2025-11-27 07:58:26'),
(29, 4, 'system', 'Payment completed for . Your download is ready!', '2025-11-27 07:58:26'),
(30, 1, 'system', 'Payment completed: Order #92 -  (TSH 1,000)', '2025-11-27 07:58:26'),
(31, 4, 'system', 'Order completed for . Your download is ready!', '2025-11-27 07:58:36'),
(32, 1, 'system', 'Order completed: Order #91 - ', '2025-11-27 07:58:36'),
(33, 4, 'system', 'Payment completed for . Your download is ready!', '2025-11-27 07:58:36'),
(34, 1, 'system', 'Payment completed: Order #91 -  (TSH 20,000)', '2025-11-27 07:58:36'),
(35, 35, 'system', 'Order failed for Unknown course. Please try again or contact support.', '2025-11-27 07:58:47'),
(36, 1, 'system', 'Order failed: Order #89 - Unknown course', '2025-11-27 07:58:47'),
(37, 35, 'system', 'Payment failed for Unknown course. Please try again or contact support.', '2025-11-27 07:58:47'),
(38, 1, 'system', 'Payment failed: Order #89 - Unknown course', '2025-11-27 07:58:47'),
(39, 4, 'system', 'Order completed for . Your download is ready!', '2025-11-27 08:03:39'),
(40, 1, 'system', 'Order completed: Order #93 - ', '2025-11-27 08:03:39'),
(41, 4, 'system', 'Payment completed for . Your download is ready!', '2025-11-27 08:03:39'),
(42, 1, 'system', 'Payment completed: Order #93 -  (TSH 1,000)', '2025-11-27 08:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled','reversed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `callback_token` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callback_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_course` (`course_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_callback_token` (`callback_token`)
) ENGINE=MyISAM AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `course_id`, `status`, `created_at`, `updated_at`, `callback_token`, `callback_used_at`) VALUES
(1, 1, 1, 'completed', '2025-09-28 05:51:22', '2025-09-28 09:19:13', NULL, NULL),
(2, 2, 1, 'pending', '2025-09-28 06:25:01', '2025-09-28 09:19:13', NULL, NULL),
(3, 2, 1, 'pending', '2025-09-28 06:36:38', '2025-09-28 09:19:13', NULL, NULL),
(4, 3, 1, 'pending', '2025-09-28 09:29:53', '2025-09-28 09:53:09', NULL, NULL),
(5, 4, 1, 'completed', '2025-09-28 09:53:27', '2025-09-29 03:05:18', NULL, NULL),
(6, 2, 1, 'pending', '2025-09-29 02:58:07', '2025-09-29 03:06:05', NULL, NULL),
(7, 3, 1, 'pending', '2025-09-29 13:45:59', '2025-09-29 13:53:10', NULL, NULL),
(8, 4, 1, 'pending', '2025-09-29 13:53:24', '2025-09-29 13:58:47', NULL, NULL),
(9, 5, 1, 'completed', '2025-09-29 15:47:58', '2025-09-29 15:50:00', NULL, NULL),
(10, 2, 21, 'completed', '2025-09-29 17:18:47', '2025-09-29 17:21:20', NULL, NULL),
(11, 4, 62, 'pending', '2025-09-29 17:29:30', '2025-09-29 17:29:46', NULL, NULL),
(12, 4, 72, 'pending', '2025-09-29 17:31:21', '2025-09-29 17:31:21', NULL, NULL),
(13, 6, 69, 'pending', '2025-09-29 17:49:39', '2025-09-29 17:50:02', NULL, NULL),
(14, 7, 67, 'pending', '2025-09-29 18:21:43', '2025-09-29 18:22:03', NULL, NULL),
(15, 8, 60, 'pending', '2025-09-29 18:22:38', '2025-09-29 18:24:56', NULL, NULL),
(16, 5, 72, 'pending', '2025-09-29 19:53:50', '2025-09-29 19:54:18', NULL, NULL),
(17, 3, 38, 'pending', '2025-09-29 20:33:32', '2025-09-29 20:34:28', NULL, NULL),
(18, 7, 70, 'pending', '2025-09-29 21:57:17', '2025-09-29 21:57:17', NULL, NULL),
(19, 7, 70, 'completed', '2025-09-29 21:57:18', '2025-10-02 05:31:16', NULL, NULL),
(20, 9, 69, 'completed', '2025-10-02 05:32:03', '2025-10-02 05:34:37', NULL, NULL),
(21, 10, 72, 'completed', '2025-10-02 06:05:16', '2025-10-02 06:05:16', NULL, NULL),
(23, 12, 72, 'completed', '2025-10-02 07:48:48', '2025-10-02 07:48:48', NULL, NULL),
(24, 12, 73, 'pending', '2025-10-02 07:50:39', '2025-10-02 07:50:58', NULL, NULL),
(25, 12, 68, 'pending', '2025-10-02 08:31:43', '2025-10-02 08:32:10', NULL, NULL),
(26, 9, 72, 'completed', '2025-10-02 09:17:31', '2025-10-02 09:17:31', NULL, NULL),
(27, 9, 67, 'pending', '2025-10-02 09:18:08', '2025-10-02 10:14:58', NULL, NULL),
(28, 14, 72, 'completed', '2025-10-02 12:41:36', '2025-10-02 12:41:36', NULL, NULL),
(29, 14, 70, 'pending', '2025-10-02 12:44:51', '2025-10-07 18:11:22', NULL, NULL),
(30, 9, 73, 'completed', '2025-10-02 15:01:54', '2025-10-02 15:01:54', NULL, NULL),
(31, 2, 66, 'pending', '2025-10-07 18:01:54', '2025-10-07 18:01:54', NULL, NULL),
(32, 17, 72, 'completed', '2025-10-07 18:14:47', '2025-10-07 18:14:47', NULL, NULL),
(33, 17, 66, 'pending', '2025-10-08 11:55:35', '2025-10-08 11:55:41', NULL, NULL),
(34, 18, 41, 'pending', '2025-10-10 06:54:59', '2025-10-10 14:20:24', NULL, NULL),
(35, 18, 13, 'pending', '2025-10-10 13:56:41', '2025-10-10 13:56:41', NULL, NULL),
(36, 18, 13, 'pending', '2025-10-10 13:57:24', '2025-10-10 13:57:24', NULL, NULL),
(37, 18, 13, 'completed', '2025-10-10 13:57:52', '2025-10-10 14:00:58', NULL, NULL),
(38, 18, 39, 'pending', '2025-10-10 14:22:53', '2025-10-10 14:22:59', NULL, NULL),
(39, 18, 41, 'pending', '2025-10-10 14:25:58', '2025-10-10 14:26:05', NULL, NULL),
(40, 18, 40, 'pending', '2025-10-10 14:26:53', '2025-10-10 14:27:00', NULL, NULL),
(41, 18, 39, 'pending', '2025-10-10 14:28:33', '2025-10-10 14:29:17', NULL, NULL),
(42, 18, 39, 'completed', '2025-10-10 14:32:42', '2025-10-10 14:33:37', NULL, NULL),
(43, 18, 41, 'pending', '2025-10-10 14:44:49', '2025-10-10 14:44:56', NULL, NULL),
(44, 18, 41, 'completed', '2025-10-10 15:18:16', '2025-10-10 15:18:16', NULL, NULL),
(45, 18, 41, 'completed', '2025-10-10 15:18:29', '2025-10-10 15:18:29', NULL, NULL),
(46, 18, 42, 'pending', '2025-10-10 15:21:55', '2025-10-11 09:59:26', NULL, NULL),
(47, 19, 140, 'completed', '2025-10-10 15:31:08', '2025-10-10 15:37:26', NULL, NULL),
(48, 19, 139, 'pending', '2025-10-10 15:40:55', '2025-10-10 15:41:00', NULL, NULL),
(49, 19, 139, 'pending', '2025-10-10 17:14:44', '2025-10-10 17:14:51', NULL, NULL),
(50, 19, 139, 'pending', '2025-10-10 17:19:50', '2025-10-10 17:19:56', NULL, NULL),
(51, 19, 138, 'pending', '2025-10-11 12:25:56', '2025-10-11 13:26:05', NULL, NULL),
(52, 20, 63, 'pending', '2025-10-11 18:05:09', '2025-10-14 11:30:54', NULL, NULL),
(53, 18, 142, 'pending', '2025-10-16 13:43:32', '2025-10-16 13:43:39', NULL, NULL),
(54, 18, 142, 'completed', '2025-10-16 13:46:16', '2025-10-16 14:00:40', NULL, NULL),
(55, 21, 145, 'pending', '2025-10-20 07:37:59', '2025-10-20 07:38:09', NULL, NULL),
(56, 19, 31, 'pending', '2025-10-21 18:47:37', '2025-10-21 18:47:44', NULL, NULL),
(57, 22, 31, 'completed', '2025-10-22 22:26:51', '2025-10-24 02:06:41', NULL, NULL),
(58, 23, 31, 'pending', '2025-10-22 23:34:22', '2025-10-22 23:34:30', NULL, NULL),
(59, 24, 31, 'pending', '2025-10-22 23:37:00', '2025-10-22 23:37:10', NULL, NULL),
(60, 25, 31, 'pending', '2025-10-23 00:05:34', '2025-10-23 00:05:46', NULL, NULL),
(61, 24, 31, 'pending', '2025-10-23 05:27:06', '2025-10-23 05:27:13', NULL, NULL),
(62, 24, 72, 'completed', '2025-10-23 05:32:08', '2025-10-23 05:32:08', NULL, NULL),
(63, 26, 31, 'pending', '2025-10-23 07:50:19', '2025-10-23 07:50:27', NULL, NULL),
(64, 27, 31, 'completed', '2025-10-23 09:19:20', '2025-10-23 12:18:05', NULL, NULL),
(65, 28, 31, 'pending', '2025-10-23 09:19:38', '2025-10-23 09:19:45', NULL, NULL),
(66, 29, 72, 'completed', '2025-10-23 14:09:50', '2025-10-23 14:09:50', NULL, NULL),
(67, 29, 144, 'completed', '2025-10-23 14:12:09', '2025-10-24 21:42:18', NULL, NULL),
(68, 30, 60, 'pending', '2025-10-23 17:37:59', '2025-10-23 17:38:08', NULL, NULL),
(69, 26, 31, 'pending', '2025-10-25 15:49:14', '2025-10-25 18:49:36', NULL, NULL),
(70, 26, 31, 'completed', '2025-10-25 19:03:56', '2025-10-25 19:25:50', NULL, NULL),
(71, 15, 135, 'pending', '2025-10-26 22:36:38', '2025-11-04 12:30:35', NULL, NULL),
(72, 15, 72, 'completed', '2025-10-26 22:37:10', '2025-10-26 22:37:10', NULL, NULL),
(73, 15, 72, 'completed', '2025-10-26 22:38:48', '2025-10-26 22:38:48', NULL, NULL),
(74, 15, 139, 'completed', '2025-10-26 22:41:31', '2025-10-26 22:41:38', NULL, NULL),
(75, 2, 138, 'completed', '2025-10-27 03:44:34', '2025-10-27 03:44:41', NULL, NULL),
(76, 2, 142, 'completed', '2025-10-27 04:11:01', '2025-10-27 04:11:08', NULL, NULL),
(77, 31, 144, 'completed', '2025-10-27 04:16:28', '2025-10-27 04:16:38', 'f906c2ca2b07e555434e8d0379e8ba80', '2025-10-27 01:16:38'),
(78, 31, 139, 'completed', '2025-10-27 04:25:42', '2025-10-27 04:25:52', '09fb7f9daa9dcc054ab0b1d0c0468af8', '2025-10-27 01:25:52'),
(79, 31, 51, 'pending', '2025-10-27 04:36:23', '2025-10-27 04:43:01', NULL, NULL),
(80, 31, 138, 'pending', '2025-10-27 04:43:37', '2025-10-27 04:44:39', NULL, NULL),
(81, 2, 145, 'pending', '2025-10-27 10:15:42', '2025-10-27 10:16:17', NULL, NULL),
(82, 2, 50, 'pending', '2025-10-27 16:48:45', '2025-10-27 16:49:59', NULL, NULL),
(83, 32, 13, 'pending', '2025-10-28 05:47:19', '2025-10-28 05:47:26', NULL, NULL),
(84, 33, 31, 'pending', '2025-10-28 13:56:12', '2025-10-28 13:56:12', NULL, NULL),
(85, 33, 31, 'pending', '2025-10-28 13:56:14', '2025-10-28 13:56:14', NULL, NULL),
(86, 33, 31, 'pending', '2025-10-28 13:56:16', '2025-10-28 13:56:24', NULL, NULL),
(87, 34, 31, 'pending', '2025-10-28 14:22:12', '2025-10-28 14:22:21', NULL, NULL),
(88, 28, 31, 'completed', '2025-10-28 18:38:10', '2025-10-28 18:40:19', 'c0e636917a22623efffa358570d347c3', NULL),
(89, 35, 31, 'failed', '2025-11-05 06:51:48', '2025-11-27 10:58:47', NULL, NULL),
(90, 37, 68, 'completed', '2025-11-05 21:28:09', '2025-11-05 21:33:58', NULL, NULL),
(91, 4, 1, 'completed', '2025-11-27 06:05:36', '2025-11-27 10:58:36', NULL, NULL),
(92, 4, 1, 'completed', '2025-11-27 10:56:17', '2025-11-27 10:58:26', NULL, NULL),
(93, 4, 1, 'completed', '2025-11-27 11:02:48', '2025-11-27 11:03:39', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

DROP TABLE IF EXISTS `otps`;
CREATE TABLE IF NOT EXISTS `otps` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `phone` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `phone` (`phone`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled','reversed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `order_tracking_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmation_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_code` int DEFAULT NULL,
  `gateway_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_raw` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payments_order` (`order_id`),
  KEY `idx_payments_status` (`status`),
  KEY `idx_payments_tracking` (`order_tracking_id`)
) ENGINE=MyISAM AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `status`, `order_tracking_id`, `confirmation_code`, `payment_method`, `payment_account`, `currency`, `status_code`, `gateway_message`, `gateway_raw`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, 50.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-28 05:51:22', '2025-09-28 09:19:13'),
(2, 2, 50.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-28 06:25:01', '2025-09-28 09:19:13'),
(3, 3, 1000.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-28 06:36:38', '2025-09-28 09:19:13'),
(4, 4, 1000.00, 'pending', 'e9cc9742-54db-4897-b82b-db46d43ed910', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-28T09:33:42.877\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e9cc9742-54db-4897-b82b-db46d43ed910\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e9cc9742-54db-4897-b82b-db46d43ed910&OrderMerchantReference=ORD4\",\"status_code\":0,\"merchant_reference\":\"ORD4\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e9cc9742-54db-4897-b82b-db46d43ed910&OrderMerchantReference=ORD4\"},\"status\":\"500\"}', NULL, '2025-09-28 09:29:53', '2025-09-28 09:53:09'),
(5, 5, 1000.00, 'pending', '704eecdc-1a10-47af-81dc-db46a22ee021', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-28T09:53:27.853\",\"confirmation_code\":\"\",\"order_tracking_id\":\"704eecdc-1a10-47af-81dc-db46a22ee021\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23studiokits\\/callback.php?OrderTrackingId=704eecdc-1a10-47af-81dc-db46a22ee021&OrderMerchantReference=ORD5\",\"status_code\":0,\"merchant_reference\":\"ORD5\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23studiokits\\/callback.php?OrderTrackingId=704eecdc-1a10-47af-81dc-db46a22ee021&OrderMerchantReference=ORD5\"},\"status\":\"500\"}', NULL, '2025-09-28 09:53:27', '2025-09-28 10:01:06'),
(6, 6, 1000.00, 'pending', 'f8d7d32f-5178-44be-ab13-db46bd2caf1b', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-28T10:17:37.333\",\"confirmation_code\":\"\",\"order_tracking_id\":\"f8d7d32f-5178-44be-ab13-db46bd2caf1b\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=f8d7d32f-5178-44be-ab13-db46bd2caf1b&OrderMerchantReference=ORD6\",\"status_code\":0,\"merchant_reference\":\"ORD6\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=f8d7d32f-5178-44be-ab13-db46bd2caf1b&OrderMerchantReference=ORD6\"},\"status\":\"500\"}', NULL, '2025-09-29 02:58:07', '2025-09-29 03:06:05'),
(7, 7, 1000.00, 'pending', 'fdc9a505-9ac5-4122-9493-db4606f44416', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-28T10:20:44.387\",\"confirmation_code\":\"\",\"order_tracking_id\":\"fdc9a505-9ac5-4122-9493-db4606f44416\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=fdc9a505-9ac5-4122-9493-db4606f44416&OrderMerchantReference=ORD7\",\"status_code\":0,\"merchant_reference\":\"ORD7\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=fdc9a505-9ac5-4122-9493-db4606f44416&OrderMerchantReference=ORD7\"},\"status\":\"500\"}', NULL, '2025-09-29 13:45:59', '2025-09-29 13:53:10'),
(8, 8, 1000.00, 'pending', '78fb7a99-9262-460e-a0a8-db45835a7aa5', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-29T13:56:00.243\",\"confirmation_code\":\"\",\"order_tracking_id\":\"78fb7a99-9262-460e-a0a8-db45835a7aa5\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=78fb7a99-9262-460e-a0a8-db45835a7aa5&OrderMerchantReference=ORD8-7248b2\",\"status_code\":0,\"merchant_reference\":\"ORD8-7248b2\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=78fb7a99-9262-460e-a0a8-db45835a7aa5&OrderMerchantReference=ORD8-7248b2\"},\"status\":\"500\"}', NULL, '2025-09-29 13:53:24', '2025-09-29 13:58:47'),
(9, 9, 1000.00, 'completed', '17d686c7-24fe-4592-a64b-db456194b856', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-29T15:43:47.423\",\"confirmation_code\":\"\",\"order_tracking_id\":\"17d686c7-24fe-4592-a64b-db456194b856\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=17d686c7-24fe-4592-a64b-db456194b856&OrderMerchantReference=ORD9\",\"status_code\":0,\"merchant_reference\":\"ORD9\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=17d686c7-24fe-4592-a64b-db456194b856&OrderMerchantReference=ORD9\"},\"status\":\"500\"}', NULL, '2025-09-29 15:47:58', '2025-09-29 15:50:25'),
(10, 10, 4300.00, 'completed', '3c6dd454-cb6c-40a5-838f-db452451383e', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4300,\"created_date\":\"2025-09-29T17:18:48.103\",\"confirmation_code\":\"\",\"order_tracking_id\":\"3c6dd454-cb6c-40a5-838f-db452451383e\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=3c6dd454-cb6c-40a5-838f-db452451383e&OrderMerchantReference=ORD10\",\"status_code\":0,\"merchant_reference\":\"ORD10\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=3c6dd454-cb6c-40a5-838f-db452451383e&OrderMerchantReference=ORD10\"},\"status\":\"500\"}', NULL, '2025-09-29 17:18:47', '2025-09-29 17:21:39'),
(11, 11, 4200.00, 'pending', '1f183bcf-3d01-49fa-bad8-db455fd38293', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4200,\"created_date\":\"2025-09-29T17:29:31.737\",\"confirmation_code\":\"\",\"order_tracking_id\":\"1f183bcf-3d01-49fa-bad8-db455fd38293\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=1f183bcf-3d01-49fa-bad8-db455fd38293&OrderMerchantReference=ORD11\",\"status_code\":0,\"merchant_reference\":\"ORD11\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=1f183bcf-3d01-49fa-bad8-db455fd38293&OrderMerchantReference=ORD11\"},\"status\":\"500\"}', NULL, '2025-09-29 17:29:30', '2025-09-29 17:29:46'),
(12, 12, 4200.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-29 17:31:21', '2025-09-29 17:31:21'),
(13, 13, 4600.00, 'pending', 'e1ddcfc2-b1dc-4a0f-a39f-db45139b25ac', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4600,\"created_date\":\"2025-09-29T17:49:39.72\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e1ddcfc2-b1dc-4a0f-a39f-db45139b25ac\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=e1ddcfc2-b1dc-4a0f-a39f-db45139b25ac&OrderMerchantReference=ORD13\",\"status_code\":0,\"merchant_reference\":\"ORD13\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=e1ddcfc2-b1dc-4a0f-a39f-db45139b25ac&OrderMerchantReference=ORD13\"},\"status\":\"500\"}', NULL, '2025-09-29 17:49:39', '2025-09-29 17:50:02'),
(14, 14, 3200.00, 'pending', '7f827682-665f-4348-9366-db45e487b96f', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3200,\"created_date\":\"2025-09-29T18:21:44.973\",\"confirmation_code\":\"\",\"order_tracking_id\":\"7f827682-665f-4348-9366-db45e487b96f\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=7f827682-665f-4348-9366-db45e487b96f&OrderMerchantReference=ORD14\",\"status_code\":0,\"merchant_reference\":\"ORD14\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=7f827682-665f-4348-9366-db45e487b96f&OrderMerchantReference=ORD14\"},\"status\":\"500\"}', NULL, '2025-09-29 18:21:43', '2025-09-29 18:22:03'),
(15, 15, 1000.00, 'pending', '9ddcb001-520f-4f65-8525-db455627eb91', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-09-29T18:22:38.627\",\"confirmation_code\":\"\",\"order_tracking_id\":\"9ddcb001-520f-4f65-8525-db455627eb91\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=9ddcb001-520f-4f65-8525-db455627eb91&OrderMerchantReference=ORD15\",\"status_code\":0,\"merchant_reference\":\"ORD15\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=9ddcb001-520f-4f65-8525-db455627eb91&OrderMerchantReference=ORD15\"},\"status\":\"500\"}', NULL, '2025-09-29 18:22:38', '2025-09-29 18:24:56'),
(16, 16, 4200.00, 'pending', '1967e28f-ad4e-4b9d-8bc1-db457a620edf', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4200,\"created_date\":\"2025-09-29T19:53:51.797\",\"confirmation_code\":\"\",\"order_tracking_id\":\"1967e28f-ad4e-4b9d-8bc1-db457a620edf\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=1967e28f-ad4e-4b9d-8bc1-db457a620edf&OrderMerchantReference=ORD16\",\"status_code\":0,\"merchant_reference\":\"ORD16\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=1967e28f-ad4e-4b9d-8bc1-db457a620edf&OrderMerchantReference=ORD16\"},\"status\":\"500\"}', NULL, '2025-09-29 19:53:50', '2025-09-29 19:54:18'),
(17, 17, 4200.00, 'pending', 'd97b0069-1067-4833-bb13-db4569e36dd4', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5400,\"created_date\":\"2025-09-29T20:32:14.587\",\"confirmation_code\":\"\",\"order_tracking_id\":\"d97b0069-1067-4833-bb13-db4569e36dd4\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=d97b0069-1067-4833-bb13-db4569e36dd4&OrderMerchantReference=ORD17\",\"status_code\":0,\"merchant_reference\":\"ORD17\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=d97b0069-1067-4833-bb13-db4569e36dd4&OrderMerchantReference=ORD17\"},\"status\":\"500\"}', NULL, '2025-09-29 20:33:32', '2025-09-29 20:34:28'),
(18, 18, 1800.00, 'pending', '6d815984-51a0-49af-9de7-db45c12360c4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-29 21:57:17', '2025-09-29 21:57:19'),
(19, 19, 1800.00, 'completed', '4b34bc9a-2ac6-4808-8d2c-db4529237c4b', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1800,\"created_date\":\"2025-09-29T21:57:19.243\",\"confirmation_code\":\"\",\"order_tracking_id\":\"4b34bc9a-2ac6-4808-8d2c-db4529237c4b\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=4b34bc9a-2ac6-4808-8d2c-db4529237c4b&OrderMerchantReference=ORD19\",\"status_code\":0,\"merchant_reference\":\"ORD19\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=4b34bc9a-2ac6-4808-8d2c-db4529237c4b&OrderMerchantReference=ORD19\"},\"status\":\"500\"}', '2025-10-02 05:31:16', '2025-09-29 21:57:18', '2025-10-02 05:31:16'),
(20, 20, 4600.00, 'completed', '0fc5b956-f11f-4a9e-b19f-db429974a94a', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4600,\"created_date\":\"2025-10-02T05:32:05.283\",\"confirmation_code\":\"\",\"order_tracking_id\":\"0fc5b956-f11f-4a9e-b19f-db429974a94a\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=0fc5b956-f11f-4a9e-b19f-db429974a94a&OrderMerchantReference=ORD20\",\"status_code\":0,\"merchant_reference\":\"ORD20\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=0fc5b956-f11f-4a9e-b19f-db429974a94a&OrderMerchantReference=ORD20\"},\"status\":\"500\"}', '2025-10-02 05:34:37', '2025-10-02 05:32:03', '2025-10-02 05:34:37'),
(21, 21, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 06:05:16', '2025-10-02 06:05:16', '2025-10-02 06:05:16'),
(22, 22, 1000.00, 'pending', 'f1b62330-658c-495d-9e0a-db42bfefc8e5', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-10-02T06:28:20.81\",\"confirmation_code\":\"\",\"order_tracking_id\":\"f1b62330-658c-495d-9e0a-db42bfefc8e5\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=f1b62330-658c-495d-9e0a-db42bfefc8e5&OrderMerchantReference=ORD22\",\"status_code\":0,\"merchant_reference\":\"ORD22\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=f1b62330-658c-495d-9e0a-db42bfefc8e5&OrderMerchantReference=ORD22\"},\"status\":\"500\"}', NULL, '2025-10-02 06:28:18', '2025-10-02 06:28:33'),
(23, 23, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 07:48:48', '2025-10-02 07:48:48', '2025-10-02 07:48:48'),
(24, 24, 1000.00, 'pending', '86e4ee17-a040-448e-bae7-db42d7e95b35', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-10-02T07:50:41.677\",\"confirmation_code\":\"\",\"order_tracking_id\":\"86e4ee17-a040-448e-bae7-db42d7e95b35\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=86e4ee17-a040-448e-bae7-db42d7e95b35&OrderMerchantReference=ORD24\",\"status_code\":0,\"merchant_reference\":\"ORD24\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=86e4ee17-a040-448e-bae7-db42d7e95b35&OrderMerchantReference=ORD24\"},\"status\":\"500\"}', NULL, '2025-10-02 07:50:39', '2025-10-02 07:50:58'),
(25, 25, 5500.00, 'pending', '5c205f1a-a72e-44a6-9a1c-db421d7a2b71', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5500,\"created_date\":\"2025-10-02T08:31:45.877\",\"confirmation_code\":\"\",\"order_tracking_id\":\"5c205f1a-a72e-44a6-9a1c-db421d7a2b71\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=5c205f1a-a72e-44a6-9a1c-db421d7a2b71&OrderMerchantReference=ORD25\",\"status_code\":0,\"merchant_reference\":\"ORD25\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=5c205f1a-a72e-44a6-9a1c-db421d7a2b71&OrderMerchantReference=ORD25\"},\"status\":\"500\"}', NULL, '2025-10-02 08:31:43', '2025-10-02 08:32:10'),
(26, 26, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 09:17:31', '2025-10-02 09:17:31', '2025-10-02 09:17:31'),
(27, 27, 3200.00, 'pending', 'e160ac0e-219b-4891-8334-db42a35a12d6', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3200,\"created_date\":\"2025-10-02T09:18:11.93\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e160ac0e-219b-4891-8334-db42a35a12d6\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=e160ac0e-219b-4891-8334-db42a35a12d6&OrderMerchantReference=ORD27\",\"status_code\":0,\"merchant_reference\":\"ORD27\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=e160ac0e-219b-4891-8334-db42a35a12d6&OrderMerchantReference=ORD27\"},\"status\":\"500\"}', NULL, '2025-10-02 09:18:08', '2025-10-02 10:14:58'),
(28, 28, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 12:41:36', '2025-10-02 12:41:36', '2025-10-02 12:41:36'),
(29, 29, 1800.00, 'pending', 'c68bd1dc-9a67-418b-8a12-db4268db94c0', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1800,\"created_date\":\"2025-10-02T12:44:53.957\",\"confirmation_code\":\"\",\"order_tracking_id\":\"c68bd1dc-9a67-418b-8a12-db4268db94c0\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=c68bd1dc-9a67-418b-8a12-db4268db94c0&OrderMerchantReference=ORD29\",\"status_code\":0,\"merchant_reference\":\"ORD29\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=c68bd1dc-9a67-418b-8a12-db4268db94c0&OrderMerchantReference=ORD29\"},\"status\":\"500\"}', NULL, '2025-10-02 12:44:51', '2025-10-07 18:11:22'),
(30, 30, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 15:01:54', '2025-10-02 15:01:54', '2025-10-02 15:01:54'),
(31, 31, 1500.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-07 18:01:54', '2025-10-07 18:01:54'),
(32, 32, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-07 18:14:47', '2025-10-07 18:14:47', '2025-10-07 18:14:47'),
(33, 33, 1500.00, 'pending', '396de957-8547-4be1-9e56-db3c78e59a17', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5400,\"created_date\":\"2025-10-08T05:27:14.26\",\"confirmation_code\":\"\",\"order_tracking_id\":\"396de957-8547-4be1-9e56-db3c78e59a17\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/ak23studiokit\\/callback.php?OrderTrackingId=396de957-8547-4be1-9e56-db3c78e59a17&OrderMerchantReference=ORD33\",\"status_code\":0,\"merchant_reference\":\"ORD33\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/ak23studiokit\\/callback.php?OrderTrackingId=396de957-8547-4be1-9e56-db3c78e59a17&OrderMerchantReference=ORD33\"},\"status\":\"500\"}', NULL, '2025-10-08 11:55:35', '2025-10-08 11:55:41'),
(34, 34, 10.00, 'pending', '79cfbbd1-1f14-49c2-b823-db425abc879c', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":10,\"created_date\":\"2025-10-02T19:10:34.73\",\"confirmation_code\":\"\",\"order_tracking_id\":\"79cfbbd1-1f14-49c2-b823-db425abc879c\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=79cfbbd1-1f14-49c2-b823-db425abc879c&OrderMerchantReference=ORD34\",\"status_code\":0,\"merchant_reference\":\"ORD34\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=79cfbbd1-1f14-49c2-b823-db425abc879c&OrderMerchantReference=ORD34\"},\"status\":\"500\"}', NULL, '2025-10-10 06:54:59', '2025-10-10 14:20:24'),
(35, 35, 4500.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 13:56:41', '2025-10-10 13:56:41'),
(36, 36, 4500.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 13:57:24', '2025-10-10 13:57:24'),
(37, 37, 4500.00, 'completed', 'db55503b-c1f6-4b0f-9209-db4123ab0bee', 'MP251010.1500.T39255', 'AIRTELTZ', '782602842', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"AIRTELTZ\",\"amount\":3900,\"created_date\":\"2025-10-10T15:00:44.333\",\"confirmation_code\":\"MP251010.1500.T39255\",\"order_tracking_id\":\"db55503b-c1f6-4b0f-9209-db4123ab0bee\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"782602842\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=db55503b-c1f6-4b0f-9209-db4123ab0bee&OrderMerchantReference=ORD37\",\"status_code\":1,\"merchant_reference\":\"ORD37\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-10 15:00:44', '2025-10-10 13:57:52', '2025-10-10 14:00:58'),
(38, 38, 1700.00, 'pending', '608eca2d-11ba-4c22-87b8-db41aa237a9f', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":9800,\"created_date\":\"2025-10-03T08:29:21.467\",\"confirmation_code\":\"\",\"order_tracking_id\":\"608eca2d-11ba-4c22-87b8-db41aa237a9f\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=608eca2d-11ba-4c22-87b8-db41aa237a9f&OrderMerchantReference=ORD38\",\"status_code\":0,\"merchant_reference\":\"ORD38\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=608eca2d-11ba-4c22-87b8-db41aa237a9f&OrderMerchantReference=ORD38\"},\"status\":\"500\"}', NULL, '2025-10-10 14:22:53', '2025-10-10 14:22:59'),
(39, 39, 10.00, 'pending', 'ab3fca90-2a2b-4d63-aeb0-db3f822495e5', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1500,\"created_date\":\"2025-10-05T16:03:18.147\",\"confirmation_code\":\"\",\"order_tracking_id\":\"ab3fca90-2a2b-4d63-aeb0-db3f822495e5\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=ab3fca90-2a2b-4d63-aeb0-db3f822495e5&OrderMerchantReference=ORD39\",\"status_code\":0,\"merchant_reference\":\"ORD39\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/www.akdownloads.com\\/callback.php?OrderTrackingId=ab3fca90-2a2b-4d63-aeb0-db3f822495e5&OrderMerchantReference=ORD39\"},\"status\":\"500\"}', NULL, '2025-10-10 14:25:58', '2025-10-10 14:26:05'),
(40, 40, 5800.00, 'pending', '69c0728e-a0fa-4945-8684-db3afbca36e9', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5800,\"created_date\":\"2025-10-10T15:26:55.083\",\"confirmation_code\":\"\",\"order_tracking_id\":\"69c0728e-a0fa-4945-8684-db3afbca36e9\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=69c0728e-a0fa-4945-8684-db3afbca36e9&OrderMerchantReference=ORD40\",\"status_code\":0,\"merchant_reference\":\"ORD40\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=69c0728e-a0fa-4945-8684-db3afbca36e9&OrderMerchantReference=ORD40\"},\"status\":\"500\"}', NULL, '2025-10-10 14:26:53', '2025-10-10 14:27:00'),
(41, 41, 1700.00, 'pending', 'fd37ab1c-49a9-413f-bafd-db3a72494252', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1700,\"created_date\":\"2025-10-10T15:28:35.253\",\"confirmation_code\":\"\",\"order_tracking_id\":\"fd37ab1c-49a9-413f-bafd-db3a72494252\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=fd37ab1c-49a9-413f-bafd-db3a72494252&OrderMerchantReference=ORD41\",\"status_code\":0,\"merchant_reference\":\"ORD41\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=fd37ab1c-49a9-413f-bafd-db3a72494252&OrderMerchantReference=ORD41\"},\"status\":\"500\"}', NULL, '2025-10-10 14:28:33', '2025-10-10 14:29:17'),
(42, 42, 1700.00, 'completed', 'f0d82e27-19c3-4764-a1e1-db3a960ffcf2', 'MP251010.1533.R44470', 'AIRTELTZ', '782602842', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"AIRTELTZ\",\"amount\":1700,\"created_date\":\"2025-10-10T15:33:22.383\",\"confirmation_code\":\"MP251010.1533.R44470\",\"order_tracking_id\":\"f0d82e27-19c3-4764-a1e1-db3a960ffcf2\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"782602842\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f0d82e27-19c3-4764-a1e1-db3a960ffcf2&OrderMerchantReference=ORD42\",\"status_code\":1,\"merchant_reference\":\"ORD42\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-10 15:33:22', '2025-10-10 14:32:42', '2025-10-10 14:33:37'),
(43, 43, 10.00, 'pending', 'a1bec726-1a31-48e4-87f9-db3a56695ef9', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":10,\"created_date\":\"2025-10-10T15:44:51.293\",\"confirmation_code\":\"\",\"order_tracking_id\":\"a1bec726-1a31-48e4-87f9-db3a56695ef9\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=a1bec726-1a31-48e4-87f9-db3a56695ef9&OrderMerchantReference=ORD43\",\"status_code\":0,\"merchant_reference\":\"ORD43\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=a1bec726-1a31-48e4-87f9-db3a56695ef9&OrderMerchantReference=ORD43\"},\"status\":\"500\"}', NULL, '2025-10-10 14:44:49', '2025-10-10 14:44:56'),
(44, 44, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 15:18:16', '2025-10-10 15:18:16', '2025-10-10 15:18:16'),
(45, 45, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 15:18:29', '2025-10-10 15:18:29', '2025-10-10 15:18:29'),
(46, 46, 3500.00, 'pending', '3bfa3739-c042-4a29-9170-db3a82ae1ce3', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3500,\"created_date\":\"2025-10-10T16:21:57.413\",\"confirmation_code\":\"\",\"order_tracking_id\":\"3bfa3739-c042-4a29-9170-db3a82ae1ce3\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=3bfa3739-c042-4a29-9170-db3a82ae1ce3&OrderMerchantReference=ORD46\",\"status_code\":0,\"merchant_reference\":\"ORD46\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=3bfa3739-c042-4a29-9170-db3a82ae1ce3&OrderMerchantReference=ORD46\"},\"status\":\"500\"}', NULL, '2025-10-10 15:21:55', '2025-10-11 09:59:26'),
(47, 47, 3800.00, 'completed', '4b1c97dc-d4ca-40ac-9f1c-db3ab6252b8d', '7601034274186034204082', 'Visa', '440353XXXXXX6150', 'TZS', 1, 'Transaction successfully processed.', '{\"payment_method\":\"Visa\",\"amount\":3800,\"created_date\":\"2025-10-10T16:37:09.29\",\"confirmation_code\":\"7601034274186034204082\",\"order_tracking_id\":\"4b1c97dc-d4ca-40ac-9f1c-db3ab6252b8d\",\"payment_status_description\":\"Completed\",\"description\":\"Transaction successfully processed.\",\"message\":\"Request processed successfully\",\"payment_account\":\"440353XXXXXX6150\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=4b1c97dc-d4ca-40ac-9f1c-db3ab6252b8d&OrderMerchantReference=ORD47\",\"status_code\":1,\"merchant_reference\":\"ORD47\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-10 16:37:09', '2025-10-10 15:31:08', '2025-10-10 15:37:26'),
(48, 48, 2500.00, 'pending', '9de2a881-9977-4d81-8077-db3ad9a07af5', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2500,\"created_date\":\"2025-10-10T16:40:56.47\",\"confirmation_code\":\"\",\"order_tracking_id\":\"9de2a881-9977-4d81-8077-db3ad9a07af5\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=9de2a881-9977-4d81-8077-db3ad9a07af5&OrderMerchantReference=ORD48\",\"status_code\":0,\"merchant_reference\":\"ORD48\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=9de2a881-9977-4d81-8077-db3ad9a07af5&OrderMerchantReference=ORD48\"},\"status\":\"500\"}', NULL, '2025-10-10 15:40:55', '2025-10-10 15:41:00'),
(49, 49, 2500.00, 'pending', '5b72e415-03e4-4d64-9c25-db3a04544787', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2500,\"created_date\":\"2025-10-10T18:14:46.33\",\"confirmation_code\":\"\",\"order_tracking_id\":\"5b72e415-03e4-4d64-9c25-db3a04544787\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=5b72e415-03e4-4d64-9c25-db3a04544787&OrderMerchantReference=ORD49\",\"status_code\":0,\"merchant_reference\":\"ORD49\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=5b72e415-03e4-4d64-9c25-db3a04544787&OrderMerchantReference=ORD49\"},\"status\":\"500\"}', NULL, '2025-10-10 17:14:44', '2025-10-10 17:14:51'),
(50, 50, 2500.00, 'pending', '13bdc68b-2685-4385-82ec-db3a2e136b64', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2500,\"created_date\":\"2025-10-10T18:19:51.833\",\"confirmation_code\":\"\",\"order_tracking_id\":\"13bdc68b-2685-4385-82ec-db3a2e136b64\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=13bdc68b-2685-4385-82ec-db3a2e136b64&OrderMerchantReference=ORD50\",\"status_code\":0,\"merchant_reference\":\"ORD50\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=13bdc68b-2685-4385-82ec-db3a2e136b64&OrderMerchantReference=ORD50\"},\"status\":\"500\"}', NULL, '2025-10-10 17:19:50', '2025-10-10 17:19:56'),
(51, 51, 4900.00, 'pending', 'e24ad7a3-0424-4ec2-9e96-db39f19fa2b0', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4900,\"created_date\":\"2025-10-11T13:25:58.373\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e24ad7a3-0424-4ec2-9e96-db39f19fa2b0\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e24ad7a3-0424-4ec2-9e96-db39f19fa2b0&OrderMerchantReference=ORD51\",\"status_code\":0,\"merchant_reference\":\"ORD51\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e24ad7a3-0424-4ec2-9e96-db39f19fa2b0&OrderMerchantReference=ORD51\"},\"status\":\"500\"}', NULL, '2025-10-11 12:25:56', '2025-10-11 13:26:05'),
(52, 52, 4900.00, 'pending', '239cd95a-2817-44e0-82a4-db3985c7f500', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4900,\"created_date\":\"2025-10-11T19:05:11.05\",\"confirmation_code\":\"\",\"order_tracking_id\":\"239cd95a-2817-44e0-82a4-db3985c7f500\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=239cd95a-2817-44e0-82a4-db3985c7f500&OrderMerchantReference=ORD52\",\"status_code\":0,\"merchant_reference\":\"ORD52\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=239cd95a-2817-44e0-82a4-db3985c7f500&OrderMerchantReference=ORD52\"},\"status\":\"500\"}', NULL, '2025-10-11 18:05:09', '2025-10-14 11:30:54'),
(53, 53, 5200.00, 'pending', 'f06426cf-3eff-4997-bca3-db34efb18025', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5200,\"created_date\":\"2025-10-16T14:43:34.383\",\"confirmation_code\":\"\",\"order_tracking_id\":\"f06426cf-3eff-4997-bca3-db34efb18025\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f06426cf-3eff-4997-bca3-db34efb18025&OrderMerchantReference=ORD53\",\"status_code\":0,\"merchant_reference\":\"ORD53\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f06426cf-3eff-4997-bca3-db34efb18025&OrderMerchantReference=ORD53\"},\"status\":\"500\"}', NULL, '2025-10-16 13:43:32', '2025-10-16 13:43:39'),
(54, 54, 5200.00, 'completed', '09f1d5a5-21a8-44dc-ac7d-db34a64053ed', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5200,\"created_date\":\"2025-10-16T14:46:18.913\",\"confirmation_code\":\"\",\"order_tracking_id\":\"09f1d5a5-21a8-44dc-ac7d-db34a64053ed\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=09f1d5a5-21a8-44dc-ac7d-db34a64053ed&OrderMerchantReference=ORD54\",\"status_code\":0,\"merchant_reference\":\"ORD54\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=09f1d5a5-21a8-44dc-ac7d-db34a64053ed&OrderMerchantReference=ORD54\"},\"status\":\"500\"}', '2025-10-16 14:00:41', '2025-10-16 13:46:16', '2025-10-16 14:00:41'),
(55, 55, 3500.00, 'pending', 'ca927307-ee10-41e7-8a30-db308dd6c9bd', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3500,\"created_date\":\"2025-10-20T08:38:02.773\",\"confirmation_code\":\"\",\"order_tracking_id\":\"ca927307-ee10-41e7-8a30-db308dd6c9bd\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=ca927307-ee10-41e7-8a30-db308dd6c9bd&OrderMerchantReference=ORD55\",\"status_code\":0,\"merchant_reference\":\"ORD55\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=ca927307-ee10-41e7-8a30-db308dd6c9bd&OrderMerchantReference=ORD55\"},\"status\":\"500\"}', NULL, '2025-10-20 07:37:59', '2025-10-20 07:38:09'),
(56, 56, 1900.00, 'pending', 'b102281a-92aa-4d38-93db-db2f32344e39', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-21T19:47:39.477\",\"confirmation_code\":\"\",\"order_tracking_id\":\"b102281a-92aa-4d38-93db-db2f32344e39\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=b102281a-92aa-4d38-93db-db2f32344e39&OrderMerchantReference=ORD56\",\"status_code\":0,\"merchant_reference\":\"ORD56\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=b102281a-92aa-4d38-93db-db2f32344e39&OrderMerchantReference=ORD56\"},\"status\":\"500\"}', NULL, '2025-10-21 18:47:37', '2025-10-21 18:47:44'),
(57, 57, 1900.00, 'completed', '9a500f1f-231a-443f-be6c-db2e46eb1e67', '25359014184806', 'TIGOTZ', '2556xxx52516', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"TIGOTZ\",\"amount\":1900,\"created_date\":\"2025-10-24T03:06:27.183\",\"confirmation_code\":\"25359014184806\",\"order_tracking_id\":\"9a500f1f-231a-443f-be6c-db2e46eb1e67\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"2556xxx52516\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=9a500f1f-231a-443f-be6c-db2e46eb1e67&OrderMerchantReference=ORD57\",\"status_code\":1,\"merchant_reference\":\"ORD57\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-24 03:06:27', '2025-10-22 22:26:51', '2025-10-24 02:06:41'),
(69, 69, 1900.00, 'pending', '90cbd441-dac1-45c7-a797-db2be6aad353', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-25T16:49:17.297\",\"confirmation_code\":\"\",\"order_tracking_id\":\"90cbd441-dac1-45c7-a797-db2be6aad353\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=90cbd441-dac1-45c7-a797-db2be6aad353&OrderMerchantReference=ORD69\",\"status_code\":0,\"merchant_reference\":\"ORD69\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=90cbd441-dac1-45c7-a797-db2be6aad353&OrderMerchantReference=ORD69\"},\"status\":\"500\"}', NULL, '2025-10-25 15:49:14', '2025-10-25 18:49:36'),
(58, 58, 1900.00, 'pending', '2829a56a-0e0a-41ea-ae3d-db2db9519802', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T00:34:25.507\",\"confirmation_code\":\"\",\"order_tracking_id\":\"2829a56a-0e0a-41ea-ae3d-db2db9519802\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=2829a56a-0e0a-41ea-ae3d-db2db9519802&OrderMerchantReference=ORD58\",\"status_code\":0,\"merchant_reference\":\"ORD58\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=2829a56a-0e0a-41ea-ae3d-db2db9519802&OrderMerchantReference=ORD58\"},\"status\":\"500\"}', NULL, '2025-10-22 23:34:22', '2025-10-22 23:34:30'),
(59, 59, 1900.00, 'pending', 'f1f7bb6f-6b21-4749-8138-db2d5557062f', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T00:37:03.09\",\"confirmation_code\":\"\",\"order_tracking_id\":\"f1f7bb6f-6b21-4749-8138-db2d5557062f\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f1f7bb6f-6b21-4749-8138-db2d5557062f&OrderMerchantReference=ORD59\",\"status_code\":0,\"merchant_reference\":\"ORD59\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f1f7bb6f-6b21-4749-8138-db2d5557062f&OrderMerchantReference=ORD59\"},\"status\":\"500\"}', NULL, '2025-10-22 23:37:00', '2025-10-22 23:37:10'),
(60, 60, 1900.00, 'pending', '9f0df149-2bbb-4351-8a88-db2d84a0c006', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T01:05:36.477\",\"confirmation_code\":\"\",\"order_tracking_id\":\"9f0df149-2bbb-4351-8a88-db2d84a0c006\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=9f0df149-2bbb-4351-8a88-db2d84a0c006&OrderMerchantReference=ORD60\",\"status_code\":0,\"merchant_reference\":\"ORD60\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=9f0df149-2bbb-4351-8a88-db2d84a0c006&OrderMerchantReference=ORD60\"},\"status\":\"500\"}', NULL, '2025-10-23 00:05:34', '2025-10-23 00:05:46');
INSERT INTO `payments` (`id`, `order_id`, `amount`, `status`, `order_tracking_id`, `confirmation_code`, `payment_method`, `payment_account`, `currency`, `status_code`, `gateway_message`, `gateway_raw`, `paid_at`, `created_at`, `updated_at`) VALUES
(61, 61, 1900.00, 'pending', '188658f5-053d-48e8-a137-db2dd2ff5c20', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T06:27:08.347\",\"confirmation_code\":\"\",\"order_tracking_id\":\"188658f5-053d-48e8-a137-db2dd2ff5c20\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=188658f5-053d-48e8-a137-db2dd2ff5c20&OrderMerchantReference=ORD61\",\"status_code\":0,\"merchant_reference\":\"ORD61\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=188658f5-053d-48e8-a137-db2dd2ff5c20&OrderMerchantReference=ORD61\"},\"status\":\"500\"}', NULL, '2025-10-23 05:27:06', '2025-10-23 05:27:13'),
(62, 62, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-23 05:32:08', '2025-10-23 05:32:08', '2025-10-23 05:32:08'),
(63, 63, 1900.00, 'pending', 'd37aecef-e639-4948-9cf5-db2d24f2cacb', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T08:50:21.707\",\"confirmation_code\":\"\",\"order_tracking_id\":\"d37aecef-e639-4948-9cf5-db2d24f2cacb\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=d37aecef-e639-4948-9cf5-db2d24f2cacb&OrderMerchantReference=ORD63\",\"status_code\":0,\"merchant_reference\":\"ORD63\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=d37aecef-e639-4948-9cf5-db2d24f2cacb&OrderMerchantReference=ORD63\"},\"status\":\"500\"}', NULL, '2025-10-23 07:50:19', '2025-10-23 07:50:27'),
(64, 64, 1900.00, 'completed', '46a9273c-8ed7-4438-9bec-db2de6e03967', '25304096474385', 'TIGOTZ', '2557xxx03474', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"TIGOTZ\",\"amount\":1900,\"created_date\":\"2025-10-23T10:20:07.56\",\"confirmation_code\":\"25304096474385\",\"order_tracking_id\":\"46a9273c-8ed7-4438-9bec-db2de6e03967\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"2557xxx03474\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=46a9273c-8ed7-4438-9bec-db2de6e03967&OrderMerchantReference=ORD64\",\"status_code\":1,\"merchant_reference\":\"ORD64\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-23 10:20:07', '2025-10-23 09:19:20', '2025-10-23 12:18:05'),
(66, 66, 0.00, 'completed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-23 14:09:50', '2025-10-23 14:09:50', '2025-10-23 14:09:50'),
(67, 67, 3200.00, 'completed', 'a272f1e0-ef18-4d9a-9bbe-db2d5b1a6e02', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3200,\"created_date\":\"2025-10-23T15:14:59.717\",\"confirmation_code\":\"\",\"order_tracking_id\":\"a272f1e0-ef18-4d9a-9bbe-db2d5b1a6e02\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=a272f1e0-ef18-4d9a-9bbe-db2d5b1a6e02&OrderMerchantReference=ORD67\",\"status_code\":0,\"merchant_reference\":\"ORD67\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=a272f1e0-ef18-4d9a-9bbe-db2d5b1a6e02&OrderMerchantReference=ORD67\"},\"status\":\"500\"}', '2025-10-24 21:42:18', '2025-10-23 14:12:09', '2025-10-24 21:42:18'),
(65, 65, 1900.00, 'pending', 'fcbdd511-421e-425a-8acf-db2d4a17a90f', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-23T10:19:40.103\",\"confirmation_code\":\"\",\"order_tracking_id\":\"fcbdd511-421e-425a-8acf-db2d4a17a90f\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=fcbdd511-421e-425a-8acf-db2d4a17a90f&OrderMerchantReference=ORD65\",\"status_code\":0,\"merchant_reference\":\"ORD65\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=fcbdd511-421e-425a-8acf-db2d4a17a90f&OrderMerchantReference=ORD65\"},\"status\":\"500\"}', NULL, '2025-10-23 09:19:38', '2025-10-23 09:19:45'),
(68, 68, 1000.00, 'pending', '163e22d9-5a00-47bf-bb77-db2de36614d8', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1000,\"created_date\":\"2025-10-23T18:38:01.217\",\"confirmation_code\":\"\",\"order_tracking_id\":\"163e22d9-5a00-47bf-bb77-db2de36614d8\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=163e22d9-5a00-47bf-bb77-db2de36614d8&OrderMerchantReference=ORD68\",\"status_code\":0,\"merchant_reference\":\"ORD68\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=163e22d9-5a00-47bf-bb77-db2de36614d8&OrderMerchantReference=ORD68\"},\"status\":\"500\"}', NULL, '2025-10-23 17:37:59', '2025-10-23 17:38:08'),
(70, 70, 1900.00, 'completed', 'a1e7401c-3200-4885-a725-db2bff79760d', '25384645898409', 'TIGOTZ', '2556xxx12954', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"TIGOTZ\",\"amount\":1900,\"created_date\":\"2025-10-25T20:17:38.557\",\"confirmation_code\":\"25384645898409\",\"order_tracking_id\":\"a1e7401c-3200-4885-a725-db2bff79760d\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"2556xxx12954\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=a1e7401c-3200-4885-a725-db2bff79760d&OrderMerchantReference=ORD70\",\"status_code\":1,\"merchant_reference\":\"ORD70\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-25 20:17:38', '2025-10-25 19:03:56', '2025-10-25 19:25:50'),
(71, 73, 0.00, 'completed', NULL, NULL, 'free', NULL, NULL, NULL, NULL, NULL, '2025-10-26 22:38:48', '2025-10-26 22:38:48', '2025-10-26 22:38:48'),
(72, 74, 2500.00, 'completed', 'dd99c6d3-031c-47b8-94c2-db2a38cfffe6', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2500,\"created_date\":\"2025-10-26T22:41:33.197\",\"confirmation_code\":\"\",\"order_tracking_id\":\"dd99c6d3-031c-47b8-94c2-db2a38cfffe6\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=dd99c6d3-031c-47b8-94c2-db2a38cfffe6&OrderMerchantReference=ORD74\",\"status_code\":0,\"merchant_reference\":\"ORD74\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=dd99c6d3-031c-47b8-94c2-db2a38cfffe6&OrderMerchantReference=ORD74\"},\"status\":\"500\"}', '2025-10-26 22:41:33', '2025-10-26 22:41:31', '2025-10-26 22:41:38'),
(73, 75, 4900.00, 'completed', '7bd0eaec-d6c0-4d6d-9209-db291a2570fe', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4900,\"created_date\":\"2025-10-27T03:44:36.103\",\"confirmation_code\":\"\",\"order_tracking_id\":\"7bd0eaec-d6c0-4d6d-9209-db291a2570fe\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=7bd0eaec-d6c0-4d6d-9209-db291a2570fe&OrderMerchantReference=ORD75\",\"status_code\":0,\"merchant_reference\":\"ORD75\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=7bd0eaec-d6c0-4d6d-9209-db291a2570fe&OrderMerchantReference=ORD75\"},\"status\":\"500\"}', '2025-10-27 03:44:36', '2025-10-27 03:44:34', '2025-10-27 03:44:41'),
(74, 76, 5200.00, 'completed', 'f05eff02-7c05-41c3-b83f-db2936856661', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5200,\"created_date\":\"2025-10-27T04:11:03.507\",\"confirmation_code\":\"\",\"order_tracking_id\":\"f05eff02-7c05-41c3-b83f-db2936856661\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f05eff02-7c05-41c3-b83f-db2936856661&OrderMerchantReference=ORD76\",\"status_code\":0,\"merchant_reference\":\"ORD76\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=f05eff02-7c05-41c3-b83f-db2936856661&OrderMerchantReference=ORD76\"},\"status\":\"500\"}', '2025-10-27 04:11:03', '2025-10-27 04:11:01', '2025-10-27 04:11:08'),
(75, 77, 3200.00, 'completed', '73ca7994-4ff5-4650-9387-db29f0bf6267', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3200,\"created_date\":\"2025-10-27T04:16:29.873\",\"confirmation_code\":\"\",\"order_tracking_id\":\"73ca7994-4ff5-4650-9387-db29f0bf6267\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=73ca7994-4ff5-4650-9387-db29f0bf6267&OrderMerchantReference=ORD77\",\"status_code\":0,\"merchant_reference\":\"ORD77\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=73ca7994-4ff5-4650-9387-db29f0bf6267&OrderMerchantReference=ORD77\"},\"status\":\"500\"}', '2025-10-27 04:16:29', '2025-10-27 04:16:28', '2025-10-27 04:16:35'),
(76, 78, 2500.00, 'completed', 'e2c07bd6-1b80-430e-b0f1-db29eb6940ea', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2500,\"created_date\":\"2025-10-27T04:25:43.773\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e2c07bd6-1b80-430e-b0f1-db29eb6940ea\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=e2c07bd6-1b80-430e-b0f1-db29eb6940ea&OrderMerchantReference=ORD78\",\"status_code\":0,\"merchant_reference\":\"ORD78\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=e2c07bd6-1b80-430e-b0f1-db29eb6940ea&OrderMerchantReference=ORD78\"},\"status\":\"500\"}', '2025-10-27 04:25:43', '2025-10-27 04:25:42', '2025-10-27 04:25:49'),
(77, 79, 7200.00, 'pending', 'cdd0dad1-206d-4ae8-90bf-db298ca48317', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":7200,\"created_date\":\"2025-10-27T04:36:25.153\",\"confirmation_code\":\"\",\"order_tracking_id\":\"cdd0dad1-206d-4ae8-90bf-db298ca48317\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=cdd0dad1-206d-4ae8-90bf-db298ca48317&OrderMerchantReference=ORD79\",\"status_code\":0,\"merchant_reference\":\"ORD79\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=cdd0dad1-206d-4ae8-90bf-db298ca48317&OrderMerchantReference=ORD79\"},\"status\":\"500\"}', NULL, '2025-10-27 04:36:23', '2025-10-27 04:43:01'),
(78, 80, 4900.00, 'pending', '9ddd589c-80b9-4df5-8b27-db29e712c0a3', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4900,\"created_date\":\"2025-10-27T04:43:37.817\",\"confirmation_code\":\"\",\"order_tracking_id\":\"9ddd589c-80b9-4df5-8b27-db29e712c0a3\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=9ddd589c-80b9-4df5-8b27-db29e712c0a3&OrderMerchantReference=ORD80\",\"status_code\":0,\"merchant_reference\":\"ORD80\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=9ddd589c-80b9-4df5-8b27-db29e712c0a3&OrderMerchantReference=ORD80\"},\"status\":\"500\"}', NULL, '2025-10-27 04:43:37', '2025-10-27 04:44:39'),
(79, 81, 3500.00, 'pending', 'd227d0d3-3fa1-43ad-abaf-db29737744c6', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3500,\"created_date\":\"2025-10-27T10:15:44.087\",\"confirmation_code\":\"\",\"order_tracking_id\":\"d227d0d3-3fa1-43ad-abaf-db29737744c6\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=d227d0d3-3fa1-43ad-abaf-db29737744c6&OrderMerchantReference=ORD81\",\"status_code\":0,\"merchant_reference\":\"ORD81\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=d227d0d3-3fa1-43ad-abaf-db29737744c6&OrderMerchantReference=ORD81\"},\"status\":\"500\"}', NULL, '2025-10-27 10:15:42', '2025-10-27 10:16:17'),
(80, 82, 4600.00, 'pending', 'bb721b5d-d569-4bc2-ab93-db29d6a745e5', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":4600,\"created_date\":\"2025-10-27T16:48:48.387\",\"confirmation_code\":\"\",\"order_tracking_id\":\"bb721b5d-d569-4bc2-ab93-db29d6a745e5\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=bb721b5d-d569-4bc2-ab93-db29d6a745e5&OrderMerchantReference=ORD82\",\"status_code\":0,\"merchant_reference\":\"ORD82\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=bb721b5d-d569-4bc2-ab93-db29d6a745e5&OrderMerchantReference=ORD82\"},\"status\":\"500\"}', NULL, '2025-10-27 16:48:45', '2025-10-27 16:49:59'),
(81, 83, 2900.00, 'pending', '085b4d0d-7d7d-4d75-a0eb-db2847b82441', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":2900,\"created_date\":\"2025-10-28T07:47:21.423\",\"confirmation_code\":\"\",\"order_tracking_id\":\"085b4d0d-7d7d-4d75-a0eb-db2847b82441\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=085b4d0d-7d7d-4d75-a0eb-db2847b82441&OrderMerchantReference=ORD83\",\"status_code\":0,\"merchant_reference\":\"ORD83\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=085b4d0d-7d7d-4d75-a0eb-db2847b82441&OrderMerchantReference=ORD83\"},\"status\":\"500\"}', NULL, '2025-10-28 05:47:19', '2025-10-28 05:55:46'),
(82, 84, 1900.00, 'pending', NULL, NULL, 'pesapal', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-28 13:56:12', '2025-10-28 13:56:12'),
(83, 85, 1900.00, 'pending', NULL, NULL, 'pesapal', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-28 13:56:14', '2025-10-28 13:56:14'),
(84, 86, 1900.00, 'pending', '3e91f68d-d70b-4f6a-a87a-db28bb8aa2ea', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-28T15:56:18.72\",\"confirmation_code\":\"\",\"order_tracking_id\":\"3e91f68d-d70b-4f6a-a87a-db28bb8aa2ea\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=3e91f68d-d70b-4f6a-a87a-db28bb8aa2ea&OrderMerchantReference=ORD86\",\"status_code\":0,\"merchant_reference\":\"ORD86\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=3e91f68d-d70b-4f6a-a87a-db28bb8aa2ea&OrderMerchantReference=ORD86\"},\"status\":\"500\"}', NULL, '2025-10-28 13:56:16', '2025-10-28 13:56:24'),
(85, 87, 1900.00, 'pending', 'e8633b4e-458f-4cb8-a77f-db28e449fd4b', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-10-28T16:22:15.91\",\"confirmation_code\":\"\",\"order_tracking_id\":\"e8633b4e-458f-4cb8-a77f-db28e449fd4b\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e8633b4e-458f-4cb8-a77f-db28e449fd4b&OrderMerchantReference=ORD87\",\"status_code\":0,\"merchant_reference\":\"ORD87\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=e8633b4e-458f-4cb8-a77f-db28e449fd4b&OrderMerchantReference=ORD87\"},\"status\":\"500\"}', NULL, '2025-10-28 14:22:12', '2025-10-28 14:22:21'),
(86, 88, 1900.00, 'completed', '54fdb418-3487-4fe8-9bec-db28b875c430', '25190295786637', 'TIGOTZ', '2557xxx25044', 'TZS', 1, 'Request processed successfully', '{\"payment_method\":\"TIGOTZ\",\"amount\":1900,\"created_date\":\"2025-10-28T20:39:36.367\",\"confirmation_code\":\"25190295786637\",\"order_tracking_id\":\"54fdb418-3487-4fe8-9bec-db28b875c430\",\"payment_status_description\":\"Completed\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"2557xxx25044\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=54fdb418-3487-4fe8-9bec-db28b875c430&OrderMerchantReference=ORD88\",\"status_code\":1,\"merchant_reference\":\"ORD88\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":null,\"code\":null,\"message\":null},\"status\":\"200\"}', '2025-10-28 20:39:36', '2025-10-28 18:38:10', '2025-10-28 18:40:19'),
(87, 89, 1900.00, 'failed', '64a89bcb-bd80-4d4d-b1be-db20083c02f3', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":1900,\"created_date\":\"2025-11-05T08:51:51.32\",\"confirmation_code\":\"\",\"order_tracking_id\":\"64a89bcb-bd80-4d4d-b1be-db20083c02f3\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=64a89bcb-bd80-4d4d-b1be-db20083c02f3&OrderMerchantReference=ORD89\",\"status_code\":0,\"merchant_reference\":\"ORD89\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=64a89bcb-bd80-4d4d-b1be-db20083c02f3&OrderMerchantReference=ORD89\"},\"status\":\"500\"}', NULL, '2025-11-05 06:51:48', '2025-11-27 10:58:47'),
(88, 90, 5500.00, 'completed', '6ae2b2f0-1b59-4c8b-8ad1-db2036f4a910', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":5500,\"created_date\":\"2025-11-05T23:28:11.543\",\"confirmation_code\":\"\",\"order_tracking_id\":\"6ae2b2f0-1b59-4c8b-8ad1-db2036f4a910\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=6ae2b2f0-1b59-4c8b-8ad1-db2036f4a910&OrderMerchantReference=ORD90\",\"status_code\":0,\"merchant_reference\":\"ORD90\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"https:\\/\\/akdownloads.com\\/callback.php?OrderTrackingId=6ae2b2f0-1b59-4c8b-8ad1-db2036f4a910&OrderMerchantReference=ORD90\"},\"status\":\"500\"}', '2025-11-05 21:33:58', '2025-11-05 21:28:09', '2025-11-05 21:33:58'),
(89, 91, 20000.00, 'completed', '3defb089-1a4d-453c-ba67-db1c96a3a90d', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3500,\"created_date\":\"2025-11-09T10:15:16.433\",\"confirmation_code\":\"\",\"order_tracking_id\":\"3defb089-1a4d-453c-ba67-db1c96a3a90d\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=3defb089-1a4d-453c-ba67-db1c96a3a90d&OrderMerchantReference=ORD91\",\"status_code\":0,\"merchant_reference\":\"ORD91\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=3defb089-1a4d-453c-ba67-db1c96a3a90d&OrderMerchantReference=ORD91\"},\"status\":\"500\"}', '2025-11-27 10:58:36', '2025-11-27 06:05:36', '2025-11-27 10:58:36'),
(90, 92, 1000.00, 'completed', '7185de07-0181-4f8b-bbb6-db1e5107b80c', '', '', '', 'TZS', 0, 'Request processed successfully', '{\"payment_method\":\"\",\"amount\":3500,\"created_date\":\"2025-11-07T13:15:40.177\",\"confirmation_code\":\"\",\"order_tracking_id\":\"7185de07-0181-4f8b-bbb6-db1e5107b80c\",\"payment_status_description\":\"INVALID\",\"description\":null,\"message\":\"Request processed successfully\",\"payment_account\":\"\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=7185de07-0181-4f8b-bbb6-db1e5107b80c&OrderMerchantReference=ORD92\",\"status_code\":0,\"merchant_reference\":\"ORD92\",\"account_number\":null,\"payment_status_code\":\"\",\"currency\":\"TZS\",\"error\":{\"error_type\":\"api_error\",\"code\":\"payment_details_not_found\",\"message\":\"Pending Payment\",\"call_back_url\":\"http:\\/\\/localhost\\/ak23\\/callback.php?OrderTrackingId=7185de07-0181-4f8b-bbb6-db1e5107b80c&OrderMerchantReference=ORD92\"},\"status\":\"500\"}', '2025-11-27 10:58:26', '2025-11-27 10:56:17', '2025-11-27 10:58:26'),
(91, 93, 1000.00, 'completed', '5fac097d-1ebf-40da-9a6c-db1736006f15', NULL, 'pesapal', NULL, NULL, NULL, NULL, NULL, '2025-11-27 11:03:39', '2025-11-27 11:02:48', '2025-11-27 11:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `api_key`, `api_secret`, `is_active`, `is_default`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'pesapal', NULL, NULL, 1, 1, '2025-07-10 17:26:55', '2025-07-10 17:26:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'sysmpanel.dashboard.view', 'web', '2025-06-26 08:21:55', '2025-06-26 08:21:55'),
(2, 'sysmpanel.categories.view', 'web', '2025-06-26 08:21:55', '2025-06-26 08:21:55'),
(3, 'sysmpanel.categories.create', 'web', '2025-06-26 08:21:55', '2025-06-26 08:21:55'),
(4, 'sysmpanel.categories.edit', 'web', '2025-06-26 08:21:55', '2025-06-26 08:21:55'),
(5, 'sysmpanel.categories.delete', 'web', '2025-06-26 08:21:55', '2025-06-26 08:21:55');

-- --------------------------------------------------------

--
-- Table structure for table `ps_logs`
--

DROP TABLE IF EXISTS `ps_logs`;
CREATE TABLE IF NOT EXISTS `ps_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level_created` (`level`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ps_users`
--

DROP TABLE IF EXISTS `ps_users`;
CREATE TABLE IF NOT EXISTS `ps_users` (
  `id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `course_id` bigint UNSIGNED NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'web', '2025-06-26 08:09:21', '2025-06-26 08:09:21'),
(2, 'editor', 'web', '2025-06-26 08:09:21', '2025-06-26 08:09:21'),
(3, 'Admin', '', NULL, NULL),
(4, 'User', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('3n4Hscklm8js1xeqgfnjsGAdgORK4rJsi3m8Lb9Z', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiV3VkVzlpdUkwRGZsazRXbnB4aGZOMmJoUFdNSGJGZlo2ZFVITFViUCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zeXNtcGFuZWwiO31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTozO30=', 1750972212);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(0, 'system_name', 'AKDOWNLOADS'),
(0, 'twilio_sid', 'ACcbbbe862d6a8e6807f11a839ab51631f'),
(0, 'twilio_token', 'ACcbbbe862d6a8e6807f11a839ab51631f'),
(0, 'twilio_from', '+255747004467'),
(0, 'sms_api_url', ''),
(0, 'twilio_sms_template', 'eyydh'),
(0, 'tawk_enabled', '1'),
(0, 'tawk_embed', '<!--Start of Tawk.to Script-->\r\n<script type=\"text/javascript\">\r\nvar Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();\r\n(function(){\r\nvar s1=document.createElement(\"script\"),s0=document.getElementsByTagName(\"script\")[0];\r\ns1.async=true;\r\ns1.src=\'https://embed.tawk.to/65127df50f2b18434fda9c01/1hb83o2ug\';\r\ns1.charset=\'UTF-8\';\r\ns1.setAttribute(\'crossorigin\',\'*\');\r\ns0.parentNode.insertBefore(s1,s0);\r\n})();\r\n</script>\r\n<!--End of Tawk.to Script-->');

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

DROP TABLE IF EXISTS `smtp_settings`;
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `key`, `value`) VALUES
(1, 'host', 'smtp.yourhost.com'),
(2, 'port', '587'),
(3, 'secure', 'tls'),
(4, 'username', 'no-reply@yourdomain.com'),
(5, 'password', 'YOUR_SMTP_PASSWORD'),
(6, 'from_email', 'no-reply@yourdomain.com'),
(7, 'from_name', 'AK23 Studio Kits'),
(8, 'debug', '0'),
(9, 'disable_ssl_verification', '0');

-- --------------------------------------------------------

--
-- Table structure for table `storage_config`
--

DROP TABLE IF EXISTS `storage_config`;
CREATE TABLE IF NOT EXISTS `storage_config` (
  `id` int UNSIGNED NOT NULL,
  `provider` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect_uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bucket_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `storage_config`
--

INSERT INTO `storage_config` (`id`, `provider`, `client_id`, `client_secret`, `redirect_uri`, `bucket_name`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'google_drive', 'google-client-id', 'google-client-secret', 'https://dummy-redirect-uri.com/google', 'google-bucket', 1, '2025-06-29 13:55:48', '2025-06-29 13:55:48', NULL),
(2, 'aws_s3', 'aws-access-key', 'aws-secret-key', 'https://dummy-redirect-uri.com/aws', 'aws-bucket', 0, '2025-06-29 13:55:48', '2025-06-29 13:55:48', NULL),
(3, 'dropbox', 'dropbox-client-id', 'dropbox-client-secret', 'https://dummy-redirect-uri.com/dropbox', 'dropbox-folder', 0, '2025-06-29 13:55:48', '2025-06-29 13:55:48', NULL),
(4, 'local', NULL, NULL, NULL, 'local-folder', 0, '2025-06-29 13:55:48', '2025-06-29 13:55:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `admin_reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `subject`, `message`, `status`, `priority`, `admin_reply`, `created_at`, `updated_at`) VALUES
(1, 32, 'PV Setting', 'i love dance', 'in_progress', 'medium', NULL, '2025-07-30 03:58:49', '2025-07-30 04:06:06'),
(2, 65, 'MUTAMARK', 'TGHHGCHG', 'closed', 'medium', NULL, '2025-09-18 14:11:24', '2025-09-21 23:56:01');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

DROP TABLE IF EXISTS `ticket_replies`;
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` bigint UNSIGNED NOT NULL,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_replies`
--

INSERT INTO `ticket_replies` (`id`, `ticket_id`, `user_id`, `message`, `created_at`, `updated_at`) VALUES
(1, 1, 32, 'sawa mkuu', '2025-07-30 03:59:31', '2025-07-30 03:59:31'),
(2, 1, 1, 'sawa ahsante kwa tarifa yako', '2025-07-30 04:06:02', '2025-07-30 04:06:02'),
(3, 2, 1, '<p>solved</p>', '2025-09-21 23:56:01', '2025-09-21 23:56:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('student','instructor','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  UNIQUE KEY `uniq_users_username` (`username`),
  KEY `idx_users_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `username`, `avatar`, `bio`, `password`, `role`, `is_verified`, `verification_token`, `verification_expires`, `reset_token`, `reset_token_expires`, `created_at`, `updated_at`) VALUES
(1, 'ndonyeye', 'bernadorenatus395@gmail.com', '0655991973', NULL, NULL, NULL, '$2y$10$jmvt.SljixRP9zaNVr.WnevdeNOlM16LWXX5jpmgaAiNq5Ck5naXS', 'student', 1, NULL, NULL, NULL, NULL, '2025-11-24 23:27:22', '2025-11-24 23:27:22'),
(2, '', 'wolinetrn@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$lla7.epGKFWLZiOeabfO6.apWjfkSIToeBavELg3TphyxHsb0z7L2', 'instructor', 0, NULL, NULL, NULL, NULL, '2025-11-26 15:09:44', '2025-11-26 15:09:44'),
(3, '', 'kigodimeet@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$NufMYuyTGxFsagO7phXaq.LbgS.kKxQOesG0gBdd962JJLgLSfXdG', 'instructor', 0, NULL, NULL, NULL, NULL, '2025-11-27 02:41:05', '2025-11-27 02:41:05'),
(4, 'ndonyeye2', 'bernadorenatus392@gmail.com', '0655991973', NULL, NULL, NULL, '$2y$10$731MaZowRxZXrOgLSlucQuMlVPQc1f3QNxv3f5X2hx6CFDg6yZyfm', 'student', 1, NULL, NULL, NULL, NULL, '2025-11-27 06:03:45', '2025-11-27 06:03:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_contact_views`
--

DROP TABLE IF EXISTS `user_contact_views`;
CREATE TABLE IF NOT EXISTS `user_contact_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `contact_id` int NOT NULL,
  `viewed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_contact` (`user_id`,`contact_id`),
  KEY `contact_id` (`contact_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` int UNSIGNED NOT NULL,
  `type` enum('earning','adjustment','withdrawal','refund') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `source` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_transactions_wallet` (`wallet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_withdraw_requests`
--

DROP TABLE IF EXISTS `wallet_withdraw_requests`;
CREATE TABLE IF NOT EXISTS `wallet_withdraw_requests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `instructor_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','rejected','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payout_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout_details` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int UNSIGNED DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_withdraw_instructor` (`instructor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_category` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_categories`
--
ALTER TABLE `course_categories`
  ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollment_payments`
--
ALTER TABLE `enrollment_payments`
  ADD CONSTRAINT `fk_enrollment_payments_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
