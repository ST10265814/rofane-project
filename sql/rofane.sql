-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2025 at 09:42 PM
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
-- Database: `rofane`
--

-- --------------------------------------------------------

--
-- Table structure for table `brs_files`
--

CREATE TABLE `brs_files` (
  `id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brs_files`
--

INSERT INTO `brs_files` (`id`, `uploaded_by`, `original_name`, `file_path`, `created_at`) VALUES
(1, 4, 'Bug_Tracker_BRS.pdf', 'modules/brs/uploads/brs_1754901308_078967.pdf', '2025-08-11 08:35:08'),
(2, 4, 'Bug_Tracker_BRS.pdf', 'modules/brs/uploads/brs_1754901356_7661db.pdf', '2025-08-11 08:35:56'),
(3, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754901401_ab5ad2.pdf', '2025-08-11 08:36:41'),
(4, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754901419_463562.pdf', '2025-08-11 08:36:59'),
(5, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754901814_be3b63.pdf', '2025-08-11 08:43:34'),
(6, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754903229_75cd4e.pdf', '2025-08-11 09:07:09'),
(7, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754903313_b06da9.pdf', '2025-08-11 09:08:33'),
(8, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754903822_522d7a.pdf', '2025-08-11 09:17:02'),
(9, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754904197_e0ff62.pdf', '2025-08-11 09:23:17'),
(10, 4, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1754989154_285351.pdf', '2025-08-12 08:59:14'),
(11, 4, 'Facebook_BRS.pdf', 'modules/brs/uploads/brs_1755083640_4878ae.pdf', '2025-08-13 11:14:00'),
(12, 4, 'Facebook_BRS.pdf', 'modules/brs/uploads/brs_1755083641_2243d6.pdf', '2025-08-13 11:14:01'),
(13, 5, 'REQ-BRS.pdf', 'modules/brs/uploads/brs_1756187356_073092.pdf', '2025-08-26 05:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `brs_test_cases`
--

CREATE TABLE `brs_test_cases` (
  `id` int(11) NOT NULL,
  `brs_id` int(11) NOT NULL,
  `requirement_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `steps` text DEFAULT NULL,
  `expected` text DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Draft',
  `bug_id` int(11) DEFAULT NULL,
  `bug_manual_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brs_test_cases`
--

INSERT INTO `brs_test_cases` (`id`, `brs_id`, `requirement_id`, `title`, `steps`, `expected`, `priority`, `created_at`, `status`, `bug_id`, `bug_manual_path`) VALUES
(1, 1, 'R-1', 'Validate: 1. Functional Requirements', '1) Open the application\n2) Navigate to the relevant module\n3) Perform the action described in the requirement\n4) Observe the behavior', '1. Functional Requirements', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(2, 1, 'R-1', 'Negative: 1. Functional Requirements', '1) Open the application\n2) Provide invalid/edge inputs related to the requirement\n3) Attempt the action\n4) Observe validation/handling', 'System should gracefully handle invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(3, 1, 'R-2', 'Validate: must receive a system notification.', '1) Open the application\n2) Navigate to the relevant module\n3) Perform the action described in the requirement\n4) Observe the behavior', 'must receive a system notification.', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(4, 1, 'R-2', 'Negative: must receive a system notification.', '1) Open the application\n2) Provide invalid/edge inputs related to the requirement\n3) Attempt the action\n4) Observe validation/handling', 'System should gracefully handle invalid/edge inputs for: must receive a system notification.', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(5, 1, 'R-3', 'Validate: 2. Non-Functional Requirements', '1) Open the application\n2) Navigate to the relevant module\n3) Perform the action described in the requirement\n4) Observe the behavior', '2. Non-Functional Requirements', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(6, 1, 'R-3', 'Negative: 2. Non-Functional Requirements', '1) Open the application\n2) Provide invalid/edge inputs related to the requirement\n3) Attempt the action\n4) Observe validation/handling', 'System should gracefully handle invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(7, 1, 'R-4', 'Validate: 3. Assumptions', '1) Open the application\n2) Navigate to the relevant module\n3) Perform the action described in the requirement\n4) Observe the behavior', '3. Assumptions', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(8, 1, 'R-4', 'Negative: 3. Assumptions', '1) Open the application\n2) Provide invalid/edge inputs related to the requirement\n3) Attempt the action\n4) Observe validation/handling', 'System should gracefully handle invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-11 08:48:43', 'Draft', NULL, NULL),
(9, 6, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(10, 6, 'R-1', 'GWT Negative: 1. Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"1. Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(11, 6, 'R-2', 'GWT: must receive a system notification.', 'Given the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"', 'Behavior conforms to requirement: must receive a system notification.', 'High', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(12, 6, 'R-2', 'GWT Negative: must receive a system notification.', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: must receive a system notification.', 'High', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(13, 6, 'R-3', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(14, 6, 'R-3', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(15, 6, 'R-4', 'GWT: 3. Assumptions', 'Given the system is available\r\nWhen the user performs the action described: \"3. Assumptions\"\r\nThen the system behaves as specified: \"3. Assumptions\"', 'Behavior conforms to requirement: 3. Assumptions', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(16, 6, 'R-4', 'GWT Negative: 3. Assumptions', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"3. Assumptions\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-11 09:07:33', 'Draft', NULL, NULL),
(17, 7, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(18, 7, 'R-1', 'GWT Negative: 1. Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"1. Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(19, 7, 'R-2', 'GWT: must receive a system notification.', 'Given the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"', 'Behavior conforms to requirement: must receive a system notification.', 'High', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(20, 7, 'R-2', 'GWT Negative: must receive a system notification.', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: must receive a system notification.', 'High', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(21, 7, 'R-3', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:08:56', 'Draft', 8, NULL),
(22, 7, 'R-3', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(23, 7, 'R-4', 'GWT: 3. Assumptions', 'Given the system is available\r\nWhen the user performs the action described: \"3. Assumptions\"\r\nThen the system behaves as specified: \"3. Assumptions\"', 'Behavior conforms to requirement: 3. Assumptions', 'Medium', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(24, 7, 'R-4', 'GWT Negative: 3. Assumptions', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"3. Assumptions\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-11 09:08:56', 'Draft', NULL, NULL),
(25, 8, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-11 09:17:12', 'Draft', 7, NULL),
(26, 8, 'R-1', 'GWT Negative: 1. Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"1. Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(27, 8, 'R-2', 'GWT: must receive a system notification.', 'Given the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"', 'Behavior conforms to requirement: must receive a system notification.', 'High', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(28, 8, 'R-2', 'GWT Negative: must receive a system notification.', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: must receive a system notification.', 'High', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(29, 8, 'R-3', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(30, 8, 'R-3', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(31, 8, 'R-4', 'GWT: 3. Assumptions', 'Given the system is available\r\nWhen the user performs the action described: \"3. Assumptions\"\r\nThen the system behaves as specified: \"3. Assumptions\"', 'Behavior conforms to requirement: 3. Assumptions', 'Medium', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(32, 8, 'R-4', 'GWT Negative: 3. Assumptions', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"3. Assumptions\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-11 09:17:12', 'Draft', NULL, NULL),
(33, 9, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-11 09:25:04', 'Ready', 4, 'modules/brs/manuals/manual_tc_33_1754906477.html'),
(34, 9, 'R-2', 'GWT: must receive a system notification.', 'Given the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"', 'Behavior conforms to requirement: must receive a system notification.', 'High', '2025-08-11 09:25:04', 'Draft', 5, NULL),
(35, 9, 'R-2', 'GWT Negative: must receive a system notification.', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: must receive a system notification.', 'High', '2025-08-11 09:25:04', 'Draft', 6, NULL),
(36, 10, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-12 09:01:04', 'Draft', 9, NULL),
(37, 10, 'R-1', 'GWT Negative: 1. Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"1. Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-12 09:01:04', 'Draft', NULL, NULL),
(38, 10, 'R-2', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-12 09:01:04', 'Draft', NULL, NULL),
(39, 10, 'R-2', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-12 09:01:04', 'Draft', NULL, NULL),
(40, 10, 'R-3', 'GWT: 3. Assumptions', 'Given the system is available\r\nWhen the user performs the action described: \"3. Assumptions\"\r\nThen the system behaves as specified: \"3. Assumptions\"', 'Behavior conforms to requirement: 3. Assumptions', 'Medium', '2025-08-12 09:01:04', 'Draft', NULL, NULL),
(41, 10, 'R-3', 'GWT Negative: 3. Assumptions', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"3. Assumptions\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-12 09:01:04', 'Draft', NULL, NULL),
(42, 12, 'R-1', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-13 11:14:43', 'Passed', NULL, NULL),
(43, 12, 'R-1', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-13 11:14:43', 'Draft', NULL, NULL),
(44, 13, 'R-1', 'GWT: 1. Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"', 'Behavior conforms to requirement: 1. Functional Requirements', 'Medium', '2025-08-26 05:50:01', 'Ready', 11, NULL),
(45, 13, 'R-1', 'GWT Negative: 1. Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"1. Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 1. Functional Requirements', 'Medium', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(46, 13, 'R-2', 'GWT: must receive a system notification.', 'Given the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"', 'Behavior conforms to requirement: must receive a system notification.', 'High', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(47, 13, 'R-2', 'GWT Negative: must receive a system notification.', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: must receive a system notification.', 'High', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(48, 13, 'R-3', 'GWT: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user performs the action described: \"2. Non-Functional Requirements\"\r\nThen the system behaves as specified: \"2. Non-Functional Requirements\"', 'Behavior conforms to requirement: 2. Non-Functional Requirements', 'Medium', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(49, 13, 'R-3', 'GWT Negative: 2. Non-Functional Requirements', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"2. Non-Functional Requirements\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 2. Non-Functional Requirements', 'Medium', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(50, 13, 'R-4', 'GWT: 3. Assumptions', 'Given the system is available\r\nWhen the user performs the action described: \"3. Assumptions\"\r\nThen the system behaves as specified: \"3. Assumptions\"', 'Behavior conforms to requirement: 3. Assumptions', 'Medium', '2025-08-26 05:50:01', 'Draft', NULL, NULL),
(51, 13, 'R-4', 'GWT Negative: 3. Assumptions', 'Given the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"3. Assumptions\"\r\nThen the system prevents the action or handles it gracefully with clear feedback', 'System blocks or handles invalid/edge inputs for: 3. Assumptions', 'Medium', '2025-08-26 05:50:01', 'Draft', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bugs`
--

CREATE TABLE `bugs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `component` varchar(80) DEFAULT NULL,
  `target_release` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fix_notes` text DEFAULT NULL,
  `commit_ref` varchar(80) DEFAULT NULL,
  `severity` enum('Low','Medium','High','Critical') NOT NULL,
  `status` enum('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
  `resolution` varchar(40) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `priority` varchar(20) DEFAULT 'Medium',
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bugs`
--

INSERT INTO `bugs` (`id`, `title`, `component`, `target_release`, `description`, `fix_notes`, `commit_ref`, `severity`, `status`, `resolution`, `closed_by`, `closed_at`, `reported_by`, `assigned_to`, `created_at`, `priority`, `updated_at`) VALUES
(1, 'Button is not clickable', NULL, NULL, 'Button is not working', NULL, NULL, 'Low', 'Resolved', NULL, NULL, NULL, 4, 2, '2025-08-11 07:08:11', 'Medium', NULL),
(2, 'Test', NULL, NULL, 'tyfudsaz', NULL, NULL, 'Medium', 'In Progress', NULL, NULL, NULL, 4, 1, '2025-08-11 07:38:22', 'Medium', NULL),
(3, 'bbcbxczx', NULL, NULL, 'fcdsz', NULL, NULL, 'Critical', 'Resolved', NULL, NULL, NULL, 4, 1, '2025-08-11 07:39:16', 'Medium', NULL),
(4, '[TC 33] GWT: 1. Functional Requirements', NULL, NULL, 'Linked Test Case: TC 33 (BRS 9)\nRequirement: R-1\n\nSteps (G/W/T):\nGiven the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"\n\nExpected:\nBehavior conforms to requirement: 1. Functional Requirements\n\nActual:\n[fill during execution]\n', NULL, NULL, 'Medium', 'Open', NULL, NULL, NULL, 4, NULL, '2025-08-11 10:01:04', 'Medium', NULL),
(5, '[TC 34] GWT: must receive a system notification.', NULL, NULL, 'Linked Test Case: TC 34 (BRS 9)\nRequirement: R-2\n\nSteps (G/W/T):\nGiven the system is available\r\nWhen the user performs the action described: \"must receive a system notification.\"\r\nThen the system behaves as specified: \"must receive a system notification.\"\n\nExpected:\nBehavior conforms to requirement: must receive a system notification.\n\nActual:\n[fill during execution]\n', NULL, NULL, 'High', 'Open', NULL, NULL, NULL, 4, NULL, '2025-08-11 10:09:20', 'Medium', NULL),
(6, '[TC 35] GWT Negative: must receive a system notification.', NULL, NULL, 'Linked Test Case: TC 35 (BRS 9)\r\nRequirement: R-2\r\n\r\nSteps (G/W/T):\r\nGiven the system is available\r\nWhen the user attempts invalid or edge inputs related to: \"must receive a system notification.\"\r\nThen the system prevents the action or handles it gracefully with clear feedback\r\n\r\nExpected:\r\nSystem blocks or handles invalid/edge inputs for: must receive a system notification.\r\n\r\nActual:\r\n[fill during execution]', NULL, NULL, 'High', 'Open', NULL, NULL, NULL, 4, NULL, '2025-08-11 10:14:57', 'Medium', NULL),
(7, '[TC 25] GWT: 1. Functional Requirements', NULL, NULL, 'Linked Test Case: TC 25 (BRS 8)\nRequirement: R-1\n\nSteps (G/W/T):\nGiven the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"\n\nExpected:\nBehavior conforms to requirement: 1. Functional Requirements\n\nActual:\n[fill during execution]\n', NULL, NULL, 'Medium', 'Closed', NULL, NULL, NULL, 4, 4, '2025-08-11 10:25:14', 'Medium', NULL),
(8, 'Veee', NULL, NULL, 'thjhgfgh', '', '', 'Medium', 'Resolved', NULL, NULL, NULL, 4, 8, '2025-08-11 10:31:54', 'Medium', '2025-09-13 06:28:15'),
(9, 'Thabiso', NULL, NULL, 'TTtt', NULL, NULL, 'Low', 'In Progress', NULL, NULL, NULL, 4, 1, '2025-08-13 11:11:49', 'Medium', NULL),
(10, 'Thabiso', NULL, NULL, 'Test', NULL, NULL, 'Low', 'Resolved', NULL, NULL, NULL, 5, 1, '2025-08-26 05:46:53', 'Medium', NULL),
(11, '[TC 44] GWT: 1. Functional Requirements', NULL, NULL, 'Linked Test Case: TC 44 (BRS 13)\r\nRequirement: R-1\r\n\r\nSteps (G/W/T):\r\nGiven the system is available\r\nWhen the user performs the action described: \"1. Functional Requirements\"\r\nThen the system behaves as specified: \"1. Functional Requirements\"\r\n\r\nThe button is not clickable\r\n\r\nExpected:\r\nBehavior conforms to requirement: 1. Functional Requirements\r\n\r\nActual:\r\n[fill during execution]', NULL, NULL, 'Medium', 'Open', NULL, NULL, NULL, 5, 5, '2025-08-26 05:50:54', 'Medium', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bug_attachments`
--

CREATE TABLE `bug_attachments` (
  `id` int(11) NOT NULL,
  `bug_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bug_attachments`
--

INSERT INTO `bug_attachments` (`id`, `bug_id`, `file_path`, `uploaded_at`) VALUES
(1, 1, 'modules/bug_tracker/uploads/bug_1754896091_734a3b80.jpeg', '2025-08-11 07:08:11'),
(2, 2, 'modules/bug_tracker/uploads/bug_1754897902_f6b3fd9f.jpg', '2025-08-11 07:38:22'),
(3, 3, 'modules/bug_tracker/uploads/bug_1754897956_f6cdcaf9.jpg', '2025-08-11 07:39:16'),
(4, 4, 'modules/brs/manuals/manual_tc_33_1754906464.html', '2025-08-11 10:01:04'),
(5, 4, 'modules/brs/manuals/manual_tc_33_1754906465.html', '2025-08-11 10:01:05'),
(6, 4, 'modules/brs/manuals/manual_tc_33_1754906477.html', '2025-08-11 10:01:17'),
(7, 6, 'modules/bug_tracker/uploads/bug_6_1754907297.jpg', '2025-08-11 10:14:57'),
(8, 8, 'modules/bug_tracker/uploads/bug_8_1754908314.jpg', '2025-08-11 10:31:54'),
(9, 9, 'modules/bug_tracker/uploads/bug_9_1755083509.jpg', '2025-08-13 11:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `bug_audit_log`
--

CREATE TABLE `bug_audit_log` (
  `id` int(11) NOT NULL,
  `bug_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `old_assigned_to` int(11) DEFAULT NULL,
  `new_assigned_to` int(11) DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bug_audit_log`
--

INSERT INTO `bug_audit_log` (`id`, `bug_id`, `action`, `old_status`, `new_status`, `old_assigned_to`, `new_assigned_to`, `changed_by`, `created_at`) VALUES
(1, 3, 'update', 'In Progress', 'Open', 1, 1, 4, '2025-08-11 08:03:55'),
(2, 3, 'inline_update', 'Open', 'In Progress', 1, 1, 4, '2025-08-11 08:17:17'),
(3, 3, 'inline_update', 'In Progress', 'Resolved', 1, 1, 4, '2025-08-11 08:26:26'),
(4, 7, 'update', 'Open', 'Open', NULL, 4, 4, '2025-08-12 08:52:06'),
(5, 7, 'inline_update', 'Open', 'Closed', 4, 4, 4, '2025-08-12 08:52:35'),
(6, 9, 'update', 'Open', 'Open', NULL, 1, 4, '2025-08-13 11:12:27'),
(7, 9, 'update', 'Open', 'In Progress', 1, 1, 4, '2025-08-13 11:13:06'),
(8, 10, 'update', 'Open', 'In Progress', NULL, 1, 5, '2025-08-26 05:47:06'),
(9, 11, 'update', 'Open', 'Open', NULL, 5, 5, '2025-08-26 05:51:14'),
(10, 10, 'inline_update', 'In Progress', 'Resolved', 1, 1, 5, '2025-08-26 05:51:37'),
(11, 8, 'update', 'Open', 'Open', 8, 8, 7, '2025-09-13 06:05:40'),
(12, 8, 'dev_update', 'Open', 'In Progress', 8, 8, 8, '2025-09-13 06:28:04'),
(13, 8, 'dev_update', 'In Progress', 'Resolved', 8, 8, 8, '2025-09-13 06:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `bug_comments`
--

CREATE TABLE `bug_comments` (
  `id` int(11) NOT NULL,
  `bug_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `commented_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bug_comments`
--

INSERT INTO `bug_comments` (`id`, `bug_id`, `user_id`, `comment_text`, `commented_by`, `created_at`) VALUES
(1, 1, 0, 'Waiting on devs', 4, '2025-08-11 07:10:01'),
(2, 9, 0, 'Please have a look ath the screen shots', 4, '2025-08-13 11:12:52'),
(3, 10, 0, 'Bug in progress', 5, '2025-08-26 05:47:44'),
(4, 8, 7, 'Test', 7, '2025-09-13 06:05:28');

-- --------------------------------------------------------

--
-- Table structure for table `bug_history`
--

CREATE TABLE `bug_history` (
  `id` int(11) NOT NULL,
  `bug_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(40) NOT NULL,
  `from_value` varchar(255) DEFAULT NULL,
  `to_value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bug_test_cases`
--

CREATE TABLE `bug_test_cases` (
  `bug_id` int(11) NOT NULL,
  `test_case_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_users`
--

CREATE TABLE `project_users` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `releases`
--

CREATE TABLE `releases` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `version` varchar(50) DEFAULT NULL,
  `release_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_cases`
--

CREATE TABLE `test_cases` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `steps` text DEFAULT NULL,
  `expected_result` text DEFAULT NULL,
  `status` enum('Draft','Approved','Rejected') DEFAULT 'Draft',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_case_bugs`
--

CREATE TABLE `test_case_bugs` (
  `test_case_id` int(11) NOT NULL,
  `bug_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_runs`
--

CREATE TABLE `test_runs` (
  `id` int(11) NOT NULL,
  `brs_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `environment` varchar(255) DEFAULT 'Default',
  `created_by` int(11) NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_runs`
--

INSERT INTO `test_runs` (`id`, `brs_id`, `name`, `environment`, `created_by`, `started_at`, `ended_at`, `created_at`) VALUES
(1, 10, 'SIT', 'QA', 4, '2025-08-12 09:47:30', NULL, '2025-08-12 09:47:30'),
(2, 12, 'Test', 'DEV', 4, '2025-08-13 11:16:58', NULL, '2025-08-13 11:16:58'),
(3, 13, 'SIT', 'Dev', 5, '2025-08-26 05:52:42', NULL, '2025-08-26 05:52:42');

-- --------------------------------------------------------

--
-- Table structure for table `test_run_cases`
--

CREATE TABLE `test_run_cases` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_run_cases`
--

INSERT INTO `test_run_cases` (`id`, `run_id`, `case_id`) VALUES
(1, 1, 36),
(2, 2, 42),
(3, 3, 44);

-- --------------------------------------------------------

--
-- Table structure for table `test_run_results`
--

CREATE TABLE `test_run_results` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `actual` text DEFAULT NULL,
  `executed_by` int(11) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_run_results`
--

INSERT INTO `test_run_results` (`id`, `run_id`, `case_id`, `status`, `actual`, `executed_by`, `executed_at`) VALUES
(1, 1, 36, 'Passed', 'Step update: Passed', 4, '2025-08-12 09:48:19'),
(2, 1, 36, 'Passed', 'Step update: Passed', 4, '2025-08-12 09:48:26'),
(3, 1, 36, 'Passed', 'Step update: Passed', 4, '2025-08-12 09:48:37'),
(4, 1, 36, 'Passed', 'Step update: Passed, Passed', 4, '2025-08-12 09:49:01'),
(5, 1, 36, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-12 09:49:11'),
(6, 1, 36, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-12 09:49:22'),
(7, 1, 36, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-12 09:49:43'),
(8, 1, 36, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-12 09:49:48'),
(9, 2, 42, 'Passed', '', 4, '2025-08-13 11:17:06'),
(10, 2, 42, 'Passed', 'Step update: Passed', 4, '2025-08-13 11:17:18'),
(11, 2, 42, 'Passed', 'Step update: Passed, Passed', 4, '2025-08-13 11:17:25'),
(12, 2, 42, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-13 11:17:32'),
(13, 2, 42, 'Passed', 'Step update: Passed, Passed, Passed', 4, '2025-08-13 11:17:36'),
(14, 2, 42, 'In Progress', 'Step update: Passed, Passed, Passed', 4, '2025-08-25 17:15:04'),
(15, 2, 42, 'In Progress', 'Step update: Passed, Passed, Passed', 4, '2025-08-25 17:15:06'),
(16, 3, 44, 'In Progress', '', 5, '2025-08-26 05:52:50'),
(17, 3, 44, 'In Progress', 'Step update: Not Run', 5, '2025-08-26 05:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `test_run_step_results`
--

CREATE TABLE `test_run_step_results` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `step_index` int(11) NOT NULL,
  `step_label` varchar(32) DEFAULT NULL,
  `step_text` text NOT NULL,
  `status` varchar(20) NOT NULL,
  `note` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `executed_by` int(11) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_run_step_results`
--

INSERT INTO `test_run_step_results` (`id`, `run_id`, `case_id`, `step_index`, `step_label`, `step_text`, `status`, `note`, `attachment_path`, `executed_by`, `executed_at`) VALUES
(1, 1, 36, 0, 'Given', 'Given the system is available', 'Passed', '', NULL, 4, '2025-08-12 09:48:19'),
(2, 1, 36, 2, 'Then', 'Then the system behaves as specified: \"1. Functional Requirements\"', 'Passed', '', NULL, 4, '2025-08-12 09:49:01'),
(3, 1, 36, 1, 'When', 'When the user performs the action described: \"1. Functional Requirements\"', 'Passed', '', NULL, 4, '2025-08-12 09:49:11'),
(4, 2, 42, 0, 'Given', 'Given the system is available', 'Passed', '', NULL, 4, '2025-08-13 11:17:18'),
(5, 2, 42, 1, 'When', 'When the user performs the action described: \"2. Non-Functional Requirements\"', 'Passed', '', NULL, 4, '2025-08-13 11:17:25'),
(6, 2, 42, 2, 'Then', 'Then the system behaves as specified: \"2. Non-Functional Requirements\"', 'Passed', '', NULL, 4, '2025-08-13 11:17:32'),
(7, 3, 44, 0, 'Given', 'Given the system is available', 'Not Run', '', NULL, 5, '2025-08-26 05:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('tester','test_manager','manager','client','developer','admin') NOT NULL DEFAULT 'tester',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Tester One', 'tester@example.com', '$2y$10$D9mMmygKXj3RlGq6bTVhru05ce7Tn5y6qQltmV9X68Kk3Ch3LZog6', 'tester', '2025-08-07 03:13:33'),
(2, 'Manager One', 'manager@example.com', '$2y$10$D9mMmygKXj3RlGq6bTVhru05ce7Tn5y6qQltmV9X68Kk3Ch3LZog6', '', '2025-08-07 03:13:33'),
(3, 'Client One', 'client@example.com', '$2y$10$D9mMmygKXj3RlGq6bTVhru05ce7Tn5y6qQltmV9X68Kk3Ch3LZog6', 'client', '2025-08-07 03:13:33'),
(4, 'Venus', 'teacher@gmail.com', '$2y$10$UWGPL0ep5C7Q8Iygq3tomelFXPwqg/yznJui1ael8oV8mV1OAHRp6', 'tester', '2025-08-07 03:15:09'),
(5, 'Thabiso Lamola', 'lamola@mail.com', '$2y$10$6tTn94gSsIOfHz2SCAkZPuBHqzS42bKPRgTpvMQH2tDm5LrGnVWf6', 'tester', '2025-08-26 05:45:36'),
(6, 'MMM', 'manager@mail.com', '$2y$10$IjDeeI1iVRTW1jswfOkl3.t8KH3BTr9KXbAO9LDXIuPYND7F53ni6', '', '2025-09-02 12:25:07'),
(7, 'Raboloko', 'raboloko@mail.com', '$2y$10$kpWR6fjKBYn.CTpBmeqcd.T55YTb5kgT.hs.WTHUKCSyNOirXVs96', 'manager', '2025-09-13 05:05:37'),
(8, 'Dev', 'dev@mail.com', '$2y$10$VY88XFEpktkrauIvnIzraOiG/lugXCZV.6sKObF5LToruLzVQ569i', 'developer', '2025-09-13 05:09:37'),
(9, 'Rodney', 'rodney@mail.com', '$2y$10$Rd.jkC6mIYX72eWV3Ph3qOc6qqrbuIQq6A8zDNhcgPZkKICXXvFPq', 'developer', '2025-09-13 19:26:56'),
(10, 'developer1', 'dev1@mail.com', '$2y$10$HGFzbWl8XnoJu9RtXas9Fux9tWHUrKPwLI9FVzH/Pm1HLwU7CTlVe', 'developer', '2025-09-13 19:30:23'),
(11, 'developer12', 'dev2@mail.com', '$2y$10$tC4mVv7v2rrv8aNkIcv1qO0XP9ir/2/DDqqdetSaNidEi9wwAHaEa', 'developer', '2025-09-13 19:32:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `brs_files`
--
ALTER TABLE `brs_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `brs_test_cases`
--
ALTER TABLE `brs_test_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `brs_id` (`brs_id`);

--
-- Indexes for table `bugs`
--
ALTER TABLE `bugs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `idx_bugs_status` (`status`),
  ADD KEY `idx_bugs_component` (`component`),
  ADD KEY `idx_bugs_release` (`target_release`),
  ADD KEY `idx_bugs_assigned_to` (`assigned_to`);

--
-- Indexes for table `bug_attachments`
--
ALTER TABLE `bug_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bug_id` (`bug_id`);

--
-- Indexes for table `bug_audit_log`
--
ALTER TABLE `bug_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bug_id` (`bug_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `bug_comments`
--
ALTER TABLE `bug_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bug_id` (`bug_id`),
  ADD KEY `commented_by` (`commented_by`);

--
-- Indexes for table `bug_history`
--
ALTER TABLE `bug_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bug_id` (`bug_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_users`
--
ALTER TABLE `project_users`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `releases`
--
ALTER TABLE `releases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `test_cases`
--
ALTER TABLE `test_cases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `test_case_bugs`
--
ALTER TABLE `test_case_bugs`
  ADD PRIMARY KEY (`test_case_id`,`bug_id`),
  ADD KEY `bug_id` (`bug_id`);

--
-- Indexes for table `test_runs`
--
ALTER TABLE `test_runs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `test_run_cases`
--
ALTER TABLE `test_run_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_run_case` (`run_id`,`case_id`);

--
-- Indexes for table `test_run_results`
--
ALTER TABLE `test_run_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `test_run_step_results`
--
ALTER TABLE `test_run_step_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_step` (`run_id`,`case_id`,`step_index`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `brs_files`
--
ALTER TABLE `brs_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `brs_test_cases`
--
ALTER TABLE `brs_test_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `bugs`
--
ALTER TABLE `bugs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `bug_attachments`
--
ALTER TABLE `bug_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `bug_audit_log`
--
ALTER TABLE `bug_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `bug_comments`
--
ALTER TABLE `bug_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bug_history`
--
ALTER TABLE `bug_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `releases`
--
ALTER TABLE `releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_cases`
--
ALTER TABLE `test_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `test_runs`
--
ALTER TABLE `test_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `test_run_cases`
--
ALTER TABLE `test_run_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `test_run_results`
--
ALTER TABLE `test_run_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `test_run_step_results`
--
ALTER TABLE `test_run_step_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `brs_files`
--
ALTER TABLE `brs_files`
  ADD CONSTRAINT `brs_files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `brs_test_cases`
--
ALTER TABLE `brs_test_cases`
  ADD CONSTRAINT `brs_test_cases_ibfk_1` FOREIGN KEY (`brs_id`) REFERENCES `brs_files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bugs`
--
ALTER TABLE `bugs`
  ADD CONSTRAINT `bugs_ibfk_1` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bugs_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `bug_attachments`
--
ALTER TABLE `bug_attachments`
  ADD CONSTRAINT `bug_attachments_ibfk_1` FOREIGN KEY (`bug_id`) REFERENCES `bugs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bug_audit_log`
--
ALTER TABLE `bug_audit_log`
  ADD CONSTRAINT `bug_audit_log_ibfk_1` FOREIGN KEY (`bug_id`) REFERENCES `bugs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bug_audit_log_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bug_comments`
--
ALTER TABLE `bug_comments`
  ADD CONSTRAINT `bug_comments_ibfk_1` FOREIGN KEY (`bug_id`) REFERENCES `bugs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bug_comments_ibfk_2` FOREIGN KEY (`commented_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bug_history`
--
ALTER TABLE `bug_history`
  ADD CONSTRAINT `bug_history_ibfk_1` FOREIGN KEY (`bug_id`) REFERENCES `bugs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bug_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_users`
--
ALTER TABLE `project_users`
  ADD CONSTRAINT `project_users_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `releases`
--
ALTER TABLE `releases`
  ADD CONSTRAINT `releases_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);

--
-- Constraints for table `test_case_bugs`
--
ALTER TABLE `test_case_bugs`
  ADD CONSTRAINT `test_case_bugs_ibfk_1` FOREIGN KEY (`test_case_id`) REFERENCES `test_cases` (`id`),
  ADD CONSTRAINT `test_case_bugs_ibfk_2` FOREIGN KEY (`bug_id`) REFERENCES `bugs` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
