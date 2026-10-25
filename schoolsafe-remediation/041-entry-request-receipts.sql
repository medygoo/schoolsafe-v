-- Protocol receipts ONLY, never another presence, attendance or finance ledger.
-- One accepted HTTP operation can be retried after a pickup or policy change.
-- Includes ALREADY_PRESENT (a no-op must not become a later physical arrival).
-- No QR, signature, portrait, name, phone or monetary facts are copied here.
-- Expand-only; previous runtime ignores this table. No expiry/down migration/cleanup implied.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_entry_requests (
    school_id integer NOT NULL CHECK (school_id>0),
    syear integer NOT NULL CHECK (syear>0),
    actor_staff_id integer NOT NULL REFERENCES staff(staff_id),
    request_key uuid NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    checkpoint_id integer NOT NULL CHECK (checkpoint_id>0),
    result varchar(24) NOT NULL CHECK (result IN ('PRESENT','ALREADY_PRESENT','DENIED')),
    reason varchar(64) NULL,
    entry_record_id integer NULL,
    financial_consult_id integer NULL,
    financial_state varchar(16) NULL CHECK (financial_state IN ('inactive','active','unavailable')),
    created_at timestamp with time zone NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY(school_id,syear,actor_staff_id,request_key),
    CHECK (COALESCE((result='DENIED' AND entry_record_id IS NULL AND financial_consult_id IS NOT NULL
            AND reason IN ('FEE_CONTROL_DENIED','FEE_CONTROL_UNAVAILABLE'))
        OR (result IN ('PRESENT','ALREADY_PRESENT') AND entry_record_id>0 AND reason IS NULL),false)),
    CHECK (COALESCE((financial_consult_id IS NOT NULL AND financial_state IN ('active','unavailable'))
        OR (financial_consult_id IS NULL AND (financial_state IS NULL OR financial_state='inactive')),false))
);
COMMIT;
