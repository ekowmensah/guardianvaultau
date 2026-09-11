ALTER TABLE admin_users ADD COLUMN totp_secret VARCHAR(255) NULL AFTER session_version;
INSERT INTO schema_migrations (version) VALUES ('004_admin_mfa');
