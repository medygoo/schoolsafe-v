BEGIN;
DO $$
DECLARE school uuid; actor uuid; principal uuid; child uuid; person record; account uuid;
BEGIN
    IF current_database() <> 'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'Isolated database required'; END IF;
    INSERT INTO app.schools(code,name) VALUES ('SCH0023','Default School') ON CONFLICT(code) DO NOTHING;
    SELECT id INTO school FROM app.schools WHERE code='SCH0023' AND name='Default School';
    IF school IS NULL THEN RAISE EXCEPTION 'School identity conflict'; END IF;
    -- Reviewed native users: no passwords, auth identities, sessions or pickup grants.
    FOR person IN SELECT * FROM (VALUES
        (1,'Admin','Administrator'),(2,'Teach','Teacher'),(3,'Parent','Parent'),(4,'loms','medy')
    ) AS native(id,first_name,last_name) LOOP
        INSERT INTO iam.users(auth_provider,external_subject)
            VALUES ('rosario','codex-addons-local:' || person.id)
            ON CONFLICT(auth_provider,external_subject) WHERE external_subject IS NOT NULL DO NOTHING;
        SELECT id INTO account FROM iam.users WHERE auth_provider='rosario' AND external_subject='codex-addons-local:' || person.id;
        IF NOT EXISTS (SELECT 1 FROM iam.profiles WHERE user_id=account AND school_id=school) THEN
            INSERT INTO iam.profiles(user_id,school_id,display_name,first_name,last_name)
                VALUES(account,school,person.first_name || ' ' || person.last_name,person.first_name,person.last_name);
        END IF;
    END LOOP;
    SELECT p.id INTO actor FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id WHERE u.external_subject='codex-addons-local:1' AND p.school_id=school;
    SELECT p.id INTO principal FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id WHERE u.external_subject='codex-addons-local:3' AND p.school_id=school;
    INSERT INTO app.students(school_id,matricule,first_name,last_name,date_of_birth,created_by)
        VALUES(school,'SCH0023-000001','Student','Student','2015-12-04',actor),
              (school,'SCH0023-000002','TEST CODEX','MATRICULE',NULL,actor)
        ON CONFLICT(school_id,matricule) DO NOTHING;
    SELECT id INTO child FROM app.students WHERE school_id=school AND matricule='SCH0023-000001';
    IF NOT EXISTS (SELECT 1 FROM app.student_guardians WHERE student_id=child AND profile_id=principal) THEN
        INSERT INTO app.student_guardians(school_id,student_id,profile_id,guardian_type,is_primary,full_name,is_authorized_pickup,created_by)
            VALUES(school,child,principal,'tuteur',true,'Parent Parent',false,actor);
    END IF;
END;
$$;
COMMIT;
SELECT json_build_object('school',s.id,'profiles',
    (SELECT json_agg(json_build_object('native',split_part(u.external_subject,':',2),'profile',p.id))
     FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id WHERE p.school_id=s.id AND u.auth_provider='rosario'),
    'students',(SELECT json_agg(json_build_object('native',CASE matricule WHEN 'SCH0023-000001' THEN '1' ELSE '2' END,'student',id))
    FROM app.students WHERE school_id=s.id)) FROM app.schools s WHERE s.code='SCH0023';
