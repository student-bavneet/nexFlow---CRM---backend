-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 08:51 AM
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
-- Database: `nexflow_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'meeting',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `client_visible` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `organization_id`, `title`, `description`, `event_type`, `start_time`, `end_time`, `user_id`, `contact_id`, `company_id`, `deal_id`, `related_type`, `related_id`, `status`, `location`, `created_at`, `updated_at`, `client_visible`) VALUES
(28, 1, 'Client Website Demo Meeting', 'Discuss website requirements, project scope, timeline, and next steps with the client.', 'Product Demo', '2026-09-23 10:00:00', '2026-09-23 11:00:00', 1, 39, 13, 22, NULL, NULL, 'Completed', 'Google Meet – Client Demo', '2026-09-13 17:19:30', '2026-09-18 21:53:07', 1),
(29, 1, 'Website Requirements Discussion', 'Discuss final website requirements, project scope, delivery timeline, and client feedback.', 'Meeting', '2026-09-25 11:00:00', '2026-09-25 12:00:00', 1, 39, 13, NULL, 'contacts', 39, 'cancelled', 'Video Meeting', '2026-09-13 17:34:27', '2026-09-13 18:54:48', 0),
(30, 1, 'Lead Discovery & Requirements Call', 'Discuss the lead\'s requirements, understand project scope, budget, timeline, and next steps.', 'Video Call', '2026-09-27 10:00:00', '2026-09-27 10:45:00', 1, NULL, NULL, NULL, 'leads', 35, 'scheduled', NULL, '2026-09-13 17:39:33', '2026-09-13 18:55:10', 0),
(31, 1, 'Future Client Strategy Meeting', NULL, 'Meeting', '2026-09-15 14:00:00', '2026-09-15 15:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'completed', 'Video Meeting', '2026-09-13 17:43:44', '2026-09-13 18:54:56', 0),
(32, 1, 'dbcfmdns', NULL, 'Product Demo', '2026-09-18 10:00:00', '2026-09-18 11:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', '', '2026-09-13 17:54:58', '2026-09-13 18:54:43', 0),
(33, 1, 'jumjhmu', NULL, 'Product Demo', '2026-09-11 02:00:00', '2026-09-11 03:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', 'Video Meeting', '2026-09-13 17:58:48', '2026-09-13 18:54:46', 0),
(34, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:08', '2026-09-13 18:50:08', 0),
(35, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:32', '2026-09-13 18:50:32', 0),
(38, 1, 'Meeting: v sfdfvsdv', 'hvbfcxdm', 'Meeting', '2026-10-13 10:00:00', '2026-10-13 10:30:00', 1, NULL, NULL, 37, 'deals', 37, 'Scheduled', 'google meet', '2026-09-13 18:53:05', '2026-09-13 18:53:05', 0),
(39, 1, 'hello', 'hi', 'Product Demo', '2026-09-30 11:00:00', '2026-09-30 12:00:00', 1, 78, 14, 26, NULL, NULL, 'Scheduled', 'Google Meet – Client Demo', '2026-09-13 18:58:29', '2026-09-13 18:58:39', 0);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events_backup_20260918_181548`
--

CREATE TABLE `calendar_events_backup_20260918_181548` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'meeting',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `calendar_events_backup_20260918_181548`
--

INSERT INTO `calendar_events_backup_20260918_181548` (`id`, `organization_id`, `title`, `description`, `event_type`, `start_time`, `end_time`, `user_id`, `contact_id`, `company_id`, `deal_id`, `related_type`, `related_id`, `status`, `location`, `created_at`, `updated_at`) VALUES
(28, 1, 'Client Website Demo Meeting', 'Discuss website requirements, project scope, timeline, and next steps with the client.', 'Product Demo', '2026-09-23 10:00:00', '2026-09-23 11:00:00', 1, 39, 13, 22, NULL, NULL, 'completed', 'Google Meet – Client Demo', '2026-09-13 17:19:30', '2026-09-13 18:54:55'),
(29, 1, 'Website Requirements Discussion', 'Discuss final website requirements, project scope, delivery timeline, and client feedback.', 'Meeting', '2026-09-25 11:00:00', '2026-09-25 12:00:00', 1, 39, 13, NULL, 'contacts', 39, 'cancelled', 'Video Meeting', '2026-09-13 17:34:27', '2026-09-13 18:54:48'),
(30, 1, 'Lead Discovery & Requirements Call', 'Discuss the lead\'s requirements, understand project scope, budget, timeline, and next steps.', 'Video Call', '2026-09-27 10:00:00', '2026-09-27 10:45:00', 1, NULL, NULL, NULL, 'leads', 35, 'scheduled', NULL, '2026-09-13 17:39:33', '2026-09-13 18:55:10'),
(31, 1, 'Future Client Strategy Meeting', NULL, 'Meeting', '2026-09-15 14:00:00', '2026-09-15 15:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'completed', 'Video Meeting', '2026-09-13 17:43:44', '2026-09-13 18:54:56'),
(32, 1, 'dbcfmdns', NULL, 'Product Demo', '2026-09-18 10:00:00', '2026-09-18 11:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', '', '2026-09-13 17:54:58', '2026-09-13 18:54:43'),
(33, 1, 'jumjhmu', NULL, 'Product Demo', '2026-09-11 02:00:00', '2026-09-11 03:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', 'Video Meeting', '2026-09-13 17:58:48', '2026-09-13 18:54:46'),
(34, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:08', '2026-09-13 18:50:08'),
(35, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:32', '2026-09-13 18:50:32'),
(38, 1, 'Meeting: v sfdfvsdv', 'hvbfcxdm', 'Meeting', '2026-10-13 10:00:00', '2026-10-13 10:30:00', 1, NULL, NULL, 37, 'deals', 37, 'Scheduled', 'google meet', '2026-09-13 18:53:05', '2026-09-13 18:53:05'),
(39, 1, 'hello', 'hi', 'Product Demo', '2026-09-30 11:00:00', '2026-09-30 12:00:00', 1, 78, 14, 26, NULL, NULL, 'Scheduled', 'Google Meet – Client Demo', '2026-09-13 18:58:29', '2026-09-13 18:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events_backup_20260918_181553`
--

CREATE TABLE `calendar_events_backup_20260918_181553` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'meeting',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `client_visible` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `calendar_events_backup_20260918_181553`
--

INSERT INTO `calendar_events_backup_20260918_181553` (`id`, `organization_id`, `title`, `description`, `event_type`, `start_time`, `end_time`, `user_id`, `contact_id`, `company_id`, `deal_id`, `related_type`, `related_id`, `status`, `location`, `created_at`, `updated_at`, `client_visible`) VALUES
(28, 1, 'Client Website Demo Meeting', 'Discuss website requirements, project scope, timeline, and next steps with the client.', 'Product Demo', '2026-09-23 10:00:00', '2026-09-23 11:00:00', 1, 39, 13, 22, NULL, NULL, 'completed', 'Google Meet – Client Demo', '2026-09-13 17:19:30', '2026-09-13 18:54:55', 0),
(29, 1, 'Website Requirements Discussion', 'Discuss final website requirements, project scope, delivery timeline, and client feedback.', 'Meeting', '2026-09-25 11:00:00', '2026-09-25 12:00:00', 1, 39, 13, NULL, 'contacts', 39, 'cancelled', 'Video Meeting', '2026-09-13 17:34:27', '2026-09-13 18:54:48', 0),
(30, 1, 'Lead Discovery & Requirements Call', 'Discuss the lead\'s requirements, understand project scope, budget, timeline, and next steps.', 'Video Call', '2026-09-27 10:00:00', '2026-09-27 10:45:00', 1, NULL, NULL, NULL, 'leads', 35, 'scheduled', NULL, '2026-09-13 17:39:33', '2026-09-13 18:55:10', 0),
(31, 1, 'Future Client Strategy Meeting', NULL, 'Meeting', '2026-09-15 14:00:00', '2026-09-15 15:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'completed', 'Video Meeting', '2026-09-13 17:43:44', '2026-09-13 18:54:56', 0),
(32, 1, 'dbcfmdns', NULL, 'Product Demo', '2026-09-18 10:00:00', '2026-09-18 11:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', '', '2026-09-13 17:54:58', '2026-09-13 18:54:43', 0),
(33, 1, 'jumjhmu', NULL, 'Product Demo', '2026-09-11 02:00:00', '2026-09-11 03:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', 'Video Meeting', '2026-09-13 17:58:48', '2026-09-13 18:54:46', 0),
(34, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:08', '2026-09-13 18:50:08', 0),
(35, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:32', '2026-09-13 18:50:32', 0),
(38, 1, 'Meeting: v sfdfvsdv', 'hvbfcxdm', 'Meeting', '2026-10-13 10:00:00', '2026-10-13 10:30:00', 1, NULL, NULL, 37, 'deals', 37, 'Scheduled', 'google meet', '2026-09-13 18:53:05', '2026-09-13 18:53:05', 0),
(39, 1, 'hello', 'hi', 'Product Demo', '2026-09-30 11:00:00', '2026-09-30 12:00:00', 1, 78, 14, 26, NULL, NULL, 'Scheduled', 'Google Meet – Client Demo', '2026-09-13 18:58:29', '2026-09-13 18:58:39', 0);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events_backup_20260918_181929`
--

CREATE TABLE `calendar_events_backup_20260918_181929` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'meeting',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `client_visible` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `calendar_events_backup_20260918_181929`
--

INSERT INTO `calendar_events_backup_20260918_181929` (`id`, `organization_id`, `title`, `description`, `event_type`, `start_time`, `end_time`, `user_id`, `contact_id`, `company_id`, `deal_id`, `related_type`, `related_id`, `status`, `location`, `created_at`, `updated_at`, `client_visible`) VALUES
(28, 1, 'Client Website Demo Meeting', 'Discuss website requirements, project scope, timeline, and next steps with the client.', 'Product Demo', '2026-09-23 10:00:00', '2026-09-23 11:00:00', 1, 39, 13, 22, NULL, NULL, 'completed', 'Google Meet – Client Demo', '2026-09-13 17:19:30', '2026-09-13 18:54:55', 0),
(29, 1, 'Website Requirements Discussion', 'Discuss final website requirements, project scope, delivery timeline, and client feedback.', 'Meeting', '2026-09-25 11:00:00', '2026-09-25 12:00:00', 1, 39, 13, NULL, 'contacts', 39, 'cancelled', 'Video Meeting', '2026-09-13 17:34:27', '2026-09-13 18:54:48', 0),
(30, 1, 'Lead Discovery & Requirements Call', 'Discuss the lead\'s requirements, understand project scope, budget, timeline, and next steps.', 'Video Call', '2026-09-27 10:00:00', '2026-09-27 10:45:00', 1, NULL, NULL, NULL, 'leads', 35, 'scheduled', NULL, '2026-09-13 17:39:33', '2026-09-13 18:55:10', 0),
(31, 1, 'Future Client Strategy Meeting', NULL, 'Meeting', '2026-09-15 14:00:00', '2026-09-15 15:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'completed', 'Video Meeting', '2026-09-13 17:43:44', '2026-09-13 18:54:56', 0),
(32, 1, 'dbcfmdns', NULL, 'Product Demo', '2026-09-18 10:00:00', '2026-09-18 11:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', '', '2026-09-13 17:54:58', '2026-09-13 18:54:43', 0),
(33, 1, 'jumjhmu', NULL, 'Product Demo', '2026-09-11 02:00:00', '2026-09-11 03:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'scheduled', 'Video Meeting', '2026-09-13 17:58:48', '2026-09-13 18:54:46', 0),
(34, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:08', '2026-09-13 18:50:08', 0),
(35, 2, 'Client Demo Pitch', 'Demoing the new CRM release', 'Meeting', '2026-10-15 14:00:00', '2026-10-15 15:00:00', 7, NULL, NULL, 40, 'deals', 40, 'Scheduled', 'https://meet.google.com/xyz', '2026-09-13 18:50:32', '2026-09-13 18:50:32', 0),
(38, 1, 'Meeting: v sfdfvsdv', 'hvbfcxdm', 'Meeting', '2026-10-13 10:00:00', '2026-10-13 10:30:00', 1, NULL, NULL, 37, 'deals', 37, 'Scheduled', 'google meet', '2026-09-13 18:53:05', '2026-09-13 18:53:05', 0),
(39, 1, 'hello', 'hi', 'Product Demo', '2026-09-30 11:00:00', '2026-09-30 12:00:00', 1, 78, 14, 26, NULL, NULL, 'Scheduled', 'Google Meet – Client Demo', '2026-09-13 18:58:29', '2026-09-13 18:58:39', 0);

-- --------------------------------------------------------

--
-- Table structure for table `client_portal_users`
--

CREATE TABLE `client_portal_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `company_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','pending_verification') NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_portal_users`
--

INSERT INTO `client_portal_users` (`id`, `organization_id`, `company_id`, `contact_id`, `email`, `password_hash`, `status`, `remember_token`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 1, 13, 39, 'shweta@gmail.com', '$2y$12$QnGYmvD7amKO96UKsuJ/hOrYnzPnZzILpYVJ0LjGwCGNxl3oXvEuy', 'active', NULL, '2026-09-22 12:11:01', '::1', '2026-09-15 20:04:33', '2026-09-22 12:11:01');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `company_code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `legal_name` varchar(150) DEFAULT NULL,
  `domain` varchar(150) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `annual_revenue` decimal(15,2) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `founded_year` smallint(4) UNSIGNED DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `tax_registration_number` varchar(100) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `primary_contact_id` int(10) UNSIGNED DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `organization_id`, `company_code`, `name`, `legal_name`, `domain`, `industry`, `company_size`, `annual_revenue`, `location`, `founded_year`, `linkedin`, `tax_registration_number`, `relationship`, `owner_id`, `primary_contact_id`, `source`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(13, 1, 'COMP-000011', 'Apex Technologies Pvt Ltd', NULL, 'apextechnologies.test', 'Enterprise Software', '50–100 employees', 89.00, 'Chandigarh, India', 2010, 'linkedin.com/company/apex-technologies-pvt-ltd-example', 'TAX-IND-0000001234', 'Customer', 1, 39, NULL, 1, 1, '2026-09-09 12:39:46', '2026-09-11 12:59:56'),
(14, 1, 'COMP-000014', 'BrightPath Analytics Pvt Ltd', NULL, 'brightpath.test', 'Data & AI', '1–20 employees', 34.00, 'Mohali, India', 2019, 'linkedin.com/company/brightpath-example', 'TAX-IND-0000002345', 'Partner', 1, 78, NULL, 1, 1, '2026-09-09 12:44:28', '2026-09-11 11:51:25'),
(26, 1, 'COMP-000026', 'Nova Retail Group', 'Nova Retail Group', 'novaretail.com', 'Banking & Finance', '100–250 employees', 12.00, 'Mohali, India', NULL, NULL, NULL, 'Prospect', 1, 65, NULL, 1, 1, '2026-09-09 15:05:01', '2026-09-10 14:26:29'),
(33, 1, 'COMP-000027', 'honda', 'honda', 'honda.com', 'Financial Services', '500–1000 employees', 89.00, 'Jalandhar, India', NULL, NULL, NULL, 'Customer', 1, 8, NULL, 1, 1, '2026-09-09 15:21:52', '2026-09-10 13:54:08'),
(48, 1, 'COMP-000034', 'Vintage', NULL, 'vintage.io', 'Telecommunications', '20–50 employees', 45.00, 'Jalandhar, India', NULL, NULL, NULL, 'Prospect', 1, 29, NULL, 1, 1, '2026-09-10 11:54:25', '2026-09-10 11:54:25'),
(51, 1, 'COMP-000049', 'Heaven', NULL, 'heaven.com', 'Cloud Infrastructure', '20–50 employees', 89.00, 'Jalandhar, India', NULL, NULL, NULL, 'Customer', 1, 53, NULL, 1, 1, '2026-09-10 12:06:55', '2026-09-10 15:48:45'),
(56, 1, 'COMP-000052', 'Spec Tech Pvt Ltd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Prospect', NULL, 58, NULL, 1, 1, '2026-09-10 12:46:40', '2026-09-11 11:51:52'),
(73, 1, NULL, 'Acme Corporation', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-09-13 00:30:36', '2026-09-13 00:30:36'),
(74, 1, 'COMP-000074', 'CloudCorp Global', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 1, '2026-09-13 00:31:19', '2026-09-13 00:31:19'),
(75, 1, 'COMP-000075', 'kvxvjb', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 1, '2026-09-13 00:34:18', '2026-09-13 00:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `contact_code` varchar(50) DEFAULT NULL,
  `first_name` varchar(75) DEFAULT NULL,
  `last_name` varchar(75) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `clean_phone` varchar(50) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `preferred_channel` varchar(50) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `lead_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `organization_id`, `contact_code`, `first_name`, `last_name`, `name`, `company_id`, `company_name`, `job_title`, `email`, `phone`, `clean_phone`, `whatsapp`, `preferred_channel`, `linkedin`, `relationship`, `owner_id`, `source`, `location`, `lead_id`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 1, 'CNT-008', 'Rahul', 'Sharma', 'Rahul Sharma', 13, 'Apex Technologies Pvt Ltd', 'CTO', 'rahul.sharma@apextechnologies.test', '+91 98765 10001', '919876510001', '+91 98765 10001', NULL, NULL, 'Customer', 1, 'Website', 'Chandigarh, India', NULL, 1, 1, '2026-09-09 13:10:44', '2026-09-09 14:42:48'),
(17, 1, 'CNT-017', 'Ram', 'Kapoor', 'Ram Kapoor', 26, 'Nova Retail Group', 'IT Director', 'ram@gmail.com', '+91 9876542349', '919876542349', '+91 9876542349', NULL, NULL, 'Prospect', 1, 'Website', 'Gangtok, India', NULL, 1, 1, '2026-09-09 15:05:01', '2026-09-09 15:05:01'),
(29, 1, 'CNT-029', 'Priya', 'Sharma', 'Priya Sharma', 33, 'honda', 'Senior IT Director', 'priyasharma@gmail.com', '+91 98765 10001', '919876510001', '+91 98765 10001', NULL, NULL, 'Prospect', 1, 'Website', 'Ludhiana, India', NULL, 1, 1, '2026-09-09 15:21:52', '2026-09-10 13:34:21'),
(38, 1, 'CNT-038', 'Sham', 'Sharma', 'Sham Sharma', 33, 'honda', 'CTO', 'sham@gmail.com', '+91 9823434543', '919823434543', '+91 9823434543', NULL, NULL, 'Prospect', 1, 'Website', 'Ludhiana, India', NULL, 1, 1, '2026-09-09 15:28:00', '2026-09-09 15:28:00'),
(39, 1, 'CNT-039', 'Shweta', 'Singh Updated', 'Shweta Singh Updated', 13, 'Apex Technologies Pvt Ltd', 'VP of Engineering', 'shweta@gmail.com', '+91 9888877777', '919888877777', '+91 877898989', NULL, NULL, 'Prospect', 1, 'Website', 'Chandigarh, India', NULL, 1, 1, '2026-09-09 15:29:28', '2026-09-21 23:32:10'),
(53, 1, 'CNT-053', 'Manoj', 'Sharma', 'Manoj Sharma', 51, 'Heaven', 'Chief Technology Officer', 'manoj@gmail.com', '7654345645', '7654345645', NULL, NULL, NULL, 'Customer', 1, 'Website', 'Ludhiana, Punjab', NULL, 1, 1, '2026-09-10 12:06:55', '2026-09-11 22:18:08'),
(58, 1, 'CNT-058', 'Harman', 'Kaur', 'Harman Kaur', 56, 'Spec Tech Pvt Ltd', 'IT Director', 'harman@gmail.com', '9878342312', '9878342312', '9878342312', NULL, NULL, 'Partner', 1, 'Website', 'Chandigarh, India', NULL, 1, 1, '2026-09-10 12:46:40', '2026-09-10 12:46:40'),
(65, 1, 'CNT-065', 'Meenakshi', NULL, 'Meenakshi', 26, 'Nova Retail Group', 'Chief Technology Officer', 'meena@gmail.com', '9871234321', '9871234321', NULL, NULL, NULL, 'Prospect', 1, 'Website', 'Ludhiana, Punjab', NULL, 1, 1, '2026-09-10 14:14:36', '2026-09-10 14:26:29'),
(72, 1, 'CNT-072', 'raman', 'kaur', 'raman kaur', 56, 'Spec Tech Pvt Ltd', 'IT Director', 'rman@gmail.com', '2341234321', '2341234321', '2341234321', 'Video Call', 'linkedin.com/in/raman-example', 'Prospect', 1, 'Website', 'Ludhiana, India', NULL, 1, 1, '2026-09-10 14:36:00', '2026-09-10 14:36:00'),
(78, 1, 'CNT-078', 'harpreet', 'kaur', 'harpreet kaur', 14, 'BrightPath Analytics Pvt Ltd', 'CTO', 'harpreet@gmail.com', '1234567898', '1234567898', '1234567898', 'WhatsApp', 'linkedin.com/in/harpreet-example', 'Prospect', 1, 'Website', 'Ludhiana, India', NULL, 1, 1, '2026-09-11 11:48:48', '2026-09-11 11:48:48'),
(91, 1, 'CNT-091', 'vbdkfmv', NULL, 'vbdkfmv', 75, 'kvxvjb', NULL, 'rahul@gmail.io', '563453', '563453', '4535t34', 'Email & Calls', NULL, 'Prospect', 1, 'Website', 'dfvdxsvd', 29, 1, 1, '2026-09-13 00:34:18', '2026-09-13 00:34:18'),
(92, 2, NULL, NULL, NULL, 'Org 2 Contact', NULL, NULL, NULL, 'org2@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-09-13 19:57:46', '2026-09-13 19:57:46'),
(104, 1, NULL, 'Temp', 'User', 'Temp Test User', 13, NULL, NULL, 'temp_test_user_1789906970@nexflow.test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-09-20 17:52:50', '2026-09-20 17:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `contact_companies`
--

CREATE TABLE `contact_companies` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL,
  `company_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_companies`
--

INSERT INTO `contact_companies` (`id`, `organization_id`, `contact_id`, `company_id`, `created_at`, `updated_at`) VALUES
(1, 1, 8, 13, '2026-09-09 13:10:44', '2026-09-09 21:54:02'),
(2, 1, 17, 26, '2026-09-09 15:05:01', '2026-09-09 21:54:02'),
(3, 1, 29, 33, '2026-09-09 15:21:52', '2026-09-09 21:54:02'),
(4, 1, 38, 33, '2026-09-09 15:28:00', '2026-09-09 21:54:02'),
(5, 1, 39, 13, '2026-09-09 15:29:28', '2026-09-09 21:54:02'),
(11, 1, 29, 48, '2026-09-10 11:54:25', '2026-09-10 11:54:25'),
(15, 1, 53, 51, '2026-09-10 12:06:55', '2026-09-10 12:06:55'),
(23, 1, 58, 56, '2026-09-10 12:46:40', '2026-09-10 12:46:40'),
(38, 1, 8, 51, '2026-09-10 13:48:31', '2026-09-10 13:48:31'),
(43, 1, 8, 33, '2026-09-10 13:54:08', '2026-09-10 13:54:08'),
(48, 1, 65, 26, '2026-09-10 14:14:36', '2026-09-10 14:14:36'),
(66, 1, 72, 56, '2026-09-10 14:36:00', '2026-09-10 14:36:00'),
(74, 1, 78, 14, '2026-09-11 11:48:48', '2026-09-11 11:48:48'),
(89, 1, 91, 75, '2026-09-13 00:34:18', '2026-09-13 00:34:18'),
(90, 1, 8, 73, '2026-09-13 19:57:46', '2026-09-13 19:57:46');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `client_email` varchar(180) DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(30) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Draft','Sent','Viewed','Pending Signature','Changes Requested','Revised','Signed','Active','Expiring Soon','Expired','Declined') NOT NULL DEFAULT 'Draft',
  `signature_status` enum('Not Sent','Sent','Viewed','Signed','Declined','Draft','Expired') NOT NULL DEFAULT 'Not Sent',
  `is_client_signed` tinyint(1) NOT NULL DEFAULT 0,
  `signed_date` datetime DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `renewal_type` enum('Auto-renew','Manual Renewal','No Renewal') DEFAULT NULL,
  `notice_days` int(10) UNSIGNED DEFAULT NULL,
  `next_renewal_date` date DEFAULT NULL,
  `overview` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `payment_terms` text DEFAULT NULL,
  `change_request_text` text DEFAULT NULL,
  `change_request_contact` varchar(150) DEFAULT NULL,
  `change_request_date` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `organization_id`, `contract_number`, `title`, `reference_number`, `company_id`, `company_name`, `contact_id`, `contact_name`, `client_email`, `type`, `value`, `currency`, `start_date`, `end_date`, `status`, `signature_status`, `is_client_signed`, `signed_date`, `owner_id`, `renewal_type`, `notice_days`, `next_renewal_date`, `overview`, `terms`, `payment_terms`, `change_request_text`, `change_request_contact`, `change_request_date`, `created_by`, `created_at`, `updated_at`) VALUES
(12, 1, 'CON-002', 'Website Development Services Agreement', NULL, 13, 'Apex Technologies Pvt Ltd', 39, 'Shweta Singh', 'shweta@gmail.com', 'Master Services Agreement (MSA)', 342200.00, 'USD ($)', '2026-09-12', '2027-09-15', 'Viewed', 'Viewed', 0, NULL, 1, '', 30, '2027-09-15', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-12 13:11:13', '2026-09-18 19:08:01'),
(14, 1, 'CON-003', 'Graphic design Service', 'BRIG-GD-2026-001', 14, 'BrightPath Analytics Pvt Ltd', 78, 'harpreet kaur', 'harpreet@gmail.com', 'Master Services Agreement (MSA)', 342200.00, 'USD ($)', '2026-09-12', '2027-09-12', 'Sent', 'Sent', 0, NULL, 1, 'Auto-renew', 10, '2027-09-12', 'Agreement for UI/UX design, frontend development, backend development, testing, and deployment of the Apex Technologies website.', 'Project work will follow the agreed scope and milestones. Client feedback should be provided within 3 business days. Critical issues will receive priority support during the agreed service period.', '50% advance upon agreement signing, 50% upon final website deployment.', NULL, NULL, NULL, 1, '2026-09-12 21:40:07', '2026-09-12 21:41:56');

-- --------------------------------------------------------

--
-- Table structure for table `contract_files`
--

CREATE TABLE `contract_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contract_files`
--

INSERT INTO `contract_files` (`id`, `organization_id`, `contract_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `uploaded_by`, `created_at`) VALUES
(3, 1, 12, 'Lorem Ipsum.pdf', 'uploads/contracts/1/12/1789227920_Lorem_Ipsum.pdf', '28.8 KB', 'application/pdf', 1, '2026-09-12 21:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `crm_custom_fields`
--

CREATE TABLE `crm_custom_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `field_key` varchar(100) NOT NULL,
  `field_label` varchar(150) NOT NULL,
  `target_object` varchar(50) NOT NULL,
  `field_type` varchar(50) NOT NULL DEFAULT 'Text',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_custom_fields`
--

INSERT INTO `crm_custom_fields` (`id`, `organization_id`, `field_key`, `field_label`, `target_object`, `field_type`, `is_required`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'budget_range', 'Budget Range', 'Leads', 'Dropdown', 0, 1, 1, NULL, '2026-09-07 14:54:09', '2026-09-07 14:54:09'),
(2, 1, 'contract_start_date', 'Contract Start Date', 'Deals', 'Date', 1, 1, 2, NULL, '2026-09-07 14:54:09', '2026-09-07 14:54:09'),
(3, 1, 'tax_registration_number', 'Tax Registration Number', 'Companies', 'Text', 0, 1, 3, NULL, '2026-09-07 14:54:09', '2026-09-07 14:54:09'),
(4, 1, 'decision_maker_score', 'Decision Maker Score', 'Contacts', 'Number', 0, 1, 4, NULL, '2026-09-07 14:54:09', '2026-09-07 14:54:09');

-- --------------------------------------------------------

--
-- Table structure for table `crm_custom_field_options`
--

CREATE TABLE `crm_custom_field_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `custom_field_id` int(10) UNSIGNED NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_custom_field_options`
--

INSERT INTO `crm_custom_field_options` (`id`, `custom_field_id`, `option_value`, `option_label`, `sort_order`, `created_at`) VALUES
(1, 1, 'Under $10,000', 'Under $10,000', 1, '2026-09-07 14:54:09'),
(2, 1, '$10,000 - $25,000', '$10,000 - $25,000', 2, '2026-09-07 14:54:09'),
(3, 1, '$25,000 - $50,000', '$25,000 - $50,000', 3, '2026-09-07 14:54:09'),
(4, 1, '$50,000+', '$50,000+', 4, '2026-09-07 14:54:09');

-- --------------------------------------------------------

--
-- Table structure for table `crm_custom_field_values`
--

CREATE TABLE `crm_custom_field_values` (
  `id` int(10) UNSIGNED NOT NULL,
  `custom_field_id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `field_value` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_integrations`
--

CREATE TABLE `crm_integrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `integration_key` varchar(50) NOT NULL,
  `integration_name` varchar(100) NOT NULL,
  `integration_type` varchar(50) NOT NULL,
  `status` enum('not_connected','pending','connected','disconnected','error') NOT NULL DEFAULT 'not_connected',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `config_json` text DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `disconnected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crm_integrations`
--

INSERT INTO `crm_integrations` (`id`, `organization_id`, `integration_key`, `integration_name`, `integration_type`, `status`, `is_enabled`, `config_json`, `connected_at`, `disconnected_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'google_calendar', 'Google Calendar', 'calendar', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00'),
(2, 1, 'outlook_calendar', 'Outlook Calendar', 'calendar', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00'),
(3, 1, 'gmail', 'Gmail Integration', 'email', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00'),
(4, 1, 'whatsapp_business', 'WhatsApp Business API', 'messaging', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00'),
(5, 1, 'twilio', 'Twilio Softphone', 'calling', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00'),
(6, 1, 'zapier', 'Zapier Webhooks', 'automation', 'not_connected', 0, NULL, NULL, NULL, '2026-09-07 21:04:37', '2026-09-13 22:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `crm_settings`
--

CREATE TABLE `crm_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_settings`
--

INSERT INTO `crm_settings` (`id`, `organization_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(120, 1, 'default_lead_status', 'New', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(121, 1, 'default_lead_source', 'Website', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(122, 1, 'lead_score_threshold', '70', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(123, 1, 'inactivity_threshold_days', '14', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(124, 1, 'lead_scoring_enabled', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(125, 1, 'duplicate_detection_enabled', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(126, 1, 'auto_followup_enabled', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(127, 1, 'task_default_status', 'Pending', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(128, 1, 'task_default_priority', 'Medium', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(129, 1, 'task_default_reminder', '15 minutes before', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(130, 1, 'task_default_view', 'list', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(131, 1, 'task_show_completed', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(132, 1, 'calling_default_country_code', '+44', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(133, 1, 'calling_default_status', 'Busy', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(134, 1, 'calling_auto_open_notes', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(135, 1, 'calling_show_floating_widget', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(136, 1, 'email_include_signature', '1', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(137, 1, 'inbox_mark_read_on_open', '0', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(138, 1, 'inbox_show_contact_context', '0', '2026-09-13 22:44:00', '2026-09-13 22:44:00'),
(140, 1, 'client_user_1_timezone', 'Asia/Kolkata', '2026-09-20 17:52:50', '2026-09-21 23:32:10'),
(144, 1, 'client_user_1_notifications', '{\"email_summaries\":1,\"in_portal\":1}', '2026-09-20 17:53:28', '2026-09-21 23:32:11');

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `object_name` varchar(100) NOT NULL,
  `field_name` varchar(150) NOT NULL,
  `field_type` varchar(80) NOT NULL DEFAULT 'Short Text',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_reports`
--

CREATE TABLE `custom_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Sales',
  `data_source` varchar(50) NOT NULL DEFAULT 'Deals',
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `group_by` varchar(50) NOT NULL DEFAULT 'Month',
  `chart_type` varchar(50) NOT NULL DEFAULT 'line',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `custom_reports`
--

INSERT INTO `custom_reports` (`id`, `organization_id`, `created_by`, `name`, `category`, `data_source`, `metrics`, `group_by`, `chart_type`, `config`, `created_at`) VALUES
(1, 1, 1, 'Monthly Pipeline Review', 'Pipeline', 'Deals', '[\"Revenue\"]', 'Stage', 'bar', NULL, '2026-09-13 22:29:06');

-- --------------------------------------------------------

--
-- Table structure for table `deals`
--

CREATE TABLE `deals` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `lead_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `stage` varchar(50) NOT NULL DEFAULT 'Prospect',
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `probability` decimal(5,2) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `priority` varchar(30) DEFAULT NULL,
  `product` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','won','lost') NOT NULL DEFAULT 'open',
  `lost_reason` varchar(255) DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deals`
--

INSERT INTO `deals` (`id`, `organization_id`, `lead_id`, `contact_id`, `deal_code`, `name`, `contact_name`, `company`, `stage`, `value`, `probability`, `source`, `priority`, `product`, `description`, `status`, `lost_reason`, `assigned_to`, `close_date`, `created_at`, `updated_at`) VALUES
(22, 1, NULL, 39, 'DL-022', 'Website Development Deal', 'Shweta Singh', 'Apex Technologies Pvt Ltd', 'Qualified', 50000.00, 25.00, 'Website', 'Medium', 'edbcjkwebcb', 'bcmdsb cms', 'open', NULL, 1, '2026-09-18', '2026-09-11 13:07:43', '2026-09-22 12:11:58'),
(23, 1, NULL, 39, 'DL-023', 'CRM Subscription', 'Shweta Singh', 'Apex Technologies Pvt Ltd', 'Qualified', 75000.00, 35.00, 'Website', 'Medium', 'Subscription', 'CRM Subscription deal', 'open', NULL, 1, '2026-09-14', '2026-09-11 13:12:08', '2026-09-11 13:59:30'),
(26, 1, NULL, 78, NULL, 'graphic design', 'harpreet kaur', 'BrightPath Analytics Pvt Ltd', 'Qualified', 50000.00, 30.00, 'Website', 'Medium', 'Product design', 'Design the sales product', 'open', NULL, 1, '2026-09-29', '2026-09-11 22:13:32', '2026-09-22 12:11:55'),
(27, 1, NULL, 65, 'DL-C22234', 'Video editing', 'Meenakshi', 'Nova Retail Group', 'Closed Won', 26000.00, 20.00, 'Outreach', 'Medium', 'Product Editing', 'Edit sales product', 'won', NULL, 1, '2026-09-22', '2026-09-11 22:15:41', '2026-09-22 12:12:27'),
(29, 1, 29, NULL, 'DL-0029', 'kvxvjb - Sales Pipeline', NULL, 'kvxvjb', 'Prospect', 25000.00, NULL, NULL, NULL, NULL, NULL, 'open', NULL, 1, '2026-10-13', '2026-09-13 00:16:48', '2026-09-13 00:16:48'),
(30, 1, 27, NULL, 'DL-0030', 'TechNova Solutions - Sales Pipeline', NULL, 'TechNova Solutions', 'Prospect', 25000.00, NULL, NULL, NULL, NULL, NULL, 'open', NULL, 1, '2026-10-13', '2026-09-13 00:20:40', '2026-09-13 00:20:40'),
(36, 1, NULL, NULL, 'DEAL-2026-001', 'CRM Website Development', 'Rahul Mehta', 'Apex Technologies Pvt Ltd', 'Closed Won', 30000.00, 60.00, 'Direct Admin Input', 'Medium', 'CRM customization and website development', 'Customer requires a CRM website with custom dashboard, user management and reporting.', 'won', NULL, 1, '2026-09-22', '2026-09-13 00:41:09', '2026-09-22 12:12:12'),
(37, 1, NULL, NULL, 'DEAL-2026-002', 'v sfdfvsdv', 'vcsghcgfj', 'fvhjbcfhbcfh', 'Prospect', 0.00, 60.00, 'Direct Admin Input', 'Medium', 'fgsgvfsgvbs', 'fbxdbfsxbfsr', 'open', NULL, 1, '2026-10-13', '2026-09-13 00:42:37', '2026-09-22 12:11:30'),
(40, 2, NULL, NULL, NULL, 'Test Deal', NULL, NULL, 'lead', 1000.00, NULL, NULL, NULL, NULL, NULL, 'open', NULL, NULL, NULL, '2026-09-13 18:49:50', '2026-09-13 18:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `document_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `stored_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_extension` varchar(20) DEFAULT NULL,
  `file_size` varchar(50) NOT NULL,
  `file_size_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `category` varchar(100) NOT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `lead_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `proposal_id` int(10) UNSIGNED DEFAULT NULL,
  `contract_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_id` int(10) UNSIGNED DEFAULT NULL,
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `related_record` varchar(200) DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `organization_id`, `document_code`, `title`, `original_filename`, `stored_filename`, `file_path`, `file_extension`, `file_size`, `file_size_bytes`, `mime_type`, `document_type`, `category`, `company_id`, `contact_id`, `lead_id`, `deal_id`, `project_id`, `proposal_id`, `contract_id`, `invoice_id`, `subscription_id`, `related_type`, `related_id`, `related_record`, `owner_id`, `uploaded_by`, `status`, `is_archived`, `is_shared`, `description`, `created_at`, `updated_at`) VALUES
(8, 1, 'DOC-001', 'Logo Image - Graphic Design', 'crm-logo.jpg', '1789153685_crm-logo.jpg', 'uploads/documents/1/8/1789153685_crm-logo.jpg', 'jpg', '55.1 KB', 56391, 'image/jpeg', 'Image', 'Projects', 14, NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, 'project', 7, 'PRJ-003 — graphic design', 1, 1, 'Available', 0, 1, 'Logo Image for graphic design project', '2026-09-12 00:38:05', '2026-09-12 00:39:28'),
(10, 1, 'DOC-002', 'Website Requirements', 'gig pdf.pdf', '1789154156_gig_pdf.pdf', 'uploads/documents/1/10/1789154156_gig_pdf.pdf', 'pdf', '126 KB', 129042, 'application/pdf', 'PDF', 'Projects', 13, NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, 'project', 3, 'PRJ-001 — Website Development Project', 1, 1, 'Available', 0, 0, 'Website requirements document.', '2026-09-12 00:45:56', '2026-09-12 00:48:51');

-- --------------------------------------------------------

--
-- Table structure for table `estimate_requests`
--

CREATE TABLE `estimate_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `request_code` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `services` varchar(255) NOT NULL,
  `budget` varchar(100) DEFAULT NULL,
  `timeline` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('New','In Review','More Info Needed','Approved','Rejected','Converted') NOT NULL DEFAULT 'New',
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `preferred_start_date` date DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `estimate_requests`
--

INSERT INTO `estimate_requests` (`id`, `organization_id`, `request_code`, `subject`, `company_id`, `company_name`, `contact_id`, `contact_name`, `email`, `phone`, `services`, `budget`, `timeline`, `source`, `owner_id`, `status`, `description`, `requirements`, `preferred_start_date`, `attachments`, `deal_id`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 1, 'REQ-2026-001', 'CRM Website Development', 13, 'Apex Technologies Pvt Ltd', NULL, 'Rahul Mehta', 'rahul.mehta@example.com', '+91 98765 43210', 'CRM customization and website development', '$20,000 - $30,000', '30 - 60 Days', 'Direct Admin Input', 1, 'Converted', 'Customer requires a CRM website with custom dashboard, user management and reporting.', '', NULL, '[]', 36, 1, '2026-09-13 00:38:43', '2026-09-13 00:41:09'),
(6, 1, 'REQ-2026-002', 'sales', 33, 'honda', 29, 'test', 'priya@gmail.com', '56t4363', 'sales', 'fbbfxbfd', 'Immediate (Within 30 Days)', 'Direct Admin Input', 1, 'Converted', 'fbxdbfsxbfsr', '', NULL, '[]', 37, 1, '2026-09-13 00:42:32', '2026-09-16 18:50:10');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `expense_date` date NOT NULL,
  `merchant` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(30) NOT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `billing_type` enum('Billable','Non-Billable') NOT NULL,
  `invoice_status` enum('Not Invoiced','Invoiced') NOT NULL,
  `invoice_reference` varchar(100) DEFAULT NULL,
  `invoiced_at` datetime DEFAULT NULL,
  `invoiced_by` int(10) UNSIGNED DEFAULT NULL,
  `reimbursement_status` enum('N/A','Pending','Reimbursed') NOT NULL,
  `reimbursed_at` datetime DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `receipt_mime` varchar(100) DEFAULT NULL,
  `receipt_size` int(11) DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `organization_id`, `reference_number`, `title`, `expense_date`, `merchant`, `category`, `amount`, `currency`, `tax_amount`, `billing_type`, `invoice_status`, `invoice_reference`, `invoiced_at`, `invoiced_by`, `reimbursement_status`, `reimbursed_at`, `company_id`, `project_id`, `payment_method`, `description`, `receipt_filename`, `receipt_path`, `receipt_mime`, `receipt_size`, `owner_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(28, 1, 'EXP-20260912-001', 'Google Ads Campaign', '2026-09-12', 'Google Ads', 'Marketing', 3000.00, 'USD ($)', 0.00, 'Billable', 'Not Invoiced', NULL, NULL, NULL, 'N/A', NULL, 13, 3, 'Corporate Visa •••• 4242', 'Google Ads campaign expense for Apex Technologies website project.', 'Lorem Ipsum.pdf', 'uploads/expenses/1/rcpt_0af923ccce6bbe56bb8a86e3.pdf', 'application/pdf', 29542, 1, 1, 1, '2026-09-12 18:06:08', '2026-09-12 18:09:51'),
(29, 1, 'EXP-20260912-002', 'Office Electricity Bill', '2026-09-12', 'Punjab State Power Corporation', 'Other', 1800.00, 'USD ($)', 0.00, 'Non-Billable', 'Not Invoiced', NULL, NULL, NULL, 'N/A', NULL, NULL, NULL, 'Corporate ACH Bank Wire', 'Monthly office electricity expense.', NULL, NULL, NULL, NULL, 1, 1, 1, '2026-09-12 18:12:14', '2026-09-12 18:12:14'),
(30, 1, 'EXP-20260912-003', 'Client Travel Expense', '2026-09-12', 'Indigo Airlines', 'Travel', 2500.00, 'USD ($)', 0.00, 'Billable', 'Not Invoiced', '', NULL, NULL, 'N/A', NULL, 13, 3, 'Corporate Visa •••• 4242', 'Travel expense incurred for the client project.', NULL, NULL, NULL, NULL, 1, 1, 1, '2026-09-12 18:14:05', '2026-09-12 18:14:05');

-- --------------------------------------------------------

--
-- Table structure for table `inbox_conversations`
--

CREATE TABLE `inbox_conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `conversation_code` varchar(50) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `lead_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `team_id` int(10) UNSIGNED DEFAULT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'Email',
  `subject` varchar(255) NOT NULL,
  `preview` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Open',
  `priority` varchar(30) NOT NULL DEFAULT 'Normal',
  `is_unread` tinyint(1) NOT NULL DEFAULT 1,
  `is_starred` tinyint(1) NOT NULL DEFAULT 0,
  `snoozed_until` datetime DEFAULT NULL,
  `last_message_at` datetime NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inbox_conversations`
--

INSERT INTO `inbox_conversations` (`id`, `organization_id`, `conversation_code`, `contact_id`, `company_id`, `lead_id`, `deal_id`, `assigned_to`, `team_id`, `channel`, `subject`, `preview`, `status`, `priority`, `is_unread`, `is_starred`, `snoozed_until`, `last_message_at`, `created_by`, `created_at`, `updated_at`) VALUES
(24, 1, 'CONV-24', 65, 13, NULL, 22, 1, NULL, 'Email', 'Website Project Follow Up', 'hlo', 'Open', 'Normal', 1, 0, NULL, '2026-09-20 17:09:08', 1, '2026-09-13 21:16:02', '2026-09-20 17:09:54');

-- --------------------------------------------------------

--
-- Table structure for table `inbox_drafts`
--

CREATE TABLE `inbox_drafts` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'Email',
  `recipient` varchar(255) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inbox_messages`
--

CREATE TABLE `inbox_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `sender_type` varchar(30) NOT NULL DEFAULT 'contact',
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `sender_name` varchar(150) NOT NULL,
  `sender_email` varchar(180) DEFAULT NULL,
  `sender_phone` varchar(50) DEFAULT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'Email',
  `direction` varchar(30) NOT NULL DEFAULT 'inbound',
  `body` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inbox_messages`
--

INSERT INTO `inbox_messages` (`id`, `organization_id`, `conversation_id`, `sender_type`, `sender_id`, `sender_name`, `sender_email`, `sender_phone`, `channel`, `direction`, `body`, `sent_at`, `created_at`) VALUES
(34, 1, 24, 'user', 1, 'Olivia Carter', 'olivia@novasphere.example', '+1 (555) 987-6543', 'Email', 'outbound', 'hi', '2026-09-13 21:16:02', '2026-09-13 21:16:02'),
(98, 1, 24, 'user', 1, 'Olivia Carter', 'olivia@novasphere.example', '+1 (555) 987-6543', 'Email', 'outbound', 'hi', '2026-09-20 17:09:00', '2026-09-20 17:09:00'),
(99, 1, 24, 'contact', 39, 'Shweta Singh', 'shweta@gmail.com', '+91 877898989', 'Email', 'inbound', 'hlo', '2026-09-20 17:09:08', '2026-09-20 17:09:08');

-- --------------------------------------------------------

--
-- Table structure for table `industries`
--

CREATE TABLE `industries` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(80) NOT NULL,
  `name` varchar(150) NOT NULL,
  `icon` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `recommendation_title` varchar(255) DEFAULT NULL,
  `recommendation_subtitle` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `industries`
--

INSERT INTO `industries` (`id`, `key`, `name`, `icon`, `description`, `recommendation_title`, `recommendation_subtitle`, `is_active`, `sort_order`) VALUES
(1, 'software', 'Technology & Software', '💻', 'Software companies, IT companies, SaaS businesses and technology teams.', '✨ Recommended Setup for Technology & Software', 'Pre-configured for SaaS, IT, and software tech teams.', 1, 1),
(2, 'services', 'Business & Professional Services', '💼', 'Consultants, professional service providers, business advisors and client-based businesses.', '✨ Recommended Setup for Professional Services', 'Pre-configured for consultants, advisors, and professional services.', 1, 2),
(3, 'agency', 'Agency', '🎨', 'Digital agencies, creative agencies, marketing agencies and client-service agencies.', '✨ Recommended Setup for Agency', 'Pre-configured for creative, digital, and marketing agencies.', 1, 3),
(4, 'realestate', 'Real Estate', '🏡', 'Real estate agencies, property consultants and property businesses.', '✨ Recommended Setup for Real Estate', 'Pre-configured for real estate agencies and property teams.', 1, 4),
(5, 'retail', 'E-commerce & Retail', '🛒', 'Online stores, retail businesses and product-based businesses.', '✨ Recommended Setup for E-commerce & Retail', 'Pre-configured for online stores and retail product sellers.', 1, 5),
(6, 'manufacturing', 'Manufacturing', '🏭', 'Manufacturers, factories and production-based businesses.', '✨ Recommended Setup for Manufacturing', 'Pre-configured for manufacturers and production suppliers.', 1, 6),
(7, 'construction', 'Construction', '🏗️', 'Construction companies, contractors and project-based businesses.', '✨ Recommended Setup for Construction', 'Pre-configured for contractors and project construction teams.', 1, 7),
(8, 'recruitment', 'Recruitment', '🎯', 'Recruitment agencies, staffing companies and hiring businesses.', '✨ Recommended Setup for Recruitment', 'Pre-configured for recruiters, hiring, and staffing agencies.', 1, 8),
(9, 'freelance', 'Freelancer', '👤', 'Freelancers, independent professionals and solo service providers.', '✨ Recommended Setup for Freelancers', 'Pre-configured for solo professionals and independent consultants.', 1, 9),
(10, 'service', 'Service Business', '🔧', 'Businesses that provide services, appointments, field work or customer support.', '✨ Recommended Setup for Service Business', 'Pre-configured for service providers, field work, and appointments.', 1, 10),
(11, 'other', 'Other / Custom Business', '⚙️', 'Don\'t see your business? Start with a flexible CRM and choose the features you need.', '✨ Flexible Custom CRM Setup', 'Pre-configured with standard essential modules.', 1, 11),
(12, 'software_technology', 'Software & Technology', 'briefcase', 'Software & Technology', NULL, NULL, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `industry_modules`
--

CREATE TABLE `industry_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `industry_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `industry_modules`
--

INSERT INTO `industry_modules` (`id`, `industry_id`, `module_id`, `is_default`, `sort_order`) VALUES
(1, 1, 7, 1, 7),
(2, 1, 3, 1, 3),
(3, 1, 2, 1, 2),
(4, 1, 10, 1, 10),
(5, 1, 4, 1, 4),
(6, 1, 13, 1, 13),
(7, 1, 9, 1, 9),
(8, 1, 11, 1, 11),
(9, 1, 1, 1, 1),
(10, 1, 5, 1, 5),
(11, 1, 8, 1, 8),
(12, 1, 15, 1, 15),
(13, 1, 14, 1, 14),
(14, 1, 6, 1, 6),
(16, 2, 7, 1, 7),
(17, 2, 3, 1, 3),
(18, 2, 2, 1, 2),
(19, 2, 10, 1, 10),
(20, 2, 4, 1, 4),
(21, 2, 13, 1, 13),
(22, 2, 9, 1, 9),
(23, 2, 12, 1, 12),
(24, 2, 11, 1, 11),
(25, 2, 1, 1, 1),
(26, 2, 5, 1, 5),
(27, 2, 8, 1, 8),
(28, 2, 15, 1, 15),
(29, 2, 6, 1, 6),
(31, 3, 7, 1, 7),
(32, 3, 3, 1, 3),
(33, 3, 2, 1, 2),
(34, 3, 10, 1, 10),
(35, 3, 4, 1, 4),
(36, 3, 13, 1, 13),
(37, 3, 9, 1, 9),
(38, 3, 12, 1, 12),
(39, 3, 11, 1, 11),
(40, 3, 1, 1, 1),
(41, 3, 5, 1, 5),
(42, 3, 8, 1, 8),
(43, 3, 15, 1, 15),
(44, 3, 6, 1, 6),
(46, 4, 7, 1, 7),
(47, 4, 3, 1, 3),
(48, 4, 2, 1, 2),
(49, 4, 10, 1, 10),
(50, 4, 4, 1, 4),
(51, 4, 13, 1, 12),
(52, 4, 9, 1, 9),
(53, 4, 11, 1, 11),
(54, 4, 1, 1, 1),
(55, 4, 5, 1, 5),
(56, 4, 8, 1, 8),
(57, 4, 15, 1, 14),
(58, 4, 6, 1, 6),
(61, 5, 7, 1, 5),
(62, 5, 3, 1, 3),
(63, 5, 2, 1, 2),
(64, 5, 12, 1, 7),
(65, 5, 11, 1, 6),
(66, 5, 1, 1, 1),
(67, 5, 15, 1, 9),
(68, 5, 14, 1, 8),
(69, 5, 6, 1, 4),
(76, 6, 7, 1, 7),
(77, 6, 3, 1, 3),
(78, 6, 2, 1, 2),
(79, 6, 10, 1, 9),
(80, 6, 4, 1, 4),
(81, 6, 9, 1, 8),
(82, 6, 12, 1, 11),
(83, 6, 11, 1, 10),
(84, 6, 1, 1, 1),
(85, 6, 5, 1, 5),
(86, 6, 15, 1, 12),
(87, 6, 6, 1, 6),
(91, 7, 7, 1, 7),
(92, 7, 3, 1, 3),
(93, 7, 2, 1, 2),
(94, 7, 10, 1, 9),
(95, 7, 4, 1, 4),
(96, 7, 13, 1, 11),
(97, 7, 9, 1, 8),
(98, 7, 11, 1, 10),
(99, 7, 1, 1, 1),
(100, 7, 5, 1, 5),
(101, 7, 15, 1, 12),
(102, 7, 6, 1, 6),
(106, 8, 7, 1, 6),
(107, 8, 3, 1, 3),
(108, 8, 2, 1, 2),
(109, 8, 10, 1, 8),
(110, 8, 4, 1, 4),
(111, 8, 13, 1, 10),
(112, 8, 11, 1, 9),
(113, 8, 1, 1, 1),
(114, 8, 8, 1, 7),
(115, 8, 15, 1, 11),
(116, 8, 6, 1, 5),
(121, 9, 7, 1, 7),
(122, 9, 3, 1, 3),
(123, 9, 2, 1, 2),
(124, 9, 10, 1, 10),
(125, 9, 4, 1, 4),
(126, 9, 13, 1, 13),
(127, 9, 9, 1, 9),
(128, 9, 12, 1, 12),
(129, 9, 11, 1, 11),
(130, 9, 1, 1, 1),
(131, 9, 5, 1, 5),
(132, 9, 8, 1, 8),
(133, 9, 15, 1, 14),
(134, 9, 6, 1, 6),
(136, 10, 7, 1, 6),
(137, 10, 3, 1, 3),
(138, 10, 2, 1, 2),
(139, 10, 10, 1, 8),
(140, 10, 4, 1, 4),
(141, 10, 13, 1, 10),
(142, 10, 9, 1, 7),
(143, 10, 11, 1, 9),
(144, 10, 1, 1, 1),
(145, 10, 15, 1, 11),
(146, 10, 6, 1, 5),
(151, 11, 7, 1, 7),
(152, 11, 3, 1, 3),
(153, 11, 2, 1, 2),
(154, 11, 4, 1, 4),
(155, 11, 11, 1, 8),
(156, 11, 1, 1, 1),
(157, 11, 5, 1, 5),
(158, 11, 15, 1, 9),
(159, 11, 6, 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `industry_pipeline_stages`
--

CREATE TABLE `industry_pipeline_stages` (
  `id` int(10) UNSIGNED NOT NULL,
  `industry_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `win_rate_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `industry_pipeline_stages`
--

INSERT INTO `industry_pipeline_stages` (`id`, `industry_id`, `name`, `win_rate_pct`, `sort_order`) VALUES
(1, 1, 'Lead Captured', 20, 1),
(2, 1, 'Discovery Call', 40, 2),
(3, 1, 'Product Demo', 60, 3),
(4, 1, 'Trial / POC', 80, 4),
(5, 1, 'Proposal Sent', 100, 5),
(6, 1, 'Closed Won', 100, 6),
(7, 2, 'Inquiry Received', 20, 1),
(8, 2, 'Initial Consult', 40, 2),
(9, 2, 'Proposal Delivered', 60, 3),
(10, 2, 'Negotiation', 80, 4),
(11, 2, 'Retainer Signed', 100, 5),
(12, 2, 'Project Active', 100, 6),
(13, 3, 'New Pitch', 20, 1),
(14, 3, 'Briefing Call', 40, 2),
(15, 3, 'Proposal Sent', 60, 3),
(16, 3, 'Contract Signed', 80, 4),
(17, 3, 'Onboarding', 100, 5),
(18, 3, 'Active Client', 100, 6),
(19, 4, 'New Inquiry', 20, 1),
(20, 4, 'Contacted', 40, 2),
(21, 4, 'Site Visit Scheduled', 60, 3),
(22, 4, 'Negotiation', 80, 4),
(23, 4, 'Booking Fee', 100, 5),
(24, 4, 'Closed Deal', 100, 6),
(25, 5, 'New Order', 20, 1),
(26, 5, 'Payment Verified', 40, 2),
(27, 5, 'Processing', 60, 3),
(28, 5, 'Shipped', 80, 4),
(29, 5, 'Delivered', 100, 5),
(30, 5, 'Completed', 100, 6),
(31, 6, 'RFQ Received', 20, 1),
(32, 6, 'Cost Estimate', 40, 2),
(33, 6, 'Sample Approved', 60, 3),
(34, 6, 'Contract Signed', 80, 4),
(35, 6, 'In Production', 100, 5),
(36, 6, 'Dispatched', 100, 6),
(37, 7, 'Bid Invited', 20, 1),
(38, 7, 'Estimate Submitted', 40, 2),
(39, 7, 'Contract Awarded', 60, 3),
(40, 7, 'Site Setup', 80, 4),
(41, 7, 'Construction Active', 100, 5),
(42, 7, 'Handover', 100, 6),
(43, 8, 'Sourced Candidate', 20, 1),
(44, 8, 'Initial Screen', 40, 2),
(45, 8, 'Client Interview', 60, 3),
(46, 8, 'Shortlisted', 80, 4),
(47, 8, 'Job Offer', 100, 5),
(48, 8, 'Placed / Hired', 100, 6),
(49, 9, 'Initial Inquiry', 20, 1),
(50, 9, 'Estimate Sent', 40, 2),
(51, 9, 'Deposit Paid', 60, 3),
(52, 9, 'Work In Progress', 80, 4),
(53, 9, 'Review / Edits', 100, 5),
(54, 9, 'Final Invoice Paid', 100, 6),
(55, 10, 'Service Request', 20, 1),
(56, 10, 'Estimate Provided', 40, 2),
(57, 10, 'Appointment Booked', 60, 3),
(58, 10, 'Technician Assigned', 80, 4),
(59, 10, 'Service Completed', 100, 5),
(60, 10, 'Invoiced', 100, 6),
(61, 11, 'New Inquiry', 20, 1),
(62, 11, 'Contacted', 40, 2),
(63, 11, 'Qualified', 60, 3),
(64, 11, 'Proposal Sent', 80, 4),
(65, 11, 'Negotiation', 100, 5),
(66, 11, 'Closed Won', 100, 6);

-- --------------------------------------------------------

--
-- Table structure for table `industry_terms`
--

CREATE TABLE `industry_terms` (
  `id` int(10) UNSIGNED NOT NULL,
  `industry_id` int(10) UNSIGNED NOT NULL,
  `leads_label` varchar(120) NOT NULL,
  `pipeline_label` varchar(120) NOT NULL,
  `contacts_label` varchar(120) NOT NULL,
  `companies_label` varchar(120) NOT NULL,
  `deals_label` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `industry_terms`
--

INSERT INTO `industry_terms` (`id`, `industry_id`, `leads_label`, `pipeline_label`, `contacts_label`, `companies_label`, `deals_label`) VALUES
(1, 1, 'Leads', 'Sales Pipeline', 'Contacts', 'Accounts', 'Deals'),
(2, 2, 'Inquiries', 'Consulting Pipeline', 'Clients', 'Organizations', 'Engagements'),
(3, 3, 'Leads', 'Agency Pipeline', 'Client Leads', 'Accounts', 'Campaigns'),
(4, 4, 'Buyers', 'Property Pipeline', 'Clients', 'Brokerages', 'Properties'),
(5, 5, 'Shoppers', 'Order Fulfillment', 'Customers', 'Vendors', 'Orders'),
(6, 6, 'RFQ Leads', 'Production Pipeline', 'Procurement Leads', 'Distributors', 'Orders'),
(7, 7, 'Bids', 'Project Pipeline', 'Subcontractors', 'General Contractors', 'Site Projects'),
(8, 8, 'Candidates', 'Hiring Pipeline', 'Job Seekers', 'Hiring Companies', 'Placements'),
(9, 9, 'Prospects', 'Gigs Pipeline', 'Clients', 'Client Accounts', 'Projects'),
(10, 10, 'Service Leads', 'Work Order Pipeline', 'Clients', 'Service Accounts', 'Work Orders'),
(11, 11, 'Leads', 'Sales Pipeline', 'Contacts', 'Companies', 'Deals');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `contract_reference` varchar(255) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `payment_terms` varchar(100) NOT NULL DEFAULT 'Net 30',
  `currency` varchar(30) NOT NULL DEFAULT 'USD ($)',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Sent','Viewed','Pending','Partially Paid','Paid','Overdue','Cancelled') NOT NULL DEFAULT 'Draft',
  `customer_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `organization_id`, `invoice_number`, `title`, `company_id`, `contact_id`, `project_id`, `deal_id`, `contract_reference`, `issue_date`, `due_date`, `payment_terms`, `currency`, `subtotal`, `discount`, `tax`, `total`, `amount_paid`, `balance_due`, `status`, `customer_notes`, `internal_notes`, `assigned_to`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 1, 'INV-001', 'm vm,m,vcm,vc', NULL, NULL, NULL, NULL, NULL, '2026-09-08', '2026-10-08', 'Net 30', 'USD ($)', 140.00, 10.00, 0.00, 130.00, 130.00, 0.00, 'Paid', 'bc bf vfb vf f', 'vfdvfdvgfbdgf', 1, 1, '2026-09-08 09:33:31', '2026-09-12 16:49:39'),
(6, 1, 'INV-002', 'Website Development Deal', 13, 39, NULL, 22, NULL, '2026-09-12', '2026-10-12', 'Net 30', 'USD ($)', 300000.00, 5000.00, 53100.00, 348100.00, 348100.00, 0.00, 'Paid', 'Thank you for choosing NexFlow CRM Solutions. Payment is due according to the agreed payment terms.', 'Invoice generated for Website Development project covering UI/UX, frontend, and backend development services.', 1, 1, '2026-09-12 16:28:38', '2026-09-12 16:45:59'),
(7, 1, 'INV-003', 'Video editing', 26, 65, 6, 27, NULL, '2026-09-12', '2026-10-12', 'Net 30', 'USD ($)', 20000.00, 10000.00, 1800.00, 11800.00, 11800.00, 0.00, 'Paid', 'fcwsedfc', 'sdcfdscf', 1, 1, '2026-09-12 16:52:30', '2026-09-12 16:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `item_title` varchar(255) NOT NULL,
  `item_description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `organization_id`, `invoice_id`, `item_title`, `item_description`, `quantity`, `unit_price`, `amount`, `sort_order`, `created_at`) VALUES
(11, 1, 5, 'gb gfbgf', NULL, 2.00, 70.00, 140.00, 0, '2026-09-08 09:33:31'),
(12, 1, 6, 'UI/UX Design', NULL, 1.00, 50000.00, 50000.00, 0, '2026-09-12 16:28:38'),
(13, 1, 6, 'Frontend Development', NULL, 1.00, 100000.00, 100000.00, 1, '2026-09-12 16:28:38'),
(14, 1, 6, 'Backend Development', NULL, 1.00, 150000.00, 150000.00, 2, '2026-09-12 16:28:38'),
(15, 1, 7, 'Video Editing', NULL, 1.00, 20000.00, 20000.00, 0, '2026-09-12 16:52:30');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('Bank Transfer','Credit Card','Wire Transfer','Cheque') NOT NULL DEFAULT 'Bank Transfer',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_payments`
--

INSERT INTO `invoice_payments` (`id`, `organization_id`, `invoice_id`, `payment_number`, `amount`, `payment_date`, `payment_method`, `transaction_reference`, `notes`, `recorded_by`, `created_at`) VALUES
(5, 1, 6, 'PAY-101', 348100.00, '2026-09-12', 'Bank Transfer', 'TRF-20260912-001', NULL, 1, '2026-09-12 16:45:59'),
(6, 1, 5, 'PAY-102', 10.00, '2026-09-12', 'Credit Card', 'UPI-20260912-001', NULL, 1, '2026-09-12 16:47:21'),
(7, 1, 5, 'PAY-103', 120.00, '2026-09-12', 'Bank Transfer', 'TRF-20260912-002', NULL, 1, '2026-09-12 16:49:39'),
(9, 1, 7, 'PAY-104', 11800.00, '2026-09-12', 'Bank Transfer', 'TRF-20260912-009', NULL, 1, '2026-09-12 16:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `lead_code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `first_name` varchar(75) DEFAULT NULL,
  `last_name` varchar(75) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'New',
  `score` int(11) NOT NULL DEFAULT 0,
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `source` varchar(50) DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `organization_id`, `lead_code`, `name`, `first_name`, `last_name`, `email`, `phone`, `whatsapp`, `company`, `job_title`, `location`, `status`, `score`, `value`, `source`, `assigned_to`, `is_archived`, `created_at`, `updated_at`) VALUES
(1, 2, 'LD-999', 'Org 2 Secret Lead', '', '', '', '', NULL, '', NULL, NULL, 'open', 0, 99999.00, '', 7, 0, '2026-09-07 21:41:50', '2026-09-07 21:41:50'),
(26, 0, NULL, 'fvfvfxvfxvg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'New', 0, 0.00, NULL, NULL, 0, '2026-09-08 15:16:17', '2026-09-08 15:16:17'),
(27, 1, 'LD-0027', 'Rahul Mehta', 'Rahul', 'Mehta', 'rahul.mehta@example.com', '+91 98765 43210', '', 'TechNova Solutions', '', '', 'Qualified', 50, 25000.00, 'Website', 1, 0, '2026-09-12 23:55:10', '2026-09-13 00:20:40'),
(28, 1, 'LD-0028', 'Harsh Mehta', 'Harsh', 'Mehta', 'harsh@gmail.com', '', '', 'Harshal Pvt. Ltd.', '', '', 'New', 50, 25000.00, 'Website', 1, 0, '2026-09-12 23:57:15', '2026-09-12 23:57:15'),
(29, 1, 'LD-0029', 'vbdkfmv', 'vbdkfmv', '', 'rahul@gmail.io', '563453', '4535t34', 'kvxvjb', '', 'dfvdxsvd', 'Qualified', 50, 25000.00, 'Website', 1, 1, '2026-09-12 23:59:04', '2026-09-13 00:34:18'),
(34, 1, 'LD-0034', 'API Test Lead', 'API', 'Test Lead', 'api_test_1789239647@test.com', '+1 555-4321', '+1 555-4321', 'API Test Corp', 'Chief Technology Officer', 'Seattle, WA', 'New', 90, 45000.00, 'LinkedIn', 1, 0, '2026-09-13 00:30:47', '2026-09-13 00:30:47'),
(35, 1, 'LD-0035', 'Subprocess Lead', 'Subprocess', 'Lead', 'sub_1789239678@test.com', '+1 555-7766', '+1 555-7766', 'CloudCorp Global', 'VP of Cloud Infrastructure', 'Austin, TX', 'Qualified', 88, 35000.00, 'Website', 1, 0, '2026-09-13 00:31:19', '2026-09-13 00:31:19'),
(36, 1, 'LD-0036', 'Subprocess Lead', 'Subprocess', 'Lead', 'sub_1789239685@test.com', '+1 555-7766', '+1 555-7766', 'CloudCorp Global', 'VP of Cloud Infrastructure', 'Austin, TX', 'Qualified', 88, 35000.00, 'Website', 1, 1, '2026-09-13 00:31:25', '2026-09-13 00:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `icon` varchar(20) NOT NULL,
  `description` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `key`, `name`, `icon`, `description`, `is_active`, `sort_order`) VALUES
(1, 'leads', 'Leads', '🎯', 'Capture & manage potential customers', 1, 1),
(2, 'contacts', 'Contacts', '👤', 'Individual client directory', 1, 2),
(3, 'companies', 'Companies', '🏢', 'Business accounts & organizations', 1, 3),
(4, 'deals', 'Deals', '💼', 'Pipeline deal tracking', 1, 4),
(5, 'projects', 'Projects', '📁', 'Plan & manage client deliverables', 1, 5),
(6, 'tasks', 'Tasks', '✅', 'To-do items & team tasking', 1, 6),
(7, 'calendar', 'Calendar', '📅', 'Meetings & appointment scheduling', 1, 7),
(8, 'proposals', 'Proposals', '📝', 'Client quotes & proposals', 1, 8),
(9, 'estimates', 'Estimates', '📊', 'Cost estimates & request forms', 1, 9),
(10, 'contracts', 'Contracts', '📄', 'Legal agreements & e-signatures', 1, 10),
(11, 'invoices', 'Invoices', '💳', 'Create & track customer invoices', 1, 11),
(12, 'expenses', 'Expenses', '💵', 'Business & project expenses', 1, 12),
(13, 'documents', 'Documents', '📂', 'Shared file store & attachments', 1, 13),
(14, 'support', 'Support', '🎧', 'Help desk & customer tickets', 1, 14),
(15, 'reports', 'Reports', '📈', 'Analytics & performance dashboards', 1, 15),
(16, 'team', 'Team', '👥', 'Employee access & permissions', 1, 16);

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `business_email` varchar(180) NOT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `state_region` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `postal_code` varchar(30) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'USD ($)',
  `timezone` varchar(80) NOT NULL DEFAULT 'UTC',
  `logo_path` varchar(255) DEFAULT NULL,
  `industry_id` int(10) UNSIGNED DEFAULT NULL,
  `industry_name` varchar(180) DEFAULT NULL,
  `setup_completed` tinyint(1) NOT NULL DEFAULT 0,
  `setup_completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `slug`, `business_email`, `phone`, `website`, `country`, `state_region`, `city`, `postal_code`, `street_address`, `currency`, `timezone`, `logo_path`, `industry_id`, `industry_name`, `setup_completed`, `setup_completed_at`, `created_at`, `updated_at`) VALUES
(1, 'NovaSphere Global Inc.', 'novasphere-digital-solutions', 'contact@novasphere.example', '+1 (800) 555-0199', 'https://novasphere.example', 'United States', 'CA', 'San Francisco', '94105', '350 Fifth Avenue, Suite 4200', 'USD ($)', 'America/New_York', 'uploads/logos/logo_41bc9e3da530e7f9110944a4.jpg', 12, 'Technology & Software', 1, '2026-09-07 13:31:48', '2026-09-07 13:31:48', '2026-09-07 14:37:17'),
(2, 'Org Two Test', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'USD ($)', 'UTC', NULL, NULL, NULL, 1, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `organization_modules`
--

CREATE TABLE `organization_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED DEFAULT NULL,
  `module_key` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `custom_name` varchar(150) DEFAULT NULL,
  `custom_description` varchar(255) DEFAULT NULL,
  `custom_icon` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organization_modules`
--

INSERT INTO `organization_modules` (`id`, `organization_id`, `module_id`, `module_key`, `enabled`, `is_custom`, `custom_name`, `custom_description`, `custom_icon`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'leads', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(2, 1, 2, 'contacts', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(3, 1, 3, 'companies', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(4, 1, 4, 'deals', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(5, 1, 5, 'projects', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(6, 1, 6, 'tasks', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(7, 1, 7, 'calendar', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(8, 1, 8, 'proposals', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(9, 1, 9, 'estimates', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(10, 1, 10, 'contracts', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(11, 1, 11, 'invoices', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(12, 1, 12, 'expenses', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(13, 1, 13, 'documents', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(14, 1, 14, 'support', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(15, 1, 15, 'reports', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00'),
(16, 1, 16, 'team', 1, 0, NULL, NULL, NULL, '2026-09-07 13:31:48', '2026-09-13 22:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `organization_settings`
--

CREATE TABLE `organization_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `terminology_json` longtext NOT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `fiscal_year_start` varchar(30) DEFAULT NULL,
  `business_hours` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organization_settings`
--

INSERT INTO `organization_settings` (`id`, `organization_id`, `terminology_json`, `company_size`, `fiscal_year_start`, `business_hours`, `created_at`, `updated_at`) VALUES
(1, 1, '{\"leads\":\"Leads\",\"pipeline\":\"Sales Pipeline\",\"contacts\":\"Contacts\",\"companies\":\"Companies\",\"deals\":\"Deals\"}', '1-10 employees', 'January', '09:00 - 18:00 EST', '2026-09-07 13:31:48', '2026-09-13 22:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module_key`, `action`, `description`, `created_at`) VALUES
(1, 'leads', 'view', 'View Leads', '2026-09-07 14:22:47'),
(2, 'leads', 'create', 'Create Leads', '2026-09-07 14:22:47'),
(3, 'leads', 'edit', 'Edit Leads', '2026-09-07 14:22:47'),
(4, 'leads', 'delete', 'Delete Leads', '2026-09-07 14:22:47'),
(5, 'leads', 'export', 'Export Leads', '2026-09-07 14:22:47'),
(6, 'leads', 'assign', 'Assign Leads', '2026-09-07 14:22:47'),
(7, 'contacts', 'view', 'View Contacts', '2026-09-07 14:22:47'),
(8, 'contacts', 'create', 'Create Contacts', '2026-09-07 14:22:47'),
(9, 'contacts', 'edit', 'Edit Contacts', '2026-09-07 14:22:47'),
(10, 'contacts', 'delete', 'Delete Contacts', '2026-09-07 14:22:47'),
(11, 'contacts', 'export', 'Export Contacts', '2026-09-07 14:22:47'),
(12, 'companies', 'view', 'View Companies', '2026-09-07 14:22:47'),
(13, 'companies', 'create', 'Create Companies', '2026-09-07 14:22:47'),
(14, 'companies', 'edit', 'Edit Companies', '2026-09-07 14:22:47'),
(15, 'companies', 'delete', 'Delete Companies', '2026-09-07 14:22:47'),
(16, 'companies', 'export', 'Export Companies', '2026-09-07 14:22:47'),
(17, 'deals', 'view', 'View Deals', '2026-09-07 14:22:47'),
(18, 'deals', 'create', 'Create Deals', '2026-09-07 14:22:47'),
(19, 'deals', 'edit', 'Edit Deals', '2026-09-07 14:22:47'),
(20, 'deals', 'delete', 'Delete Deals', '2026-09-07 14:22:47'),
(21, 'deals', 'export', 'Export Deals', '2026-09-07 14:22:47'),
(22, 'deals', 'assign', 'Assign Deals', '2026-09-07 14:22:47'),
(23, 'projects', 'view', 'View Projects', '2026-09-07 14:22:47'),
(24, 'projects', 'create', 'Create Projects', '2026-09-07 14:22:47'),
(25, 'projects', 'edit', 'Edit Projects', '2026-09-07 14:22:47'),
(26, 'projects', 'delete', 'Delete Projects', '2026-09-07 14:22:47'),
(27, 'projects', 'export', 'Export Projects', '2026-09-07 14:22:47'),
(28, 'tasks', 'view', 'View Tasks', '2026-09-07 14:22:47'),
(29, 'tasks', 'create', 'Create Tasks', '2026-09-07 14:22:47'),
(30, 'tasks', 'edit', 'Edit Tasks', '2026-09-07 14:22:47'),
(31, 'tasks', 'delete', 'Delete Tasks', '2026-09-07 14:22:47'),
(32, 'tasks', 'assign', 'Assign Tasks', '2026-09-07 14:22:47'),
(33, 'calendar', 'view', 'View Calendar', '2026-09-07 14:22:47'),
(34, 'calendar', 'create', 'Create Calendar', '2026-09-07 14:22:47'),
(35, 'calendar', 'edit', 'Edit Calendar', '2026-09-07 14:22:47'),
(36, 'calendar', 'delete', 'Delete Calendar', '2026-09-07 14:22:47'),
(37, 'proposals', 'view', 'View Proposals', '2026-09-07 14:22:47'),
(38, 'proposals', 'create', 'Create Proposals', '2026-09-07 14:22:47'),
(39, 'proposals', 'edit', 'Edit Proposals', '2026-09-07 14:22:47'),
(40, 'proposals', 'delete', 'Delete Proposals', '2026-09-07 14:22:47'),
(41, 'proposals', 'export', 'Export Proposals', '2026-09-07 14:22:47'),
(42, 'estimates', 'view', 'View Estimates', '2026-09-07 14:22:47'),
(43, 'estimates', 'create', 'Create Estimates', '2026-09-07 14:22:47'),
(44, 'estimates', 'edit', 'Edit Estimates', '2026-09-07 14:22:47'),
(45, 'estimates', 'delete', 'Delete Estimates', '2026-09-07 14:22:47'),
(46, 'estimates', 'export', 'Export Estimates', '2026-09-07 14:22:47'),
(47, 'contracts', 'view', 'View Contracts', '2026-09-07 14:22:47'),
(48, 'contracts', 'create', 'Create Contracts', '2026-09-07 14:22:47'),
(49, 'contracts', 'edit', 'Edit Contracts', '2026-09-07 14:22:47'),
(50, 'contracts', 'delete', 'Delete Contracts', '2026-09-07 14:22:47'),
(51, 'contracts', 'export', 'Export Contracts', '2026-09-07 14:22:47'),
(52, 'invoices', 'view', 'View Invoices', '2026-09-07 14:22:47'),
(53, 'invoices', 'create', 'Create Invoices', '2026-09-07 14:22:47'),
(54, 'invoices', 'edit', 'Edit Invoices', '2026-09-07 14:22:47'),
(55, 'invoices', 'delete', 'Delete Invoices', '2026-09-07 14:22:47'),
(56, 'invoices', 'export', 'Export Invoices', '2026-09-07 14:22:47'),
(57, 'expenses', 'view', 'View Expenses', '2026-09-07 14:22:47'),
(58, 'expenses', 'create', 'Create Expenses', '2026-09-07 14:22:47'),
(59, 'expenses', 'edit', 'Edit Expenses', '2026-09-07 14:22:47'),
(60, 'expenses', 'delete', 'Delete Expenses', '2026-09-07 14:22:47'),
(61, 'expenses', 'export', 'Export Expenses', '2026-09-07 14:22:47'),
(62, 'documents', 'view', 'View Documents', '2026-09-07 14:22:47'),
(63, 'documents', 'create', 'Create Documents', '2026-09-07 14:22:47'),
(64, 'documents', 'edit', 'Edit Documents', '2026-09-07 14:22:47'),
(65, 'documents', 'delete', 'Delete Documents', '2026-09-07 14:22:47'),
(66, 'support', 'view', 'View Support', '2026-09-07 14:22:47'),
(67, 'support', 'create', 'Create Support', '2026-09-07 14:22:47'),
(68, 'support', 'edit', 'Edit Support', '2026-09-07 14:22:47'),
(69, 'support', 'delete', 'Delete Support', '2026-09-07 14:22:47'),
(70, 'reports', 'view', 'View Reports', '2026-09-07 14:22:47'),
(71, 'reports', 'export', 'Export Reports', '2026-09-07 14:22:47'),
(72, 'team', 'view', 'View Team', '2026-09-07 14:22:47'),
(73, 'team', 'create', 'Create Team', '2026-09-07 14:22:47'),
(74, 'team', 'edit', 'Edit Team', '2026-09-07 14:22:47'),
(75, 'team', 'delete', 'Delete Team', '2026-09-07 14:22:47'),
(76, 'team', 'manage', 'Manage Team', '2026-09-07 14:22:47'),
(77, 'settings', 'view', 'View Settings', '2026-09-07 14:22:47'),
(78, 'settings', 'edit', 'Edit Settings', '2026-09-07 14:22:47'),
(79, 'settings', 'manage', 'Manage Settings', '2026-09-07 14:22:47'),
(96, 'pipeline', 'view', 'View permission for Pipeline', '2026-09-07 14:26:52'),
(97, 'pipeline', 'create', 'Create permission for Pipeline', '2026-09-07 14:26:52'),
(98, 'pipeline', 'edit', 'Edit permission for Pipeline', '2026-09-07 14:26:52'),
(99, 'pipeline', 'delete', 'Delete permission for Pipeline', '2026-09-07 14:26:52'),
(100, 'pipeline', 'assign', 'Assign permission for Pipeline', '2026-09-07 14:26:52'),
(120, 'proposals', 'download', 'Download permission for Proposals', '2026-09-07 14:26:52'),
(121, 'proposals', 'send', 'Send permission for Proposals', '2026-09-07 14:26:52'),
(127, 'estimates', 'download', 'Download permission for Estimates', '2026-09-07 14:26:52'),
(128, 'estimates', 'send', 'Send permission for Estimates', '2026-09-07 14:26:52'),
(134, 'contracts', 'download', 'Download permission for Contracts', '2026-09-07 14:26:52'),
(135, 'contracts', 'send', 'Send permission for Contracts', '2026-09-07 14:26:52'),
(141, 'invoices', 'download', 'Download permission for Invoices', '2026-09-07 14:26:52'),
(142, 'invoices', 'send', 'Send permission for Invoices', '2026-09-07 14:26:52'),
(152, 'documents', 'export', 'Export permission for Documents', '2026-09-07 14:26:52'),
(153, 'documents', 'download', 'Download permission for Documents', '2026-09-07 14:26:52'),
(154, 'documents', 'send', 'Send permission for Documents', '2026-09-07 14:26:52'),
(169, 'help', 'view', 'View permission for Help', '2026-09-07 14:26:52'),
(170, 'subscriptions', 'view', 'View permission for Subscriptions', '2026-09-07 14:26:52'),
(171, 'subscriptions', 'create', 'Create permission for Subscriptions', '2026-09-07 14:26:52'),
(172, 'subscriptions', 'edit', 'Edit permission for Subscriptions', '2026-09-07 14:26:52'),
(173, 'subscriptions', 'delete', 'Delete permission for Subscriptions', '2026-09-07 14:26:52'),
(174, 'subscriptions', 'export', 'Export permission for Subscriptions', '2026-09-07 14:26:52'),
(175, 'inbox', 'view', 'View permission for Inbox', '2026-09-07 14:26:52'),
(176, 'inbox', 'create', 'Create permission for Inbox', '2026-09-07 14:26:52'),
(177, 'inbox', 'send', 'Send permission for Inbox', '2026-09-07 14:26:52');

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_stages`
--

CREATE TABLE `pipeline_stages` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `win_rate_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#2563EB',
  `probability` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_system` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pipeline_stages`
--

INSERT INTO `pipeline_stages` (`id`, `organization_id`, `name`, `sort_order`, `win_rate_pct`, `is_active`, `created_at`, `updated_at`, `color`, `probability`, `is_system`) VALUES
(14, 1, 'Prospect', 1, 10, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#64748B', 10.00, 0),
(15, 1, 'Qualified', 2, 30, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#2563EB', 30.00, 0),
(16, 1, 'Proposal', 3, 60, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#F59E0B', 60.00, 0),
(17, 1, 'Negotiation', 4, 80, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#8B5CF6', 80.00, 0),
(18, 1, 'Closed Won', 5, 100, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#10B981', 100.00, 1),
(19, 1, 'Closed Lost', 6, 0, 1, '2026-09-07 14:47:10', '2026-09-13 22:44:00', '#EF4444', 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `client_name` varchar(200) NOT NULL,
  `manager_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `priority` varchar(30) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `progress` tinyint(3) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `organization_id`, `project_code`, `name`, `client_name`, `manager_id`, `status`, `priority`, `start_date`, `due_date`, `budget`, `progress`, `description`, `deal_id`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 1, 'PRJ-001', 'Website Development Project', 'Apex Technologies Pvt Ltd', 1, 'In Progress', 'High', '2026-09-15', '2026-11-27', 3000000.00, 30, 'Developed a responsive and user-friendly website with modern design, smooth navigation, and mobile compatibility. The project includes interactive features, optimized performance, and a clean interface to provide a seamless user experience.', 22, 1, '2026-09-11 22:26:09', '2026-09-11 23:58:56'),
(6, 1, 'PRJ-002', 'Video editing', 'Nova Retail Group', 1, 'Planning', 'Medium', '2026-09-25', '2026-09-30', 223000.00, 20, 'video editinf project', 27, 1, '2026-09-11 23:56:59', '2026-09-11 23:56:59'),
(7, 1, 'PRJ-003', 'graphic design', 'BrightPath Analytics Pvt Ltd', 1, 'Planning', 'Medium', '2026-10-07', '2026-10-24', 450000.00, 25, 'graphic design', 26, 1, '2026-09-11 23:59:45', '2026-09-11 23:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` varchar(50) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_files`
--

INSERT INTO `project_files` (`id`, `organization_id`, `project_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `uploaded_by`, `created_at`) VALUES
(1, 1, 7, 'crm-logo.jpg', 'uploads/projects/1/proj_7_1789151469_2d22bc4507ef.jpg', '55 KB', 'image/jpeg', 1, '2026-09-12 00:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `project_milestones`
--

CREATE TABLE `project_milestones` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_milestones`
--

INSERT INTO `project_milestones` (`id`, `organization_id`, `project_id`, `name`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(2, 1, 7, 'design the structure', 'Pending', 1, '2026-09-12 00:00:56', '2026-09-12 00:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `proposal_number` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `client_email` varchar(180) DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_name` varchar(200) DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `project_name` varchar(200) DEFAULT NULL,
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `scope` text DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(30) DEFAULT NULL,
  `payment_terms` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `status` enum('Draft','Sent','Viewed','Changes Requested','Accepted','Declined','Expired') NOT NULL DEFAULT 'Draft',
  `change_request_type` varchar(50) DEFAULT NULL,
  `change_request_message` text DEFAULT NULL,
  `change_requested_by` varchar(150) DEFAULT NULL,
  `change_requested_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `organization_id`, `proposal_number`, `title`, `company_id`, `company_name`, `contact_id`, `contact_name`, `client_email`, `deal_id`, `deal_name`, `project_id`, `project_name`, `prepared_by`, `issue_date`, `expiry_date`, `scope`, `subtotal`, `discount`, `tax_rate`, `tax`, `total`, `currency`, `payment_terms`, `terms`, `status`, `change_request_type`, `change_request_message`, `change_requested_by`, `change_requested_at`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 1, 'PROP-001', 'cdcsdvcdc', NULL, 'gbdbxxgfb', NULL, NULL, NULL, NULL, 'nm,n mcv nm', NULL, 'bbbnmbnmb', 1, '2026-09-08', '2026-10-08', 'dfc dcf vfd', 108.00, 30.00, 18.00, 14.04, 92.04, 'USD ($)', 'hvjhbvmbmnb', 'bj,mnmnj,mnkj', 'Draft', NULL, NULL, NULL, NULL, 1, '2026-09-08 15:05:02', '2026-09-08 15:05:02'),
(7, 1, 'PROP-003', 'xsas', NULL, 'Apex Global Innovations E2E', NULL, 'Alex Vance', 'alex.vance@apexinnovations-e2e.com', NULL, 'nm,n mcv nm', NULL, 'xsxsaxs', 1, '2026-09-09', '2026-10-09', 'ccxsdca', 13.00, 0.00, 18.00, 2.34, 15.34, 'USD ($)', NULL, NULL, 'Draft', NULL, NULL, NULL, NULL, 1, '2026-09-09 12:06:15', '2026-09-09 12:06:15'),
(8, 1, 'PROP-004', 'Website Development Proposal', 13, 'Apex Technologies Pvt Ltd', NULL, NULL, NULL, 22, 'Website Development Deal', NULL, 'Website Development Project', 1, '2026-09-12', '2026-10-16', 'Development of a responsive corporate website including UI design, frontend development, backend integration and deployment.', 280000.00, 10000.00, 18.00, 48600.00, 318600.00, 'USD ($)', '50% advance upon agreement signing,\n50% upon final deployment.', 'This proposal is valid for 30 days.\nAll rates are subject to the agreed project scope.', 'Accepted', 'Other', 'hi, i am shweta', 'Shweta Singh', '2026-09-16 12:08:16', 1, '2026-09-12 12:42:12', '2026-09-16 12:10:13'),
(9, 1, 'PROP-005', 'Website Development Proposal (Copy)', 13, 'Apex Technologies Pvt Ltd', NULL, NULL, NULL, 22, 'Website Development Deal', NULL, 'Website Development Project', 1, '2026-09-12', '2026-10-12', 'Development of a responsive corporate website including UI design, frontend development, backend integration and deployment.', 280000.00, 10000.00, 18.00, 48600.00, 318600.00, 'USD ($)', '50% advance upon agreement signing,\n50% upon final deployment.', 'This proposal is valid for 30 days.\nAll rates are subject to the agreed project scope.', 'Declined', NULL, NULL, NULL, NULL, 1, '2026-09-12 13:00:37', '2026-09-16 12:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_items`
--

CREATE TABLE `proposal_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `proposal_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proposal_items`
--

INSERT INTO `proposal_items` (`id`, `organization_id`, `proposal_id`, `name`, `description`, `qty`, `rate`, `amount`, `sort_order`, `created_at`) VALUES
(13, 1, 5, 'bhjb', 'jknn,nm', 1.00, 108.00, 108.00, 1, '2026-09-08 15:05:02'),
(15, 1, 7, 'cdxs', 'sdcxs', 1.00, 13.00, 13.00, 1, '2026-09-09 12:06:15'),
(25, 1, 9, 'UI/UX Design', 'Modern and responsive UI/UX design for the website, including user-friendly layouts, wireframes, visual design, and mobile optimization.', 1.00, 50000.00, 50000.00, 1, '2026-09-12 13:00:43'),
(26, 1, 9, 'Frontend Development', 'Responsive frontend development with clean layouts, interactive components, cross-browser compatibility, and optimized user experience.', 1.00, 80000.00, 80000.00, 2, '2026-09-12 13:00:43'),
(27, 1, 9, 'Backend Development', 'Secure backend development with database integration, admin functionality, APIs, form handling, and reliable data management.', 1.00, 150000.00, 150000.00, 3, '2026-09-12 13:00:43'),
(28, 1, 8, 'UI/UX Design', 'Modern and responsive UI/UX design for the website, including user-friendly layouts, wireframes, visual design, and mobile optimization.', 1.00, 50000.00, 50000.00, 1, '2026-09-16 12:09:53'),
(29, 1, 8, 'Frontend Development', 'Responsive frontend development with clean layouts, interactive components, cross-browser compatibility, and optimized user experience.', 1.00, 80000.00, 80000.00, 2, '2026-09-16 12:09:53'),
(30, 1, 8, 'Backend Development', 'Secure backend development with database integration, admin functionality, APIs, form handling, and reliable data management.', 1.00, 150000.00, 150000.00, 3, '2026-09-16 12:09:53');

-- --------------------------------------------------------

--
-- Table structure for table `report_saved_presets`
--

CREATE TABLE `report_saved_presets` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Sales',
  `tab` varchar(50) NOT NULL DEFAULT 'overview',
  `filter_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filter_config`)),
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_saved_presets`
--

INSERT INTO `report_saved_presets` (`id`, `organization_id`, `user_id`, `name`, `category`, `tab`, `filter_config`, `is_system`, `created_at`) VALUES
(1, 1, 1, 'Management Weekly', 'Executive', 'overview', '{\"dateRange\":\"last7\"}', 0, '2026-09-13 22:29:06');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `organization_id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 1, 'Super Administrator', 'super_admin', 'Full unrestricted system access to all organization data, settings, and modules.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47'),
(2, 1, 'Sales Manager', 'sales_manager', 'Full access to sales pipeline, leads, contacts, deals, tasks, and team reports.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47'),
(3, 1, 'Sales Representative', 'sales_rep', 'Access to assigned leads, deals, contacts, and personal tasks.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47'),
(4, 1, 'Account Executive', 'account_executive', 'Full access to assigned accounts, deals, pipeline, and customer contacts.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47'),
(5, 1, 'Sales Operations', 'sales_ops', 'Operational access to pipeline configurations, reporting, data export, and settings.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47'),
(6, 1, 'Viewer', 'viewer', 'Read-only access to permitted CRM modules.', 1, '2026-09-07 14:22:47', '2026-09-07 14:22:47');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 34, '2026-09-07 14:22:47'),
(2, 1, 36, '2026-09-07 14:22:47'),
(3, 1, 35, '2026-09-07 14:22:47'),
(4, 1, 33, '2026-09-07 14:22:47'),
(5, 1, 13, '2026-09-07 14:22:47'),
(6, 1, 15, '2026-09-07 14:22:47'),
(7, 1, 14, '2026-09-07 14:22:47'),
(8, 1, 16, '2026-09-07 14:22:47'),
(9, 1, 12, '2026-09-07 14:22:47'),
(10, 1, 8, '2026-09-07 14:22:47'),
(11, 1, 10, '2026-09-07 14:22:47'),
(12, 1, 9, '2026-09-07 14:22:47'),
(13, 1, 11, '2026-09-07 14:22:47'),
(14, 1, 7, '2026-09-07 14:22:47'),
(15, 1, 48, '2026-09-07 14:22:47'),
(16, 1, 50, '2026-09-07 14:22:47'),
(17, 1, 49, '2026-09-07 14:22:47'),
(18, 1, 51, '2026-09-07 14:22:47'),
(19, 1, 47, '2026-09-07 14:22:47'),
(20, 1, 22, '2026-09-07 14:22:47'),
(21, 1, 18, '2026-09-07 14:22:47'),
(22, 1, 20, '2026-09-07 14:22:47'),
(23, 1, 19, '2026-09-07 14:22:47'),
(24, 1, 21, '2026-09-07 14:22:47'),
(25, 1, 17, '2026-09-07 14:22:47'),
(26, 1, 63, '2026-09-07 14:22:47'),
(27, 1, 65, '2026-09-07 14:22:47'),
(28, 1, 64, '2026-09-07 14:22:47'),
(29, 1, 62, '2026-09-07 14:22:47'),
(30, 1, 43, '2026-09-07 14:22:47'),
(31, 1, 45, '2026-09-07 14:22:47'),
(32, 1, 44, '2026-09-07 14:22:47'),
(33, 1, 46, '2026-09-07 14:22:47'),
(34, 1, 42, '2026-09-07 14:22:47'),
(35, 1, 58, '2026-09-07 14:22:47'),
(36, 1, 60, '2026-09-07 14:22:47'),
(37, 1, 59, '2026-09-07 14:22:47'),
(38, 1, 61, '2026-09-07 14:22:47'),
(39, 1, 57, '2026-09-07 14:22:47'),
(40, 1, 53, '2026-09-07 14:22:47'),
(41, 1, 55, '2026-09-07 14:22:47'),
(42, 1, 54, '2026-09-07 14:22:47'),
(43, 1, 56, '2026-09-07 14:22:47'),
(44, 1, 52, '2026-09-07 14:22:47'),
(45, 1, 6, '2026-09-07 14:22:47'),
(46, 1, 2, '2026-09-07 14:22:47'),
(47, 1, 4, '2026-09-07 14:22:47'),
(48, 1, 3, '2026-09-07 14:22:47'),
(49, 1, 5, '2026-09-07 14:22:47'),
(50, 1, 1, '2026-09-07 14:22:47'),
(51, 1, 24, '2026-09-07 14:22:47'),
(52, 1, 26, '2026-09-07 14:22:47'),
(53, 1, 25, '2026-09-07 14:22:47'),
(54, 1, 27, '2026-09-07 14:22:47'),
(55, 1, 23, '2026-09-07 14:22:47'),
(56, 1, 38, '2026-09-07 14:22:47'),
(57, 1, 40, '2026-09-07 14:22:47'),
(58, 1, 39, '2026-09-07 14:22:47'),
(59, 1, 41, '2026-09-07 14:22:47'),
(60, 1, 37, '2026-09-07 14:22:47'),
(61, 1, 71, '2026-09-07 14:22:47'),
(62, 1, 70, '2026-09-07 14:22:47'),
(63, 1, 78, '2026-09-07 14:22:47'),
(64, 1, 79, '2026-09-07 14:22:47'),
(65, 1, 77, '2026-09-07 14:22:47'),
(66, 1, 67, '2026-09-07 14:22:47'),
(67, 1, 69, '2026-09-07 14:22:47'),
(68, 1, 68, '2026-09-07 14:22:47'),
(69, 1, 66, '2026-09-07 14:22:47'),
(70, 1, 32, '2026-09-07 14:22:47'),
(71, 1, 29, '2026-09-07 14:22:47'),
(72, 1, 31, '2026-09-07 14:22:47'),
(73, 1, 30, '2026-09-07 14:22:47'),
(74, 1, 28, '2026-09-07 14:22:47'),
(75, 1, 73, '2026-09-07 14:22:47'),
(76, 1, 75, '2026-09-07 14:22:47'),
(77, 1, 74, '2026-09-07 14:22:47'),
(78, 1, 76, '2026-09-07 14:22:47'),
(79, 1, 72, '2026-09-07 14:22:47'),
(80, 2, 1, '2026-09-07 14:22:47'),
(81, 2, 2, '2026-09-07 14:22:47'),
(82, 2, 3, '2026-09-07 14:22:47'),
(83, 2, 4, '2026-09-07 14:22:47'),
(84, 2, 5, '2026-09-07 14:22:47'),
(85, 2, 6, '2026-09-07 14:22:47'),
(86, 2, 7, '2026-09-07 14:22:47'),
(87, 2, 8, '2026-09-07 14:22:47'),
(88, 2, 9, '2026-09-07 14:22:47'),
(89, 2, 10, '2026-09-07 14:22:47'),
(90, 2, 11, '2026-09-07 14:22:47'),
(91, 2, 12, '2026-09-07 14:22:47'),
(92, 2, 13, '2026-09-07 14:22:47'),
(93, 2, 14, '2026-09-07 14:22:47'),
(94, 2, 17, '2026-09-07 14:22:47'),
(95, 2, 18, '2026-09-07 14:22:47'),
(96, 2, 19, '2026-09-07 14:22:47'),
(97, 2, 20, '2026-09-07 14:22:47'),
(98, 2, 21, '2026-09-07 14:22:47'),
(99, 2, 22, '2026-09-07 14:22:47'),
(100, 2, 23, '2026-09-07 14:22:47'),
(101, 2, 24, '2026-09-07 14:22:47'),
(102, 2, 25, '2026-09-07 14:22:47'),
(103, 2, 28, '2026-09-07 14:22:47'),
(104, 2, 29, '2026-09-07 14:22:47'),
(105, 2, 30, '2026-09-07 14:22:47'),
(106, 2, 32, '2026-09-07 14:22:47'),
(107, 2, 33, '2026-09-07 14:22:47'),
(108, 2, 34, '2026-09-07 14:22:47'),
(109, 2, 35, '2026-09-07 14:22:47'),
(110, 2, 37, '2026-09-07 14:22:47'),
(111, 2, 38, '2026-09-07 14:22:47'),
(112, 2, 39, '2026-09-07 14:22:47'),
(113, 2, 42, '2026-09-07 14:22:47'),
(114, 2, 43, '2026-09-07 14:22:47'),
(115, 2, 44, '2026-09-07 14:22:47'),
(116, 2, 47, '2026-09-07 14:22:47'),
(117, 2, 52, '2026-09-07 14:22:47'),
(118, 2, 57, '2026-09-07 14:22:47'),
(119, 2, 62, '2026-09-07 14:22:47'),
(120, 2, 63, '2026-09-07 14:22:47'),
(121, 2, 64, '2026-09-07 14:22:47'),
(122, 2, 66, '2026-09-07 14:22:47'),
(123, 2, 67, '2026-09-07 14:22:47'),
(124, 2, 68, '2026-09-07 14:22:47'),
(125, 2, 70, '2026-09-07 14:22:47'),
(126, 2, 71, '2026-09-07 14:22:47'),
(127, 2, 72, '2026-09-07 14:22:47'),
(128, 2, 74, '2026-09-07 14:22:47'),
(129, 2, 77, '2026-09-07 14:22:47'),
(130, 3, 1, '2026-09-07 14:22:47'),
(131, 3, 2, '2026-09-07 14:22:47'),
(132, 3, 3, '2026-09-07 14:22:47'),
(133, 3, 7, '2026-09-07 14:22:47'),
(134, 3, 8, '2026-09-07 14:22:47'),
(135, 3, 9, '2026-09-07 14:22:47'),
(136, 3, 12, '2026-09-07 14:22:47'),
(137, 3, 17, '2026-09-07 14:22:47'),
(138, 3, 18, '2026-09-07 14:22:47'),
(139, 3, 19, '2026-09-07 14:22:47'),
(140, 3, 28, '2026-09-07 14:22:47'),
(141, 3, 29, '2026-09-07 14:22:47'),
(142, 3, 30, '2026-09-07 14:22:47'),
(143, 3, 33, '2026-09-07 14:22:47'),
(144, 3, 34, '2026-09-07 14:22:47'),
(145, 3, 35, '2026-09-07 14:22:47'),
(146, 3, 70, '2026-09-07 14:22:47'),
(147, 6, 1, '2026-09-07 14:22:47'),
(148, 6, 7, '2026-09-07 14:22:47'),
(149, 6, 12, '2026-09-07 14:22:47'),
(150, 6, 17, '2026-09-07 14:22:47'),
(151, 6, 70, '2026-09-07 14:22:47'),
(184, 1, 134, '2026-09-07 14:26:52'),
(190, 1, 135, '2026-09-07 14:26:52'),
(210, 1, 153, '2026-09-07 14:26:52'),
(214, 1, 152, '2026-09-07 14:26:52'),
(216, 1, 154, '2026-09-07 14:26:52'),
(224, 1, 127, '2026-09-07 14:26:52'),
(230, 1, 128, '2026-09-07 14:26:52'),
(244, 1, 169, '2026-09-07 14:26:52'),
(246, 1, 176, '2026-09-07 14:26:52'),
(248, 1, 177, '2026-09-07 14:26:52'),
(250, 1, 175, '2026-09-07 14:26:52'),
(256, 1, 141, '2026-09-07 14:26:52'),
(262, 1, 142, '2026-09-07 14:26:52'),
(278, 1, 100, '2026-09-07 14:26:52'),
(280, 1, 97, '2026-09-07 14:26:52'),
(282, 1, 99, '2026-09-07 14:26:52'),
(284, 1, 98, '2026-09-07 14:26:52'),
(286, 1, 96, '2026-09-07 14:26:52'),
(302, 1, 120, '2026-09-07 14:26:53'),
(308, 1, 121, '2026-09-07 14:26:53'),
(322, 1, 171, '2026-09-07 14:26:53'),
(324, 1, 173, '2026-09-07 14:26:53'),
(326, 1, 172, '2026-09-07 14:26:53'),
(328, 1, 174, '2026-09-07 14:26:53'),
(330, 1, 170, '2026-09-07 14:26:53'),
(373, 2, 96, '2026-09-07 14:26:53'),
(374, 2, 97, '2026-09-07 14:26:53'),
(375, 2, 98, '2026-09-07 14:26:53'),
(376, 2, 99, '2026-09-07 14:26:53'),
(380, 2, 31, '2026-09-07 14:26:53'),
(396, 3, 96, '2026-09-07 14:26:53'),
(402, 3, 169, '2026-09-07 14:26:53'),
(403, 4, 1, '2026-09-07 14:26:53'),
(404, 4, 2, '2026-09-07 14:26:53'),
(405, 4, 3, '2026-09-07 14:26:53'),
(406, 4, 7, '2026-09-07 14:26:53'),
(407, 4, 8, '2026-09-07 14:26:53'),
(408, 4, 9, '2026-09-07 14:26:53'),
(409, 4, 12, '2026-09-07 14:26:53'),
(410, 4, 13, '2026-09-07 14:26:53'),
(411, 4, 14, '2026-09-07 14:26:53'),
(412, 4, 96, '2026-09-07 14:26:53'),
(413, 4, 97, '2026-09-07 14:26:53'),
(414, 4, 98, '2026-09-07 14:26:53'),
(415, 4, 28, '2026-09-07 14:26:53'),
(416, 4, 29, '2026-09-07 14:26:53'),
(417, 4, 30, '2026-09-07 14:26:53'),
(418, 4, 33, '2026-09-07 14:26:53'),
(419, 4, 70, '2026-09-07 14:26:53'),
(420, 4, 169, '2026-09-07 14:26:53'),
(421, 5, 1, '2026-09-07 14:26:53'),
(422, 5, 2, '2026-09-07 14:26:53'),
(423, 5, 3, '2026-09-07 14:26:53'),
(424, 5, 5, '2026-09-07 14:26:53'),
(425, 5, 7, '2026-09-07 14:26:53'),
(426, 5, 8, '2026-09-07 14:26:53'),
(427, 5, 9, '2026-09-07 14:26:53'),
(428, 5, 12, '2026-09-07 14:26:53'),
(429, 5, 13, '2026-09-07 14:26:53'),
(430, 5, 14, '2026-09-07 14:26:53'),
(431, 5, 96, '2026-09-07 14:26:53'),
(432, 5, 97, '2026-09-07 14:26:53'),
(433, 5, 98, '2026-09-07 14:26:53'),
(434, 5, 70, '2026-09-07 14:26:53'),
(435, 5, 71, '2026-09-07 14:26:53'),
(436, 5, 72, '2026-09-07 14:26:53'),
(437, 5, 77, '2026-09-07 14:26:53'),
(438, 5, 78, '2026-09-07 14:26:53'),
(442, 6, 96, '2026-09-07 14:26:53'),
(444, 6, 169, '2026-09-07 14:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `subscription_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `project_name` varchar(200) DEFAULT NULL,
  `plan_tier` varchar(100) NOT NULL,
  `billing_cycle` enum('Monthly','Quarterly','Yearly') NOT NULL DEFAULT 'Monthly',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(30) NOT NULL DEFAULT 'USD ($)',
  `status` enum('Active','Trial','Past Due','Paused','Cancelled') NOT NULL DEFAULT 'Active',
  `start_date` date NOT NULL,
  `trial_end_date` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `renewal_type` enum('Automatic','Manual') NOT NULL DEFAULT 'Automatic',
  `payment_method` varchar(100) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `contract_scope` text DEFAULT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `organization_id`, `subscription_code`, `name`, `company_id`, `company_name`, `contact_id`, `project_id`, `project_name`, `plan_tier`, `billing_cycle`, `amount`, `currency`, `status`, `start_date`, `trial_end_date`, `next_billing_date`, `renewal_type`, `payment_method`, `billing_address`, `contract_scope`, `owner_id`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(9, 1, 'SUB-2026-001', 'mm, m,cd fcm,d', NULL, 'gbdbxxgfb', NULL, NULL, 'cds cd', 'Enterprise Tier', 'Monthly', 123322.00, 'USD ($)', 'Active', '2026-09-08', NULL, '2026-10-08', 'Automatic', NULL, NULL, 'vc vc vcvcf fvvfve', 1, 1, 1, '2026-09-08 15:01:53', '2026-09-08 15:01:53'),
(10, 1, 'SUB-2026-010', 'Website Maintenance & Support', NULL, 'Apex Technologies Pvt Ltd', NULL, NULL, 'Corporate Website Maintenance', 'Professional Suite', 'Monthly', 2500.00, 'USD ($)', 'Active', '2026-09-12', NULL, '2026-10-12', 'Automatic', NULL, NULL, 'Monthly Website maintenance, security updates, backups and technical support.', 1, 1, 1, '2026-09-12 23:09:28', '2026-09-12 23:10:25'),
(11, 1, 'SUB-2026-011', 'Enterprise Cloud Hosting', NULL, 'Nova Retail Group', NULL, NULL, 'E-Commerce Cloud Hosting', 'Enterprise Tier', 'Yearly', 24000.00, 'USD ($)', 'Active', '2026-09-12', NULL, '2027-09-12', 'Automatic', NULL, NULL, 'Annual enterprise hosting and infrastructure support.', 1, 1, 1, '2026-09-12 23:12:34', '2026-09-12 23:12:34'),
(12, 1, 'SUB-2026-012', 'SEO & Digital Marketing', NULL, 'BrightPath Analytics Pvt Ltd', NULL, NULL, 'SEO Campaign', 'Professional Suite', 'Monthly', 1800.00, 'USD ($)', 'Paused', '2026-09-12', NULL, '2026-10-31', 'Manual', NULL, NULL, 'Subscription temporarily paused by customer.', 1, 1, 1, '2026-09-12 23:14:04', '2026-09-12 23:14:04'),
(13, 1, 'SUB-2026-013', 'CRM Professional Trial', NULL, 'Vintage', NULL, NULL, 'NexFlow CRM Setup', 'Professional Suite', 'Monthly', 5600.00, 'USD ($)', 'Trial', '2026-09-12', NULL, '2026-12-09', 'Manual', NULL, NULL, '14-day CRM trial before paid subscription.', 1, 1, 1, '2026-09-12 23:15:36', '2026-09-12 23:15:36'),
(16, 1, 'SUB-2026-014', 'vfxd vf', NULL, 'Heaven', NULL, NULL, 'fd bdfbf', 'Enterprise Tier', 'Monthly', 0.00, 'USD ($)', 'Trial', '2026-09-12', NULL, '2026-10-12', 'Automatic', NULL, NULL, 'v x fcx bvf bdvb', 1, 1, 1, '2026-09-12 23:32:15', '2026-09-12 23:32:15');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_invoices`
--

CREATE TABLE `subscription_invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('Paid','Trial','Past Due','Pending','Cancelled') NOT NULL DEFAULT 'Paid',
  `issue_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_invoices`
--

INSERT INTO `subscription_invoices` (`id`, `organization_id`, `subscription_id`, `invoice_number`, `amount`, `status`, `issue_date`, `paid_date`, `payment_method`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(13, 1, 9, 'INV-2026-001', 123322.00, 'Paid', '2026-09-08', '2026-09-08', NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-08 15:01:53', '2026-09-08 15:01:53'),
(14, 1, 10, 'INV-2026-014', 2500.00, 'Paid', '2026-09-12', '2026-09-12', NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-12 23:09:28', '2026-09-12 23:09:28'),
(15, 1, 11, 'INV-2026-015', 24000.00, 'Paid', '2026-09-12', '2026-09-12', NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-12 23:12:34', '2026-09-12 23:12:34'),
(16, 1, 12, 'INV-2026-016', 1800.00, 'Pending', '2026-09-12', NULL, NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-12 23:14:04', '2026-09-12 23:14:04'),
(17, 1, 13, 'INV-2026-017', 5600.00, 'Trial', '2026-09-12', NULL, NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-12 23:15:36', '2026-09-12 23:15:36'),
(18, 1, 16, 'INV-2026-018', 0.00, 'Trial', '2026-09-12', NULL, NULL, 'Initial invoice generated upon subscription creation.', 1, '2026-09-12 23:32:15', '2026-09-12 23:32:15');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `task_type` varchar(50) NOT NULL DEFAULT 'General Task',
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','waiting','completed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `due_date` datetime DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `deal_id` int(10) UNSIGNED DEFAULT NULL,
  `client_visible` tinyint(1) NOT NULL DEFAULT 0,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `organization_id`, `title`, `task_type`, `description`, `status`, `priority`, `due_date`, `assigned_to`, `company_id`, `contact_id`, `deal_id`, `client_visible`, `related_type`, `related_id`, `created_at`, `updated_at`) VALUES
(39, 1, 'Client Website Demo – Follow Up', 'Meeting', 'Follow-up discussion regarding website development progress, pending requirements, and project approval.', 'pending', 'high', '2026-09-16 09:00:00', 1, 13, 39, 22, 1, 'deals', 22, '2026-09-13 17:31:06', '2026-09-13 17:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `organization_id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Sales', 'Primary organization sales team', 'active', '2026-09-07 14:22:47', '2026-09-07 14:22:47');

-- --------------------------------------------------------

--
-- Table structure for table `team_activities`
--

CREATE TABLE `team_activities` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `related_entity` varchar(50) DEFAULT NULL,
  `related_entity_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_activities`
--

INSERT INTO `team_activities` (`id`, `organization_id`, `user_id`, `activity_type`, `title`, `description`, `related_entity`, `related_entity_id`, `created_by`, `created_at`) VALUES
(1, 1, 1, 'lead_assigned', 'Lead Assigned', 'Assigned Org 2 Secret Lead () to Olivia Carter', 'leads', 1, 1, '2026-09-07 21:41:50'),
(2, 1, 8, 'member_invited', 'Team Member Invited', 'Invited Marcus Vance as Senior Account Executive', 'users', 8, 1, '2026-09-07 21:43:43'),
(3, 1, 9, 'member_invited', 'Team Member Invited', 'Invited Marcus Vance as Senior Account Executive', 'users', 9, 1, '2026-09-07 21:43:43'),
(4, 1, 11, 'member_invited', 'Team Member Invited', 'Invited Marcus Vance as Senior Account Executive', 'users', 11, 1, '2026-09-07 21:44:40'),
(5, 1, 11, 'member_updated', 'Profile Updated', 'Updated details for Marcus Vance-Updated', 'users', 11, 1, '2026-09-07 21:44:40'),
(6, 1, 11, 'lead_assigned', 'Lead Assigned', 'Assigned Acme Corp Dealership () to Marcus Vance-Updated', 'leads', 3, 1, '2026-09-07 21:44:40'),
(7, 1, 1, 'work_reassigned', 'Workload Reassigned', 'Reassigned 2 all from Marcus Vance-Updated to Olivia Carter (Transferring work before leave)', 'users', 1, 1, '2026-09-07 21:44:40'),
(8, 1, 11, 'note_created', 'Note Added', 'Q4 target alignment completed.', 'team_notes', 1, 1, '2026-09-07 21:44:40'),
(9, 1, 11, 'member_deactivated', 'Member Deactivated', 'Member ID 11 deactivated', 'users', 11, 1, '2026-09-07 21:44:40'),
(10, 1, 13, 'member_invited', 'Team Member Invited', 'Invited Marcus Vance as Senior Account Executive', 'users', 13, 1, '2026-09-07 21:45:25'),
(11, 1, 13, 'member_updated', 'Profile Updated', 'Updated details for Marcus Vance-Updated', 'users', 13, 1, '2026-09-07 21:45:25'),
(12, 1, 13, 'lead_assigned', 'Lead Assigned', 'Assigned Acme Corp Dealership () to Marcus Vance-Updated', 'leads', 5, 1, '2026-09-07 21:45:25'),
(13, 1, 1, 'work_reassigned', 'Workload Reassigned', 'Reassigned 2 all from Marcus Vance-Updated to Olivia Carter (Transferring work before leave)', 'users', 1, 1, '2026-09-07 21:45:25'),
(14, 1, 13, 'note_created', 'Note Added', 'Q4 target alignment completed.', 'team_notes', 2, 1, '2026-09-07 21:45:25'),
(15, 1, 13, 'member_deactivated', 'Member Deactivated', 'Member ID 13 deactivated', 'users', 13, 1, '2026-09-07 21:45:25'),
(16, 1, 14, 'member_invited', 'Team Member Invited', 'Invited Tanu Kaur as Account Executive', 'users', 14, 1, '2026-09-07 21:57:40'),
(17, 1, 14, 'note_created', 'Note Added', 'dcdszvfcxbfvb', 'team_notes', 3, 1, '2026-09-07 21:57:59'),
(18, 1, 1, 'created', 'Lead Created', 'Lead Alexander Vance (Apex Cloud) was created by Olivia Carter.', 'leads', 10, 1, '2026-09-07 22:28:11'),
(105, 1, 1, 'company_created', 'Company created: CSV Export Corp', 'Company CSV Export Corp (COMP-000001) created by Olivia Carter', 'companies', 5, 1, '2026-09-08 12:22:21'),
(106, 1, 1, 'company_created', 'Company created: CSV Export Corp', 'Company CSV Export Corp (COMP-000001) created by Olivia Carter', 'companies', 6, 1, '2026-09-08 12:22:31'),
(203, 1, 1, 'subscription_created', 'Subscription Created: mm, m,cd fcm,d', 'Subscription SUB-2026-001 was created for gbdbxxgfb (Enterprise Tier).', 'subscriptions', 9, 1, '2026-09-08 15:01:53'),
(205, 1, 1, 'create', 'Invoice INV-001 created', 'Total amount USD ($) 130.00', 'invoices', 5, 1, '2026-09-08 15:03:31'),
(208, 1, 1, 'proposal_created', 'Proposal Created', 'Proposal PROP-001 created as Draft by Olivia Carter', 'proposals', 5, 1, '2026-09-08 15:05:02'),
(210, 1, 1, 'proposal_created', 'Proposal Created', 'Proposal PROP-003 created as Draft by Olivia Carter', 'proposals', 7, 1, '2026-09-09 12:06:15'),
(236, 1, 1, 'company_created', 'Company created: Apex Technologies Pvt Ltd', 'Company Apex Technologies Pvt Ltd (COMP-000011) created by Olivia Carter', 'companies', 13, 1, '2026-09-09 12:39:46'),
(237, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-09 12:41:04'),
(238, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-09 12:41:23'),
(239, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-09 12:41:42'),
(240, 1, 1, 'company_created', 'Company created: BrightPath Analytics Pvt Ltd', 'Company BrightPath Analytics Pvt Ltd (COMP-000014) created by Olivia Carter', 'companies', 14, 1, '2026-09-09 12:44:28'),
(241, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-09 12:44:39'),
(242, 1, 1, 'Create', 'Created contact Rahul Sharma', '', 'contacts', 8, 1, '2026-09-09 13:10:44'),
(243, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-09 13:11:21'),
(244, 2, 7, 'Create', 'Created contact Intruder Test', '', 'contacts', 12, 7, '2026-09-09 14:22:22'),
(245, 1, 1, 'company_created', 'Company created: pvt', 'Company pvt (COMP-000015) created by Olivia Carter', 'companies', 21, 1, '2026-09-09 14:32:56'),
(246, 1, 1, 'company_created', 'Company created: honda', 'Company honda (COMP-000022) created by Olivia Carter', 'companies', 22, 1, '2026-09-09 14:34:04'),
(247, 1, 1, 'Create', 'Created contact Kenji Sato', '', 'contacts', 13, 1, '2026-09-09 14:42:43'),
(248, 1, 1, 'Create', 'Created contact Priya Patel', '', 'contacts', 14, 1, '2026-09-09 14:42:43'),
(249, 1, 1, 'Create', 'Created contact Arun Verma', '', 'contacts', 15, 1, '2026-09-09 14:42:43'),
(251, 1, 1, 'Create', 'Created contact Ram Kapoor', '', 'contacts', 17, 1, '2026-09-09 15:05:01'),
(261, 1, 1, 'Create', 'Created contact Kenji Sato', '', 'contacts', 26, 1, '2026-09-09 15:17:33'),
(262, 1, 1, 'Create', 'Created contact Priya Patel', '', 'contacts', 27, 1, '2026-09-09 15:17:33'),
(263, 1, 1, 'Create', 'Created contact Arun Verma', '', 'contacts', 28, 1, '2026-09-09 15:17:33'),
(264, 1, 1, 'Create', 'Created contact Priya Sharma', '', 'contacts', 29, 1, '2026-09-09 15:21:52'),
(274, 1, 1, 'Create', 'Created contact Sham Sharma', '', 'contacts', 38, 1, '2026-09-09 15:28:00'),
(275, 1, 1, 'Create', 'Created contact Shweta Singh', '', 'contacts', 39, 1, '2026-09-09 15:29:28'),
(291, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000034) created by Olivia Carter', 'companies', 46, 1, '2026-09-09 21:59:22'),
(292, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000047) created by Olivia Carter', 'companies', 47, 1, '2026-09-09 21:59:22'),
(293, 1, 1, 'company_created', 'Company created: Vintage', 'Company Vintage (COMP-000034) created by Olivia Carter', 'companies', 48, 1, '2026-09-10 11:54:25'),
(294, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000049) created by Olivia Carter', 'companies', 49, 1, '2026-09-10 12:05:07'),
(295, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000050) created by Olivia Carter', 'companies', 50, 1, '2026-09-10 12:05:07'),
(296, 1, 1, 'company_created', 'Company created: Heaven', 'Company Heaven (COMP-000049) created by Olivia Carter', 'companies', 51, 1, '2026-09-10 12:06:55'),
(297, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000052) created by Olivia Carter', 'companies', 54, 1, '2026-09-10 12:43:47'),
(298, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000055) created by Olivia Carter', 'companies', 55, 1, '2026-09-10 12:43:47'),
(299, 1, 1, 'Create', 'Created contact Harman Kaur', '', 'contacts', 58, 1, '2026-09-10 12:46:40'),
(300, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000057) created by Olivia Carter', 'companies', 57, 1, '2026-09-10 13:01:52'),
(301, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000058) created by Olivia Carter', 'companies', 58, 1, '2026-09-10 13:01:52'),
(302, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:14:57'),
(303, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:34:20'),
(304, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:34:20'),
(305, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:34:20'),
(306, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:34:21'),
(307, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:34:21'),
(308, 1, 1, 'Update', 'Updated contact details for Priya Sharma', '', 'contacts', 29, 1, '2026-09-10 13:34:21'),
(309, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000057) created by Olivia Carter', 'companies', 59, 1, '2026-09-10 13:34:30'),
(310, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000060) created by Olivia Carter', 'companies', 60, 1, '2026-09-10 13:34:30'),
(311, 1, 1, 'Update', 'Updated contact details for Manoj Sharma', '', 'contacts', 53, 1, '2026-09-10 13:37:10'),
(312, 1, 1, 'company_updated', 'Company updated: Heaven', 'Company details for Heaven updated by Olivia Carter', 'companies', 51, 1, '2026-09-10 13:48:31'),
(313, 1, 1, 'company_updated', 'Company updated: Heaven', 'Company details for Heaven updated by Olivia Carter', 'companies', 51, 1, '2026-09-10 13:48:31'),
(314, 1, 1, 'company_created', 'Company created: Toyota Test', 'Company Toyota Test (COMP-000057) created by Olivia Carter', 'companies', 61, 1, '2026-09-10 13:48:41'),
(315, 1, 1, 'company_created', 'Company created: Subaru Test', 'Company Subaru Test (COMP-000062) created by Olivia Carter', 'companies', 62, 1, '2026-09-10 13:48:41'),
(316, 1, 1, 'company_updated', 'Company updated: honda', 'Company details for honda updated by Olivia Carter', 'companies', 33, 1, '2026-09-10 13:54:08'),
(317, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 13:58:05'),
(318, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 13:58:53'),
(319, 1, 1, 'contact_created', 'Contact created: Aarav Patel', 'Contact Aarav Patel created for company BrightPath Analytics Pvt Ltd', 'contacts', 62, 1, '2026-09-10 14:11:12'),
(320, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 14:11:12'),
(321, 1, 1, 'contact_created', 'Contact created: Aarav Patel', 'Contact Aarav Patel created for company BrightPath Analytics Pvt Ltd', 'contacts', 63, 1, '2026-09-10 14:12:14'),
(322, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 14:12:14'),
(324, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 14:12:44'),
(325, 1, 1, 'company_created', 'Company created: Temp Test Zero Contacts Ltd', 'Company Temp Test Zero Contacts Ltd (COMP-000057) created by Olivia Carter', 'companies', 63, 1, '2026-09-10 14:12:44'),
(326, 1, 1, 'company_updated', 'Company updated: Temp Test Zero Contacts Ltd', 'Company details for Temp Test Zero Contacts Ltd updated by Olivia Carter', 'companies', 63, 1, '2026-09-10 14:12:45'),
(327, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 14:12:45'),
(328, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-10 14:12:45'),
(329, 1, 1, 'contact_created', 'Contact created: Meenakshi', 'Contact Meenakshi created for company Nova Retail Group', 'contacts', 65, 1, '2026-09-10 14:14:36'),
(330, 1, 1, 'company_updated', 'Company updated: Nova Retail Group', 'Company details for Nova Retail Group updated by Olivia Carter', 'companies', 26, 1, '2026-09-10 14:14:36'),
(333, 1, 1, 'Update', 'Updated contact details for Meenakshi', '', 'contacts', 65, 1, '2026-09-10 14:26:29'),
(334, 1, 1, 'Create', 'Created contact Rohan Verma', '', 'contacts', 66, 1, '2026-09-10 14:30:48'),
(335, 1, 1, 'Create', 'Created contact Simran Kaur', '', 'contacts', 67, 1, '2026-09-10 14:30:48'),
(336, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 66, 1, '2026-09-10 14:30:49'),
(337, 1, 1, 'Create', 'Created contact Rohan Verma', '', 'contacts', 68, 1, '2026-09-10 14:31:02'),
(338, 1, 1, 'Create', 'Created contact Simran Kaur', '', 'contacts', 69, 1, '2026-09-10 14:31:02'),
(339, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 68, 1, '2026-09-10 14:31:02'),
(340, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 68, 1, '2026-09-10 14:31:02'),
(341, 1, 1, 'Create', 'Created contact Rohan Verma', '', 'contacts', 70, 1, '2026-09-10 14:31:23'),
(342, 1, 1, 'Create', 'Created contact Simran Kaur', '', 'contacts', 71, 1, '2026-09-10 14:31:23'),
(343, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 70, 1, '2026-09-10 14:31:23'),
(344, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 70, 1, '2026-09-10 14:31:23'),
(345, 1, 1, 'Update', 'Updated contact details for Rohan Verma', '', 'contacts', 70, 1, '2026-09-10 14:31:24'),
(349, 1, 1, 'Create', 'Created contact raman kaur', '', 'contacts', 72, 1, '2026-09-10 14:36:00'),
(359, 1, 1, 'Meeting', 'cdzsvsdv', 'fbhvsbfm', 'companies', 13, 1, '2026-09-10 15:36:54'),
(360, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-10 15:51:42'),
(361, 1, 1, 'company_updated', 'Company updated: Apex Technologies Pvt Ltd', 'Company details for Apex Technologies Pvt Ltd updated by Olivia Carter', 'companies', 13, 1, '2026-09-11 11:34:39'),
(362, 1, 1, 'Task', 'Added task: dvdc', '', 'contacts', 58, 1, '2026-09-11 11:44:13'),
(363, 1, 1, 'Note', 'Added a note', '', 'contacts', 58, 1, '2026-09-11 11:44:25'),
(364, 1, 1, 'contact_added', 'Contact added: harpreet kaur', 'Added contact harpreet kaur to BrightPath Analytics Pvt Ltd (Set as Primary Contact)', 'companies', 14, 1, '2026-09-11 11:48:48'),
(365, 1, 1, 'company_updated', 'Company updated: BrightPath Analytics Pvt Ltd', 'Company details for BrightPath Analytics Pvt Ltd updated by Olivia Carter', 'companies', 14, 1, '2026-09-11 11:51:25'),
(366, 1, 1, 'company_updated', 'Company updated: Spec Tech Pvt Ltd', 'Company details for Spec Tech Pvt Ltd updated by Olivia Carter', 'companies', 56, 1, '2026-09-11 11:51:52'),
(367, 1, 1, 'Deal Created', 'Deal created: Website Development Project', 'Initial stage: Prospect, Value: $50,000', 'deals', 22, 1, '2026-09-11 13:07:43'),
(368, 1, 1, 'Deal Created', 'Deal created: CRM Subscription', 'Initial stage: Prospect, Value: $25,000', 'deals', 23, 1, '2026-09-11 13:12:08'),
(371, 1, 1, 'Stage Change', 'Stage changed to Proposal', 'Moved from Prospect → Proposal', 'deals', 22, 1, '2026-09-11 13:17:23'),
(372, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Proposal → Qualified', 'deals', 22, 1, '2026-09-11 13:23:28'),
(373, 1, 1, 'Stage Change', 'Stage changed to Proposal', 'Moved from Qualified → Proposal', 'deals', 22, 1, '2026-09-11 13:23:32'),
(374, 1, 1, 'Deal Updated', 'Deal details updated', 'Updated deal properties', 'deals', 23, 1, '2026-09-11 13:25:44'),
(375, 1, 1, 'Stage Change', 'Stage updated to Qualified', 'Transitioned from Prospect → Qualified', 'deals', 23, 1, '2026-09-11 13:59:30'),
(378, 1, 1, 'Email', 'dcdcd', 'cdscdsv', 'deals', 23, 1, '2026-09-11 22:02:00'),
(379, 1, 1, 'deal_created', 'Deal created: graphic design', 'Created deal graphic design ($50000) for BrightPath Analytics Pvt Ltd', 'companies', 14, 1, '2026-09-11 22:13:32'),
(380, 1, 1, 'Deal Updated', 'Deal details updated', 'Updated deal properties', 'deals', 26, 1, '2026-09-11 18:44:57'),
(381, 1, 1, 'Deal', 'Created deal \'Video editing\' valued at $26,000.00', '', 'contacts', 65, 1, '2026-09-11 22:15:41'),
(382, 1, 1, 'Deal Updated', 'Deal details updated', 'Updated deal properties', 'deals', 27, 1, '2026-09-11 18:47:26'),
(383, 1, 1, 'Call', 'Call: lnjn,mnm,', 'lnjn,mnm,', 'contacts', 53, 1, '2026-09-11 22:18:08'),
(384, 1, 1, 'project_created', 'Project Created', 'Project Website Development Project (PRJ-001) created for Apex Technologies Pvt Ltd.', 'projects', 3, 1, '2026-09-11 22:26:09'),
(385, 1, 1, 'project_updated', 'Project Updated', 'Project Website Development Project updated.', 'projects', 3, 1, '2026-09-11 22:57:50'),
(386, 1, 1, 'Deal Updated', 'Deal details updated', 'Updated deal properties', 'deals', 22, 1, '2026-09-11 20:14:49'),
(387, 1, 1, 'project_created', 'Project Created', 'Project Video editing (PRJ-002) created for Nova Retail Group.', 'projects', 6, 1, '2026-09-11 23:56:59'),
(388, 1, 1, 'project_updated', 'Project Updated', 'Project Website Development Project updated.', 'projects', 3, 1, '2026-09-11 23:58:56'),
(389, 1, 1, 'project_created', 'Project Created', 'Project graphic design (PRJ-003) created for BrightPath Analytics Pvt Ltd.', 'projects', 7, 1, '2026-09-11 23:59:45'),
(390, 1, 1, 'task_created', 'Task Added', 'Added task: collect all the necessary materials', 'projects', 7, 1, '2026-09-12 00:00:35'),
(391, 1, 1, 'milestone_created', 'Milestone Added', 'Added milestone: design the structure', 'projects', 7, 1, '2026-09-12 00:00:56'),
(392, 1, 1, 'file_uploaded', 'File Uploaded', 'Uploaded document: crm-logo.jpg (55 KB)', 'projects', 7, 1, '2026-09-12 00:01:09'),
(393, 1, 1, 'note', 'Manual Note', 'collect the materials', 'projects', 7, 1, '2026-09-12 00:01:34'),
(394, 1, 1, 'created', '', 'Document \'DOC-001\' — \'Logo Image - Graphic Design\' was uploaded.', 'documents', 8, 0, '2026-09-12 00:38:05'),
(395, 1, 1, 'updated', '', 'Document \'DOC-001\' — \'Logo Image - Graphic Design\' was updated.', 'documents', 8, 0, '2026-09-12 00:38:37'),
(396, 1, 1, 'shared', '', 'Document \'DOC-001\' — \'Logo Image - Graphic Design\' was shared.', 'documents', 8, 0, '2026-09-12 00:38:47'),
(397, 1, 1, 'updated', '', 'Document \'DOC-001\' — \'Logo Image - Graphic Design\' was archived.', 'documents', 8, 0, '2026-09-12 00:39:05'),
(398, 1, 1, 'updated', '', 'Document \'DOC-001\' — \'Logo Image - Graphic Design\' was unarchived.', 'documents', 8, 0, '2026-09-12 00:39:28'),
(400, 1, 1, 'created', '', 'Document \'DOC-002\' — \'Website Requirements\' was uploaded.', 'documents', 10, 0, '2026-09-12 00:45:56'),
(401, 1, 1, 'updated', '', 'Document \'DOC-002\' — \'Website Requirements\' was updated.', 'documents', 10, 0, '2026-09-12 00:48:51'),
(402, 1, 1, 'proposal_created', 'Proposal Created', 'Proposal PROP-004 created as Draft by Olivia Carter', 'proposals', 8, 1, '2026-09-12 12:42:12'),
(403, 1, 1, 'proposal_updated', 'Proposal Updated', 'Proposal PROP-004 details were updated.', 'proposals', 8, 1, '2026-09-12 13:00:20'),
(404, 1, 1, 'proposal_duplicated', 'Proposal Duplicated', 'Proposal duplicated from PROP-004', 'proposals', 9, 1, '2026-09-12 13:00:37'),
(405, 1, 1, 'proposal_updated', 'Proposal Updated', 'Proposal PROP-005 details were updated.', 'proposals', 9, 1, '2026-09-12 13:00:43'),
(406, 1, 1, 'contract_created', 'Contract Created', 'Contract CON-002 (\"Website Development Services Agreement\") was created as Draft.', 'contracts', 12, 1, '2026-09-12 13:11:13'),
(407, 1, 1, 'contract_created', 'Contract Created', 'Contract CON-003 (\"Graphic design Service\") was created as Draft.', 'contracts', 14, 1, '2026-09-12 21:40:07'),
(408, 1, 1, 'contract_updated', 'Contract Updated', 'Contract CON-003 was updated.', 'contracts', 14, 1, '2026-09-12 21:41:32'),
(410, 1, 1, 'contract_sent', 'Contract Sent', 'Contract CON-003 was sent to client.', 'contracts', 14, 1, '2026-09-12 21:41:56'),
(411, 1, 1, 'create', 'Invoice INV-002 created', 'Total amount USD ($) 295,018.00', 'invoices', 6, 1, '2026-09-12 21:58:38'),
(412, 1, 1, 'payment', 'Payment PAY-101 recorded for INV-002', 'Amount: USD ($) 348,100.00 via Bank Transfer. Remaining balance: USD ($) 0.00', 'invoices', 6, 1, '2026-09-12 22:15:59'),
(413, 1, 1, 'payment', 'Payment PAY-102 recorded for INV-001', 'Amount: USD ($) 10.00 via Credit Card. Remaining balance: USD ($) 120.00', 'invoices', 5, 1, '2026-09-12 22:17:22'),
(414, 1, 1, 'payment', 'Payment PAY-103 recorded for INV-001', 'Amount: USD ($) 120.00 via Bank Transfer. Remaining balance: USD ($) 0.00', 'invoices', 5, 1, '2026-09-12 22:19:39'),
(415, 1, 1, 'create', 'Invoice INV-003 created', 'Total amount USD ($) 11,800.00', 'invoices', 7, 1, '2026-09-12 22:22:30'),
(416, 1, 1, 'payment', 'Payment PAY-104 recorded for INV-003', 'Amount: USD ($) 11,800.00 via Bank Transfer. Remaining balance: USD ($) 0.00', 'invoices', 7, 1, '2026-09-12 22:28:12'),
(417, 1, 1, 'subscription_created', 'Subscription Created: Website Maintenance & Support', 'Subscription SUB-2026-010 was created for Apex Technologies Pvt Ltd (Professional Suite).', 'subscriptions', 10, 1, '2026-09-12 23:09:28'),
(418, 1, 1, 'subscription_created', 'Subscription Created: Enterprise Cloud Hosting', 'Subscription SUB-2026-011 was created for Nova Retail Group (Enterprise Tier).', 'subscriptions', 11, 1, '2026-09-12 23:12:34'),
(419, 1, 1, 'subscription_created', 'Subscription Created: SEO & Digital Marketing', 'Subscription SUB-2026-012 was created for BrightPath Analytics Pvt Ltd (Professional Suite).', 'subscriptions', 12, 1, '2026-09-12 23:14:04'),
(420, 1, 1, 'subscription_created', 'Subscription Created: CRM Professional Trial', 'Subscription SUB-2026-013 was created for Vintage (Professional Suite).', 'subscriptions', 13, 1, '2026-09-12 23:15:36'),
(421, 1, 1, 'subscription_created', 'Subscription Created: vfxd vf', 'Subscription SUB-2026-014 was created for Heaven (Enterprise Tier).', 'subscriptions', 16, 1, '2026-09-12 23:32:15'),
(422, 1, 1, 'created', 'Recorded expense EXP-20260912-001', 'Expense \'Google Ads Campaign\' recorded for amount $2,500.00 (Google Ads).', 'expenses', 28, 1, '2026-09-12 23:36:08'),
(423, 1, 1, 'updated', 'Updated expense EXP-20260912-001', 'Expense parameters updated for \'Google Ads Campaign\'.', 'expenses', 28, 1, '2026-09-12 23:36:55'),
(424, 1, 1, 'updated', 'Updated expense EXP-20260912-001', 'Expense parameters updated for \'Google Ads Campaign\'.', 'expenses', 28, 1, '2026-09-12 23:37:33'),
(425, 1, 1, 'updated', 'Updated expense EXP-20260912-001', 'Expense parameters updated for \'Google Ads Campaign\'.', 'expenses', 28, 1, '2026-09-12 23:39:51'),
(426, 1, 1, 'created', 'Recorded expense EXP-20260912-002', 'Expense \'Office Electricity Bill\' recorded for amount $1,800.00 (Punjab State Power Corporation).', 'expenses', 29, 1, '2026-09-12 23:42:14'),
(427, 1, 1, 'created', 'Recorded expense EXP-20260912-003', 'Expense \'Client Travel Expense\' recorded for amount $2,500.00 (Indigo Airlines).', 'expenses', 30, 1, '2026-09-12 23:44:05'),
(433, 1, 1, 'created', 'Lead Created', 'Lead Rahul Mehta (TechNova Solutions) was created by Olivia Carter.', 'leads', 27, 1, '2026-09-12 23:55:10'),
(434, 1, 1, 'created', 'Lead Created', 'Lead Harsh Mehta (Harshal Pvt. Ltd.) was created by Olivia Carter.', 'leads', 28, 1, '2026-09-12 23:57:15'),
(435, 1, 1, 'created', 'Lead Created', 'Lead vbdkfmv (kvxvjb) was created by Olivia Carter.', 'leads', 29, 1, '2026-09-12 23:59:04'),
(436, 1, 1, 'updated', 'Lead Details Updated', 'Lead details updated by Olivia Carter.', 'leads', 27, 1, '2026-09-13 00:12:22'),
(437, 1, 1, 'updated', 'Lead Details Updated', 'Lead details updated by Olivia Carter.', 'leads', 27, 1, '2026-09-13 00:12:22'),
(438, 1, 1, 'updated', 'Lead Details Updated', 'Lead details updated by Olivia Carter.', 'leads', 27, 1, '2026-09-13 00:12:37'),
(439, 1, 1, 'updated', 'Lead Details Updated', 'Lead details updated by Olivia Carter.', 'leads', 27, 1, '2026-09-13 00:12:37'),
(440, 1, 1, 'updated', 'Lead Details Updated', 'Lead details updated by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:13:52'),
(441, 1, 1, 'task_added', 'Task Created', 'Task \'[Follow-up] [Follow-up] grsvgrsgvrs\' created by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:14:51'),
(442, 1, 1, 'note_added', 'Note Added', 'New note added by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:15:00'),
(443, 1, 1, 'status_changed', 'Status Updated', 'Status changed from New to Qualified by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:15:14'),
(444, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Sales Deal #29 and marked as Qualified by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:16:48'),
(445, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Sales Deal #29 and marked as Qualified by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:18:16'),
(446, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Sales Deal #30 and marked as Qualified by Olivia Carter.', 'leads', 27, 1, '2026-09-13 00:20:40'),
(447, 1, 1, 'created', 'Lead Created', 'Lead API Test Lead (API Test Corp) was created by Olivia Carter.', 'leads', 34, 1, '2026-09-13 00:30:47'),
(448, 1, 1, 'created', 'Lead Created', 'Lead Subprocess Lead (CloudCorp Global) was created by Olivia Carter.', 'leads', 35, 1, '2026-09-13 00:31:19'),
(449, 1, 1, 'lead_archived', 'Lead Archived', 'Lead \'Subprocess Lead\' (#35) archived by Olivia Carter.', 'leads', 35, 1, '2026-09-13 00:31:19'),
(450, 1, 1, 'lead_unarchived', 'Lead Unarchived', 'Lead \'Subprocess Lead\' (#35) unarchived by Olivia Carter.', 'leads', 35, 1, '2026-09-13 00:31:19'),
(451, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Contact #CNT-087 and Sales Deal #32 by Olivia Carter.', 'leads', 35, 1, '2026-09-13 00:31:19'),
(452, 1, 1, 'contact_created', 'Contact created from lead', 'Contact created from lead #35 (Subprocess Lead) by Olivia Carter.', 'contacts', 87, 1, '2026-09-13 00:31:19'),
(453, 1, 1, 'created', 'Lead Created', 'Lead Subprocess Lead (CloudCorp Global) was created by Olivia Carter.', 'leads', 36, 1, '2026-09-13 00:31:25'),
(454, 1, 1, 'lead_archived', 'Lead Archived', 'Lead \'Subprocess Lead\' (#36) archived by Olivia Carter.', 'leads', 36, 1, '2026-09-13 00:31:25'),
(455, 1, 1, 'lead_unarchived', 'Lead Unarchived', 'Lead \'Subprocess Lead\' (#36) unarchived by Olivia Carter.', 'leads', 36, 1, '2026-09-13 00:31:25'),
(456, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Contact #CNT-088 and Sales Deal #33 by Olivia Carter.', 'leads', 36, 1, '2026-09-13 00:31:25'),
(457, 1, 1, 'contact_created', 'Contact created from lead', 'Contact created from lead #36 (Subprocess Lead) by Olivia Carter.', 'contacts', 88, 1, '2026-09-13 00:31:25'),
(462, 1, 1, 'contact_created', 'Contact created from lead', 'Contact created from lead #37 (Subprocess Lead) by Olivia Carter.', 'contacts', 89, 1, '2026-09-13 00:31:35'),
(463, 1, 1, 'lead_archived', 'Lead Archived', 'Lead \'Subprocess Lead\' (#36) archived by Olivia Carter.', 'leads', 36, 1, '2026-09-13 00:33:14'),
(464, 1, 1, 'lead_archived', 'Lead Archived', 'Lead \'vbdkfmv\' (#29) archived by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:33:51'),
(465, 1, 1, 'converted', 'Lead Converted', 'Lead converted to Contact #CNT-091 and Sales Deal #29 by Olivia Carter.', 'leads', 29, 1, '2026-09-13 00:34:18'),
(466, 1, 1, 'contact_created', 'Contact created from lead', 'Contact created from lead #29 (vbdkfmv) by Olivia Carter.', 'contacts', 91, 1, '2026-09-13 00:34:18'),
(467, 1, 1, 'request_created', 'Estimate Request Created', 'Estimate request REQ-2026-001 created for Apex Technologies Pvt Ltd.', 'estimate_requests', 5, 1, '2026-09-13 00:38:43'),
(468, 1, 1, 'status_changed', 'Status Changed', 'Request status changed from New to In Review.', 'estimate_requests', 5, 1, '2026-09-13 00:40:49'),
(469, 1, 1, 'converted_to_deal', 'Converted to Deal', 'Converted to active sales opportunity: CRM Website Development (DEAL-2026-001).', 'estimate_requests', 5, 1, '2026-09-13 00:41:09'),
(470, 1, 1, 'request_created', 'Estimate Request Created', 'Estimate request REQ-2026-002 created for fvhjbcfhbcfh.', 'estimate_requests', 6, 1, '2026-09-13 00:42:32'),
(471, 1, 1, 'converted_to_deal', 'Converted to Deal', 'Converted to active sales opportunity: v sfdfvsdv (DEAL-2026-002).', 'estimate_requests', 6, 1, '2026-09-13 00:42:37'),
(483, 1, 1, 'request_updated', 'Request Updated', 'Estimate request REQ-2026-002 parameters updated.', 'estimate_requests', 6, 1, '2026-09-13 00:56:58'),
(484, 1, 1, 'request_updated', 'Request Updated', 'Estimate request REQ-2026-002 parameters updated.', 'estimate_requests', 6, 1, '2026-09-13 00:57:23'),
(485, 1, 1, 'task_added', 'Task Created', 'Task \'[Follow-up] [Follow-up] gbfsc bcsf bf\' created by Olivia Carter.', 'leads', 35, 1, '2026-09-13 14:21:51'),
(486, 1, 1, 'task_added', 'Task Created', 'Task \'[Follow-up] [Follow-up] bvkjfvb jcfvn,mcsfvn,\' created by Olivia Carter.', 'leads', 35, 1, '2026-09-13 14:22:28'),
(487, 1, 1, 'event_deleted', 'Deleted event \'To Delete\'', '', 'calendar', 13, 1, '2026-09-13 14:37:23'),
(488, 1, 1, 'event_created', 'Created event \'Automated Calendar Sync Test 99\'', 'Scheduled for Sep 22, 2026 11:00 AM', 'calendar', 15, 1, '2026-09-13 14:38:09'),
(489, 1, 1, 'event_deleted', 'Deleted event \'To Delete Test\'', '', 'calendar', 18, 1, '2026-09-13 14:38:09'),
(490, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'AUTOMATED TEST EVENT 6aa66f1ccbc59\'', 'Event scheduled for Sep 16, 2026 2:00 PM', 'calendar_events', 20, 1, '2026-09-13 15:08:36'),
(491, 1, 1, 'Meeting', 'Scheduled meeting: CROSS MODULE CONTACT MEETING 6aa66f1d8e260 on 2026-09-15 at 11:00', '', 'contacts', 58, 1, '2026-09-13 15:08:37'),
(492, 1, 1, 'Meeting', 'Scheduled meeting: CROSS MODULE DEAL MEETING 6aa66f1da149a on 2026-09-17 at 03:30', 'Cross-module deal meeting test', 'deals', 23, 1, '2026-09-13 11:38:37'),
(493, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'AUTOMATED TEST EVENT 6aa66f6990b6c\'', 'Event scheduled for Sep 16, 2026 2:00 PM', 'calendar_events', 23, 1, '2026-09-13 15:09:53'),
(494, 1, 1, 'Meeting', 'Scheduled meeting: CROSS MODULE CONTACT MEETING 6aa66f6a1a9fb on 2026-09-15 at 11:00', '', 'contacts', 58, 1, '2026-09-13 15:09:54'),
(495, 1, 1, 'Meeting', 'Scheduled meeting: CROSS MODULE DEAL MEETING 6aa66f6a29c2f on 2026-09-17 at 03:30', 'Cross-module deal meeting test', 'deals', 23, 1, '2026-09-13 11:39:54'),
(496, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'dbcfmdns\'', 'Event scheduled for Sep 13, 2026 10:00 AM', 'calendar_events', 26, 1, '2026-09-13 17:15:01'),
(497, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'Client Website Demo Meeting\'', 'Event scheduled for Sep 15, 2026 10:00 AM', 'calendar_events', 27, 1, '2026-09-13 17:18:04'),
(498, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'Client Website Demo Meeting\'', 'Event scheduled for Sep 13, 2026 10:00 AM', 'calendar_events', 28, 1, '2026-09-13 17:19:30'),
(499, 1, 1, 'Meeting', 'Scheduled meeting: Website Requirements Discussion on 2026-09-19 at 11:00', '', 'contacts', 39, 1, '2026-09-13 17:34:27'),
(500, 1, 1, 'meeting_scheduled', 'Meeting Scheduled', 'Meeting \'Lead Discovery & Requirements Call\' scheduled for Sep 20, 2026 10:00 AM by Olivia Carter.', 'leads', 35, 1, '2026-09-13 17:39:33'),
(501, 1, 1, 'meeting_scheduled', 'Scheduled Meeting: \'Future Client Strategy Meeting\'', 'Event scheduled for Sep 22, 2026 2:00 PM', 'calendar_events', 31, 1, '2026-09-13 17:43:44'),
(502, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'dbcfmdns\'', 'Event scheduled for Sep 13, 2026 10:00 AM', 'calendar_events', 32, 1, '2026-09-13 17:54:58'),
(503, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'jumjhmu\'', 'Event scheduled for Sep 13, 2026 12:00 AM', 'calendar_events', 33, 1, '2026-09-13 17:58:48'),
(508, 1, 1, 'Meeting', 'Scheduled meeting: Meeting: v sfdfvsdv on 2026-10-13 at 10:00 AM', 'hvbfcxdm', 'deals', 37, 1, '2026-09-13 15:23:05'),
(509, 1, 1, 'meeting_scheduled', 'Scheduled Product Demo: \'hello\'', 'Event scheduled for Sep 13, 2026 11:00 AM', 'calendar_events', 39, 1, '2026-09-13 18:58:29'),
(510, 1, 1, 'Email', 'Composed conversation: Quarterly Service Agreement Review', 'Hi Alice, here is our proposal for the quarterly service renewal.', 'contacts', 8, 1, '2026-09-13 19:22:43'),
(511, 1, 1, 'Email', 'Composed conversation: Quarterly Service Agreement Review', 'Hi Alice, here is our proposal for the quarterly service renewal.', 'contacts', 8, 1, '2026-09-13 19:22:54'),
(512, 1, 1, 'Email', 'Replied to conversation #2', 'Thank you for your feedback. We have adjusted the terms accordingly.', 'contacts', 8, 1, '2026-09-13 19:22:55'),
(513, 2, 7, 'Email', 'Composed conversation: Org 2 Confidential Thread', 'Strictly confidential message for Organization 2.', 'contacts', NULL, 7, '2026-09-13 19:22:56'),
(514, 1, 1, 'Email', 'Composed conversation: WhatsApp Coordination', 'Hello via WhatsApp message test', 'contacts', NULL, 1, '2026-09-13 19:22:56'),
(515, 1, 1, 'Email', 'Composed conversation: Quarterly Service Agreement Review', 'Hi Alice, here is our proposal for the quarterly service renewal.', 'contacts', 8, 1, '2026-09-13 19:23:08'),
(516, 1, 1, 'Email', 'Replied to conversation #5', 'Thank you for your feedback. We have adjusted the terms accordingly.', 'contacts', 8, 1, '2026-09-13 19:23:10'),
(517, 2, 7, 'Email', 'Composed conversation: Org 2 Confidential Thread', 'Strictly confidential message for Organization 2.', 'contacts', NULL, 7, '2026-09-13 19:23:10'),
(518, 1, 1, 'Email', 'Composed conversation: WhatsApp Coordination', 'Hello via WhatsApp message test', 'contacts', NULL, 1, '2026-09-13 19:23:10'),
(519, 1, 1, 'Email', 'Composed conversation: Website Project Follow Up', 'Hello Rahul,\r\n\r\nI am following up regarding the website project. Please let us know a suitable time for discussion.\r\n\r\nRegards,\r\nNexFlow Team', 'contacts', NULL, 1, '2026-09-13 19:25:54'),
(520, 1, 1, 'Email', 'Replied to conversation #8', 'Hi', 'contacts', NULL, 1, '2026-09-13 19:34:49'),
(521, 1, 1, 'Email', 'Composed conversation: graphic design', 'hi', 'contacts', NULL, 1, '2026-09-13 19:35:47'),
(522, 1, 1, 'Email', 'Composed conversation: TEST Snooze Conversation', 'This conversation will be snoozed.', 'contacts', NULL, 1, '2026-09-13 19:57:31'),
(523, 1, 1, 'Email', 'Composed conversation: TEST Snooze Conversation', 'This conversation will be snoozed.', 'contacts', NULL, 1, '2026-09-13 19:57:45'),
(524, 1, 1, 'Email', 'Composed conversation: TEST CRM Linked Conversation', 'Conversation with full CRM relationships.', 'contacts', 8, 1, '2026-09-13 19:57:46'),
(525, 1, 1, 'Email', 'Composed conversation: TEST Draft Sent to Conversation', 'Final body text for real conversation.', 'contacts', NULL, 1, '2026-09-13 19:57:47'),
(526, 1, 1, 'Email', 'Composed conversation: Quarterly Service Agreement Review', 'Hi Alice, here is our proposal for the quarterly service renewal.', 'contacts', 8, 1, '2026-09-13 19:57:50'),
(527, 1, 1, 'Email', 'Replied to conversation #14', 'Thank you for your feedback. We have adjusted the terms accordingly.', 'contacts', 8, 1, '2026-09-13 19:57:52'),
(528, 2, 7, 'Email', 'Composed conversation: Org 2 Confidential Thread', 'Strictly confidential message for Organization 2.', 'contacts', NULL, 7, '2026-09-13 19:57:52'),
(529, 1, 1, 'Email', 'Composed conversation: WhatsApp Coordination', 'Hello via WhatsApp message test', 'contacts', NULL, 1, '2026-09-13 19:57:53'),
(530, 1, 1, 'Email', 'Composed conversation: Website Project Follow Up', 'hi shweta', 'contacts', 39, 1, '2026-09-13 21:06:44'),
(531, 1, 1, 'Email', 'Composed conversation: TEST Snooze Conversation', 'This conversation will be snoozed.', 'contacts', NULL, 1, '2026-09-13 21:12:05'),
(532, 1, 1, 'Email', 'Composed conversation: TEST CRM Linked Conversation', 'Conversation with full CRM relationships.', 'contacts', 8, 1, '2026-09-13 21:12:06'),
(533, 1, 1, 'Email', 'Composed conversation: TEST Draft Sent to Conversation', 'Final body text for real conversation.', 'contacts', NULL, 1, '2026-09-13 21:12:07'),
(534, 1, 1, 'Email', 'Composed conversation: Quarterly Service Agreement Review', 'Hi Alice, here is our proposal for the quarterly service renewal.', 'contacts', 8, 1, '2026-09-13 21:12:10'),
(535, 1, 1, 'Email', 'Replied to conversation #21', 'Thank you for your feedback. We have adjusted the terms accordingly.', 'contacts', 8, 1, '2026-09-13 21:12:12'),
(536, 2, 7, 'Email', 'Composed conversation: Org 2 Confidential Thread', 'Strictly confidential message for Organization 2.', 'contacts', NULL, 7, '2026-09-13 21:12:12'),
(537, 1, 1, 'Email', 'Composed conversation: WhatsApp Coordination', 'Hello via WhatsApp message test', 'contacts', NULL, 1, '2026-09-13 21:12:13'),
(538, 1, 1, 'Email', 'Composed conversation: Website Project Follow Up', 'hi', 'contacts', 39, 1, '2026-09-13 21:16:02'),
(539, 1, 1, 'proposal_sent', 'Proposal Sent', 'Proposal PROP-004 was sent to client.', 'proposals', 8, 1, '2026-09-16 12:03:15'),
(540, 1, 39, 'proposal_viewed', 'Proposal Viewed', 'Proposal PROP-004 viewed by client (Shweta Singh)', 'proposals', 8, 39, '2026-09-16 12:03:57'),
(541, 1, 39, 'proposal_change_requested', 'Change Request Submitted', 'Client (Shweta Singh) requested changes (Other): \"hi, i am shweta\"', 'proposals', 8, 39, '2026-09-16 12:08:16'),
(542, 1, 1, 'proposal_sent', 'Revised Proposal Sent', 'Revised proposal PROP-004 was sent to client.', 'proposals', 8, 1, '2026-09-16 12:09:35'),
(543, 1, 39, 'proposal_viewed', 'Proposal Viewed', 'Proposal PROP-004 viewed by client (Shweta Singh)', 'proposals', 8, 39, '2026-09-16 12:09:40'),
(544, 1, 1, 'proposal_updated', 'Proposal Updated', 'Proposal PROP-004 details were updated.', 'proposals', 8, 1, '2026-09-16 12:09:53'),
(545, 1, 39, 'proposal_accepted', 'Proposal Accepted', 'Proposal PROP-004 accepted by client (Shweta Singh)', 'proposals', 8, 39, '2026-09-16 12:10:13'),
(546, 1, 1, 'proposal_sent', 'Proposal Sent', 'Proposal PROP-005 was sent to client.', 'proposals', 9, 1, '2026-09-16 12:11:01'),
(547, 1, 39, 'proposal_declined', 'Proposal Declined', 'Proposal PROP-005 declined by client (Shweta Singh)', 'proposals', 9, 39, '2026-09-16 12:11:07'),
(552, 1, 1, 'request_updated', 'Request Updated', 'Estimate request REQ-2026-002 parameters updated.', 'estimate_requests', 6, 1, '2026-09-16 18:49:35'),
(553, 1, 1, 'request_updated', 'Request Updated', 'Estimate request REQ-2026-002 parameters updated.', 'estimate_requests', 6, 1, '2026-09-16 18:50:10'),
(569, 1, 1, 'contract_sent', 'Contract Sent', 'Contract CON-002 was sent to client.', 'contracts', 12, 1, '2026-09-18 19:07:54'),
(570, 1, 39, 'contract_viewed', 'Contract Viewed', 'Contract CON-002 viewed in Client Portal by Shweta Singh', 'contracts', 12, 39, '2026-09-18 19:08:01'),
(571, 1, 1, 'meeting_scheduled', 'Scheduled Meeting: \'Temporary Security Verification Meeting\'', 'Event scheduled for Sep 21, 2026 2:00 PM', 'calendar_events', 40, 1, '2026-09-18 21:49:29'),
(572, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Regression Test Outbound Reply', 'contacts', 65, 1, '2026-09-18 22:39:51'),
(573, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Regression Test Outbound Reply', 'contacts', 65, 1, '2026-09-18 22:40:15'),
(574, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Compatibility Reply - 6aad7258ef633', 'contacts', 65, 1, '2026-09-18 22:48:17'),
(575, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Compatibility Reply - 6aad72659e6e4', 'contacts', 65, 1, '2026-09-18 22:48:29'),
(576, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Regression Test Outbound Reply', 'contacts', 65, 1, '2026-09-18 22:48:36'),
(577, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Compatibility Reply - 6aad7297b3afd', 'contacts', 65, 1, '2026-09-18 22:49:19'),
(578, 1, 1, 'Email', 'Replied to conversation #24', 'Admin Regression Test Outbound Reply', 'contacts', 65, 1, '2026-09-20 17:06:49'),
(579, 1, 1, 'Email', 'Replied to conversation #24', 'hi', 'contacts', 65, 1, '2026-09-20 17:09:00'),
(582, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Prospect → Qualified', 'deals', 37, 1, '2026-09-22 08:41:28'),
(583, 1, 1, 'Stage Change', 'Stage changed to Prospect', 'Moved from Qualified → Prospect', 'deals', 37, 1, '2026-09-22 08:41:30'),
(584, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Prospect → Qualified', 'deals', 36, 1, '2026-09-22 08:41:34'),
(585, 1, 1, 'Stage Change', 'Stage changed to Prospect', 'Moved from Qualified → Prospect', 'deals', 36, 1, '2026-09-22 08:41:37'),
(586, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Proposal → Qualified', 'deals', 26, 1, '2026-09-22 08:41:55'),
(587, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Proposal → Qualified', 'deals', 22, 1, '2026-09-22 08:41:58'),
(588, 1, 1, 'Stage Change', 'Stage changed to Qualified', 'Moved from Prospect → Qualified', 'deals', 36, 1, '2026-09-22 08:42:05'),
(589, 1, 1, 'Stage Change', 'Stage changed to Closed Won', 'Moved from Qualified → Closed Won', 'deals', 36, 1, '2026-09-22 08:42:12'),
(590, 1, 1, 'Stage Change', 'Stage changed to Closed Won', 'Moved from Prospect → Closed Won', 'deals', 27, 1, '2026-09-22 08:42:27');

-- --------------------------------------------------------

--
-- Table structure for table `team_defaults`
--

CREATE TABLE `team_defaults` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `default_lead_owner_id` int(10) UNSIGNED DEFAULT NULL,
  `default_deal_owner_id` int(10) UNSIGNED DEFAULT NULL,
  `default_task_assignee_id` int(10) UNSIGNED DEFAULT NULL,
  `default_team_id` int(10) UNSIGNED DEFAULT NULL,
  `default_availability` varchar(30) NOT NULL DEFAULT 'Available',
  `working_hours` varchar(100) NOT NULL DEFAULT '09:00 - 17:00',
  `sales_target_period` varchar(30) NOT NULL DEFAULT 'Monthly',
  `default_monthly_quota` varchar(50) NOT NULL DEFAULT '$50,000',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_defaults`
--

INSERT INTO `team_defaults` (`id`, `organization_id`, `default_lead_owner_id`, `default_deal_owner_id`, `default_task_assignee_id`, `default_team_id`, `default_availability`, `working_hours`, `sales_target_period`, `default_monthly_quota`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, NULL, 'Available', '09:00 - 17:00', 'Monthly', '$50,000', '2026-09-07 14:22:47', '2026-09-13 22:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `team_notes`
--

CREATE TABLE `team_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED DEFAULT NULL,
  `related_type` varchar(50) NOT NULL DEFAULT 'member',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_notes`
--

INSERT INTO `team_notes` (`id`, `organization_id`, `member_id`, `related_type`, `related_id`, `created_by`, `title`, `content`, `is_pinned`, `created_at`, `updated_at`) VALUES
(45, 1, 1, 'contracts', 12, 1, 'Internal Contract Note', 'vc vf fc', 1, '2026-09-12 21:15:37', '2026-09-12 21:15:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `first_name` varchar(75) DEFAULT NULL,
  `last_name` varchar(75) DEFAULT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `language` varchar(20) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `short_bio` text DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `availability` varchar(30) NOT NULL DEFAULT 'Available',
  `reports_to` int(10) UNSIGNED DEFAULT NULL,
  `quota` decimal(12,2) NOT NULL DEFAULT 50000.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `organization_id`, `name`, `first_name`, `last_name`, `display_name`, `email`, `phone`, `job_title`, `department`, `location`, `timezone`, `language`, `photo_path`, `short_bio`, `linkedin_url`, `password_hash`, `role`, `role_id`, `availability`, `reports_to`, `quota`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Olivia Carter', 'Olivia', 'Carter', 'Olivia Carter', 'olivia@novasphere.example', '+1 (555) 987-6543', 'Super Administrator', '', 'San Francisco, CA', 'America/Los_Angeles', 'en-US', 'uploads/users/user_1_1788769756_9662f40f.jpg', 'Founder & Super Admin at NovaSphere', 'https://linkedin.com/in/oliviacarter', '$2y$10$ne1fdzTcuAJ9ty20xpkp2eKBSJMoGqnlpA1/v.F32dfXrVZd/EZ2C', 'super_admin', 1, 'Available', NULL, 50000.00, 'active', '2026-09-22 12:10:14', '2026-09-07 13:31:48', '2026-09-07 13:59:16'),
(7, 2, '', 'Org2', 'Admin', NULL, 'org2_admin@test.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$2yJH8OJ6StZRaa7OhHEWquXdLfmeFS/Zj53wZU7/PwverXMU./FG.', 'admin', NULL, 'Available', NULL, 50000.00, 'active', '2026-09-07 22:47:21', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(14, 1, 'Tanu Kaur', 'Tanu', 'Kaur', NULL, 'tanu@gmail.com', '7987786754', 'Account Executive', 'Account Management', 'Chandigarh', NULL, NULL, NULL, NULL, NULL, '$2y$10$AinXyvhZkFzw7wkW8tT2ze1V/uHeqBhaK4VufP/v.UU9wtDbYprWa', 'account_executive', 4, 'Available', 1, 50000.00, 'active', NULL, '2026-09-07 21:57:40', '2026-09-07 21:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `new_lead_assigned` tinyint(1) NOT NULL DEFAULT 1,
  `lead_status_changed` tinyint(1) NOT NULL DEFAULT 1,
  `follow_up_due` tinyint(1) NOT NULL DEFAULT 1,
  `deal_assigned` tinyint(1) NOT NULL DEFAULT 1,
  `task_due_soon` tinyint(1) NOT NULL DEFAULT 1,
  `missed_simulated_call` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_notification_preferences`
--

INSERT INTO `user_notification_preferences` (`id`, `user_id`, `new_lead_assigned`, `lead_status_changed`, `follow_up_due`, `deal_assigned`, `task_due_soon`, `missed_simulated_call`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 1, 1, '2026-09-07 14:05:10', '2026-09-13 22:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_id`, `created_at`) VALUES
(5, 11, 17, '2026-09-07 21:44:40'),
(6, 11, 19, '2026-09-07 21:44:40'),
(7, 11, 1, '2026-09-07 21:44:40'),
(8, 11, 5, '2026-09-07 21:44:40'),
(13, 13, 17, '2026-09-07 21:45:25'),
(14, 13, 19, '2026-09-07 21:45:25'),
(15, 13, 1, '2026-09-07 21:45:25'),
(16, 13, 5, '2026-09-07 21:45:25'),
(33, 14, 1, '2026-09-07 21:57:40'),
(34, 14, 96, '2026-09-07 21:57:40'),
(35, 14, 7, '2026-09-07 21:57:40'),
(36, 14, 8, '2026-09-07 21:57:40'),
(37, 14, 9, '2026-09-07 21:57:40'),
(38, 14, 10, '2026-09-07 21:57:40'),
(39, 14, 11, '2026-09-07 21:57:40'),
(40, 14, 12, '2026-09-07 21:57:40'),
(41, 14, 28, '2026-09-07 21:57:40'),
(42, 14, 29, '2026-09-07 21:57:40'),
(43, 14, 30, '2026-09-07 21:57:40'),
(44, 14, 33, '2026-09-07 21:57:40'),
(45, 14, 34, '2026-09-07 21:57:40'),
(46, 14, 175, '2026-09-07 21:57:40'),
(47, 14, 177, '2026-09-07 21:57:40'),
(48, 14, 169, '2026-09-07 21:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(20) NOT NULL DEFAULT 'en-US',
  `timezone` varchar(100) NOT NULL DEFAULT 'America/Los_Angeles',
  `date_format` varchar(30) NOT NULL DEFAULT 'MMM DD, YYYY',
  `time_format` varchar(10) NOT NULL DEFAULT '12h',
  `week_starts_on` varchar(10) NOT NULL DEFAULT 'Sunday',
  `currency` varchar(20) NOT NULL DEFAULT 'USD ($)',
  `number_format` varchar(20) NOT NULL DEFAULT '1,234.56',
  `landing_page` varchar(50) NOT NULL DEFAULT 'dashboard',
  `table_rows` int(10) UNSIGNED NOT NULL DEFAULT 25,
  `interface_density` varchar(20) NOT NULL DEFAULT 'comfortable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `language`, `timezone`, `date_format`, `time_format`, `week_starts_on`, `currency`, `number_format`, `landing_page`, `table_rows`, `interface_density`, `created_at`, `updated_at`) VALUES
(1, 1, 'en-US', 'America/Los_Angeles', 'MMM DD, YYYY', '12h', 'Sunday', 'USD ($)', '1,234.56', 'dashboard', 25, 'comfortable', '2026-09-07 14:05:10', '2026-09-13 22:44:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_cal_related` (`related_type`,`related_id`),
  ADD KEY `idx_cal_org_user` (`organization_id`,`user_id`),
  ADD KEY `idx_cal_start` (`organization_id`,`start_time`),
  ADD KEY `idx_cal_contact` (`organization_id`,`contact_id`),
  ADD KEY `idx_cal_company` (`organization_id`,`company_id`),
  ADD KEY `idx_cal_deal` (`organization_id`,`deal_id`);

--
-- Indexes for table `client_portal_users`
--
ALTER TABLE `client_portal_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cpu_org_email` (`organization_id`,`email`),
  ADD KEY `idx_cpu_contact` (`contact_id`),
  ADD KEY `idx_cpu_company` (`company_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_companies_org` (`organization_id`),
  ADD KEY `idx_companies_owner` (`owner_id`),
  ADD KEY `idx_companies_relationship` (`relationship`),
  ADD KEY `idx_companies_org_name` (`organization_id`,`name`),
  ADD KEY `idx_companies_primary_contact` (`primary_contact_id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contacts_org` (`organization_id`),
  ADD KEY `idx_contacts_owner` (`owner_id`),
  ADD KEY `idx_contacts_email` (`email`),
  ADD KEY `idx_contacts_relationship` (`relationship`),
  ADD KEY `idx_contacts_org_name` (`organization_id`,`name`),
  ADD KEY `idx_contacts_lead` (`lead_id`),
  ADD KEY `idx_contacts_org_company` (`organization_id`,`company_id`),
  ADD KEY `fk_contacts_company` (`company_id`);

--
-- Indexes for table `contact_companies`
--
ALTER TABLE `contact_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_contact_company` (`organization_id`,`contact_id`,`company_id`),
  ADD KEY `idx_cc_org` (`organization_id`),
  ADD KEY `idx_cc_contact` (`contact_id`),
  ADD KEY `idx_cc_company` (`company_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_contract_num` (`organization_id`,`contract_number`),
  ADD KEY `idx_contracts_org_status` (`organization_id`,`status`),
  ADD KEY `idx_contracts_company` (`company_id`),
  ADD KEY `idx_contracts_contact` (`contact_id`),
  ADD KEY `idx_contracts_owner` (`owner_id`),
  ADD KEY `idx_contracts_end_date` (`end_date`);

--
-- Indexes for table `contract_files`
--
ALTER TABLE `contract_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract_files_org` (`organization_id`),
  ADD KEY `idx_contract_files_contract` (`contract_id`),
  ADD KEY `fk_contract_files_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `crm_custom_fields`
--
ALTER TABLE `crm_custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_org_target_key` (`organization_id`,`target_object`,`field_key`);

--
-- Indexes for table `crm_custom_field_options`
--
ALTER TABLE `crm_custom_field_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custom_field_id` (`custom_field_id`);

--
-- Indexes for table `crm_custom_field_values`
--
ALTER TABLE `crm_custom_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_field_entity` (`custom_field_id`,`entity_type`,`entity_id`);

--
-- Indexes for table `crm_integrations`
--
ALTER TABLE `crm_integrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_org_integration` (`organization_id`,`integration_key`),
  ADD KEY `idx_org_status` (`organization_id`,`status`);

--
-- Indexes for table `crm_settings`
--
ALTER TABLE `crm_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_org_key` (`organization_id`,`setting_key`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_custom_fields_org` (`organization_id`);

--
-- Indexes for table `custom_reports`
--
ALTER TABLE `custom_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_custom_reports_org` (`organization_id`,`created_by`);

--
-- Indexes for table `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_stage` (`stage`),
  ADD KEY `idx_lead_id` (`lead_id`),
  ADD KEY `idx_deals_source` (`source`),
  ADD KEY `idx_deals_contact` (`contact_id`),
  ADD KEY `idx_deals_org_status_close` (`organization_id`,`status`,`close_date`),
  ADD KEY `idx_deals_org_user_status` (`organization_id`,`assigned_to`,`status`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_documents_org_code` (`organization_id`,`document_code`),
  ADD KEY `idx_documents_org` (`organization_id`),
  ADD KEY `idx_documents_status` (`organization_id`,`is_archived`,`status`),
  ADD KEY `idx_documents_category` (`organization_id`,`category`),
  ADD KEY `idx_documents_type` (`organization_id`,`document_type`),
  ADD KEY `idx_documents_owner` (`organization_id`,`owner_id`),
  ADD KEY `idx_documents_company` (`organization_id`,`company_id`),
  ADD KEY `idx_documents_project` (`organization_id`,`project_id`),
  ADD KEY `fk_docs_company` (`company_id`),
  ADD KEY `fk_docs_contact` (`contact_id`),
  ADD KEY `fk_docs_lead` (`lead_id`),
  ADD KEY `fk_docs_deal` (`deal_id`),
  ADD KEY `fk_docs_project` (`project_id`),
  ADD KEY `fk_docs_proposal` (`proposal_id`),
  ADD KEY `fk_docs_contract` (`contract_id`),
  ADD KEY `fk_docs_invoice` (`invoice_id`),
  ADD KEY `fk_docs_subscription` (`subscription_id`),
  ADD KEY `fk_docs_owner` (`owner_id`),
  ADD KEY `fk_docs_uploader` (`uploaded_by`);

--
-- Indexes for table `estimate_requests`
--
ALTER TABLE `estimate_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_est_req_org_code` (`organization_id`,`request_code`),
  ADD KEY `idx_est_req_org` (`organization_id`),
  ADD KEY `idx_est_req_org_status` (`organization_id`,`status`),
  ADD KEY `idx_est_req_company` (`company_id`),
  ADD KEY `idx_est_req_contact` (`contact_id`),
  ADD KEY `idx_est_req_owner` (`owner_id`),
  ADD KEY `idx_est_req_deal` (`deal_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_expenses_org_ref` (`organization_id`,`reference_number`),
  ADD KEY `idx_expenses_org_date` (`organization_id`,`expense_date`),
  ADD KEY `idx_expenses_org_category` (`organization_id`,`category`),
  ADD KEY `idx_expenses_org_billing` (`organization_id`,`billing_type`),
  ADD KEY `idx_expenses_org_invoice` (`organization_id`,`invoice_status`),
  ADD KEY `idx_expenses_org_reimburse` (`organization_id`,`reimbursement_status`),
  ADD KEY `idx_expenses_company` (`company_id`),
  ADD KEY `idx_expenses_project` (`project_id`),
  ADD KEY `idx_expenses_owner` (`owner_id`),
  ADD KEY `fk_expenses_created_by` (`created_by`),
  ADD KEY `fk_expenses_updated_by` (`updated_by`),
  ADD KEY `fk_expenses_invoiced_by` (`invoiced_by`);

--
-- Indexes for table `inbox_conversations`
--
ALTER TABLE `inbox_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inbox_conv_org` (`organization_id`),
  ADD KEY `idx_inbox_conv_code` (`conversation_code`),
  ADD KEY `idx_inbox_conv_contact` (`contact_id`),
  ADD KEY `idx_inbox_conv_company` (`company_id`),
  ADD KEY `idx_inbox_conv_lead` (`lead_id`),
  ADD KEY `idx_inbox_conv_deal` (`deal_id`),
  ADD KEY `idx_inbox_conv_assigned` (`assigned_to`),
  ADD KEY `idx_inbox_conv_team` (`team_id`),
  ADD KEY `idx_inbox_conv_channel` (`channel`),
  ADD KEY `idx_inbox_conv_status` (`status`),
  ADD KEY `idx_inbox_conv_priority` (`priority`),
  ADD KEY `idx_inbox_conv_unread` (`is_unread`),
  ADD KEY `idx_inbox_conv_starred` (`is_starred`),
  ADD KEY `idx_inbox_conv_snooze` (`snoozed_until`),
  ADD KEY `idx_inbox_conv_last_msg` (`last_message_at`);

--
-- Indexes for table `inbox_drafts`
--
ALTER TABLE `inbox_drafts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drafts_org_user` (`organization_id`,`user_id`),
  ADD KEY `fk_drafts_user` (`user_id`),
  ADD KEY `fk_drafts_contact` (`contact_id`),
  ADD KEY `fk_drafts_company` (`company_id`),
  ADD KEY `fk_drafts_deal` (`deal_id`);

--
-- Indexes for table `inbox_messages`
--
ALTER TABLE `inbox_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inbox_msg_org` (`organization_id`),
  ADD KEY `idx_inbox_msg_conv` (`conversation_id`),
  ADD KEY `idx_inbox_msg_sender` (`sender_type`,`sender_id`),
  ADD KEY `idx_inbox_msg_sent` (`sent_at`);

--
-- Indexes for table `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_industry_key` (`key`);

--
-- Indexes for table `industry_modules`
--
ALTER TABLE `industry_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_industry_module` (`industry_id`,`module_id`),
  ADD KEY `fk_im_module` (`module_id`);

--
-- Indexes for table `industry_pipeline_stages`
--
ALTER TABLE `industry_pipeline_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_industry_stage` (`industry_id`,`name`),
  ADD KEY `idx_stage_industry` (`industry_id`);

--
-- Indexes for table `industry_terms`
--
ALTER TABLE `industry_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_industry_terms` (`industry_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_org_invoice_num` (`organization_id`,`invoice_number`),
  ADD KEY `idx_inv_org_status` (`organization_id`,`status`),
  ADD KEY `idx_inv_org_company` (`organization_id`,`company_id`),
  ADD KEY `idx_inv_org_due` (`organization_id`,`due_date`),
  ADD KEY `fk_inv_company` (`company_id`),
  ADD KEY `fk_inv_contact` (`contact_id`),
  ADD KEY `fk_inv_project` (`project_id`),
  ADD KEY `fk_inv_deal` (`deal_id`),
  ADD KEY `fk_inv_assigned` (`assigned_to`),
  ADD KEY `fk_inv_created_by` (`created_by`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_items_org_inv` (`organization_id`,`invoice_id`),
  ADD KEY `fk_item_invoice` (`invoice_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_payments_org_inv` (`organization_id`,`invoice_id`),
  ADD KEY `fk_pay_invoice` (`invoice_id`),
  ADD KEY `fk_pay_recorded_by` (`recorded_by`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_leads_org_archived` (`organization_id`,`is_archived`),
  ADD KEY `idx_leads_org_source_created` (`organization_id`,`source`,`created_at`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_module_key` (`key`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_slug` (`slug`),
  ADD KEY `fk_org_industry` (`industry_id`);

--
-- Indexes for table `organization_modules`
--
ALTER TABLE `organization_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_module` (`organization_id`,`module_key`),
  ADD KEY `idx_org_module_id` (`module_id`);

--
-- Indexes for table `organization_settings`
--
ALTER TABLE `organization_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_settings` (`organization_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_module_action` (`module_key`,`action`);

--
-- Indexes for table `pipeline_stages`
--
ALTER TABLE `pipeline_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pipeline_org` (`organization_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_projects_org_code` (`organization_id`,`project_code`),
  ADD KEY `idx_projects_org` (`organization_id`),
  ADD KEY `idx_projects_manager` (`manager_id`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_due_date` (`due_date`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pf_org` (`organization_id`),
  ADD KEY `idx_pf_project` (`project_id`);

--
-- Indexes for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pm_org` (`organization_id`),
  ADD KEY `idx_pm_project` (`project_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_proposal_number` (`organization_id`,`proposal_number`),
  ADD KEY `fk_proposals_company` (`company_id`),
  ADD KEY `fk_proposals_contact` (`contact_id`),
  ADD KEY `fk_proposals_deal` (`deal_id`),
  ADD KEY `fk_proposals_project` (`project_id`),
  ADD KEY `fk_proposals_prepared_by` (`prepared_by`),
  ADD KEY `fk_proposals_creator` (`created_by`),
  ADD KEY `idx_proposals_org_status` (`organization_id`,`status`),
  ADD KEY `idx_proposals_org_expiry` (`organization_id`,`expiry_date`);

--
-- Indexes for table `proposal_items`
--
ALTER TABLE `proposal_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_proposal_items_proposal` (`proposal_id`),
  ADD KEY `idx_proposal_items_lookup` (`organization_id`,`proposal_id`,`sort_order`);

--
-- Indexes for table `report_saved_presets`
--
ALTER TABLE `report_saved_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_presets_org` (`organization_id`,`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_org_role_slug` (`organization_id`,`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_permission` (`role_id`,`permission_id`),
  ADD KEY `fk_rp_permission` (`permission_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subs_org_code` (`organization_id`,`subscription_code`),
  ADD KEY `idx_subs_org` (`organization_id`),
  ADD KEY `idx_subs_company` (`company_id`),
  ADD KEY `idx_subs_company_name` (`company_name`),
  ADD KEY `idx_subs_contact` (`contact_id`),
  ADD KEY `idx_subs_project` (`project_id`),
  ADD KEY `idx_subs_status` (`status`),
  ADD KEY `idx_subs_plan` (`plan_tier`),
  ADD KEY `idx_subs_billing_cycle` (`billing_cycle`),
  ADD KEY `idx_subs_next_billing` (`next_billing_date`),
  ADD KEY `idx_subs_owner` (`owner_id`),
  ADD KEY `idx_subs_created_by` (`created_by`),
  ADD KEY `idx_subs_is_active` (`is_active`),
  ADD KEY `idx_subs_created_at` (`created_at`);

--
-- Indexes for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sub_inv_org_num` (`organization_id`,`invoice_number`),
  ADD KEY `idx_sub_inv_sub` (`subscription_id`),
  ADD KEY `idx_sub_inv_org` (`organization_id`),
  ADD KEY `idx_sub_inv_status` (`status`),
  ADD KEY `idx_sub_inv_issue_date` (`issue_date`),
  ADD KEY `idx_sub_inv_paid_date` (`paid_date`),
  ADD KEY `fk_sub_inv_creator` (`created_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tasks_company` (`company_id`),
  ADD KEY `idx_tasks_contact` (`contact_id`),
  ADD KEY `idx_tasks_deal` (`deal_id`),
  ADD KEY `idx_tasks_type` (`task_type`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teams_org` (`organization_id`);

--
-- Indexes for table `team_activities`
--
ALTER TABLE `team_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_activities_org_type_created` (`organization_id`,`activity_type`,`created_at`);

--
-- Indexes for table `team_defaults`
--
ALTER TABLE `team_defaults`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organization_id` (`organization_id`);

--
-- Indexes for table `team_notes`
--
ALTER TABLE `team_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org` (`organization_id`),
  ADD KEY `idx_member` (`member_id`),
  ADD KEY `idx_pinned` (`is_pinned`),
  ADD KEY `idx_notes_related` (`related_type`,`related_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_email` (`email`),
  ADD KEY `idx_user_org` (`organization_id`);

--
-- Indexes for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_perm` (`user_id`,`permission_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_permission_id` (`permission_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `client_portal_users`
--
ALTER TABLE `client_portal_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `contact_companies`
--
ALTER TABLE `contact_companies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `contract_files`
--
ALTER TABLE `contract_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99994;

--
-- AUTO_INCREMENT for table `crm_custom_fields`
--
ALTER TABLE `crm_custom_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crm_custom_field_options`
--
ALTER TABLE `crm_custom_field_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `crm_custom_field_values`
--
ALTER TABLE `crm_custom_field_values`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `crm_integrations`
--
ALTER TABLE `crm_integrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

--
-- AUTO_INCREMENT for table `crm_settings`
--
ALTER TABLE `crm_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `custom_fields`
--
ALTER TABLE `custom_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_reports`
--
ALTER TABLE `custom_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `estimate_requests`
--
ALTER TABLE `estimate_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `inbox_conversations`
--
ALTER TABLE `inbox_conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `inbox_drafts`
--
ALTER TABLE `inbox_drafts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inbox_messages`
--
ALTER TABLE `inbox_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `industry_modules`
--
ALTER TABLE `industry_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `industry_pipeline_stages`
--
ALTER TABLE `industry_pipeline_stages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `industry_terms`
--
ALTER TABLE `industry_terms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `organization_modules`
--
ALTER TABLE `organization_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `organization_settings`
--
ALTER TABLE `organization_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `pipeline_stages`
--
ALTER TABLE `pipeline_stages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `project_milestones`
--
ALTER TABLE `project_milestones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `proposal_items`
--
ALTER TABLE `proposal_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `report_saved_presets`
--
ALTER TABLE `report_saved_presets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=445;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `team_activities`
--
ALTER TABLE `team_activities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=591;

--
-- AUTO_INCREMENT for table `team_defaults`
--
ALTER TABLE `team_defaults`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `team_notes`
--
ALTER TABLE `team_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_portal_users`
--
ALTER TABLE `client_portal_users`
  ADD CONSTRAINT `fk_cpu_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpu_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpu_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `fk_companies_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_companies_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_companies_primary_contact` FOREIGN KEY (`primary_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `fk_contacts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contacts_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contacts_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contacts_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contact_companies`
--
ALTER TABLE `contact_companies`
  ADD CONSTRAINT `fk_cc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cc_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cc_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contracts_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contracts_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contract_files`
--
ALTER TABLE `contract_files`
  ADD CONSTRAINT `fk_contract_files_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contract_files_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contract_files_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crm_custom_field_options`
--
ALTER TABLE `crm_custom_field_options`
  ADD CONSTRAINT `crm_custom_field_options_ibfk_1` FOREIGN KEY (`custom_field_id`) REFERENCES `crm_custom_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crm_custom_field_values`
--
ALTER TABLE `crm_custom_field_values`
  ADD CONSTRAINT `crm_custom_field_values_ibfk_1` FOREIGN KEY (`custom_field_id`) REFERENCES `crm_custom_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD CONSTRAINT `fk_custom_fields_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deals`
--
ALTER TABLE `deals`
  ADD CONSTRAINT `fk_deals_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_docs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docs_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_docs_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `estimate_requests`
--
ALTER TABLE `estimate_requests`
  ADD CONSTRAINT `fk_est_req_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_est_req_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_est_req_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_est_req_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_est_req_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_invoiced_by` FOREIGN KEY (`invoiced_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_expenses_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inbox_conversations`
--
ALTER TABLE `inbox_conversations`
  ADD CONSTRAINT `fk_inbox_conv_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inbox_drafts`
--
ALTER TABLE `inbox_drafts`
  ADD CONSTRAINT `fk_drafts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drafts_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drafts_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_drafts_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drafts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inbox_messages`
--
ALTER TABLE `inbox_messages`
  ADD CONSTRAINT `fk_inbox_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `inbox_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inbox_msg_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `industry_modules`
--
ALTER TABLE `industry_modules`
  ADD CONSTRAINT `fk_im_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_im_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `industry_pipeline_stages`
--
ALTER TABLE `industry_pipeline_stages`
  ADD CONSTRAINT `fk_stage_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `industry_terms`
--
ALTER TABLE `industry_terms`
  ADD CONSTRAINT `fk_terms_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_inv_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inv_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pay_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pay_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `fk_org_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `organization_modules`
--
ALTER TABLE `organization_modules`
  ADD CONSTRAINT `fk_org_module_catalog` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_org_module_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organization_settings`
--
ALTER TABLE `organization_settings`
  ADD CONSTRAINT `fk_settings_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pipeline_stages`
--
ALTER TABLE `pipeline_stages`
  ADD CONSTRAINT `fk_pipeline_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `fk_pf_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `fk_proposals_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proposals_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proposals_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proposals_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proposals_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_proposals_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_proposals_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proposal_items`
--
ALTER TABLE `proposal_items`
  ADD CONSTRAINT `fk_proposal_items_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_proposal_items_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subs_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_subs_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subs_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  ADD CONSTRAINT `fk_sub_inv_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sub_inv_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_inv_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_deal` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `fk_teams_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_defaults`
--
ALTER TABLE `team_defaults`
  ADD CONSTRAINT `fk_team_def_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD CONSTRAINT `fk_user_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_user_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
