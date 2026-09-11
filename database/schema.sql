SET FOREIGN_KEY_CHECKS=0;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_statements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `statement_number` varchar(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject_user_id` int(11) NOT NULL,
  `snapshot` longtext NOT NULL,
  `snapshot_hash` char(64) NOT NULL,
  `signature` char(64) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_statement_number` (`statement_number`),
  KEY `idx_account_statement_user_time` (`user_id`,`issued_at`),
  KEY `idx_account_statement_subject` (`subject_user_id`),
  CONSTRAINT `fk_account_statement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_activity_created_at` (`created_at`),
  KEY `idx_admin_activity_target_user` (`target_user_id`),
  KEY `fk_admin_activity_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone_number` varchar(30) DEFAULT NULL,
  `role` enum('super_admin','operator','auditor') NOT NULL DEFAULT 'operator',
  `status` enum('Active','Suspended') NOT NULL DEFAULT 'Active',
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `totp_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_quality_issues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_type` varchar(100) NOT NULL,
  `entity_ids` varchar(255) NOT NULL,
  `fingerprint` char(64) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_data_quality_open_issue` (`issue_type`,`fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `insurance_number` varchar(255) DEFAULT NULL,
  `reference_code` varchar(255) DEFAULT NULL,
  `transaction_code` varchar(255) DEFAULT NULL,
  `box_dimension` varchar(255) DEFAULT NULL,
  `deposited_item` text DEFAULT NULL,
  `package_type` varchar(255) DEFAULT NULL,
  `package_quantity` int(10) unsigned DEFAULT NULL,
  `total_weight` decimal(18,3) DEFAULT NULL,
  `deposit_date` date DEFAULT NULL,
  `monthly_charges` decimal(18,2) DEFAULT NULL,
  `amount_paid` decimal(18,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AUD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_details_user_id` (`user_id`),
  UNIQUE KEY `uq_item_details_insurance_number` (`insurance_number`),
  UNIQUE KEY `uq_item_details_reference_code` (`reference_code`),
  UNIQUE KEY `uq_item_details_transaction_code` (`transaction_code`),
  CONSTRAINT `fk_item_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `realm` enum('user','admin') NOT NULL,
  `username_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempt_lookup` (`realm`,`username_hash`,`ip_address`,`attempted_at`),
  KEY `idx_login_attempt_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `next_of_kin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name_of_beneficial` varchar(255) DEFAULT NULL,
  `relation_with_user` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `telephone_number_kin` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_next_of_kin_user_id` (`user_id`),
  CONSTRAINT `fk_next_of_kin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_event_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `realm` enum('user','admin','system') NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_event_type_time` (`event_type`,`created_at`),
  KEY `idx_security_event_actor` (`realm`,`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state_of_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `current_gold_worth` decimal(18,2) DEFAULT NULL,
  `price_per_kilogram` decimal(18,2) DEFAULT NULL,
  `cost_of_safe_keeping` decimal(18,2) DEFAULT NULL,
  `date_of_safe_keeping` date DEFAULT NULL,
  `quantity` int(10) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'AUD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_state_of_items_user_id` (`user_id`),
  CONSTRAINT `fk_state_of_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_record_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` enum('create','update','password_change','delete') NOT NULL,
  `snapshot` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_revision_user_time` (`user_id`,`created_at`),
  KEY `idx_user_revision_admin_time` (`admin_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userprofile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `married_status` enum('Single','Married','Divorced') DEFAULT NULL,
  `has_child` enum('Yes','No') DEFAULT NULL,
  `child_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_userprofile_user_id` (`user_id`),
  CONSTRAINT `fk_userprofile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone_number` varchar(255) DEFAULT NULL,
  `role` enum('User') NOT NULL DEFAULT 'User',
  `status` enum('Active','Suspended','Closed') NOT NULL DEFAULT 'Active',
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `schema_migrations` (`version`) VALUES
('001_production_hardening'),
('002_email_uniqueness_after_resolution'),
('003_statement_snapshots'),
('004_admin_mfa'),
('005_remove_redundant_indexes');

SET FOREIGN_KEY_CHECKS=1;
