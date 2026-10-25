-- Native scan scope. LOCAL/ISOLATED QUALIFICATION ONLY; no production execution.
-- Add metadata to the existing engine, not a second passage/attendance ledger.
-- Historical records/checkpoints/proofs stay unassigned: never infer their school.
BEGIN;
ALTER TABLE entry_exit_student_records
    ADD COLUMN IF NOT EXISTS schoolsafe_scan_school_id integer,
    ADD COLUMN IF NOT EXISTS schoolsafe_scan_syear integer;
ALTER TABLE entry_exit_checkpoints
    ADD COLUMN IF NOT EXISTS schoolsafe_scan_school_id integer,
    ADD COLUMN IF NOT EXISTS schoolsafe_scan_syear integer,
    ADD COLUMN IF NOT EXISTS schoolsafe_scan_enabled boolean NOT NULL DEFAULT false;
ALTER TABLE schoolsafe_pickup_proofs
    ADD COLUMN IF NOT EXISTS syear integer,
    ADD COLUMN IF NOT EXISTS entry_record_id integer REFERENCES entry_exit_student_records(id);
CREATE INDEX IF NOT EXISTS schoolsafe_scan_native_scope_idx
    ON entry_exit_student_records(schoolsafe_scan_school_id,schoolsafe_scan_syear,student_id,id DESC)
    WHERE unauthorized IS NULL;
CREATE INDEX IF NOT EXISTS schoolsafe_pickup_proofs_entry_idx
    ON schoolsafe_pickup_proofs(school_id,syear,student_id,entry_record_id);
COMMIT;
