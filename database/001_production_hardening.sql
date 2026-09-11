-- Guardian Vault production hardening migration.
-- Back up the database and resolve any duplicate child-table user_id values
-- before applying this migration in another environment.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN status ENUM('Active', 'Suspended', 'Closed') NOT NULL DEFAULT 'Active' AFTER role,
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE users SET role = 'User' WHERE role <> 'User' OR role IS NULL;
ALTER TABLE users MODIFY role ENUM('User') NOT NULL DEFAULT 'User';

ALTER TABLE admin_users
    ADD COLUMN role ENUM('super_admin', 'operator', 'auditor') NOT NULL DEFAULT 'operator' AFTER telephone_number,
    ADD COLUMN status ENUM('Active', 'Suspended') NOT NULL DEFAULT 'Active' AFTER role,
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_admin_users_username (username);

-- Preserve the existing administrator's ability to manage administrator accounts.
UPDATE admin_users SET role = 'super_admin' WHERE id = (SELECT first_admin.id FROM (SELECT MIN(id) AS id FROM admin_users) first_admin);

ALTER TABLE userprofile
    DROP FOREIGN KEY userprofile_ibfk_1,
    MODIFY user_id INT NOT NULL,
    MODIFY married_status ENUM('Single', 'Married', 'Divorced') NULL,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_userprofile_user_id (user_id),
    ADD CONSTRAINT fk_userprofile_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE item_details
    DROP FOREIGN KEY item_details_ibfk_1,
    MODIFY user_id INT NOT NULL,
    MODIFY package_quantity INT UNSIGNED NULL,
    MODIFY total_weight DECIMAL(18,3) NULL,
    MODIFY monthly_charges DECIMAL(18,2) NULL,
    MODIFY amount_paid DECIMAL(18,2) NULL,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'AUD' AFTER amount_paid,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_item_details_user_id (user_id),
    ADD UNIQUE KEY uq_item_details_insurance_number (insurance_number),
    ADD UNIQUE KEY uq_item_details_reference_code (reference_code),
    ADD UNIQUE KEY uq_item_details_transaction_code (transaction_code),
    ADD CONSTRAINT fk_item_details_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE state_of_items
    DROP FOREIGN KEY state_of_items_ibfk_1,
    MODIFY user_id INT NOT NULL,
    MODIFY current_gold_worth DECIMAL(18,2) NULL,
    MODIFY price_per_kilogram DECIMAL(18,2) NULL,
    MODIFY cost_of_safe_keeping DECIMAL(18,2) NULL,
    MODIFY quantity INT UNSIGNED NULL,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'AUD' AFTER quantity,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_state_of_items_user_id (user_id),
    ADD CONSTRAINT fk_state_of_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE next_of_kin
    DROP FOREIGN KEY next_of_kin_ibfk_1,
    MODIFY user_id INT NOT NULL,
    ADD COLUMN address TEXT NULL AFTER telephone_number_kin,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD UNIQUE KEY uq_next_of_kin_user_id (user_id),
    ADD CONSTRAINT fk_next_of_kin_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    realm ENUM('user', 'admin') NOT NULL,
    username_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_login_attempt_lookup (realm, username_hash, ip_address, attempted_at),
    INDEX idx_login_attempt_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_event_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    realm ENUM('user', 'admin', 'system') NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    actor_id INT NULL,
    subject_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_security_event_type_time (event_type, created_at),
    INDEX idx_security_event_actor (realm, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_user_id INT NULL,
    details VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_admin_activity_created_at (created_at),
    INDEX idx_admin_activity_target_user (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_record_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    action ENUM('create', 'update', 'password_change', 'delete') NOT NULL,
    snapshot LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_revision_user_time (user_id, created_at),
    INDEX idx_user_revision_admin_time (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_quality_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_type VARCHAR(100) NOT NULL,
    entity_ids VARCHAR(255) NOT NULL,
    fingerprint CHAR(64) NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_quality_open_issue (issue_type, fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO data_quality_issues (issue_type, entity_ids, fingerprint)
SELECT 'duplicate_user_email', GROUP_CONCAT(id ORDER BY id), SHA2(LOWER(TRIM(email)), 256)
FROM users
WHERE email IS NOT NULL AND TRIM(email) <> ''
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;

INSERT INTO schema_migrations (version) VALUES ('001_production_hardening');
