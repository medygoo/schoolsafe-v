BEGIN;
DO $$ BEGIN
 IF current_database() <> 'rosario_addons_test' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF;
END $$;

CREATE SCHEMA schoolsafe_attendance;

CREATE TABLE schoolsafe_attendance.arrival_ledger (
    source_instance text NOT NULL CHECK (source_instance ~ '^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$'),
    source_event_id uuid NOT NULL,
    target_school_id uuid NOT NULL,
    target_student_id uuid NOT NULL,
    school_id integer NOT NULL CHECK (school_id > 0),
    student_id integer NOT NULL CHECK (student_id > 0),
    syear numeric NOT NULL CHECK (syear > 0 AND syear = trunc(syear)),
    enrollment_id integer NOT NULL CHECK (enrollment_id > 0),
    occurred_at timestamptz NOT NULL,
    school_timezone text NOT NULL,
    school_date date NOT NULL,
    status text NOT NULL DEFAULT 'arrival_evidence' CHECK (status = 'arrival_evidence'),
    projected_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source_instance, source_event_id),
    CHECK (school_date = (occurred_at AT TIME ZONE school_timezone)::date)
);

CREATE INDEX arrival_ledger_school_date_student
    ON schoolsafe_attendance.arrival_ledger (school_id, syear, school_date, student_id);

COMMIT;
