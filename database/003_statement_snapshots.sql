CREATE TABLE account_statements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    statement_number VARCHAR(40) NOT NULL,
    user_id INT NULL,
    subject_user_id INT NOT NULL,
    snapshot LONGTEXT NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    signature CHAR(64) NOT NULL,
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_statement_number (statement_number),
    INDEX idx_account_statement_user_time (user_id, issued_at),
    INDEX idx_account_statement_subject (subject_user_id),
    CONSTRAINT fk_account_statement_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('003_statement_snapshots');
