-- The unique user_id indexes created by migration 001 also support the foreign keys.
-- Remove the legacy non-unique copies to reduce write overhead.
ALTER TABLE userprofile DROP INDEX user_id;
ALTER TABLE item_details DROP INDEX user_id;
ALTER TABLE state_of_items DROP INDEX user_id;
ALTER TABLE next_of_kin DROP INDEX user_id;

INSERT INTO schema_migrations (version) VALUES ('005_remove_redundant_indexes');
