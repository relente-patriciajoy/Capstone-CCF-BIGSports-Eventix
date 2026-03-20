-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2026 at 03:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_registration`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_recovery_request`
--

CREATE TABLE `account_recovery_request` (
  `request_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_recovery_request`
--

INSERT INTO `account_recovery_request` (`request_id`, `full_name`, `phone`, `message`, `status`, `admin_note`, `submitted_at`, `resolved_at`, `resolved_by`) VALUES
(1, 'Patricia Joyie Cuizon Relente', '09273328608', 'I am Patricia the Event Head of the volleyball training last month February 3, 2026. #23456', 'resolved', '', '2026-03-14 20:22:03', '2026-03-20 15:07:05', 5),
(2, 'Patricia Joyie Cuizon Relente', '09273328608', 'This is just a test for the 2nd time', 'resolved', '', '2026-03-14 20:42:28', '2026-03-20 15:07:01', 5);

-- --------------------------------------------------------

--
-- Table structure for table `announcement`
--

CREATE TABLE `announcement` (
  `announcement_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement`
--

INSERT INTO `announcement` (`announcement_id`, `event_id`, `sent_by`, `subject`, `message`, `sent_at`) VALUES
(1, 16, 6, 'test', 'this is a test announcement', '2026-03-07 13:05:51'),
(2, 16, 6, 'testing', 'test email message', '2026-03-07 13:15:36');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `registration_id` int(11) DEFAULT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `status` varchar(10) DEFAULT 'absent',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `registration_id`, `check_in_time`, `check_out_time`, `status`, `notes`) VALUES
(13, 23, '2026-03-05 11:39:01', '2026-03-05 11:39:02', 'present', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `backup_log`
--

CREATE TABLE `backup_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` enum('backup','restore','delete') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_log`
--

INSERT INTO `backup_log` (`log_id`, `admin_id`, `action`, `filename`, `file_size`, `created_at`) VALUES
(1, 5, 'backup', 'eventix_backup_2026-01-04_13-19-32.sql', 29119, '2026-01-04 20:19:32'),
(2, 5, 'delete', 'eventix_backup_2026-01-04_13-13-02.sql', NULL, '2026-01-04 20:19:42'),
(3, 5, 'delete', 'eventix_backup_2026-01-04_13-13-12.sql', NULL, '2026-01-04 20:19:46'),
(4, 5, 'backup', 'thisisupdatetest_2026-01-04_13-20-17.sql', 29420, '2026-01-04 20:20:17'),
(5, 5, 'backup', 'thisisupdatetest_2026-01-04_20-25-29.sql', 29506, '2026-01-04 20:25:29'),
(6, 5, 'backup', 'eventix_backup_2026-01-13_11-58-29.sql', 32409, '2026-01-13 11:58:30'),
(7, 5, 'backup', 'eventix_backup_2026-01-13_11-58-36.sql', 32493, '2026-01-13 11:58:36'),
(8, 5, 'restore', '11_58pm_2026-01-13_11-58-49.sql', NULL, '2026-01-13 12:00:35');

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `log_id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `email_type` enum('reminder_3day','reminder_1day','reminder_day_of','announcement') NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_log`
--

INSERT INTO `email_log` (`log_id`, `registration_id`, `email_type`, `sent_at`) VALUES
(1, 23, 'announcement', '2026-03-07 13:05:51');

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `organizer_id` int(11) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `has_tables` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this event uses table assignments',
  `gender_separated` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = male/female assigned to separate tables'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event`
--

INSERT INTO `event` (`event_id`, `title`, `description`, `start_time`, `end_time`, `venue_id`, `organizer_id`, `capacity`, `price`, `created_at`, `category_id`, `has_tables`, `gender_separated`) VALUES
(16, 'Volleyball', 'Volleyball Tournament', '2026-01-29 11:53:00', '2026-01-29 20:53:00', 22, 3, 70, 0.00, '2026-01-20 20:54:26', 1, 0, 0),
(20, 'CCF Mid-Year Recharge: Rooted & Grounded', 'Join us for a powerful afternoon of worship, testimony, and deep-dive breakout sessions. As we hit the midpoint of the year, let’s realign our hearts with God’s Word and strengthen our small group connections. Open to all D-Group members and guests!', '2026-07-11 14:00:00', '2026-07-11 18:00:00', 26, 3, 400, 0.00, '2026-03-19 10:31:50', 2, 1, 1),
(22, 'B1G Friday: Purposeful Living', 'Are you feeling burnt out or questioning your career path? Join our B1G Friday session as we discuss how to live out your faith in the marketplace. We’ll have a guest speaker sharing practical tips on work-life balance and honoring God through your profession.', '2026-04-24 19:30:00', '2026-04-24 21:30:00', 27, 3, 120, 0.00, '2026-03-19 10:34:33', 2, 1, 1),
(24, 'Parenting 101: Navigating the Digital Age', 'A specialized workshop for parents and guardians. Learn biblical principles on how to guide your children through the challenges of social media and technology. Includes a printed workbook and a Q&A panel with family counselors.', '2026-05-16 09:00:00', '2026-05-16 12:00:00', 28, 3, 80, 0.00, '2026-03-19 10:36:54', 2, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_access`
--

CREATE TABLE `event_access` (
  `access_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_manage_attendance` tinyint(1) DEFAULT 0,
  `can_export_data` tinyint(1) DEFAULT 0,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_access`
--

INSERT INTO `event_access` (`access_id`, `event_id`, `user_id`, `can_view`, `can_edit`, `can_delete`, `can_manage_attendance`, `can_export_data`, `granted_by`, `granted_at`, `reason`) VALUES
(4, 16, 6, 1, 0, 0, 1, 1, 5, '2026-01-25 03:23:36', '');

-- --------------------------------------------------------

--
-- Table structure for table `event_category`
--

CREATE TABLE `event_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_category`
--

INSERT INTO `event_category` (`category_id`, `category_name`, `description`) VALUES
(1, 'Sports', 'Sports-related eventss'),
(2, 'Non-Sports', 'Non-sports related events'),
(4, 'Sunday Event', 'This is sunday event');

-- --------------------------------------------------------

--
-- Table structure for table `event_table`
--

CREATE TABLE `event_table` (
  `table_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 10,
  `gender_assignment` enum('male','female','mixed') NOT NULL DEFAULT 'mixed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_table`
--

INSERT INTO `event_table` (`table_id`, `event_id`, `table_number`, `capacity`, `gender_assignment`, `created_at`) VALUES
(1, 20, 1, 10, 'male', '2026-03-20 21:16:30'),
(2, 20, 2, 10, 'male', '2026-03-20 21:16:30'),
(3, 20, 3, 10, 'female', '2026-03-20 21:16:30'),
(4, 20, 4, 10, 'female', '2026-03-20 21:16:30'),
(5, 22, 1, 10, 'male', '2026-03-20 21:47:57'),
(6, 22, 2, 10, 'male', '2026-03-20 21:47:57'),
(7, 22, 3, 10, 'female', '2026-03-20 21:47:57'),
(8, 22, 4, 10, 'female', '2026-03-20 21:47:58');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempt`
--

CREATE TABLE `login_attempt` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempt`
--

INSERT INTO `login_attempt` (`attempt_id`, `user_id`, `email`, `success`, `ip_address`, `user_agent`, `attempted_at`) VALUES
(1, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 16:45:09'),
(2, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 16:45:50'),
(3, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 16:46:41'),
(4, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 16:48:26'),
(5, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 22:11:28'),
(6, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 22:20:51'),
(7, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2025-12-30 22:24:00'),
(8, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-01 19:37:08'),
(9, 5, 'patriciarelente03@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-01 20:03:53'),
(10, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-01 20:04:00'),
(11, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-01 20:04:39'),
(12, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-04 12:17:45'),
(13, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-04 19:56:06'),
(14, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-12 11:14:14'),
(15, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-12 11:14:52'),
(16, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 10:43:54'),
(17, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 10:48:07'),
(18, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 11:31:19'),
(19, 5, 'patriciarelente03@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-16 20:58:20'),
(20, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-16 20:58:31'),
(21, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-16 21:00:02'),
(22, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-16 21:13:05'),
(23, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-18 15:36:11'),
(24, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-18 15:51:52'),
(25, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-18 16:37:14'),
(26, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-18 16:38:12'),
(27, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-18 16:38:34'),
(28, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-18 16:39:11'),
(29, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-19 20:32:14'),
(30, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-19 20:32:59'),
(31, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-19 20:33:24'),
(32, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 20:36:21'),
(33, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 20:36:47'),
(34, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 20:40:12'),
(35, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 20:40:28'),
(36, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-19 21:17:11'),
(37, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-19 21:36:09'),
(38, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 21:41:20'),
(39, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 21:42:45'),
(40, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-20 20:35:03'),
(41, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-20 20:36:31'),
(42, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-20 20:53:04'),
(43, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-22 14:31:10'),
(44, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-24 11:30:49'),
(45, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-24 12:14:21'),
(46, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-24 12:39:03'),
(47, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-24 17:24:19'),
(48, 5, 'patriciarelente03@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-24 17:24:44'),
(49, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-24 17:24:52'),
(50, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-24 18:09:35'),
(51, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-25 10:47:55'),
(52, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-25 11:12:39'),
(53, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-25 16:03:03'),
(54, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-25 16:18:57'),
(55, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-25 21:45:44'),
(56, 5, 'patriciarelente03@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-25 21:47:18'),
(57, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-25 21:47:27'),
(58, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-26 10:08:23'),
(59, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-26 10:08:58'),
(60, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-26 16:27:01'),
(61, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-26 16:27:45'),
(62, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-26 16:32:32'),
(63, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-27 19:06:01'),
(64, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 19:10:12'),
(65, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 19:15:30'),
(66, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-27 19:16:06'),
(67, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 12:02:32'),
(68, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:04:45'),
(69, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:06:47'),
(70, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:32:17'),
(71, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-02 14:40:56'),
(72, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 14:41:58'),
(73, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 15:12:32'),
(74, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 11:26:51'),
(75, 6, 'relente.patriciajoy@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 19:05:55'),
(76, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-07 11:24:35'),
(77, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 11:24:57'),
(78, 7, 'tcuizon03@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-07 14:26:15'),
(79, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-07 14:26:24'),
(80, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:01:02'),
(81, 7, 'tcuizon03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 12:01:31'),
(82, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:34:41'),
(83, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 18:39:08'),
(84, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:48:50'),
(85, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 14:09:41'),
(86, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 20:14:14'),
(87, 6, 'relente.patriciajoy@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 20:41:04'),
(88, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 20:41:18'),
(89, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 20:42:41'),
(90, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 10:28:30'),
(91, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 13:05:27'),
(92, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 13:18:40'),
(93, 5, 'patriciarelente03@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 13:24:00'),
(94, 5, 'patriciarelente03@gmail.com', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 13:35:28'),
(95, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 14:17:38'),
(96, 6, 'relente.patriciajoy@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 20:56:17');

-- --------------------------------------------------------

--
-- Table structure for table `organizer`
--

CREATE TABLE `organizer` (
  `organizer_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizer`
--

INSERT INTO `organizer` (`organizer_id`, `name`, `contact_email`, `phone`) VALUES
(1, 'Jane Cruz Deee', 'janedee123@gmail.com', '0123456988857'),
(2, 'John Cruz Doe', 'john123@example.com', '123456988856'),
(3, 'Patricia Joyie Cuizon Relente', 'relente.patriciajoy@gmail.com', '09273328608');

-- --------------------------------------------------------

--
-- Table structure for table `otp_code`
--

CREATE TABLE `otp_code` (
  `otp_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `otp_code` varchar(6) NOT NULL,
  `otp_type` enum('registration','login','reset_password') NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_code`
--

INSERT INTO `otp_code` (`otp_id`, `user_id`, `email`, `phone`, `otp_code`, `otp_type`, `expires_at`, `is_used`, `created_at`) VALUES
(1, 6, 'relente.patriciajoy@gmail.com', '09273328608', '772149', 'login', '2025-12-19 12:04:32', 1, '2025-12-19 18:59:32'),
(2, 6, 'relente.patriciajoy@gmail.com', '09273328608', '243152', 'login', '2025-12-19 12:05:40', 1, '2025-12-19 19:00:40'),
(3, 6, 'relente.patriciajoy@gmail.com', '09273328608', '164534', 'login', '2025-12-19 12:05:50', 1, '2025-12-19 19:00:50'),
(4, 6, 'relente.patriciajoy@gmail.com', '09273328608', '541959', 'login', '2025-12-19 12:10:51', 1, '2025-12-19 19:05:51'),
(5, 6, 'relente.patriciajoy@gmail.com', '09273328608', '555628', 'login', '2025-12-19 12:10:55', 1, '2025-12-19 19:05:55'),
(6, 6, 'relente.patriciajoy@gmail.com', '09273328608', '190553', 'login', '2025-12-19 12:11:25', 1, '2025-12-19 19:06:25'),
(7, 6, 'relente.patriciajoy@gmail.com', '09273328608', '341910', 'login', '2025-12-19 12:12:51', 1, '2025-12-19 19:07:51'),
(8, 6, 'relente.patriciajoy@gmail.com', '09273328608', '351453', 'login', '2025-12-20 04:36:45', 1, '2025-12-20 11:31:45'),
(9, 6, 'relente.patriciajoy@gmail.com', '09273328608', '413374', 'login', '2025-12-20 04:41:54', 1, '2025-12-20 11:36:54'),
(10, 5, 'patriciarelente03@gmail.com', '+639123453221', '306023', 'login', '2025-12-20 04:52:59', 1, '2025-12-20 11:47:59'),
(11, 5, 'patriciarelente03@gmail.com', '+639123453221', '124069', 'login', '2025-12-20 06:50:46', 1, '2025-12-20 13:45:46'),
(12, 6, 'relente.patriciajoy@gmail.com', '09273328608', '248921', 'login', '2025-12-21 05:47:46', 1, '2025-12-21 12:42:46'),
(13, 5, 'patriciarelente03@gmail.com', '+639123453221', '540179', 'login', '2025-12-21 05:49:51', 1, '2025-12-21 12:44:51'),
(14, 5, 'patriciarelente03@gmail.com', '+639123453221', '894974', '', '2025-12-21 12:35:00', 1, '2025-12-21 19:30:00'),
(15, 5, 'patriciarelente03@gmail.com', '+639123453221', '082517', 'login', '2025-12-21 12:39:09', 1, '2025-12-21 19:34:09'),
(16, NULL, 'tcuizon03@gmail.com', '09927198279', '219065', 'registration', '2025-12-21 13:12:11', 1, '2025-12-21 20:07:11'),
(17, NULL, 'tcuizon03@gmail.com', '09927198279', '076146', 'registration', '2025-12-21 13:12:18', 1, '2025-12-21 20:07:18'),
(18, NULL, 'tcuizon03@gmail.com', '09927198279', '772682', 'registration', '2025-12-21 13:12:54', 1, '2025-12-21 20:07:54'),
(19, 5, 'patriciarelente03@gmail.com', '+639123453221', '751549', 'login', '2025-12-22 04:33:06', 1, '2025-12-22 11:28:06'),
(20, 5, 'patriciarelente03@gmail.com', '+639123453221', '068521', 'login', '2025-12-22 04:35:24', 1, '2025-12-22 11:30:24'),
(21, 6, 'relente.patriciajoy@gmail.com', '09273328608', '293160', 'login', '2025-12-22 04:38:54', 1, '2025-12-22 11:33:54'),
(22, NULL, 'tcuizon03@gmail.com', '09927198279', '113458', 'registration', '2025-12-22 04:40:25', 1, '2025-12-22 11:35:25'),
(23, 5, 'patriciarelente03@gmail.com', '+639123453221', '770634', 'login', '2025-12-22 04:41:42', 1, '2025-12-22 11:36:42'),
(24, 6, 'relente.patriciajoy@gmail.com', '09273328608', '007880', 'login', '2025-12-22 05:42:50', 1, '2025-12-22 12:37:50'),
(25, 6, 'relente.patriciajoy@gmail.com', '09273328608', '561513', 'login', '2025-12-23 09:46:24', 1, '2025-12-23 16:41:24'),
(26, 6, 'relente.patriciajoy@gmail.com', '09273328608', '863119', 'login', '2025-12-23 09:47:47', 1, '2025-12-23 16:42:47'),
(27, 6, 'relente.patriciajoy@gmail.com', '09273328608', '675511', 'login', '2025-12-23 10:10:00', 1, '2025-12-23 17:05:00'),
(28, 6, 'relente.patriciajoy@gmail.com', '09273328608', '554610', 'login', '2025-12-23 10:10:40', 1, '2025-12-23 17:05:40'),
(29, 5, 'patriciarelente03@gmail.com', '+639123453221', '302827', 'login', '2025-12-24 14:01:11', 1, '2025-12-24 20:56:11'),
(30, 5, 'patriciarelente03@gmail.com', '+639123453221', '078876', 'login', '2025-12-26 02:37:36', 1, '2025-12-26 09:32:36'),
(31, 6, 'relente.patriciajoy@gmail.com', '09273328608', '368311', 'login', '2025-12-26 02:41:25', 1, '2025-12-26 09:36:25'),
(32, 7, 'tcuizon03@gmail.com', '09927198279', '945272', 'login', '2025-12-26 03:57:18', 1, '2025-12-26 10:52:18'),
(33, 6, 'relente.patriciajoy@gmail.com', '09273328608', '929876', 'login', '2025-12-26 04:04:21', 1, '2025-12-26 10:59:21'),
(34, 6, 'relente.patriciajoy@gmail.com', '09273328608', '917085', 'login', '2025-12-26 05:14:04', 1, '2025-12-26 12:09:04'),
(35, 7, 'tcuizon03@gmail.com', '09927198279', '626521', 'login', '2025-12-26 05:15:08', 1, '2025-12-26 12:10:08'),
(36, 6, 'relente.patriciajoy@gmail.com', '09273328608', '421544', 'login', '2025-12-26 05:19:22', 1, '2025-12-26 12:14:22'),
(37, 7, 'tcuizon03@gmail.com', '09927198279', '967932', 'login', '2025-12-30 09:49:58', 1, '2025-12-30 16:44:58'),
(38, 5, 'patriciarelente03@gmail.com', '+639123453221', '104870', 'login', '2025-12-30 09:51:35', 1, '2025-12-30 16:46:35'),
(39, 6, 'relente.patriciajoy@gmail.com', '09273328608', '405507', 'login', '2025-12-30 15:25:44', 1, '2025-12-30 22:20:44'),
(40, 6, 'relente.patriciajoy@gmail.com', '09273328608', '298829', 'login', '2026-01-04 05:22:37', 1, '2026-01-04 12:17:37'),
(41, 5, 'patriciarelente03@gmail.com', '+639123453221', '312774', 'login', '2026-01-04 13:00:53', 1, '2026-01-04 19:55:53'),
(42, 6, 'relente.patriciajoy@gmail.com', '09273328608', '183815', 'login', '2026-01-12 04:19:06', 1, '2026-01-12 11:14:06'),
(43, 5, 'patriciarelente03@gmail.com', '+639123453221', '673224', 'login', '2026-01-12 04:19:44', 1, '2026-01-12 11:14:44'),
(44, 5, 'patriciarelente03@gmail.com', '+639123453221', '060030', 'login', '2026-01-13 03:47:18', 1, '2026-01-13 10:42:18'),
(45, 6, 'relente.patriciajoy@gmail.com', '09273328608', '191080', 'login', '2026-01-13 03:53:00', 1, '2026-01-13 10:48:00'),
(46, NULL, 'jcnolluda@gmail.com', '09911874327', '241240', 'registration', '2026-01-13 04:30:01', 1, '2026-01-13 11:25:01'),
(47, NULL, 'jcnolluda@gmail.com', '09911874327', '122783', 'registration', '2026-01-13 04:30:09', 1, '2026-01-13 11:25:09'),
(48, NULL, 'jcnolluda@gmail.com', '09911874327', '772018', 'registration', '2026-01-13 04:30:14', 0, '2026-01-13 11:25:14'),
(49, 7, 'tcuizon03@gmail.com', '09927198279', '456576', 'login', '2026-01-13 04:36:12', 1, '2026-01-13 11:31:12'),
(50, 6, 'relente.patriciajoy@gmail.com', '09273328608', '078454', 'login', '2026-01-16 14:04:57', 1, '2026-01-16 20:59:57'),
(51, 7, 'tcuizon03@gmail.com', '09927198279', '086273', 'login', '2026-01-16 14:18:00', 1, '2026-01-16 21:13:00'),
(52, 5, 'patriciarelente03@gmail.com', '+639123453221', '266311', 'login', '2026-01-18 08:40:59', 1, '2026-01-18 15:35:59'),
(53, 5, 'patriciarelente03@gmail.com', '+639123453221', '472915', 'login', '2026-01-18 08:53:11', 1, '2026-01-18 15:48:11'),
(54, 5, 'patriciarelente03@gmail.com', '+639123453221', '029586', 'login', '2026-01-18 08:56:47', 1, '2026-01-18 15:51:47'),
(55, 6, 'relente.patriciajoy@gmail.com', '09273328608', '255640', 'login', '2026-01-18 09:42:07', 1, '2026-01-18 16:37:07'),
(56, 6, 'relente.patriciajoy@gmail.com', '09273328608', '363911', 'login', '2026-01-18 09:43:07', 1, '2026-01-18 16:38:07'),
(57, 6, 'relente.patriciajoy@gmail.com', '09273328608', '350220', 'login', '2026-01-18 09:43:26', 1, '2026-01-18 16:38:26'),
(58, 5, 'patriciarelente03@gmail.com', '+639123453221', '685663', 'login', '2026-01-19 13:37:08', 1, '2026-01-19 20:32:08'),
(59, 5, 'patriciarelente03@gmail.com', '+639123453221', '596548', 'login', '2026-01-19 13:37:53', 1, '2026-01-19 20:32:53'),
(60, 5, 'patriciarelente03@gmail.com', '+639123453221', '965603', 'login', '2026-01-19 13:38:17', 1, '2026-01-19 20:33:17'),
(61, 6, 'relente.patriciajoy@gmail.com', '09273328608', '593743', 'login', '2026-01-19 13:41:16', 1, '2026-01-19 20:36:16'),
(62, 6, 'relente.patriciajoy@gmail.com', '09273328608', '822965', 'login', '2026-01-19 13:41:41', 1, '2026-01-19 20:36:41'),
(63, 5, 'patriciarelente03@gmail.com', '+639123453221', '651988', 'login', '2026-01-19 13:45:06', 1, '2026-01-19 20:40:06'),
(64, 7, 'tcuizon03@gmail.com', '09927198279', '034239', 'login', '2026-01-19 14:46:13', 1, '2026-01-19 21:41:13'),
(65, 6, 'relente.patriciajoy@gmail.com', '09273328608', '566401', 'login', '2026-01-19 14:47:39', 1, '2026-01-19 21:42:39'),
(66, 7, 'tcuizon03@gmail.com', '09927198279', '199491', 'login', '2026-01-24 05:43:56', 1, '2026-01-24 12:38:56'),
(67, 6, 'relente.patriciajoy@gmail.com', '09273328608', '036975', 'login', '2026-01-25 14:50:36', 1, '2026-01-25 21:45:36'),
(68, 5, 'patriciarelente03@gmail.com', '+639123453221', '706020', 'login', '2026-02-27 12:10:54', 1, '2026-02-27 19:05:54'),
(69, 5, 'patriciarelente03@gmail.com', '+639123453221', '049393', 'login', '2026-02-27 12:15:06', 1, '2026-02-27 19:10:06'),
(70, 7, 'tcuizon03@gmail.com', '09927198279', '621629', 'login', '2026-03-01 04:59:39', 1, '2026-03-01 11:54:39'),
(71, 7, 'tcuizon03@gmail.com', '09927198279', '999733', 'login', '2026-03-01 05:07:26', 1, '2026-03-01 12:02:26'),
(72, 5, 'patriciarelente03@gmail.com', '+639123453221', '074609', 'login', '2026-03-01 05:09:38', 1, '2026-03-01 12:04:38'),
(73, 6, 'relente.patriciajoy@gmail.com', '09273328608', '683381', 'login', '2026-03-01 05:11:40', 1, '2026-03-01 12:06:40'),
(74, 7, 'tcuizon03@gmail.com', '09927198279', '316298', 'login', '2026-03-01 05:37:07', 1, '2026-03-01 12:32:07'),
(75, 6, 'relente.patriciajoy@gmail.com', '09273328608', '938666', 'login', '2026-03-13 11:44:02', 1, '2026-03-13 18:39:02'),
(76, 5, 'patriciarelente03@gmail.com', '+639123453221', '142750', 'login', '2026-03-14 05:53:44', 1, '2026-03-14 12:48:44'),
(77, 6, 'relente.patriciajoy@gmail.com', '09273328608', '758814', '', '2026-03-14 13:22:17', 0, '2026-03-14 20:17:17'),
(78, 6, 'relente.patriciajoy@gmail.com', '09273328608', '219920', 'reset_password', '2026-03-14 13:29:00', 1, '2026-03-14 20:24:00'),
(79, 6, 'relente.patriciajoy@gmail.com', '09273328608', '051595', 'reset_password', '2026-03-14 13:32:32', 1, '2026-03-14 20:27:32'),
(80, 6, 'relente.patriciajoy@gmail.com', '09273328608', '672274', 'reset_password', '2026-03-14 13:34:48', 1, '2026-03-14 20:29:48'),
(81, 6, 'relente.patriciajoy@gmail.com', '09273328608', '767440', 'reset_password', '2026-03-14 13:36:02', 1, '2026-03-14 20:31:02'),
(82, 6, 'relente.patriciajoy@gmail.com', '09273328608', '745396', 'reset_password', '2026-03-14 13:37:18', 1, '2026-03-14 20:32:18'),
(83, 6, 'relente.patriciajoy@gmail.com', '09273328608', '523735', 'reset_password', '2026-03-14 13:37:29', 1, '2026-03-14 20:32:29'),
(84, 6, 'relente.patriciajoy@gmail.com', '09273328608', '442475', 'reset_password', '2026-03-14 13:39:07', 1, '2026-03-14 20:34:07'),
(85, 6, 'relente.patriciajoy@gmail.com', '09273328608', '368866', 'reset_password', '2026-03-14 13:44:05', 1, '2026-03-14 20:39:05'),
(86, 6, 'relente.patriciajoy@gmail.com', '09273328608', '781493', 'login', '2026-03-14 13:46:14', 1, '2026-03-14 20:41:14'),
(87, 5, 'patriciarelente03@gmail.com', '+639123453221', '120709', 'login', '2026-03-20 06:40:22', 1, '2026-03-20 13:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `otp_log`
--

CREATE TABLE `otp_log` (
  `log_id` int(11) NOT NULL,
  `otp_id` int(11) NOT NULL,
  `delivery_method` enum('sms','email') NOT NULL,
  `delivery_status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `registration_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permission`
--

CREATE TABLE `permission` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission`
--

INSERT INTO `permission` (`permission_id`, `permission_name`, `permission_category`, `description`, `created_at`) VALUES
(1, 'event.create', 'event', 'Can create new events', '2026-01-23 04:20:17'),
(2, 'event.view.all', 'event', 'Can view all events', '2026-01-23 04:20:17'),
(3, 'event.view.own', 'event', 'Can view own events only', '2026-01-23 04:20:17'),
(4, 'event.edit.all', 'event', 'Can edit all events', '2026-01-23 04:20:17'),
(5, 'event.edit.own', 'event', 'Can edit own events only', '2026-01-23 04:20:17'),
(6, 'event.delete.all', 'event', 'Can delete all events', '2026-01-23 04:20:17'),
(7, 'event.delete.own', 'event', 'Can delete own events only', '2026-01-23 04:20:17'),
(8, 'event.search', 'event', 'Can search events', '2026-01-23 04:20:17'),
(9, 'attendance.view.all', 'attendance', 'Can view all attendance records', '2026-01-23 04:20:17'),
(10, 'attendance.view.own', 'attendance', 'Can view own event attendance', '2026-01-23 04:20:17'),
(11, 'attendance.mark', 'attendance', 'Can mark attendance', '2026-01-23 04:20:17'),
(12, 'attendance.edit', 'attendance', 'Can edit attendance records', '2026-01-23 04:20:17'),
(13, 'attendance.export', 'attendance', 'Can export attendance data', '2026-01-23 04:20:17'),
(14, 'attendance.qr.scan', 'attendance', 'Can scan QR codes for attendance', '2026-01-23 04:20:17'),
(15, 'user.view', 'user', 'Can view users', '2026-01-23 04:20:17'),
(16, 'user.create', 'user', 'Can create users', '2026-01-23 04:20:17'),
(17, 'user.edit', 'user', 'Can edit users', '2026-01-23 04:20:17'),
(18, 'user.delete', 'user', 'Can delete users', '2026-01-23 04:20:17'),
(19, 'user.promote', 'user', 'Can change user roles and permissions', '2026-01-23 04:20:17'),
(20, 'system.settings', 'system', 'Can access system settings', '2026-01-23 04:20:17'),
(21, 'system.reports', 'system', 'Can generate system reports', '2026-01-23 04:20:17'),
(22, 'system.backup', 'system', 'Can backup and restore database', '2026-01-23 04:20:17'),
(23, 'permission.manage', 'system', 'Can manage permissions', '2026-01-23 04:20:17');

-- --------------------------------------------------------

--
-- Table structure for table `permission_audit_log`
--

CREATE TABLE `permission_audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission_audit_log`
--

INSERT INTO `permission_audit_log` (`log_id`, `user_id`, `permission_id`, `event_id`, `action`, `changed_by`, `old_value`, `new_value`, `reason`, `created_at`) VALUES
(1, 6, NULL, NULL, 'event_access', 5, NULL, NULL, 'She can\'t delete, this is a test', '2026-01-24 09:26:18'),
(2, 7, NULL, NULL, 'role_change', 5, NULL, NULL, '', '2026-01-24 09:30:03'),
(3, 7, NULL, NULL, 'role_change', 5, NULL, NULL, '', '2026-01-24 09:30:28'),
(4, 7, NULL, NULL, 'role_change', 5, NULL, NULL, '', '2026-01-24 09:30:46'),
(5, 7, 16, NULL, 'grant', 5, NULL, NULL, 'She is event head', '2026-01-24 09:54:44'),
(6, 7, 16, NULL, 'grant', 5, NULL, NULL, 'She is event head', '2026-01-24 10:00:50'),
(7, 6, NULL, NULL, 'event_access', 5, NULL, NULL, '', '2026-01-24 10:01:22'),
(8, 6, NULL, NULL, 'event_access', 5, NULL, NULL, '', '2026-01-24 10:02:42'),
(9, 7, 1, NULL, 'grant', 5, NULL, NULL, 'Test - allowing user to create events', '2026-01-24 10:05:16'),
(10, 7, 1, NULL, 'grant', 5, NULL, NULL, 'Test - allowing user to create events', '2026-01-24 10:05:35'),
(11, 7, NULL, NULL, 'role_change', 5, NULL, NULL, '', '2026-01-24 10:08:08'),
(12, 7, NULL, NULL, 'role_change', 5, NULL, NULL, '', '2026-01-24 10:10:00'),
(13, 7, 14, NULL, 'grant', 5, NULL, NULL, 'test', '2026-01-24 10:10:34'),
(14, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 02:52:40'),
(15, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 03:02:09'),
(16, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 03:02:32'),
(17, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 03:14:35'),
(18, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 03:17:53'),
(19, 6, NULL, 16, 'event_access', 5, NULL, NULL, '', '2026-01-25 03:23:36'),
(20, 6, 13, NULL, 'grant', 5, NULL, NULL, 'test', '2026-01-25 13:48:04'),
(21, 6, 10, NULL, 'grant', 5, NULL, NULL, 'yesy', '2026-01-25 13:48:18'),
(22, 6, 13, NULL, 'revoke', 5, NULL, NULL, 'test', '2026-01-25 13:48:37'),
(23, 6, 12, NULL, 'grant', 5, NULL, NULL, 'fa', '2026-01-25 13:48:48'),
(24, 6, 11, NULL, 'grant', 5, NULL, NULL, 'test', '2026-01-25 13:48:55'),
(25, 6, 9, NULL, 'grant', 5, NULL, NULL, 'es', '2026-01-25 13:49:08'),
(26, 6, 9, NULL, 'grant', 5, NULL, NULL, 'es', '2026-01-25 13:49:37'),
(27, 6, 12, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(28, 6, 11, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(29, 6, 9, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(30, 6, 10, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(31, 6, 1, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(32, 6, 7, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(33, 6, 5, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(34, 6, 8, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(35, 6, 3, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(36, 6, 21, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(37, 6, 13, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(38, 6, 14, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(39, 6, 6, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(40, 6, 4, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(41, 6, 2, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(42, 6, 23, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(43, 6, 22, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(44, 6, 20, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(45, 6, 16, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(46, 6, 18, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(47, 6, 17, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(48, 6, 19, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(49, 6, 15, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:09:56'),
(50, 6, 12, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(51, 6, 11, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(52, 6, 9, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(53, 6, 10, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(54, 6, 1, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(55, 6, 7, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(56, 6, 5, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(57, 6, 8, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(58, 6, 3, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(59, 6, 21, NULL, 'grant', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(60, 6, 13, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(61, 6, 14, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(62, 6, 6, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(63, 6, 4, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(64, 6, 2, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(65, 6, 23, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(66, 6, 22, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(67, 6, 20, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(68, 6, 16, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(69, 6, 18, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(70, 6, 17, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(71, 6, 19, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(72, 6, 15, NULL, 'revoke', 5, NULL, NULL, 'test remove', '2026-01-26 02:18:48'),
(73, 6, 12, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(74, 6, 11, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(75, 6, 9, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(76, 6, 10, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(77, 6, 1, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(78, 6, 7, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(79, 6, 5, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(80, 6, 8, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(81, 6, 3, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(82, 6, 21, NULL, 'grant', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(83, 6, 13, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(84, 6, 14, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(85, 6, 6, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(86, 6, 4, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(87, 6, 2, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(88, 6, 23, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(89, 6, 22, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(90, 6, 20, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(91, 6, 16, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(92, 6, 18, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(93, 6, 17, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(94, 6, 19, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22'),
(95, 6, 15, NULL, 'revoke', 5, NULL, NULL, 'Nothing change', '2026-01-26 02:19:22');

-- --------------------------------------------------------

--
-- Table structure for table `registration`
--

CREATE TABLE `registration` (
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'confirmed',
  `qr_token` varchar(64) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `table_number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration`
--

INSERT INTO `registration` (`registration_id`, `user_id`, `event_id`, `registration_date`, `status`, `qr_token`, `qr_code`, `table_number`) VALUES
(23, 6, 16, '2026-03-02 15:14:02', 'confirmed', '15f0cedf0b7c0f0d3c4105ce3eae9db63c3ed37692c61a6d970d2ef5db95b038', 'qr_reg_23.png', 0),
(26, 6, 22, '2026-03-20 21:02:43', 'confirmed', 'fd55fb2408be9877b6f9477ddfe1a2ee2509535c96a32b530b7453b95ad71b69', 'qr_reg_26.png', 3);

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`role_id`, `role_name`, `description`, `created_at`) VALUES
(1, 'user', 'Regular participant - can browse and register for events', '2026-01-23 04:16:37'),
(2, 'event_head', 'Event organizer - can create and manage events', '2026-01-23 04:16:37'),
(3, 'admin', 'System administrator - full access to all features', '2026-01-23 04:16:37');

-- --------------------------------------------------------

--
-- Table structure for table `role_permission`
--

CREATE TABLE `role_permission` (
  `role_permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permission`
--

INSERT INTO `role_permission` (`role_permission_id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 3, 12, '2026-01-23 04:20:17'),
(2, 3, 13, '2026-01-23 04:20:17'),
(3, 3, 11, '2026-01-23 04:20:17'),
(4, 3, 14, '2026-01-23 04:20:17'),
(5, 3, 9, '2026-01-23 04:20:17'),
(6, 3, 10, '2026-01-23 04:20:17'),
(7, 3, 1, '2026-01-23 04:20:17'),
(8, 3, 6, '2026-01-23 04:20:17'),
(9, 3, 7, '2026-01-23 04:20:17'),
(10, 3, 4, '2026-01-23 04:20:17'),
(11, 3, 5, '2026-01-23 04:20:17'),
(12, 3, 8, '2026-01-23 04:20:17'),
(13, 3, 2, '2026-01-23 04:20:17'),
(14, 3, 3, '2026-01-23 04:20:17'),
(15, 3, 23, '2026-01-23 04:20:17'),
(16, 3, 22, '2026-01-23 04:20:17'),
(17, 3, 21, '2026-01-23 04:20:17'),
(18, 3, 20, '2026-01-23 04:20:17'),
(19, 3, 16, '2026-01-23 04:20:17'),
(20, 3, 18, '2026-01-23 04:20:17'),
(21, 3, 17, '2026-01-23 04:20:17'),
(22, 3, 19, '2026-01-23 04:20:17'),
(23, 3, 15, '2026-01-23 04:20:17'),
(32, 2, 12, '2026-01-23 04:20:17'),
(34, 2, 11, '2026-01-23 04:20:17'),
(35, 2, 14, '2026-01-23 04:20:17'),
(36, 2, 10, '2026-01-23 04:20:17'),
(37, 2, 1, '2026-01-23 04:20:17'),
(38, 2, 7, '2026-01-23 04:20:17'),
(39, 2, 5, '2026-01-23 04:20:17'),
(40, 2, 8, '2026-01-23 04:20:17'),
(41, 2, 3, '2026-01-23 04:20:17'),
(42, 2, 21, '2026-01-23 04:20:17'),
(47, 1, 10, '2026-01-23 04:20:17'),
(48, 1, 8, '2026-01-23 04:20:17'),
(49, 1, 2, '2026-01-23 04:20:17');

-- --------------------------------------------------------

--
-- Table structure for table `trusted_device`
--

CREATE TABLE `trusted_device` (
  `device_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_token` varchar(255) NOT NULL,
  `device_fingerprint` varchar(255) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_name` text DEFAULT NULL,
  `last_used` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `trusted_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trusted_device`
--

INSERT INTO `trusted_device` (`device_id`, `user_id`, `device_token`, `device_fingerprint`, `user_agent`, `ip_address`, `device_name`, `last_used`, `created_at`, `expires_at`, `trusted_until`) VALUES
(1, 7, 'bf3cc0ccb3f54edc71cd6d5b69941415afff8388e47f3cb6a28ce9ca6b1e2828', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2025-12-30 16:45:50', '2025-12-30 16:45:31', '2026-01-29 09:45:31', '2026-01-29 09:45:31'),
(2, 5, '5aef65ae3985236749dabef861b7c1efb34d48b862137d1972ed622c06c1d1ae', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2025-12-30 22:24:00', '2025-12-30 16:47:12', '2026-01-29 09:47:12', '2026-01-29 09:47:12'),
(3, 5, '2d3fd21333d29680966f4b3eb241c9f1a56afc4c4b34bdcecb901dd52c0a699f', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-01 19:37:08', '2025-12-30 22:24:00', '2026-01-29 15:24:00', '2026-01-29 15:24:00'),
(4, 5, '5458b3fc2f37edfa5f9147a92a9190e03165ad46018feed722a6aeb8d15dd90c', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-01 20:04:39', '2026-01-01 19:37:08', '2026-01-31 12:37:08', '2026-01-31 12:37:08'),
(5, 5, '3bc381b84ecbd82e751b3e2183c3f9eda01e0f70ef0de3fc872a4abb30e06558', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-01 20:04:39', '2026-01-01 20:04:39', '2026-01-31 13:04:39', '2026-01-31 13:04:39'),
(6, 6, 'cd8bf763f9a24fe6770585f3aebae9f08cec8248753035a850edea34fd501782', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-04 12:19:17', '2026-01-04 12:19:17', '2026-02-03 05:19:17', '2026-02-03 05:19:17'),
(7, 5, '842319a35675e51e541698b1ccfb6b192da9f8dd7200c13693e8b9d0da931dfe', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-16 20:58:31', '2026-01-13 10:44:51', '2026-02-12 03:44:51', '2026-02-12 03:44:51'),
(8, 6, '2b3abde261425608dd193f8a6c9516752e371bca732e9715cbe4d864568d3965', 'f7391976a91e47cc6de2d2ad83460624b37883fdc8f0b7fd6f81d0a9fc3593aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Sa', '2026-01-26 16:32:32', '2026-01-13 10:48:26', '2026-02-25 09:32:32', '2026-02-25 09:32:32'),
(9, 7, '199073362b858ff8ea66fad22b04f9b43bb134f999dd36d13bb85a7ee985cfda', 'f07436dfc67d0f728de9dd8245af118d2933984c53bc1ab2eb56958adbe1ea97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-24 18:09:35', '2026-01-13 11:31:36', '2026-02-23 11:09:35', '2026-02-23 11:09:35'),
(10, 5, '816569cc6382dc9908ac4ad920131738e51a3c95adee84c502744d6532aa907e', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-16 20:58:31', '2026-01-16 20:58:31', '2026-02-15 13:58:31', '2026-02-15 13:58:31'),
(11, 6, '5fa62743b311208421f6c22a01f9c330d251c440d8c9ddd5c6a7c11a02ee27ba', 'd905266e38f24b2244e3d277e765e9413bfd1bcb8945aeb3c15f697c038d7ab3', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-16 21:00:44', '2026-01-16 21:00:44', '2026-02-15 14:00:44', '2026-02-15 14:00:44'),
(12, 5, 'ce931931bfb0bccf9d14b3db713906db42210e6a819c483ed91a6d0a26327c22', '8d01e1dab9201eba698bc6d5bccf7b341cdef6afc26442071c06be563193ce48', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Sa', '2026-01-26 16:27:01', '2026-01-16 21:13:42', '2026-02-25 09:27:01', '2026-02-25 09:27:01'),
(14, 6, '13420df1c623458c2c3944fafa3626b2c18e33d50aa77cd92427e7c49ec532fe', 'f07436dfc67d0f728de9dd8245af118d2933984c53bc1ab2eb56958adbe1ea97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Sa', '2026-01-18 16:39:11', '2026-01-18 16:38:49', '2026-02-17 09:39:11', '2026-02-17 09:39:11'),
(15, 7, '98a99aa97173af6c3d050e2c87cda9cb7a26d21f92aa5aa96e10e9c9d326051a', 'c373d35ef5d71c5f34ab04e20839e870a5ae1e36e1fffe7bd20cc2d78741abec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Sa', '2026-03-08 12:01:31', '2026-02-27 19:06:39', '2026-04-07 05:01:31', '2026-04-07 05:01:31'),
(16, 6, '42ea3528c4725c6216e157e9049364e2e17ce2d8c01eadc4d0524e12b5435d3c', 'ae19a203e0cfff11e538a46111a5ca11ec04ce8b6c32637852772ad9ac5b5078', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Sa', '2026-03-08 12:01:02', '2026-02-27 19:11:12', '2026-04-07 05:01:02', '2026-04-07 05:01:02'),
(17, 5, '07af97917bb9a8f972eb074ef2095cce862a35be48207c08e4a980a034e162a4', 'ae19a203e0cfff11e538a46111a5ca11ec04ce8b6c32637852772ad9ac5b5078', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Sa', '2026-03-08 12:34:41', '2026-03-01 12:05:06', '2026-04-07 05:34:41', '2026-04-07 05:34:41'),
(18, 7, 'f8418b39fc2940aef60deb10a9b410258e1759aeec195af567b4153187607ac2', 'ae19a203e0cfff11e538a46111a5ca11ec04ce8b6c32637852772ad9ac5b5078', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Sa', '2026-03-01 12:32:57', '2026-03-01 12:32:57', '2026-03-31 05:32:57', '2026-03-31 05:32:57'),
(19, 6, '70d519201790717567a67e0dafe524fde8e057b7ffa83dbdcb2dcdce499526f5', 'c373d35ef5d71c5f34ab04e20839e870a5ae1e36e1fffe7bd20cc2d78741abec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Sa', '2026-03-13 18:39:33', '2026-03-13 18:39:33', '2026-04-12 11:39:33', '2026-04-12 11:39:33'),
(20, 5, 'e339a9bfd9068d03caf15e090dc5c81cf6b7173c8a1d9a1c8bfb0b5fa63451f3', '5b43de8f7990ebd39b7f81d2a7febbc144fc308ede43c5371ed5a7d30cdf5eba', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Sa', '2026-03-20 13:37:13', '2026-03-14 12:49:23', '2026-04-19 06:37:13', '2026-04-19 06:37:13'),
(21, 6, 'c0539f2a91d937b90911224e378973571834327cbed78b34525e5f4b19485c18', '5b43de8f7990ebd39b7f81d2a7febbc144fc308ede43c5371ed5a7d30cdf5eba', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Sa', '2026-03-20 20:56:17', '2026-03-14 20:41:37', '2026-04-19 13:56:17', '2026-04-19 13:56:17');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(20) DEFAULT 'attendee',
  `role_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `phone`, `password`, `created_at`, `role`, `role_id`, `status`, `email_verified`, `phone_verified`, `verification_token`) VALUES
(1, 'Jennieyah', 'Cruz', 'Doee', 'female', 'jennie123@example.com', '01234569056', '$2y$10$PoT.4U9KxSENTnfXFu6SxOsF2kElQQIbFkTc.BqvoIdcTlgVuvfUe', '2025-06-11 20:31:30', 'admin', 3, 'active', 0, 0, NULL),
(2, 'Janee', 'Cruz', 'Doe', 'female', 'janedee123@gmail.com', '0123456988856', '$2y$10$g/8Ho1n068.ulYCQwaDke.GNX6ejkrXkOeDtWfIPyk5ryg0ZXiSbW', '2025-06-11 22:04:48', 'event_head', 2, 'active', 0, 0, NULL),
(5, 'Patricia Joy', 'Cuizon', 'Relente', 'female', 'patriciarelente03@gmail.com', '+639123453221', '$2y$10$g29juvrAWROwx3ZBgXPXROOKyLYD4CaGfp3pdvc80JULhNnr6Z.s2', '2025-11-18 04:40:46', 'admin', 3, 'active', 0, 0, NULL),
(6, 'Patricia Joyie', 'Cuizon', 'Relente', 'female', 'relente.patriciajoy@gmail.com', '09273328608', '$2y$10$7PcyhpKPZH.QKNDc49vI9OeXfPJmqIRLD9cL5qa3zQoERNoOP.sxu', '2025-12-19 17:05:22', 'event_head', 2, 'active', 0, 0, NULL),
(7, 'Patricia', 'Relente', 'Cuizon', 'female', 'tcuizon03@gmail.com', '09927198279', '$2y$10$OQWaHnOv1Dmi5NiLdbTeyOfW5SysL1xyzUFsT3MWcljOMEOeYrDr2', '2025-12-22 11:35:56', 'user', 1, 'active', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permission`
--

CREATE TABLE `user_permission` (
  `user_permission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted` tinyint(1) DEFAULT 1,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permission`
--

INSERT INTO `user_permission` (`user_permission_id`, `user_id`, `permission_id`, `granted`, `granted_by`, `granted_at`, `reason`) VALUES
(1, 7, 16, 1, 5, '2026-01-24 10:00:50', 'She is event head'),
(3, 7, 1, 1, 5, '2026-01-24 10:05:35', 'Test - allowing user to create events'),
(5, 7, 14, 1, 5, '2026-01-24 10:10:34', 'test'),
(6, 6, 13, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(7, 6, 10, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(9, 6, 12, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(10, 6, 11, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(11, 6, 9, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(17, 6, 1, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(18, 6, 7, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(19, 6, 5, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(20, 6, 8, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(21, 6, 3, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(22, 6, 21, 1, 5, '2026-01-26 02:19:22', 'Nothing change'),
(24, 6, 14, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(25, 6, 6, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(26, 6, 4, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(27, 6, 2, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(28, 6, 23, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(29, 6, 22, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(30, 6, 20, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(31, 6, 16, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(32, 6, 18, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(33, 6, 17, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(34, 6, 19, 0, 5, '2026-01-26 02:19:22', 'Nothing change'),
(35, 6, 15, 0, 5, '2026-01-26 02:19:22', 'Nothing change');

-- --------------------------------------------------------

--
-- Table structure for table `venue`
--

CREATE TABLE `venue` (
  `venue_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venue`
--

INSERT INTO `venue` (`venue_id`, `name`, `address`, `city`, `capacity`) VALUES
(1, 'Volleyball Courta', '#4 Sample Address', 'CCF', 21),
(2, 'Volleyball Court', '#4 Sample Address', 'Sample City', NULL),
(4, 'gsadgs', 'fsadfsdf', 'dsfsad', NULL),
(5, 'sdfa', 'sdf', 'sddfdg', NULL),
(6, 'Basketball Court', '#123 Sample Address', 'Sample City', NULL),
(7, 'CCF', ' 3F CCF Bldg., Prime St. Madrigal Business Park Ayala Alabang', 'Alabang', NULL),
(8, 'CCF Court Alabang', 'Alabang', 'Alabang', 70),
(9, 'CCF Court Alabang', 'Alabang', 'Alabang', NULL),
(11, 'CCF Court Alabang', 'Alabang', 'Alabang', NULL),
(12, 'test', 'test', 'test', NULL),
(13, 'test', 'test', 'test', NULL),
(14, 'test', 'test', 'test', NULL),
(15, 'Tennis Court', '#4 Sample Address', 'Alabang', 70),
(18, 'asd', 'adsd', 'asd', NULL),
(19, 'CCF Court Alabang', 'Alabang', 'Alabang', NULL),
(20, 'Alabang', 'Alabang', 'Alabang', NULL),
(21, 'CCF Court Alabang', 'test', 'test', NULL),
(22, 'Court', 'Alabang', 'Muntinlupa', NULL),
(23, 'Court', 'Alabang', 'Muntinlupa', NULL),
(24, 'Court', 'Alabang', 'Muntinlupa', NULL),
(25, 'Court', 'Alabang', 'Muntinlupa', NULL),
(26, 'CCF Center - Multi-purpose Hall', 'Frontera Verde, Ortigas Avenue corner C5 Road', 'Pasig City', NULL),
(27, 'CCF Center - Room 301', 'Frontera Verde, Ortigas Avenue corner C5 Road', 'Pasig City', NULL),
(28, 'CCF Satellite - Alabang', 'Prime St., Madrigal Business Park', 'Muntinlupa City', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_event`
--

CREATE TABLE `volunteer_event` (
  `volunteer_event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL COMMENT 'Used for public QR scan URL',
  `created_by` int(11) NOT NULL COMMENT 'user_id of the Admin who created it',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `volunteer_event`
--

INSERT INTO `volunteer_event` (`volunteer_event_id`, `title`, `description`, `event_date`, `location`, `qr_token`, `created_by`, `created_at`) VALUES
(1, 'Saturday Service', 'This is a saturday service volunteer', '2026-05-06 14:30:00', 'CCF Center', '7ffc60518a274408a2bffd1b1c749599', 5, '2026-03-20 13:51:44');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_member`
--

CREATE TABLE `volunteer_member` (
  `volunteer_member_id` int(11) NOT NULL,
  `role_type_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','confirmed') NOT NULL DEFAULT 'pending',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_role_type`
--

CREATE TABLE `volunteer_role_type` (
  `role_type_id` int(11) NOT NULL,
  `volunteer_event_id` int(11) NOT NULL,
  `role_name` enum('ushering','admin','technical') NOT NULL,
  `team_lead_id` int(11) DEFAULT NULL COMMENT 'user_id of the team lead',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `volunteer_role_type`
--

INSERT INTO `volunteer_role_type` (`role_type_id`, `volunteer_event_id`, `role_name`, `team_lead_id`, `created_at`) VALUES
(3, 1, 'ushering', 7, '2026-03-20 15:09:00'),
(4, 1, 'technical', 5, '2026-03-20 15:09:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_recovery_request`
--
ALTER TABLE `account_recovery_request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted` (`submitted_at`);

--
-- Indexes for table `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `fk_announcement_event` (`event_id`),
  ADD KEY `fk_announcement_user` (`sent_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`);

--
-- Indexes for table `backup_log`
--
ALTER TABLE `backup_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `unique_reminder` (`registration_id`,`email_type`);

--
-- Indexes for table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `fk_event_category` (`category_id`);

--
-- Indexes for table `event_access`
--
ALTER TABLE `event_access`
  ADD PRIMARY KEY (`access_id`),
  ADD UNIQUE KEY `unique_event_access` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `event_category`
--
ALTER TABLE `event_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`category_name`);

--
-- Indexes for table `event_table`
--
ALTER TABLE `event_table`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `uq_event_table` (`event_id`,`table_number`),
  ADD KEY `idx_event_id` (`event_id`);

--
-- Indexes for table `login_attempt`
--
ALTER TABLE `login_attempt`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `user_id` (`user_id`,`attempted_at`),
  ADD KEY `email` (`email`,`attempted_at`);

--
-- Indexes for table `organizer`
--
ALTER TABLE `organizer`
  ADD PRIMARY KEY (`organizer_id`);

--
-- Indexes for table `otp_code`
--
ALTER TABLE `otp_code`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_used` (`is_used`);

--
-- Indexes for table `otp_log`
--
ALTER TABLE `otp_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `otp_id` (`otp_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `registration_id` (`registration_id`);

--
-- Indexes for table `permission`
--
ALTER TABLE `permission`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `permission_id` (`permission_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `registration`
--
ALTER TABLE `registration`
  ADD PRIMARY KEY (`registration_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_registration_event` (`event_id`),
  ADD KEY `idx_qr_token` (`qr_token`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`role_permission_id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `trusted_device`
--
ALTER TABLE `trusted_device`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `device_token` (`device_token`),
  ADD KEY `device_token_2` (`device_token`),
  ADD KEY `user_id` (`user_id`,`device_fingerprint`),
  ADD KEY `idx_user_device` (`user_id`,`device_token`),
  ADD KEY `idx_trusted_until` (`trusted_until`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_role` (`role_id`);

--
-- Indexes for table `user_permission`
--
ALTER TABLE `user_permission`
  ADD PRIMARY KEY (`user_permission_id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `venue`
--
ALTER TABLE `venue`
  ADD PRIMARY KEY (`venue_id`);

--
-- Indexes for table `volunteer_event`
--
ALTER TABLE `volunteer_event`
  ADD PRIMARY KEY (`volunteer_event_id`),
  ADD UNIQUE KEY `uq_qr_token` (`qr_token`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `volunteer_member`
--
ALTER TABLE `volunteer_member`
  ADD PRIMARY KEY (`volunteer_member_id`),
  ADD UNIQUE KEY `uq_role_user` (`role_type_id`,`user_id`),
  ADD KEY `idx_role_type` (`role_type_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `volunteer_role_type`
--
ALTER TABLE `volunteer_role_type`
  ADD PRIMARY KEY (`role_type_id`),
  ADD UNIQUE KEY `uq_event_role` (`volunteer_event_id`,`role_name`),
  ADD KEY `idx_vol_event` (`volunteer_event_id`),
  ADD KEY `fk_vrt_lead` (`team_lead_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_recovery_request`
--
ALTER TABLE `account_recovery_request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement`
--
ALTER TABLE `announcement`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `backup_log`
--
ALTER TABLE `backup_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event`
--
ALTER TABLE `event`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `event_access`
--
ALTER TABLE `event_access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `event_category`
--
ALTER TABLE `event_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_table`
--
ALTER TABLE `event_table`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_attempt`
--
ALTER TABLE `login_attempt`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `organizer`
--
ALTER TABLE `organizer`
  MODIFY `organizer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `otp_code`
--
ALTER TABLE `otp_code`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `otp_log`
--
ALTER TABLE `otp_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `permission`
--
ALTER TABLE `permission`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `registration`
--
ALTER TABLE `registration`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `role_permission`
--
ALTER TABLE `role_permission`
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `trusted_device`
--
ALTER TABLE `trusted_device`
  MODIFY `device_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_permission`
--
ALTER TABLE `user_permission`
  MODIFY `user_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `venue`
--
ALTER TABLE `venue`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `volunteer_event`
--
ALTER TABLE `volunteer_event`
  MODIFY `volunteer_event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `volunteer_member`
--
ALTER TABLE `volunteer_member`
  MODIFY `volunteer_member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `volunteer_role_type`
--
ALTER TABLE `volunteer_role_type`
  MODIFY `role_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement`
--
ALTER TABLE `announcement`
  ADD CONSTRAINT `fk_announcement_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcement_user` FOREIGN KEY (`sent_by`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_registration` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`registration_id`) ON DELETE CASCADE;

--
-- Constraints for table `backup_log`
--
ALTER TABLE `backup_log`
  ADD CONSTRAINT `fk_backup_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_log`
--
ALTER TABLE `email_log`
  ADD CONSTRAINT `fk_emaillog_registration` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`registration_id`) ON DELETE CASCADE;

--
-- Constraints for table `event`
--
ALTER TABLE `event`
  ADD CONSTRAINT `event_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venue` (`venue_id`),
  ADD CONSTRAINT `event_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `organizer` (`organizer_id`),
  ADD CONSTRAINT `fk_event_category` FOREIGN KEY (`category_id`) REFERENCES `event_category` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `event_access`
--
ALTER TABLE `event_access`
  ADD CONSTRAINT `event_access_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_access_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `event_table`
--
ALTER TABLE `event_table`
  ADD CONSTRAINT `fk_et_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_code`
--
ALTER TABLE `otp_code`
  ADD CONSTRAINT `otp_code_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_log`
--
ALTER TABLE `otp_log`
  ADD CONSTRAINT `otp_log_ibfk_1` FOREIGN KEY (`otp_id`) REFERENCES `otp_code` (`otp_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registration` (`registration_id`) ON DELETE CASCADE;

--
-- Constraints for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  ADD CONSTRAINT `permission_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permission_audit_log_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permission` (`permission_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `permission_audit_log_ibfk_3` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `permission_audit_log_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `registration`
--
ALTER TABLE `registration`
  ADD CONSTRAINT `fk_registration_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registration_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD CONSTRAINT `role_permission_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permission_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permission` (`permission_id`) ON DELETE CASCADE;

--
-- Constraints for table `trusted_device`
--
ALTER TABLE `trusted_device`
  ADD CONSTRAINT `trusted_device_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`);

--
-- Constraints for table `user_permission`
--
ALTER TABLE `user_permission`
  ADD CONSTRAINT `user_permission_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permission` (`permission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `volunteer_member`
--
ALTER TABLE `volunteer_member`
  ADD CONSTRAINT `fk_vm_role` FOREIGN KEY (`role_type_id`) REFERENCES `volunteer_role_type` (`role_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vm_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_role_type`
--
ALTER TABLE `volunteer_role_type`
  ADD CONSTRAINT `fk_vrt_event` FOREIGN KEY (`volunteer_event_id`) REFERENCES `volunteer_event` (`volunteer_event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vrt_lead` FOREIGN KEY (`team_lead_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
