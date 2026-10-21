-- SchoolSafe: targeted first access for newly provisioned native staff only.
-- Additive/idempotent. No existing account, password or global policy changes.
-- Rollback application first; keep this table and pending records (do not drop
-- or clear them while a temporary password remains valid).
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_native_onboarding (
    staff_id integer NOT NULL,
    syear integer NOT NULL,
    expires_at timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP + interval '24 hours'),
    completed_at timestamp NULL,
    generation integer NOT NULL DEFAULT 1,
    created_by integer NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (staff_id, syear)
);
ALTER TABLE schoolsafe_native_onboarding ADD COLUMN IF NOT EXISTS generation integer NOT NULL DEFAULT 1;
COMMIT;
