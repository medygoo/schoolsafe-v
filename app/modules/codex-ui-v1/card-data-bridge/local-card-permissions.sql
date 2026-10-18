BEGIN;
DO $$
DECLARE school uuid; actor uuid; actor_user uuid; role uuid; permission record; grant_id uuid;
BEGIN
    IF current_database() <> 'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'Isolated database required'; END IF;
    SELECT id INTO STRICT school FROM app.schools WHERE code='SCH0023';
    SELECT p.id,p.user_id INTO STRICT actor,actor_user FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id
        WHERE p.school_id=school AND u.auth_provider='rosario' AND u.external_subject='codex-addons-local:1';
    PERFORM api.set_request_context(actor_user,actor,school,gen_random_uuid());
    INSERT INTO iam.scopes(code,label,description) VALUES
        ('school','School','Active school data'),('assigned_classes','Assigned classes','Classes assigned to the current profile')
        ON CONFLICT(code) DO NOTHING;
    INSERT INTO iam.permissions(code,label,default_scope_code) VALUES
        ('cards.request.print','Request card printing','school'),
        ('school.student.read','Read student records','assigned_classes') ON CONFLICT(code) DO NOTHING;
    INSERT INTO iam.roles(school_id,code,label,created_by)
        VALUES(school,'rosario_cards_local_admin','Rosario local card operator',actor) ON CONFLICT(school_id,code) DO NOTHING;
    SELECT id INTO STRICT role FROM iam.roles WHERE school_id=school AND code='rosario_cards_local_admin';
    INSERT INTO iam.profile_roles(school_id,profile_id,role_id,assigned_by)
        VALUES(school,actor,role,actor) ON CONFLICT DO NOTHING;
    FOR permission IN SELECT id FROM iam.permissions WHERE code IN ('cards.request.print','school.student.read') LOOP
        INSERT INTO iam.role_permission_grants(school_id,role_id,permission_id,effect,reason,granted_by)
            VALUES(school,role,permission.id,'allow','Owner approved isolated local Card/scan integration',actor)
            ON CONFLICT(school_id,role_id,permission_id) DO NOTHING;
        SELECT id INTO STRICT grant_id FROM iam.role_permission_grants
            WHERE school_id=school AND role_id=role AND permission_id=permission.id;
        INSERT INTO iam.grant_scopes(school_id,grant_id,scope_code,assigned_by)
            VALUES(school,grant_id,'school',actor) ON CONFLICT DO NOTHING;
    END LOOP;
    INSERT INTO app.scan_control_grants(school_id,profile_id,capability,granted_by,reason)
        SELECT school,actor,capability,actor,'Owner approved isolated local Card/scan integration'
        FROM unnest(ARRAY['CARD_CREATE','CARD_PRINT','SCAN_ENTRY','SCAN_PICKUP','SCAN_FEES','SCAN_VERIFY']) capability
        ON CONFLICT DO NOTHING;
END;
$$;
COMMIT;
