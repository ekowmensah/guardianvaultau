-- Apply only after every open duplicate_user_email issue has been resolved.
-- Normalize addresses in application code before this database constraint is added.
ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);
INSERT INTO schema_migrations (version) VALUES ('002_email_uniqueness_after_resolution');
