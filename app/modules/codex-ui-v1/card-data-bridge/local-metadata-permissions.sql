\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE school uuid; actor uuid; actor_user uuid; role uuid; permission record; grant_id uuid;
BEGIN
 IF current_database() <> 'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'LOCAL_ISOLATED_DATABASE_REQUIRED'; END IF;
 SELECT id INTO STRICT school FROM app.schools WHERE code='SCH0023';
 SELECT p.id,p.user_id INTO STRICT actor,actor_user FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
  WHERE p.school_id=school AND u.auth_provider='rosario' AND u.external_subject='codex-addons-local:1';
 PERFORM api.set_request_context(actor_user,actor,school,gen_random_uuid());
 SELECT id INTO STRICT role FROM iam.roles WHERE school_id=school AND code='rosario_cards_local_admin';
 INSERT INTO iam.permissions(code,label,default_scope_code) VALUES
  ('school.class.read','Read classes for card preparation','school'),
  ('school.guardian.read','Read guardian contacts for card preparation','school') ON CONFLICT(code) DO NOTHING;
 FOR permission IN SELECT id FROM iam.permissions WHERE code IN ('school.class.read','school.guardian.read') LOOP
  INSERT INTO iam.role_permission_grants(school_id,role_id,permission_id,effect,reason,granted_by)
   VALUES(school,role,permission.id,'allow','Owner approved isolated local Card integration: metadata reads only',actor)
   ON CONFLICT(school_id,role_id,permission_id) DO NOTHING;
  SELECT id INTO STRICT grant_id FROM iam.role_permission_grants WHERE school_id=school AND role_id=role AND permission_id=permission.id;
  INSERT INTO iam.grant_scopes(school_id,grant_id,scope_code,assigned_by) VALUES(school,grant_id,'school',actor) ON CONFLICT DO NOTHING;
 END LOOP;
END $$;
COMMIT;
