\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE school uuid; actor uuid; account uuid; teacher uuid; child uuid; year uuid; classroom uuid;
BEGIN
 IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF;
 SELECT id INTO STRICT school FROM app.schools WHERE code='SCH0023';
 SELECT p.id,p.user_id INTO STRICT actor,account FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
 WHERE p.school_id=school AND u.external_subject='codex-addons-local:1';
 PERFORM api.set_request_context(account,actor,school,gen_random_uuid());
 SELECT p.id INTO STRICT teacher FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
 WHERE p.school_id=school AND u.external_subject='codex-addons-local:2';
 SELECT id INTO STRICT child FROM app.students WHERE school_id=school AND matricule='SCH0023-000002'
 AND first_name='TEST CODEX' AND last_name='MATRICULE';
 INSERT INTO app.academic_years(school_id,label,starts_on,ends_on,periods,is_active)
 VALUES(school,'TEST CODEX 2026-2027','2026-06-13','2027-06-12','Semestres',true) ON CONFLICT(school_id,label) DO NOTHING;
 SELECT id INTO STRICT year FROM app.academic_years WHERE school_id=school AND label='TEST CODEX 2026-2027';
 SELECT id INTO classroom FROM app.classes WHERE school_id=school AND academic_year_id=year AND name='TEST CODEX MATERNELLE';
 IF classroom IS NULL THEN
  INSERT INTO app.classes(school_id,academic_year_id,cycle_key,name,teacher_profile_id)
  VALUES(school,year,'nursery','TEST CODEX MATERNELLE',teacher) RETURNING id INTO classroom;
 END IF;
 UPDATE app.students SET class_id=classroom,lifecycle_status='active' WHERE id=child AND school_id=school;
 IF NOT EXISTS(SELECT 1 FROM app.student_enrollments WHERE student_id=child AND class_id=classroom AND academic_year_id=year) THEN
  INSERT INTO app.student_enrollments(school_id,student_id,academic_year_id,class_id,status,starts_on,created_by)
  VALUES(school,child,year,classroom,'active','2026-10-12',actor);
 END IF;
END $$;
COMMIT;
SELECT id,name,cycle_key,teacher_profile_id FROM app.classes WHERE name='TEST CODEX MATERNELLE';
