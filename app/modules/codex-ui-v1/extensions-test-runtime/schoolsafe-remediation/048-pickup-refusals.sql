-- 048 — Refus de sortie tracés (spec v1.1 § 5). Additif et idempotent.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_pickup_refusals (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    location_id integer NULL,
    guardian_id integer NULL REFERENCES schoolsafe_pickup_guardians(id),
    reason varchar(32) NOT NULL,
    note varchar(300) NOT NULL DEFAULT '',
    staff_id integer NULL REFERENCES staff(staff_id),
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_pickup_refusals_reason_allowed
        CHECK (reason IN ('NOT_AUTHORIZED', 'UNKNOWN_PERSON', 'NOT_IN_LIST', 'OTHER'))
);
CREATE INDEX IF NOT EXISTS schoolsafe_pickup_refusals_student_idx
    ON schoolsafe_pickup_refusals (school_id, student_id, created_at DESC);
DO $$ DECLARE r text; BEGIN
    FOR r IN SELECT DISTINCT grantee FROM information_schema.role_table_grants
              WHERE table_name = 'schoolsafe_pickup_guardians' AND grantee NOT IN ('postgres', 'PUBLIC') LOOP
        EXECUTE format('GRANT SELECT, INSERT ON schoolsafe_pickup_refusals TO %I', r);
        EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE schoolsafe_pickup_refusals_id_seq TO %I', r);
    END LOOP;
END $$;
COMMIT;
