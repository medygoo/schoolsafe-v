BEGIN;
CREATE TABLE card_bridge.matricule_school (
    source_instance text NOT NULL CHECK (btrim(source_instance) <> ''),
    native_school_id bigint NOT NULL CHECK (native_school_id > 0),
    school_code text NOT NULL UNIQUE CHECK (school_code ~ '^SCH[0-9]{4}$'),
    last_number bigint NOT NULL DEFAULT 0 CHECK (last_number BETWEEN 0 AND 999999),
    PRIMARY KEY (source_instance, native_school_id)
);
CREATE TABLE card_bridge.student_matricule (
    source_instance text NOT NULL,
    native_student_id bigint NOT NULL CHECK (native_student_id > 0),
    origin_school_id bigint NOT NULL,
    matricule text NOT NULL UNIQUE CHECK (btrim(matricule) <> ''),
    preserved_existing boolean NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (source_instance, native_student_id),
    FOREIGN KEY (source_instance, origin_school_id)
        REFERENCES card_bridge.matricule_school (source_instance, native_school_id)
);

-- Server-only operation: caller must first authorize the native student and school.
CREATE FUNCTION card_bridge.ensure_student_matricule(
    p_source text, p_school bigint, p_student bigint, p_existing text DEFAULT NULL
) RETURNS text LANGUAGE plpgsql SET search_path = pg_catalog AS $$
DECLARE
    v_result text;
    v_code text;
    v_number bigint;
BEGIN
    IF p_source IS NULL OR btrim(p_source) = '' OR p_school IS NULL OR p_school <= 0
        OR p_student IS NULL OR p_student <= 0 THEN
        RAISE EXCEPTION 'Invalid native identity' USING ERRCODE = '22023';
    END IF;
    IF p_existing IS NOT NULL AND (p_existing = '' OR p_existing <> btrim(p_existing)) THEN
        RAISE EXCEPTION 'Existing matricule must be nonblank and canonical' USING ERRCODE = '22023';
    END IF;
    -- Serialize even simultaneous requests for the same child in different schools.
    PERFORM pg_advisory_xact_lock(hashtextextended(p_source || ':' || p_student::text, 0));
    SELECT matricule INTO v_result FROM card_bridge.student_matricule
        WHERE source_instance = p_source AND native_student_id = p_student;
    IF FOUND THEN
        IF p_existing IS NOT NULL AND p_existing <> v_result THEN
            RAISE EXCEPTION 'Existing matricule conflicts with permanent registry' USING ERRCODE = '23505';
        END IF;
        RETURN v_result;
    END IF;
    SELECT school_code INTO v_code FROM card_bridge.matricule_school
        WHERE source_instance = p_source AND native_school_id = p_school FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'School code requires explicit registration' USING ERRCODE = '22023';
    END IF;
    IF p_existing IS NOT NULL THEN
        v_result := p_existing;
    ELSE
        LOOP
            UPDATE card_bridge.matricule_school SET last_number = last_number + 1
                WHERE source_instance = p_source AND native_school_id = p_school
                AND last_number < 999999 RETURNING last_number INTO v_number;
            IF NOT FOUND THEN
                RAISE EXCEPTION 'Matricule sequence exhausted' USING ERRCODE = '22003';
            END IF;
            v_result := v_code || '-' || lpad(v_number::text, 6, '0');
            EXIT WHEN NOT EXISTS (SELECT 1 FROM card_bridge.student_matricule WHERE matricule = v_result);
        END LOOP;
    END IF;
    INSERT INTO card_bridge.student_matricule
        (source_instance, native_student_id, origin_school_id, matricule, preserved_existing)
        VALUES (p_source, p_student, p_school, v_result, p_existing IS NOT NULL);
    RETURN v_result;
END;
$$;
REVOKE ALL ON card_bridge.matricule_school, card_bridge.student_matricule FROM PUBLIC;
REVOKE ALL ON FUNCTION card_bridge.ensure_student_matricule(text, bigint, bigint, text) FROM PUBLIC;
COMMIT;
