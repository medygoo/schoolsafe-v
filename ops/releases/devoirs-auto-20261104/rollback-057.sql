BEGIN;
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events ADD CONSTRAINT schoolsafe_notify_events_type_allowed CHECK (event_type IN (
  'security.entry','student.pickup','payment.receipt','school.announcement','remediation.update',
  'attendance.closure','absence.detected','case.updated','security.p1','financial.control','fee.reminder'));
COMMIT;
