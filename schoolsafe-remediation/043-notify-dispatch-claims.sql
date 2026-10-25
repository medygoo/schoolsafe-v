-- Durable ownership of EXISTING deliveries, not another queue/state store.
-- Never expire/release a claim automatically: the provider may have accepted
-- before a worker crashed. Stop legacy dispatchers before adopting this schema.
BEGIN;
ALTER TABLE schoolsafe_notify_deliveries
    ADD COLUMN IF NOT EXISTS dispatch_claim_token varchar(32) NULL,
    ADD COLUMN IF NOT EXISTS dispatch_claimed_at timestamp NULL;
CREATE INDEX IF NOT EXISTS schoolsafe_notify_deliveries_unclaimed_idx
    ON schoolsafe_notify_deliveries(created_at,id)
    WHERE status='pending' AND dispatch_claim_token IS NULL;
COMMIT;
