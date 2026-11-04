BEGIN;
UPDATE course_periods SET does_attendance=NULL WHERE school_id=3 AND syear=2026 AND course_period_id IN (1,4,5);
COMMIT;
