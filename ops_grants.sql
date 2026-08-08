-- ops_grants.sql
--
-- DBA-run least-privilege lockdown for the laundry database.
-- NOT run automatically by Laravel migrations -- migrations run as a privileged
-- account (e.g. root during setup); this script provisions the low-privilege
-- account the running application actually uses day to day.
--
-- Usage: replace {{DB_NAME}} and the two {{...PASSWORD}} placeholders with real
-- values, then run once against the target database as an admin user:
--   mysql -u root -p < ops_grants.sql
--
-- Guarantee this enforces (Master Document §5/§6): activity_log and
-- order_status_history cannot be altered or deleted by the application, even
-- if the application's own DB credential is compromised -- only SELECT/INSERT
-- are ever granted on them, so the trigger-driven INSERTs into these tables
-- (which execute with the *definer's* privileges, not the connecting user's)
-- keep working regardless.
--
-- Why per-table grants instead of GRANT ... ON {{DB_NAME}}.* + REVOKE on the
-- three sensitive tables: MySQL/MariaDB privilege checks are a union across
-- grant levels. A table-level REVOKE cannot narrow a broader database-level
-- GRANT -- the wider grant still wins. The only correct way to withhold a
-- privilege on specific tables is to never grant it there in the first place.

-- ============================================================
-- laundry_app -- the account routes/config/database.php should use in .env
-- ============================================================
CREATE USER IF NOT EXISTS 'laundry_app'@'%' IDENTIFIED BY '{{LAUNDRY_APP_PASSWORD}}';

-- Baseline: SELECT + INSERT everywhere.
GRANT SELECT, INSERT ON {{DB_NAME}}.* TO 'laundry_app'@'%';

-- UPDATE + DELETE granted per table below. The three append-only tables are
-- simply never granted these, rather than granted-then-revoked (see note
-- above on why REVOKE-after-db-level-GRANT does not work here). Add new
-- tables to this list as the schema grows; anything left off defaults to
-- SELECT + INSERT only, which is the safe default for an omission.
GRANT UPDATE, DELETE ON {{DB_NAME}}.backups TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.cache TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.cache_locks TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.clothes_categories TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.clothing_items TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.collections TO 'laundry_app'@'%';
GRANT UPDATE ON {{DB_NAME}}.credit_transactions TO 'laundry_app'@'%'; -- DELETE intentionally withheld (append-only ledger)
GRANT UPDATE, DELETE ON {{DB_NAME}}.customers TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.customer_complaints TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.damage_records TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.damage_resolutions TO 'laundry_app'@'%';
-- damage_status_history: SELECT + INSERT only (append-only audit trail, same as order_status_history)
GRANT UPDATE, DELETE ON {{DB_NAME}}.damage_types TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.expenses TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.expense_categories TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.failed_jobs TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.jobs TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.job_batches TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.laundry_packages TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.model_has_permissions TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.model_has_roles TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.notifications TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.orders TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.order_clothes_lines TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.order_package_lines TO 'laundry_app'@'%';
-- order_status_history: SELECT + INSERT only (append-only audit trail)
GRANT UPDATE, DELETE ON {{DB_NAME}}.password_reset_tokens TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.payments TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.permissions TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.receipts TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.refunds TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.roles TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.role_has_permissions TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.sessions TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.settings TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.subscriptions TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.subscription_packages TO 'laundry_app'@'%';
GRANT UPDATE ON {{DB_NAME}}.subscription_cycles TO 'laundry_app'@'%'; -- DELETE intentionally withheld (never hard-deleted; closeIfExhausted() only ever updates)
GRANT UPDATE, DELETE ON {{DB_NAME}}.users TO 'laundry_app'@'%';
GRANT UPDATE, DELETE ON {{DB_NAME}}.washing_machines TO 'laundry_app'@'%';

-- ============================================================
-- laundry_readonly -- optional, for a future reporting/BI connection
-- ============================================================
CREATE USER IF NOT EXISTS 'laundry_readonly'@'%' IDENTIFIED BY '{{LAUNDRY_READONLY_PASSWORD}}';
GRANT SELECT ON {{DB_NAME}}.* TO 'laundry_readonly'@'%';

FLUSH PRIVILEGES;
