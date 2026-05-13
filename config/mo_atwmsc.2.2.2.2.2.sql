-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 08:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mo_atwms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(71, 3, 'User Logout', 'User logged out', '::1', '2026-05-13 08:11:09'),
(74, 9, 'User Logout', 'User logged out', '::1', '2026-05-13 11:51:59'),
(75, 1, 'Admin Login', 'Super Admin logged in', '::1', '2026-05-13 11:54:33'),
(76, 1, 'Admin Logout', 'Super Admin logged out', '::1', '2026-05-13 12:46:21'),
(79, 3, 'User Logout', 'User logged out', '::1', '2026-05-13 16:56:47'),
(80, 9, 'User Logout', 'User logged out', '::1', '2026-05-13 16:56:52'),
(84, 1, 'Approve User', 'User ID: 22 approved and activated. New status: Active', '::1', '2026-05-13 18:09:58'),
(85, 1, 'Approve User', 'User ID: 21 approved and activated. New status: Active', '::1', '2026-05-13 18:10:02'),
(86, 1, 'Approve User', 'User ID: 20 approved and activated. New status: Active', '::1', '2026-05-13 18:10:04'),
(87, 1, 'Approve User', 'User ID: 19 approved and activated. New status: Active', '::1', '2026-05-13 18:10:21'),
(88, 1, 'Reject User', 'User ID: 18 rejected. Remarks: Wrong Information', '::1', '2026-05-13 18:10:31'),
(89, 1, 'Admin Logout', 'Super Admin logged out', '::1', '2026-05-13 18:27:12'),
(90, 19, 'User Logout', 'User logged out', '::1', '2026-05-13 18:29:27'),
(91, 20, 'User Logout', 'User logged out', '::1', '2026-05-13 18:38:34'),
(92, 21, 'User Logout', 'User logged out', '::1', '2026-05-13 18:40:51'),
(93, 22, 'User Logout', 'User logged out', '::1', '2026-05-13 18:43:10'),
(94, 23, 'User Logout', 'User logged out', '::1', '2026-05-13 18:45:45'),
(95, 9, 'User Logout', 'User logged out', '::1', '2026-05-13 18:51:34');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `doc_sequence_number` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `document_type` varchar(100) DEFAULT 'General',
  `priority` enum('Normal','Urgent','Critical') DEFAULT 'Normal',
  `classification` varchar(100) DEFAULT NULL,
  `sub_classification` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(50) DEFAULT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_received` timestamp NULL DEFAULT NULL,
  `deadline` timestamp NULL DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Archived') DEFAULT 'Pending',
  `office_department` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `doc_sequence_number`, `title`, `description`, `created_by`, `created_at`, `updated_at`, `document_type`, `priority`, `classification`, `sub_classification`, `tracking_number`, `sender_id`, `sender_name`, `date_sent`, `date_received`, `deadline`, `file_path`, `notes`, `status`, `office_department`) VALUES
(187, 3, 'Digital Marketing Workshop Invitation', 'You are invited to attend an interactive workshop covering social media marketing, SEO, content creation, and digital advertising strategies.', 19, '2026-05-13 18:16:18', '2026-05-13 18:20:55', 'General', 'Urgent', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-P9IRA', NULL, 'Marketing Department', '2026-05-13 18:16:18', '2026-05-13 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0019-1778696178-6a04bff2a3699.jpg', '0', 'Approved', 'Local Civil Registrar'),
(189, 5, 'Health and Wellness Awareness Seminar', 'We cordially invite you to participate in a seminar promoting healthy lifestyles, mental wellness, stress management, and workplace well-being.\r\nTitle: Invitation to Cybersecurity Awareness Training', 19, '2026-05-13 18:18:57', '2026-05-13 18:24:17', 'General', 'Critical', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-PITEI', NULL, 'Employee Welfare Office', '2026-05-13 18:18:57', '2026-04-29 16:00:00', '2026-05-13 16:00:00', 'uploads/documents/DOC-0019-1778696337-6a04c09111f35.webp', '0', 'Approved', 'Local Civil Registrar'),
(190, 6, 'Customer Service Excellence Seminar Invitation', 'Enhance your customer service skills through practical discussions, case studies, and proven techniques for delivering exceptional client experiences.', 19, '2026-05-13 18:19:59', '2026-05-13 18:20:23', 'General', 'Urgent', 'Letter', 'Request Letter', 'DOC-20260514-7CX8T', NULL, 'Customer Relations Department', '2026-05-13 18:19:59', '2026-05-08 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0019-1778696399-6a04c0cf79e2d.webp', '0', 'Pending', 'Local Civil Registrar'),
(191, NULL, 'Travel Request - Digital Marketing Workshop Invitation', 'You are invited to attend an interactive workshop covering social media marketing, SEO, content creation, and digital advertising strategies.', 19, '2026-05-13 18:22:11', '2026-05-13 18:22:11', 'Travel Request', 'Urgent', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514022211-6a04c153d256f', NULL, 'Local Civil Registrar', '2026-05-13 18:22:11', '2026-05-12 16:00:00', NULL, NULL, '{\"officer_name\":\"Local Civil Registrar\",\"order_type\":\"Travel Order\",\"purpose_of_order\":\"Meeting\",\"purpose_specify\":\"\",\"travelers\":[{\"name\":\"Juan Dela Cruz\",\"position\":\"Head\"}],\"event_title\":\"Digital Marketing Workshop Invitation\",\"event_date\":\"2026-05-14\",\"event_place\":\"Provincial Kapitolyo\",\"event_description\":\"You are invited to attend an interactive workshop covering social media marketing, SEO, content creation, and digital advertising strategies.\",\"noted_by\":\"Juan Dela Cruz\",\"sender\":\"Local Civil Registrar\",\"date_received\":\"2026-05-13\",\"classification\":\"Invitation\",\"sub_classification\":\"Seminar\\/Training Invitation\",\"priority\":\"Urgent\",\"document_type\":\"Travel Request\",\"type\":\"Travel Request\",\"parent_document_id\":187}', 'Pending', 'Local Civil Registrar'),
(192, 7, 'Invitation to Career Development Workshop', 'Participate in a workshop aimed at improving career planning, resume writing, interview preparation, and professional growth opportunities.', 20, '2026-05-13 18:30:41', '2026-05-13 18:34:06', 'General', 'Urgent', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-KZOTF', NULL, 'Career Development Office', '2026-05-13 18:30:41', '2026-05-03 16:00:00', '2026-05-13 16:00:00', 'uploads/documents/DOC-0020-1778697041-6a04c3514b4f8.webp', '0', 'Pending', 'Municipal Treasurer\'s Office'),
(193, 8, 'Environmental Sustainability Forum Invitation', 'Join us for a forum discussing sustainable practices, environmental responsibility, and innovative solutions for a greener future.', 20, '2026-05-13 18:31:14', '2026-05-13 18:34:01', 'General', 'Critical', 'Letter', 'Request Letter', 'DOC-20260514-S6XL2', NULL, 'Environmental Programs Office', '2026-05-13 18:31:14', '2026-05-12 16:00:00', '2026-05-12 16:00:00', 'uploads/documents/DOC-0020-1778697074-6a04c372eb6ad.jpg', '0', 'Pending', 'Municipal Treasurer\'s Office'),
(194, 9, 'Invitation to Project Management Seminar', 'Gain valuable insights into project planning, execution, risk management, and team collaboration from experienced project managers.', 20, '2026-05-13 18:31:56', '2026-05-13 18:33:32', 'General', 'Critical', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-2EOKO', NULL, 'Operations Management Department', '2026-05-13 18:31:56', '2026-05-13 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0020-1778697116-6a04c39c12bb5.webp', '0', 'Approved', 'Municipal Treasurer\'s Office'),
(195, 10, 'Entrepreneurship and Startup Seminar Invitation', 'Discover practical strategies for building and growing successful startups through expert-led discussions and networking opportunities.', 20, '2026-05-13 18:32:24', '2026-05-13 18:33:49', 'General', 'Normal', 'Invitation', 'Meeting Invitation', 'DOC-20260514-XBRHX', NULL, 'Business Development Office', '2026-05-13 18:32:24', '2026-05-13 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0020-1778697144-6a04c3b87eed1.webp', '0', '', 'Municipal Treasurer\'s Office'),
(196, 11, 'Invitation to Human Resource Management Seminar', 'Attend a seminar focused on modern HR practices, employee engagement, recruitment strategies, and workplace development.', 20, '2026-05-13 18:33:00', '2026-05-13 18:33:55', 'General', 'Urgent', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-4B29E', NULL, 'Human Resources Division', '2026-05-13 18:33:00', '2026-05-12 16:00:00', '2026-05-13 16:00:00', 'uploads/documents/DOC-0020-1778697180-6a04c3dc863e0.webp', '0', 'Approved', 'Municipal Treasurer\'s Office'),
(197, NULL, 'Travel Request - Invitation', 'Invitation', 20, '2026-05-13 18:36:00', '2026-05-13 18:36:00', 'Travel Request', 'Urgent', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514023600-6a04c49041d68', NULL, 'Municipal Treasurer\'s Office', '2026-05-13 18:36:00', '2026-05-12 16:00:00', NULL, NULL, '{\"officer_name\":\"Municipal Treasurer\'s Office\",\"order_type\":\"Travel Order\",\"purpose_of_order\":\"Others\",\"purpose_specify\":\"Seminar\",\"travelers\":[{\"name\":\"Juan Dela Cruz\",\"position\":\"Head\"}],\"event_title\":\"Invitation\",\"event_date\":\"2026-05-14\",\"event_place\":\"Kapitolyo\",\"event_description\":\"Invitation\",\"noted_by\":\"Alexander Pajarillo\",\"sender\":\"Municipal Treasurer\'s Office\",\"date_received\":\"2026-05-13\",\"classification\":\"Invitation\",\"sub_classification\":\"Seminar\\/Training Invitation\",\"priority\":\"Urgent\",\"document_type\":\"Travel Request\",\"type\":\"Travel Request\",\"parent_document_id\":196}', 'Pending', 'Municipal Treasurer\'s Office'),
(198, 12, 'Data Privacy and Compliance Seminar Invitation', 'Learn about current data privacy regulations, compliance requirements, and best practices for protecting organizational information.', 21, '2026-05-13 18:39:09', '2026-05-13 18:39:35', 'General', 'Critical', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514-PSE5F', NULL, 'Compliance and Legal Affairs Office', '2026-05-13 18:39:09', '2026-04-21 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0021-1778697549-6a04c54d8fc67.jpg', '0', 'Approved', 'Mayor\'s Office'),
(199, NULL, 'Travel Request - Seminar Workshop', 'Learn about current data privacy regulations, compliance requirements, and best practices for protecting organizational information.', 21, '2026-05-13 18:40:26', '2026-05-13 18:40:26', 'Travel Request', 'Critical', 'Invitation', 'Seminar/Training Invitation', 'DOC-20260514024026-6a04c59ac7970', NULL, 'Mayor\'s Office', '2026-05-13 18:40:26', '2026-05-12 16:00:00', NULL, NULL, '{\"officer_name\":\"Mayor\'s Office\",\"order_type\":\"Travel Order\",\"purpose_of_order\":\"Meeting\",\"purpose_specify\":\"\",\"travelers\":[{\"name\":\"Juan Dela Cruz\",\"position\":\"head\"}],\"event_title\":\"Seminar Workshop\",\"event_date\":\"2026-05-15\",\"event_place\":\"Kapitolyo\",\"event_description\":\"Learn about current data privacy regulations, compliance requirements, and best practices for protecting organizational information.\",\"noted_by\":\"Juan Dela Cruz\",\"sender\":\"Mayor\'s Office\",\"date_received\":\"2026-05-13\",\"classification\":\"Invitation\",\"sub_classification\":\"Seminar\\/Training Invitation\",\"priority\":\"Critical\",\"document_type\":\"Travel Request\",\"type\":\"Travel Request\",\"parent_document_id\":198}', 'Pending', 'Mayor\'s Office'),
(200, 13, 'Invitation to Educational Advancement Seminar', 'Join educators and students for discussions on academic development, modern teaching methods, and lifelong learning opportunities.', 22, '2026-05-13 18:41:32', '2026-05-13 18:41:53', 'General', 'Critical', 'Invitation', 'Meeting Invitation', 'DOC-20260514-2T7QF', NULL, 'Academic Affairs Office', '2026-05-13 18:41:32', '2026-05-13 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0022-1778697692-6a04c5dcc87a5.webp', '0', 'Approved', 'Municipal Planning and Development Office'),
(201, NULL, 'Travel Request - Invitation to Educational Advancement Seminar', 'Join educators and students for discussions on academic development, modern teaching methods, and lifelong learning opportunities.', 22, '2026-05-13 18:42:48', '2026-05-13 18:42:48', 'Travel Request', 'Critical', 'Invitation', 'Meeting Invitation', 'DOC-20260514024248-6a04c628d2e44', NULL, 'Municipal Planning and Development Office', '2026-05-13 18:42:48', '2026-05-12 16:00:00', NULL, NULL, '{\"officer_name\":\"Municipal Planning and Development Office\",\"order_type\":\"Travel Order\",\"purpose_of_order\":\"Meeting\",\"purpose_specify\":\"\",\"travelers\":[{\"name\":\"Juan Tamad\",\"position\":\"Head\"}],\"event_title\":\"Invitation to Educational Advancement Seminar\",\"event_date\":\"2026-05-15\",\"event_place\":\"Kapitolyo\",\"event_description\":\"Join educators and students for discussions on academic development, modern teaching methods, and lifelong learning opportunities.\",\"noted_by\":\"Criz Morcozo\",\"sender\":\"Municipal Planning and Development Office\",\"date_received\":\"2026-05-13\",\"classification\":\"Invitation\",\"sub_classification\":\"Meeting Invitation\",\"priority\":\"Critical\",\"document_type\":\"Travel Request\",\"type\":\"Travel Request\",\"parent_document_id\":200}', 'Pending', 'Municipal Planning and Development Office'),
(202, 14, 'Team Building and Collaboration Workshop Invitation', 'Strengthen teamwork, communication, and workplace collaboration through engaging activities and practical team-building exercises.', 23, '2026-05-13 18:43:56', '2026-05-13 18:44:38', 'Invitation', 'Critical', 'Travel-Related Communication', 'Field Visit/Inspection', 'DOC-20260514-1ZQJV', NULL, 'Organizational Development Department', '2026-05-13 18:43:56', '2026-05-13 16:00:00', '2026-05-14 16:00:00', 'uploads/documents/DOC-0023-1778697836-6a04c66c2ca5c.jpg', '{\"sender\":\"Organizational Development Department\",\"date_received\":\"2026-05-14\",\"deadline\":\"2026-05-15\",\"classification\":\"Invitation\",\"sub_classification\":\"Meeting Invitation\",\"priority\":\"Critical\",\"doc_sequence_number\":0,\"file_path\":\"..\\/uploads\\/documents\\/DOC-0202-6a04c6825c601-c3ac3892.jpg\"}', 'Approved', 'Accounting Office'),
(203, NULL, 'Travel Request - Team Building and Collaboration Workshop Invitation', 'Strengthen teamwork, communication, and workplace collaboration through engaging activities and practical team-building exercises.', 23, '2026-05-13 18:45:19', '2026-05-13 18:45:19', 'Travel Request', 'Critical', 'Travel-Related Communication', 'Field Visit/Inspection', 'DOC-20260514024519-6a04c6bf66c90', NULL, 'Accounting Office', '2026-05-13 18:45:19', '2026-05-12 16:00:00', NULL, NULL, '{\"officer_name\":\"Accounting Office\",\"order_type\":\"Travel Order\",\"purpose_of_order\":\"Meeting\",\"purpose_specify\":\"\",\"travelers\":[{\"name\":\"Briann Canuto\",\"position\":\"Head\"}],\"event_title\":\"Team Building and Collaboration Workshop Invitation\",\"event_date\":\"2026-05-15\",\"event_place\":\"Kapitolyo\",\"event_description\":\"Strengthen teamwork, communication, and workplace collaboration through engaging activities and practical team-building exercises.\",\"noted_by\":\"Briann Canuto\",\"sender\":\"Accounting Office\",\"date_received\":\"2026-05-13\",\"classification\":\"Travel-Related Communication\",\"sub_classification\":\"Field Visit\\/Inspection\",\"priority\":\"Critical\",\"document_type\":\"Travel Request\",\"type\":\"Travel Request\",\"parent_document_id\":202}', 'Pending', 'Accounting Office');

-- --------------------------------------------------------

--
-- Table structure for table `document_assignments`
--

CREATE TABLE `document_assignments` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `office_department` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Received','Checking Documents','Waiting For Approval by Mayor','Completed','Returned','In Progress','Forwarded','Submitted to Administrative Office') DEFAULT 'Pending',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `completion_file` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_assignments`
--

INSERT INTO `document_assignments` (`id`, `document_id`, `assigned_by`, `assigned_to`, `office_department`, `notes`, `status`, `assigned_at`, `received_at`, `completed_at`, `archived_at`, `completion_file`, `updated_at`) VALUES
(131, 190, 19, 8, NULL, 'Document routed from department', 'Submitted to Administrative Office', '2026-05-13 12:20:23', NULL, NULL, NULL, NULL, '2026-05-13 18:20:23'),
(132, 189, 19, 8, NULL, 'Document routed from department', '', '2026-05-13 12:20:35', '2026-05-13 18:24:17', NULL, NULL, NULL, '2026-05-13 18:24:17'),
(133, 187, 19, 8, NULL, 'Document routed from department', 'Completed', '2026-05-13 12:20:41', '2026-05-13 18:20:55', '2026-05-13 18:22:52', NULL, NULL, '2026-05-13 18:22:52'),
(134, 196, 20, 8, NULL, 'Document routed from department', 'Completed', '2026-05-13 12:33:06', '2026-05-13 18:33:55', '2026-05-13 18:36:10', NULL, NULL, '2026-05-13 18:36:10'),
(135, 195, 20, 8, NULL, 'Document routed from department\n\nReturn Reason: Not Enough Funds', 'Returned', '2026-05-13 12:33:12', NULL, NULL, NULL, NULL, '2026-05-13 18:33:49'),
(136, 194, 20, 8, NULL, 'Document routed from department', '', '2026-05-13 12:33:18', '2026-05-13 18:33:32', NULL, NULL, NULL, '2026-05-13 18:33:32'),
(137, 193, 20, 8, NULL, 'Document routed from department', 'Submitted to Administrative Office', '2026-05-13 12:34:01', NULL, NULL, NULL, NULL, '2026-05-13 18:34:01'),
(138, 192, 20, 8, NULL, 'Document routed from department', 'Submitted to Administrative Office', '2026-05-13 12:34:06', NULL, NULL, NULL, NULL, '2026-05-13 18:34:06'),
(139, 198, 21, 8, NULL, 'Document routed from department', 'Completed', '2026-05-13 12:39:26', '2026-05-13 18:39:35', '2026-05-13 18:40:43', NULL, NULL, '2026-05-13 18:40:43'),
(140, 200, 22, 8, NULL, 'Document routed from department', 'Completed', '2026-05-13 12:41:41', '2026-05-13 18:41:53', '2026-05-13 18:43:04', NULL, NULL, '2026-05-13 18:43:04'),
(141, 202, 23, 8, NULL, 'Document routed from department', 'Completed', '2026-05-13 12:44:30', '2026-05-13 18:44:38', '2026-05-13 18:45:29', NULL, NULL, '2026-05-13 18:45:29');

-- --------------------------------------------------------

--
-- Table structure for table `document_uploads`
--

CREATE TABLE `document_uploads` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_uploads`
--

INSERT INTO `document_uploads` (`id`, `assignment_id`, `file_path`, `notes`, `uploaded_by`, `uploaded_at`) VALUES
(41, 133, 'uploads/documents/upload_1778696568_6a04c1789f885.jpg', '', 'Administrative Head', '2026-05-13 18:22:48'),
(42, 134, 'uploads/documents/upload_1778697368_6a04c498885ce.jpg', '', 'Administrative Head', '2026-05-13 18:36:08'),
(43, 139, 'uploads/documents/upload_1778697642_6a04c5aa4fb94.jpg', '', 'Administrative Head', '2026-05-13 18:40:42'),
(44, 140, 'uploads/documents/upload_1778697781_6a04c635a349a.jpg', '', 'Administrative Head', '2026-05-13 18:43:01'),
(45, 141, 'uploads/documents/upload_1778697927_6a04c6c7318ac.jpg', '', 'Administrative Head', '2026-05-13 18:45:27');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `ip_address` varchar(50) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `old_status` varchar(100) DEFAULT NULL,
  `new_status` varchar(100) DEFAULT NULL,
  `message` longtext NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `tracking_number`, `document_id`, `assignment_id`, `old_status`, `new_status`, `message`, `is_read`, `created_at`, `read_at`) VALUES
(207, 8, 'assignment', 'DOC-20260514-7CX8T', 190, 131, NULL, 'Pending', 'Department staff routed document (DOC-20260514-7CX8T) to Administrative', 0, '2026-05-13 18:20:23', NULL),
(208, 8, 'assignment', 'DOC-20260514-PITEI', 189, 132, NULL, 'Pending', 'Department staff routed document (DOC-20260514-PITEI) to Administrative', 0, '2026-05-13 18:20:35', NULL),
(209, 8, 'assignment', 'DOC-20260514-P9IRA', 187, 133, NULL, 'Pending', 'Department staff routed document (DOC-20260514-P9IRA) to Administrative', 0, '2026-05-13 18:20:41', NULL),
(210, 19, 'status_update', 'DOC-20260514-P9IRA', 187, 133, 'Pending', 'Approved', 'Your document (DOC-20260514-P9IRA) has been approved by Administrative', 0, '2026-05-13 18:20:55', NULL),
(211, 8, 'assignment', 'DOC-20260514022211-6a04c153d256f', 191, 133, NULL, 'Pending', 'Department staff submitted a travel request (DOC-20260514022211-6a04c153d256f)', 0, '2026-05-13 18:22:11', NULL),
(212, 19, 'status_update', 'DOC-20260514-P9IRA', 187, 133, 'Approved', 'Uploaded', 'Administrative uploaded supporting files for your document', 0, '2026-05-13 18:22:48', NULL),
(213, 19, 'status_update', 'DOC-20260514-P9IRA', 187, 133, NULL, 'Completed', 'Document has been marked as Completed by Administrative Assistant', 0, '2026-05-13 18:22:52', NULL),
(214, 19, 'status_update', 'DOC-20260514-PITEI', 189, 132, 'Pending', 'Approved', 'Your document (DOC-20260514-PITEI) has been approved by Administrative', 0, '2026-05-13 18:24:17', NULL),
(215, 8, 'assignment', 'DOC-20260514-4B29E', 196, 134, NULL, 'Pending', 'Department staff routed document (DOC-20260514-4B29E) to Administrative', 0, '2026-05-13 18:33:06', NULL),
(216, 8, 'assignment', 'DOC-20260514-XBRHX', 195, 135, NULL, 'Pending', 'Department staff routed document (DOC-20260514-XBRHX) to Administrative', 0, '2026-05-13 18:33:12', NULL),
(217, 8, 'assignment', 'DOC-20260514-2EOKO', 194, 136, NULL, 'Pending', 'Department staff routed document (DOC-20260514-2EOKO) to Administrative', 0, '2026-05-13 18:33:18', NULL),
(218, 20, 'status_update', 'DOC-20260514-2EOKO', 194, 136, 'Pending', 'Approved', 'Your document (DOC-20260514-2EOKO) has been approved by Administrative', 0, '2026-05-13 18:33:32', NULL),
(219, 20, 'status_update', 'DOC-20260514-XBRHX', 195, 135, 'Pending', 'Returned', 'Your document (DOC-20260514-XBRHX) was returned by Administrative. Reason: Not Enough Funds', 0, '2026-05-13 18:33:49', NULL),
(220, 20, 'status_update', 'DOC-20260514-4B29E', 196, 134, 'Pending', 'Approved', 'Your document (DOC-20260514-4B29E) has been approved by Administrative', 0, '2026-05-13 18:33:55', NULL),
(221, 8, 'assignment', 'DOC-20260514-S6XL2', 193, 137, NULL, 'Pending', 'Department staff routed document (DOC-20260514-S6XL2) to Administrative', 0, '2026-05-13 18:34:01', NULL),
(222, 8, 'assignment', 'DOC-20260514-KZOTF', 192, 138, NULL, 'Pending', 'Department staff routed document (DOC-20260514-KZOTF) to Administrative', 0, '2026-05-13 18:34:06', NULL),
(223, 8, 'assignment', 'DOC-20260514023600-6a04c49041d68', 197, 134, NULL, 'Pending', 'Department staff submitted a travel request (DOC-20260514023600-6a04c49041d68)', 0, '2026-05-13 18:36:00', NULL),
(224, 20, 'status_update', 'DOC-20260514-4B29E', 196, 134, 'Approved', 'Uploaded', 'Administrative uploaded supporting files for your document', 0, '2026-05-13 18:36:08', NULL),
(225, 20, 'status_update', 'DOC-20260514-4B29E', 196, 134, NULL, 'Completed', 'Document has been marked as Completed by Administrative Assistant', 0, '2026-05-13 18:36:10', NULL),
(226, 8, 'assignment', 'DOC-20260514-PSE5F', 198, 139, NULL, 'Pending', 'Department staff routed document (DOC-20260514-PSE5F) to Administrative', 0, '2026-05-13 18:39:26', NULL),
(227, 21, 'status_update', 'DOC-20260514-PSE5F', 198, 139, 'Pending', 'Approved', 'Your document (DOC-20260514-PSE5F) has been approved by Administrative', 0, '2026-05-13 18:39:35', NULL),
(228, 8, 'assignment', 'DOC-20260514024026-6a04c59ac7970', 199, 139, NULL, 'Pending', 'Department staff submitted a travel request (DOC-20260514024026-6a04c59ac7970)', 0, '2026-05-13 18:40:26', NULL),
(229, 21, 'status_update', 'DOC-20260514-PSE5F', 198, 139, 'Approved', 'Uploaded', 'Administrative uploaded supporting files for your document', 0, '2026-05-13 18:40:42', NULL),
(230, 21, 'status_update', 'DOC-20260514-PSE5F', 198, 139, NULL, 'Completed', 'Document has been marked as Completed by Administrative Assistant', 0, '2026-05-13 18:40:43', NULL),
(231, 8, 'assignment', 'DOC-20260514-2T7QF', 200, 140, NULL, 'Pending', 'Department staff routed document (DOC-20260514-2T7QF) to Administrative', 0, '2026-05-13 18:41:41', NULL),
(232, 22, 'status_update', 'DOC-20260514-2T7QF', 200, 140, 'Pending', 'Approved', 'Your document (DOC-20260514-2T7QF) has been approved by Administrative', 0, '2026-05-13 18:41:53', NULL),
(233, 8, 'assignment', 'DOC-20260514024248-6a04c628d2e44', 201, 140, NULL, 'Pending', 'Department staff submitted a travel request (DOC-20260514024248-6a04c628d2e44)', 0, '2026-05-13 18:42:48', NULL),
(234, 22, 'status_update', 'DOC-20260514-2T7QF', 200, 140, 'Approved', 'Uploaded', 'Administrative uploaded supporting files for your document', 0, '2026-05-13 18:43:01', NULL),
(235, 22, 'status_update', 'DOC-20260514-2T7QF', 200, 140, NULL, 'Completed', 'Document has been marked as Completed by Administrative Assistant', 0, '2026-05-13 18:43:04', NULL),
(236, 8, 'assignment', 'DOC-20260514-1ZQJV', 202, 141, NULL, 'Pending', 'Department staff routed document (DOC-20260514-1ZQJV) to Administrative', 0, '2026-05-13 18:44:30', NULL),
(237, 23, 'status_update', 'DOC-20260514-1ZQJV', 202, 141, 'Pending', 'Approved', 'Your document (DOC-20260514-1ZQJV) has been approved by Administrative', 0, '2026-05-13 18:44:38', NULL),
(238, 8, 'assignment', 'DOC-20260514024519-6a04c6bf66c90', 203, 141, NULL, 'Pending', 'Department staff submitted a travel request (DOC-20260514024519-6a04c6bf66c90)', 0, '2026-05-13 18:45:19', NULL),
(239, 23, 'status_update', 'DOC-20260514-1ZQJV', 202, 141, 'Approved', 'Uploaded', 'Administrative uploaded supporting files for your document', 0, '2026-05-13 18:45:27', NULL),
(240, 23, 'status_update', 'DOC-20260514-1ZQJV', 202, 141, NULL, 'Completed', 'Document has been marked as Completed by Administrative Assistant', 0, '2026-05-13 18:45:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `travel_requests`
--

CREATE TABLE `travel_requests` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(20) DEFAULT NULL,
  `document_title` varchar(255) DEFAULT NULL,
  `request_type` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `current_owner` varchar(100) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Department Staff','Administrative Assistant','Mayor','Record Officer') NOT NULL DEFAULT 'Department Staff',
  `position` varchar(100) DEFAULT NULL,
  `office_department` varchar(150) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `house_no` varchar(100) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Active','Inactive') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejection_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `middle_name`, `email`, `username`, `password`, `role`, `position`, `office_department`, `civil_status`, `date_of_birth`, `contact_number`, `house_no`, `street`, `barangay`, `municipality`, `province`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`, `rejection_remarks`) VALUES
(1, 'System', 'Administrator', NULL, 'admin@lgumeceedes.gov.ph', 'admin', '$2y$12$dz3RdlXPKLJXK1Y9YgIeee9qhNx.FE1nE85Qzexabb9Bshgmv1GxK', 'Super Admin', 'Super Admin', 'Mayor\'s Office', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', 1, '2026-04-22 06:58:19', '2026-04-22 06:58:19', '2026-04-22 07:50:57', NULL),
(3, 'Alexander', 'Pajarillo', NULL, 'mayor@lgumeceedes.gov.ph', 'mayor_maria', '$2y$12$dz3RdlXPKLJXK1Y9YgIeee9qhNx.FE1nE85Qzexabb9Bshgmv1GxK', 'Mayor', 'Mayor', 'Mayor\'s Office', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', 1, '2026-04-22 06:58:19', '2026-04-22 06:58:19', '2026-05-13 17:56:04', NULL),
(8, 'Administrative', 'Head', 'Staff', 'admin.head@lgumerceedes.gov.ph', 'admin_head', '$2y$12$dz3RdlXPKLJXK1Y9YgIeee9qhNx.FE1nE85Qzexabb9Bshgmv1GxK', 'Administrative Assistant', 'Administrative Assistant', 'Administrative Office', 'Single', '1988-06-15', '+63 917 123 4567', '789', 'Admin Street', 'Talao', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-04-22 09:25:04', '2026-05-13 17:55:53', NULL),
(9, 'Juan', 'Criz', 'Cruz', 'lgurecord@gmail.com', 'lgurecord', '$2y$12$dz3RdlXPKLJXK1Y9YgIeee9qhNx.FE1nE85Qzexabb9Bshgmv1GxK', 'Record Officer', 'Head', 'Mayor\'s Office', 'Single', '2004-08-01', '09669519648', 'asd', 'asd', 'Hinipaan', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-04-22 11:42:02', '2026-05-13 17:56:18', NULL),
(18, 'Ariana', 'Dela Cruz', 'Santos', 'ariana.delacruz.mho@example.com', 'ariana_mho', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Staff Nurse', 'Municipal Health Office', 'Single', '1998-03-14', '09170000001', '101', 'Rizal Street', 'Barangay I (Poblacion)', 'Mercedes', 'Camarines Norte', 'Inactive', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:10:31', 'Wrong Information'),
(19, 'Carlo', 'Reyes', 'Villanueva', 'carlo.reyes.lcr@example.com', 'carlo_lcr', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Records Clerk', 'Local Civil Registrar', 'Married', '1994-11-22', '09170000002', '45B', 'Bonifacio Avenue', 'Barangay II (Poblacion)', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:10:21', NULL),
(20, 'Nina', 'Gonzales', 'Lopez', 'nina.gonzales.treasury@example.com', 'nina_treasury', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Account Analyst', 'Municipal Treasurer\'s Office', 'Single', '1996-07-05', '09170000003', '78', 'Del Pilar Street', 'Barangay III', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:10:04', NULL),
(21, 'Marco', 'Aquino', 'Diaz', 'marco.aquino.mayor@example.com', 'marco_mayor', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Executive Aide', 'Mayor\'s Office', 'Married', '1992-01-19', '09170000004', '12', 'Mabini Street', 'Barangay IV (Poblacion)', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:10:02', NULL),
(22, 'Liza', 'Fernandez', 'Morales', 'liza.fernandez.mpdo@example.com', 'liza_mpdo', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Planning Officer I', 'Municipal Planning and Development Office', 'Single', '1997-09-30', '09170000005', '230', 'Quezon Street', 'Barangay V', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:09:58', NULL),
(23, 'Joel', 'Mendoza', 'Castillo', 'joel.mendoza.accounting@example.com', 'joel_accounting', '$2y$12$lhM3n1ymVxAhzGAV5OP4pOXBwlUL3YUQQ.cygzAE0H0.eaBYqTfem', 'Department Staff', 'Bookkeeper', 'Accounting Office', 'Married', '1990-05-16', '09170000006', '66', 'Luna Street', 'Barangay VI', 'Mercedes', 'Camarines Norte', 'Active', NULL, NULL, '2026-05-13 18:08:45', '2026-05-13 18:08:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_office_department` (`office_department`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_classification` (`classification`),
  ADD KEY `idx_deadline` (`deadline`);

--
-- Indexes for table `document_assignments`
--
ALTER TABLE `document_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_at` (`assigned_at`);

--
-- Indexes for table `document_uploads`
--
ALTER TABLE `document_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignment` (`assignment_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_attempted_at` (`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `travel_requests`
--
ALTER TABLE `travel_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_code` (`tracking_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `document_assignments`
--
ALTER TABLE `document_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `document_uploads`
--
ALTER TABLE `document_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

--
-- AUTO_INCREMENT for table `travel_requests`
--
ALTER TABLE `travel_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_assignments`
--
ALTER TABLE `document_assignments`
  ADD CONSTRAINT `document_assignments_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_assignments_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_assignments_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_uploads`
--
ALTER TABLE `document_uploads`
  ADD CONSTRAINT `document_uploads_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `document_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`assignment_id`) REFERENCES `document_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
