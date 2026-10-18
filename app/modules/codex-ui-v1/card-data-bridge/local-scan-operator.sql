\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
  IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF;
END $$;
CREATE OR REPLACE FUNCTION card_bridge_local.resolve_operator(p_profile uuid,p_school uuid,p_subject text)
RETURNS TABLE(profile_id uuid,user_id uuid,school_id uuid)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog AS $$
 SELECT p.id,p.user_id,p.school_id FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
 WHERE p.id=p_profile AND p.school_id=p_school AND p.is_active AND p.account_status='active'
 AND u.is_active AND u.auth_provider='rosario' AND u.external_subject=p_subject
$$;
REVOKE ALL ON FUNCTION card_bridge_local.resolve_operator(uuid,uuid,text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_local.resolve_operator(uuid,uuid,text) TO schoolsafe_card_bridge;
COMMIT;
