BEGIN;
DO $$
DECLARE school uuid; actor uuid; actor_user uuid; role uuid; permission record; grant_id uuid;
BEGIN
 IF current_database() <> 'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'Isolated database required'; END IF;
 SELECT id INTO STRICT school FROM app.schools WHERE code='SCH0023';
 SELECT p.id,p.user_id INTO STRICT actor,actor_user FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
 WHERE p.school_id=school AND u.auth_provider='rosario' AND u.external_subject='codex-addons-local:1';
 PERFORM api.set_request_context(actor_user,actor,school,gen_random_uuid());
 INSERT INTO iam.permissions(code,label,default_scope_code) VALUES
 ('school.guardian.manage','Manage school pickup persons','school'),
 ('roles.manage','Manage school task delegation','school') ON CONFLICT(code) DO NOTHING;
 INSERT INTO iam.roles(school_id,code,label,created_by) VALUES
 (school,'rosario_local_family_scan_manager','Local approved family and scan manager',actor)
 ON CONFLICT(school_id,code) DO NOTHING;
 SELECT id INTO STRICT role FROM iam.roles WHERE school_id=school AND code='rosario_local_family_scan_manager';
 IF EXISTS(SELECT 1 FROM iam.profile_roles WHERE role_id=role AND profile_id<>actor) THEN
 RAISE EXCEPTION 'Dedicated role contains an unexpected profile'; END IF;
 INSERT INTO iam.profile_roles(school_id,profile_id,role_id,assigned_by) VALUES(school,actor,role,actor) ON CONFLICT DO NOTHING;
 FOR permission IN SELECT id FROM iam.permissions WHERE code IN ('school.guardian.manage','roles.manage') LOOP
 INSERT INTO iam.role_permission_grants(school_id,role_id,permission_id,effect,reason,granted_by)
 VALUES(school,role,permission.id,'allow','Owner approved isolated admin family and scan delegation',actor)
 ON CONFLICT(school_id,role_id,permission_id) DO NOTHING;
 SELECT id INTO STRICT grant_id FROM iam.role_permission_grants WHERE school_id=school AND role_id=role AND permission_id=permission.id;
 INSERT INTO iam.grant_scopes(school_id,grant_id,scope_code,assigned_by) VALUES(school,grant_id,'school',actor) ON CONFLICT DO NOTHING;
 END LOOP;
 PERFORM audit.write_event('bridge.local.admin_permissions','profile',actor,jsonb_build_object('permissions',ARRAY['school.guardian.manage','roles.manage'],'owner_approved',true));
END;
$$;
COMMIT;
