BEGIN;
DELETE FROM schedule WHERE student_id=3 AND course_period_id=1 AND syear=2026 AND start_date='2026-11-04';
UPDATE course_periods SET filled_seats=0 WHERE course_period_id=1;
COMMIT;
