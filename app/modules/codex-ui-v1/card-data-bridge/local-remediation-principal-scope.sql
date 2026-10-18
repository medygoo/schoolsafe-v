BEGIN;
DO $$ BEGIN
 IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF;
END $$;
CREATE FUNCTION card_bridge_notifications.principal_scope(p_students uuid[]) RETURNS uuid[]
LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog AS $$
 SELECT coalesce(array_agg(s.id),'{}'::uuid[]) FROM app.students s
 WHERE iam.context_is_valid() AND s.school_id=iam.current_school_id()
 AND s.id=ANY(p_students)
 AND (SELECT count(*) FROM app.student_guardians g WHERE g.student_id=s.id
      AND g.school_id=s.school_id AND g.is_primary AND g.is_active)=1
 AND EXISTS(SELECT 1 FROM app.student_guardians g WHERE g.student_id=s.id
      AND g.school_id=s.school_id AND g.is_primary AND g.is_active
      AND g.profile_id=iam.current_profile_id());
$$;
REVOKE ALL ON FUNCTION card_bridge_notifications.principal_scope(uuid[]) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_notifications.principal_scope(uuid[]) TO schoolsafe_card_bridge;
COMMIT;
