BEGIN;
INSERT INTO schedule(syear,school_id,student_id,start_date,course_id,course_period_id,mp,marking_period_id)
SELECT 2026,3,3,CURRENT_DATE,1,1,'FY',15
WHERE NOT EXISTS (SELECT 1 FROM schedule WHERE student_id=3 AND course_period_id=1 AND syear=2026);
UPDATE course_periods SET filled_seats=(SELECT count(DISTINCT student_id) FROM schedule WHERE course_period_id=1 AND (end_date IS NULL OR end_date>=CURRENT_DATE)) WHERE course_period_id=1;
COMMIT;
