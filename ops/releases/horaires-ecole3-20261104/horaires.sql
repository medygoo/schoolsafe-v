BEGIN;
INSERT INTO schoolsafe_attendance_policy(school_id,syear,gradelevel_id,arrival_time,closure_time,detection_time,effective_on)
SELECT 3,2026,g.id,'07:30','07:45','08:00','2026-11-05' FROM school_gradelevels g
WHERE g.school_id=3 AND NOT EXISTS (SELECT 1 FROM schoolsafe_attendance_policy p WHERE p.school_id=3 AND p.syear=2026 AND p.gradelevel_id=g.id AND p.course_period_id IS NULL AND p.effective_on='2026-11-05');
COMMIT;
