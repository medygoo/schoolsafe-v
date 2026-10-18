BEGIN;
DO $$
DECLARE school uuid; profile record; child uuid;
BEGIN
    SELECT id INTO STRICT school FROM app.schools WHERE code='SCH0023';
    SELECT id INTO STRICT child FROM app.students WHERE school_id=school AND matricule='SCH0023-000001';
    FOR profile IN SELECT p.id,p.user_id,u.external_subject FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id WHERE p.school_id=school LOOP
        PERFORM api.set_request_context(profile.user_id,profile.id,school,gen_random_uuid());
        IF NOT iam.context_is_valid() THEN RAISE EXCEPTION 'Invalid real target context'; END IF;
        IF profile.external_subject='codex-addons-local:1' THEN
            IF NOT iam.can_access('cards.request.print',NULL,child,NULL) THEN RAISE EXCEPTION 'Admin card permission missing'; END IF;
            PERFORM api.scan_control_require('SCAN_ENTRY');
            PERFORM api.scan_control_require('SCAN_PICKUP');
            PERFORM api.scan_control_require('SCAN_FEES');
            PERFORM api.scan_control_require('SCAN_VERIFY');
        ELSE
            IF iam.can_access('cards.request.print',NULL,child,NULL) THEN RAISE EXCEPTION 'Unexpected privilege expansion'; END IF;
            BEGIN
                PERFORM api.scan_control_require('SCAN_PICKUP');
                RAISE EXCEPTION 'Unauthorized scan accepted';
            EXCEPTION WHEN insufficient_privilege THEN NULL; END;
        END IF;
    END LOOP;
    IF EXISTS (SELECT 1 FROM app.student_guardians WHERE school_id=school AND is_authorized_pickup) THEN
        RAISE EXCEPTION 'Unexpected pickup authorization';
    END IF;
    IF EXISTS (SELECT 1 FROM app.student_cards WHERE school_id=school) THEN RAISE EXCEPTION 'Unexpected official card'; END IF;
    RAISE NOTICE 'PASS: real target contexts, admin minimum grants, four scan capabilities, other profiles denied, no pickup grant or fabricated card';
END;
$$;
ROLLBACK;
