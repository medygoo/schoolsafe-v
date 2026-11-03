-- SchoolSafe 051 : type de notification « fee.reminder » (rappels de frais et cantine par type, 03/11).
-- Additif et rejouable : la liste autorisee est remplacee par la meme liste + fee.reminder.
BEGIN;
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events ADD CONSTRAINT schoolsafe_notify_events_type_allowed CHECK (event_type IN (
  'security.entry','student.pickup','payment.receipt','school.announcement','remediation.update',
  'attendance.closure','absence.detected','case.updated','security.p1','financial.control','fee.reminder'));
COMMIT;
