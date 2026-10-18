\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE subject integer; course integer; section integer; period integer;
BEGIN
 IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF;
 IF NOT EXISTS(SELECT 1 FROM students WHERE student_id=2 AND first_name='TEST CODEX' AND last_name='MATRICULE')
 OR NOT EXISTS(SELECT 1 FROM staff WHERE staff_id=2 AND profile='teacher') THEN RAISE EXCEPTION 'TEST_IDENTITIES_REQUIRED'; END IF;
 SELECT marking_period_id INTO STRICT period FROM marking_periods WHERE school_id=1 AND syear=2026 AND mp_type='year';
 SELECT subject_id INTO subject FROM course_subjects WHERE school_id=1 AND syear=2026 AND title='TEST CODEX CARTES';
 IF subject IS NULL THEN
  INSERT INTO course_subjects(syear,school_id,title,short_name) VALUES(2026,1,'TEST CODEX CARTES','TC') RETURNING subject_id INTO subject;
 END IF;
 SELECT course_id INTO course FROM courses WHERE school_id=1 AND syear=2026 AND title='TEST CODEX MATERNELLE';
 IF course IS NULL THEN
  INSERT INTO courses(syear,school_id,subject_id,title,short_name,grade_level)
  VALUES(2026,1,subject,'TEST CODEX MATERNELLE','TCM',1) RETURNING course_id INTO course;
 END IF;
 SELECT course_period_id INTO section FROM course_periods WHERE school_id=1 AND syear=2026 AND title='TEST CODEX MATERNELLE';
 IF section IS NULL THEN
  INSERT INTO course_periods(syear,school_id,course_id,title,short_name,mp,marking_period_id,teacher_id,total_seats,does_attendance)
  VALUES(2026,1,course,'TEST CODEX MATERNELLE','TCM','FY',period,2,10,'Y') RETURNING course_period_id INTO section;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM schedule WHERE student_id=2 AND school_id=1 AND syear=2026 AND course_period_id=section) THEN
  INSERT INTO schedule(syear,school_id,student_id,start_date,course_id,course_period_id,mp,marking_period_id,modified_by)
  VALUES(2026,1,2,'2026-10-12',course,section,'FY',period,'TEST CODEX');
 END IF;
END $$;
COMMIT;
SELECT course_period_id,title,teacher_id FROM course_periods WHERE title='TEST CODEX MATERNELLE';
