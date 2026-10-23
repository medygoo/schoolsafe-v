-- Expand only: keep every legacy consultation and billing table unchanged.
-- Old runtime writers remain valid (new columns NULL); never infer historical years.
-- Rollback path: previous qualified runtime may ignore these additive columns.
-- No automatic down migration or destructive contraction. QA before any release.
BEGIN;
ALTER TABLE schoolsafe_fee_consults
    ADD COLUMN IF NOT EXISTS syear integer NULL,
    ADD COLUMN IF NOT EXISTS operation varchar(16) NULL,
    ADD COLUMN IF NOT EXISTS context_snapshot jsonb NULL,
    ADD COLUMN IF NOT EXISTS request_key varchar(64) NULL,
    ADD COLUMN IF NOT EXISTS entry_record_id bigint NULL;
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='schoolsafe_fee_consults_context_scope'
        AND conrelid='schoolsafe_fee_consults'::regclass) THEN
        ALTER TABLE schoolsafe_fee_consults ADD CONSTRAINT schoolsafe_fee_consults_context_scope CHECK (
            (syear IS NULL AND operation IS NULL AND context_snapshot IS NULL AND request_key IS NULL AND entry_record_id IS NULL)
            OR COALESCE(syear>0 AND operation IN ('consult','entry') AND jsonb_typeof(context_snapshot)='object'
                AND context_snapshot->>'version'='1'
                AND context_snapshot->>'syear'=syear::text
                AND context_snapshot->>'operation'=operation
                AND context_snapshot->'school'->>'id'=school_id::text
                AND context_snapshot->'student'->>'id'=student_id::text
                AND context_snapshot->>'actor_staff_id'=consulted_by::text
                AND (request_key IS NULL OR request_key ~ '^[a-f0-9]{8}-([a-f0-9]{4}-){3}[a-f0-9]{12}$')
                AND (entry_record_id IS NULL OR (operation='entry' AND entry_record_id>0)),false)
        );
    END IF;
END $$;
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_fee_consults_request_unique
    ON schoolsafe_fee_consults(school_id,syear,consulted_by,operation,request_key) WHERE request_key IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_fee_consults_entry_unique
    ON schoolsafe_fee_consults(entry_record_id) WHERE entry_record_id IS NOT NULL;
COMMIT;
