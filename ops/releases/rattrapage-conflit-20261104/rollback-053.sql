BEGIN;
DROP INDEX IF EXISTS schoolsafe_remediation_sessions_teacher_idx;
ALTER TABLE schoolsafe_remediation_settings DROP COLUMN IF EXISTS other_teacher;
COMMIT;
