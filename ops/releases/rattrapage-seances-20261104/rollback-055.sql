BEGIN;
DROP INDEX IF EXISTS schoolsafe_remediation_sessions_group_idx;
ALTER TABLE schoolsafe_remediation_sessions DROP COLUMN IF EXISTS group_key, DROP COLUMN IF EXISTS attendance_card;
COMMIT;
