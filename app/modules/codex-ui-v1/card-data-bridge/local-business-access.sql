\set ON_ERROR_STOP on
BEGIN;
DO $$
BEGIN
 IF current_database() <> 'schoolsafe_cards_isolated' THEN
  RAISE EXCEPTION 'LOCAL_ISOLATED_DATABASE_REQUIRED';
 END IF;
 IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='schoolsafe_card_bridge') THEN
  RAISE EXCEPTION 'EXISTING_BRIDGE_ROLE_REQUIRES_REVIEW';
 END IF;
 CREATE ROLE schoolsafe_card_bridge LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE
  NOINHERIT NOREPLICATION NOBYPASSRLS;
END $$;
CREATE SCHEMA card_bridge_local;
REVOKE ALL ON SCHEMA card_bridge_local FROM PUBLIC;
CREATE FUNCTION card_bridge_local.resolve_identity(p_profile uuid,p_school uuid,p_student uuid,p_subject text)
RETURNS TABLE(profile_id uuid,user_id uuid,school_id uuid,student_id uuid)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog AS $$
 SELECT p.id,p.user_id,p.school_id,s.id
 FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
 JOIN app.students s ON s.school_id=p.school_id
 WHERE p.id=p_profile AND p.school_id=p_school AND s.id=p_student
 AND p.is_active AND p.account_status='active' AND u.is_active
 AND u.auth_provider='rosario' AND u.external_subject=p_subject
$$;
REVOKE ALL ON FUNCTION card_bridge_local.resolve_identity(uuid,uuid,uuid,text) FROM PUBLIC;
GRANT CONNECT ON DATABASE schoolsafe_cards_isolated TO schoolsafe_card_bridge;
GRANT USAGE ON SCHEMA card_bridge_local,api,iam TO schoolsafe_card_bridge;
GRANT EXECUTE ON FUNCTION card_bridge_local.resolve_identity(uuid,uuid,uuid,text),
 api.set_request_context(uuid,uuid,uuid,uuid),
 iam.require_access(text,uuid,uuid,uuid,uuid,uuid,jsonb) TO schoolsafe_card_bridge;
COMMIT;
