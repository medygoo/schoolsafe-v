\set ON_ERROR_STOP on
BEGIN;
INSERT INTO card_bridge.school_map VALUES
    ('TEST CODEX A', 1, '10000000-0000-0000-0000-000000000001', now()),
    ('TEST CODEX A', 2, '10000000-0000-0000-0000-000000000002', now());
INSERT INTO card_bridge.entity_map
    (source_instance, native_school_id, entity_type, native_entity_id, target_entity_id)
VALUES
    ('TEST CODEX A', 1, 'student', 1, '20000000-0000-0000-0000-000000000001'),
    ('TEST CODEX A', 2, 'student', 1, '20000000-0000-0000-0000-000000000002');

DO $$
DECLARE mapped uuid;
BEGIN
    SELECT target_entity_id INTO mapped FROM card_bridge.entity_map
    WHERE source_instance = 'TEST CODEX A' AND native_school_id = 1
      AND entity_type = 'student' AND native_entity_id = 1;
    IF mapped IS DISTINCT FROM '20000000-0000-0000-0000-000000000001'::uuid THEN
        RAISE EXCEPTION 'Scoped lookup failed';
    END IF;
    IF EXISTS (SELECT FROM card_bridge.entity_map WHERE source_instance = 'UNKNOWN') THEN
        RAISE EXCEPTION 'Unknown instance resolved';
    END IF;
    BEGIN
        INSERT INTO card_bridge.entity_map VALUES
          ('TEST CODEX A', 1, 'student', 1, gen_random_uuid(), now());
        RAISE EXCEPTION 'Duplicate source accepted';
    EXCEPTION WHEN unique_violation THEN NULL;
    END;
    BEGIN
        INSERT INTO card_bridge.entity_map VALUES
          ('TEST CODEX A', 2, 'student', 2, mapped, now());
        RAISE EXCEPTION 'Cross-school target reuse accepted';
    EXCEPTION WHEN unique_violation THEN NULL;
    END;
    BEGIN
        INSERT INTO card_bridge.entity_map VALUES
          ('TEST CODEX A', 999, 'student', 2, gen_random_uuid(), now());
        RAISE EXCEPTION 'Unknown school accepted';
    EXCEPTION WHEN foreign_key_violation THEN NULL;
    END;
    BEGIN
        INSERT INTO card_bridge.entity_map VALUES
          ('TEST CODEX A', 1, 'credential', 2, gen_random_uuid(), now());
        RAISE EXCEPTION 'Credential mapping accepted';
    EXCEPTION WHEN check_violation THEN NULL;
    END;
END $$;
ROLLBACK;
DO $$ BEGIN
    IF EXISTS (SELECT FROM card_bridge.entity_map) OR EXISTS (SELECT FROM card_bridge.school_map) THEN
        RAISE EXCEPTION 'Test data remained';
    END IF;
END $$;
SELECT 'PASS: scoped identity constraints; test data rolled back' AS result;
