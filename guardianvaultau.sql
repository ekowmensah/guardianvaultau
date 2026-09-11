-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 11, 2026 at 06:00 PM
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
-- Database: `guardianvaultau`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_statements`
--

CREATE TABLE `account_statements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `statement_number` varchar(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject_user_id` int(11) NOT NULL,
  `snapshot` longtext NOT NULL,
  `snapshot_hash` char(64) NOT NULL,
  `signature` char(64) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone_number` varchar(30) DEFAULT NULL,
  `role` enum('super_admin','operator','auditor') NOT NULL DEFAULT 'operator',
  `status` enum('Active','Suspended') NOT NULL DEFAULT 'Active',
  `session_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `totp_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `telephone_number`, `role`, `status`, `session_version`, `totp_secret`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2a$12$hy0y1yNPe04HN/DDFFcFYeS8YJRsvuqmnWSRlXmH8TEo7EVAmY/SC', 'Admin', 'User', 'admin@example.com', '1234567890', 'super_admin', 'Active', 1, NULL, '2025-06-17 10:42:14', '2026-09-11 15:18:55');

-- --------------------------------------------------------

--
-- Table structure for table `data_quality_issues`
--

CREATE TABLE `data_quality_issues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `issue_type` varchar(100) NOT NULL,
  `entity_ids` varchar(255) NOT NULL,
  `fingerprint` char(64) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_quality_issues`
--

INSERT INTO `data_quality_issues` (`id`, `issue_type`, `entity_ids`, `fingerprint`, `resolved_at`, `created_at`) VALUES
(1, 'duplicate_user_email', '72,73', '3c87d568ccaf6de7cfb1ea728f79ff65d3434292f4b0bf6c4aec7f467d177498', NULL, '2026-09-11 15:19:31');

-- --------------------------------------------------------

--
-- Table structure for table `item_details`
--

CREATE TABLE `item_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `insurance_number` varchar(255) DEFAULT NULL,
  `reference_code` varchar(255) DEFAULT NULL,
  `transaction_code` varchar(255) DEFAULT NULL,
  `box_dimension` varchar(255) DEFAULT NULL,
  `deposited_item` text DEFAULT NULL,
  `package_type` varchar(255) DEFAULT NULL,
  `package_quantity` int(10) UNSIGNED DEFAULT NULL,
  `total_weight` decimal(18,3) DEFAULT NULL,
  `deposit_date` date DEFAULT NULL,
  `monthly_charges` decimal(18,2) DEFAULT NULL,
  `amount_paid` decimal(18,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AUD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_details`
--

INSERT INTO `item_details` (`id`, `user_id`, `insurance_number`, `reference_code`, `transaction_code`, `box_dimension`, `deposited_item`, `package_type`, `package_quantity`, `total_weight`, `deposit_date`, `monthly_charges`, `amount_paid`, `currency`, `created_at`, `updated_at`) VALUES
(38, 71, 'INS6AA377EC3BE5C', 'REF6AA377EC3BE5D', 'TRX6AA377EC3BE5E', '20x30', 'diamon', 'carton', 17, 38383.000, '1996-05-03', 25360.00, 1254.00, 'AUD', '2026-09-11 15:18:55', '2026-09-11 15:18:55'),
(41, 74, 'INS6AA424B3C3645', 'REF6AA424B3C3646', 'TRX6AA424B3C3647', '15 x 15 x 15 cm', 'Pure Gold Yellow', 'Box', 15, 15.000, '2015-12-15', 1500.00, 15000.00, 'AUD', '2026-09-11 15:57:54', '2026-09-11 15:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `realm` enum('user','admin') NOT NULL,
  `username_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `next_of_kin`
--

CREATE TABLE `next_of_kin` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name_of_beneficial` varchar(255) DEFAULT NULL,
  `relation_with_user` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `telephone_number_kin` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `next_of_kin`
--

INSERT INTO `next_of_kin` (`id`, `user_id`, `name_of_beneficial`, `relation_with_user`, `date_of_birth`, `email_address`, `telephone_number_kin`, `address`, `created_at`, `updated_at`) VALUES
(38, 71, 'SAMUEL DADSO', 'sister', '1978-05-04', 'ekowme@gmail.com', '0545644749', NULL, '2026-09-11 15:18:55', '2026-09-11 15:18:55'),
(41, 74, 'FATIMATU AWUDU', 'Sister15', '2015-12-15', 'fatimatu15@gmail.com', '0554828615', NULL, '2026-09-11 15:57:54', '2026-09-11 15:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schema_migrations`
--

INSERT INTO `schema_migrations` (`version`, `applied_at`) VALUES
('001_production_hardening', '2026-09-11 15:38:52'),
('003_statement_snapshots', '2026-09-11 15:38:52'),
('004_admin_mfa', '2026-09-11 15:40:01'),
('005_remove_redundant_indexes', '2026-09-11 15:50:02');

-- --------------------------------------------------------

--
-- Table structure for table `security_event_log`
--

CREATE TABLE `security_event_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `realm` enum('user','admin','system') NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_event_log`
--

INSERT INTO `security_event_log` (`id`, `realm`, `event_type`, `actor_id`, `subject_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 'user', 'logout', 71, 71, '::1', 'curl/8.21.0', NULL, '2026-09-11 15:46:01'),
(2, 'user', 'statement_issued', 71, 71, '::1', 'curl/8.21.0', 'GV-202609-A1EEF42374243FCB', '2026-09-11 15:46:18'),
(3, 'user', 'logout', 71, 71, '::1', 'curl/8.21.0', NULL, '2026-09-11 15:46:19'),
(4, 'admin', 'logout', 1, 1, '::1', 'curl/8.21.0', NULL, '2026-09-11 15:51:04'),
(5, 'user', 'statement_issued', 71, 71, '::1', 'curl/8.21.0', 'GV-202609-1813DA93A6CC1AD2', '2026-09-11 15:51:57'),
(6, 'user', 'logout', 71, 71, '::1', 'curl/8.21.0', NULL, '2026-09-11 15:51:57');

-- --------------------------------------------------------

--
-- Table structure for table `state_of_items`
--

CREATE TABLE `state_of_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_gold_worth` decimal(18,2) DEFAULT NULL,
  `price_per_kilogram` decimal(18,2) DEFAULT NULL,
  `cost_of_safe_keeping` decimal(18,2) DEFAULT NULL,
  `date_of_safe_keeping` date DEFAULT NULL,
  `quantity` int(10) UNSIGNED DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AUD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `state_of_items`
--

INSERT INTO `state_of_items` (`id`, `user_id`, `current_gold_worth`, `price_per_kilogram`, `cost_of_safe_keeping`, `date_of_safe_keeping`, `quantity`, `currency`, `created_at`, `updated_at`) VALUES
(38, 71, 36547.00, 123.00, 2564.00, '1988-04-07', 5, 'AUD', '2026-09-11 15:18:55', '2026-09-11 15:18:55'),
(41, 74, 15000.00, 150.00, 15.00, '2015-12-15', 15, 'AUD', '2026-09-11 15:57:54', '2026-09-11 15:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `userprofile`
--

CREATE TABLE `userprofile` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `married_status` enum('Single','Married','Divorced') DEFAULT NULL,
  `has_child` enum('Yes','No') DEFAULT NULL,
  `child_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userprofile`
--

INSERT INTO `userprofile` (`id`, `user_id`, `nationality`, `married_status`, `has_child`, `child_name`, `address`, `created_at`, `updated_at`) VALUES
(38, 71, 'Ghana', 'Single', 'No', 'Yaw mensah', 'dlkjdaf dfjasdkjflsdkjf ldjf; asldfjl sdalfd', '2026-09-11 15:18:55', '2026-09-11 15:18:55'),
(41, 74, 'Ghanaian', 'Single', 'No', 'Kobina Adom', 'BRAKWA BREMAN RURAL BANK BOX 55', '2026-09-11 15:57:54', '2026-09-11 15:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone_number` varchar(255) DEFAULT NULL,
  `role` enum('User') NOT NULL DEFAULT 'User',
  `status` enum('Active','Suspended','Closed') NOT NULL DEFAULT 'Active',
  `session_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `telephone_number`, `role`, `status`, `session_version`, `created_at`, `updated_at`) VALUES
(71, 'ACC6AA377CE13F16', '$2y$10$UmSi5Csr2pEE/7pRm4C0suhIPxas5NJ6CP.OciCNYorPG46AcW0L6', 'Paa Kow', 'Mensah', 'ekowme@gmail.com', '0545644749', 'User', 'Active', 1, '2026-09-11 15:19:31', '2026-09-11 15:19:31'),
(74, 'ACC6AA4248DABB3F', '$2y$10$dZeYCI0jpO8LAZxEIuSUSe0vEwBawCl5nLFIYcJ9qwWxKeUip.y.6', 'Tito', 'Nash', 'titonash@gmail.com', '0545644749', 'User', 'Active', 1, '2026-09-11 15:57:54', '2026-09-11 15:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `user_record_revisions`
--

CREATE TABLE `user_record_revisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` enum('create','update','password_change','delete') NOT NULL,
  `snapshot` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_statements`
--
ALTER TABLE `account_statements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_statement_number` (`statement_number`),
  ADD KEY `idx_account_statement_user_time` (`user_id`,`issued_at`),
  ADD KEY `idx_account_statement_subject` (`subject_user_id`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_activity_created_at` (`created_at`),
  ADD KEY `idx_admin_activity_target_user` (`target_user_id`),
  ADD KEY `fk_admin_activity_admin` (`admin_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_users_username` (`username`);

--
-- Indexes for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_data_quality_open_issue` (`issue_type`,`fingerprint`);

--
-- Indexes for table `item_details`
--
ALTER TABLE `item_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_item_details_user_id` (`user_id`),
  ADD UNIQUE KEY `uq_item_details_insurance_number` (`insurance_number`),
  ADD UNIQUE KEY `uq_item_details_reference_code` (`reference_code`),
  ADD UNIQUE KEY `uq_item_details_transaction_code` (`transaction_code`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempt_lookup` (`realm`,`username_hash`,`ip_address`,`attempted_at`),
  ADD KEY `idx_login_attempt_time` (`attempted_at`);

--
-- Indexes for table `next_of_kin`
--
ALTER TABLE `next_of_kin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_next_of_kin_user_id` (`user_id`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `security_event_log`
--
ALTER TABLE `security_event_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_security_event_type_time` (`event_type`,`created_at`),
  ADD KEY `idx_security_event_actor` (`realm`,`actor_id`);

--
-- Indexes for table `state_of_items`
--
ALTER TABLE `state_of_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_state_of_items_user_id` (`user_id`);

--
-- Indexes for table `userprofile`
--
ALTER TABLE `userprofile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_userprofile_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_record_revisions`
--
ALTER TABLE `user_record_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_revision_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_user_revision_admin_time` (`admin_id`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_statements`
--
ALTER TABLE `account_statements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `item_details`
--
ALTER TABLE `item_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `next_of_kin`
--
ALTER TABLE `next_of_kin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `security_event_log`
--
ALTER TABLE `security_event_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `state_of_items`
--
ALTER TABLE `state_of_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `userprofile`
--
ALTER TABLE `userprofile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `user_record_revisions`
--
ALTER TABLE `user_record_revisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_statements`
--
ALTER TABLE `account_statements`
  ADD CONSTRAINT `fk_account_statement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `item_details`
--
ALTER TABLE `item_details`
  ADD CONSTRAINT `fk_item_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `next_of_kin`
--
ALTER TABLE `next_of_kin`
  ADD CONSTRAINT `fk_next_of_kin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `state_of_items`
--
ALTER TABLE `state_of_items`
  ADD CONSTRAINT `fk_state_of_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `userprofile`
--
ALTER TABLE `userprofile`
  ADD CONSTRAINT `fk_userprofile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
