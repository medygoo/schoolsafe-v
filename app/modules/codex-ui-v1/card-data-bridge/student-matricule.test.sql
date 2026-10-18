BEGIN;
INSERT INTO card_bridge.matricule_school VALUES ('TEST-CODEX-MATRICULE', 23, 'SCH0023', 0);
DO $$
DECLARE v text;
BEGIN
    v := card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,1);
    IF v <> 'SCH0023-000001' THEN RAISE EXCEPTION 'Wrong format'; END IF;
    IF card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,1) <> v THEN RAISE EXCEPTION 'Not idempotent'; END IF;
    IF card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',999,1) <> v THEN RAISE EXCEPTION 'Changed on transfer'; END IF;
    IF card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,2,'LEGACY-42') <> 'LEGACY-42' THEN RAISE EXCEPTION 'Legacy changed'; END IF;
    PERFORM card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,3,'SCH0023-000002');
    IF card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,4) <> 'SCH0023-000003' THEN RAISE EXCEPTION 'Collision not skipped'; END IF;
    BEGIN
        PERFORM card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,1,'OTHER');
        RAISE EXCEPTION 'Conflict accepted';
    EXCEPTION WHEN unique_violation THEN NULL; END;
    BEGIN
        PERFORM card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',999,5);
        RAISE EXCEPTION 'Unknown school accepted';
    EXCEPTION WHEN invalid_parameter_value THEN NULL; END;
    BEGIN
        PERFORM card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,0);
        RAISE EXCEPTION 'Invalid student accepted';
    EXCEPTION WHEN invalid_parameter_value THEN NULL; END;
    UPDATE card_bridge.matricule_school SET last_number=999999 WHERE source_instance='TEST-CODEX-MATRICULE';
    BEGIN
        PERFORM card_bridge.ensure_student_matricule('TEST-CODEX-MATRICULE',23,6);
        RAISE EXCEPTION 'Overflow accepted';
    EXCEPTION WHEN numeric_value_out_of_range THEN NULL; END;
    RAISE NOTICE 'PASS: format, idempotency, transfer, legacy, collision, conflict, school, identity, exhaustion';
END;
$$;
ROLLBACK;
