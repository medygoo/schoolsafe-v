\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF; END $$;
CREATE OR REPLACE FUNCTION card_bridge_local.student_schooling(p_student uuid)
RETURNS jsonb LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE result jsonb;
BEGIN
 PERFORM iam.require_access('cards.request.print',NULL,p_student);
 SELECT jsonb_build_object('classId',c.id,'yearId',y.id,'teacherId',p.id,'teacherName',p.display_name)
 INTO result FROM app.students s
 JOIN app.classes c ON c.id=s.class_id AND c.school_id=s.school_id AND c.is_active
 JOIN app.academic_years y ON y.id=c.academic_year_id AND y.school_id=s.school_id AND y.is_active
 LEFT JOIN iam.profiles p ON p.id=c.teacher_profile_id AND p.school_id=s.school_id AND p.is_active
 WHERE s.id=p_student AND s.school_id=iam.current_school_id()
 AND EXISTS(SELECT 1 FROM app.student_enrollments e WHERE e.student_id=s.id AND e.school_id=s.school_id
 AND e.class_id=c.id AND e.academic_year_id=y.id AND e.status='active'
 AND e.starts_on<=CURRENT_DATE AND (e.ends_on IS NULL OR e.ends_on>=CURRENT_DATE));
 RETURN result;
END $$;
REVOKE ALL ON FUNCTION card_bridge_local.student_schooling(uuid) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_local.student_schooling(uuid) TO schoolsafe_card_bridge;
COMMIT;
