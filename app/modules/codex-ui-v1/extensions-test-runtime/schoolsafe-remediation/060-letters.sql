-- SchoolSafe 060 : registre des courriers de l'ecole (04/11, lot 7 « Convocations et courriers », valide).
-- Chaque courrier recoit un numero (ex. CONV-2026-0001), garde son texte, son destinataire et son envoi.
-- Base des archives de l'ecole (lot 8). Additif et rejouable.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_letters (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    reference text NOT NULL CHECK (reference ~ '^[A-Z]{3,5}-[0-9]{4}-[0-9]{4}$'),
    kind text NOT NULL CHECK (kind IN ('convocation','reunion','discipline','frais','attestation','libre')),
    student_id integer REFERENCES students(student_id),
    course_period_id integer REFERENCES course_periods(course_period_id),
    subject text NOT NULL CHECK (length(btrim(subject)) BETWEEN 1 AND 200),
    body text NOT NULL CHECK (length(body) BETWEEN 1 AND 10000),
    meeting_at timestamp,
    place text CHECK (place IS NULL OR length(place) <= 200),
    layout text,
    staff_id integer NOT NULL REFERENCES staff(staff_id),
    created_at timestamptz NOT NULL DEFAULT now(),
    sent_at timestamptz,
    sent_count integer NOT NULL DEFAULT 0,
    UNIQUE (school_id, reference),
    CHECK (student_id IS NOT NULL OR course_period_id IS NOT NULL OR kind IN ('libre','reunion'))
);
CREATE INDEX IF NOT EXISTS schoolsafe_letters_school_idx ON schoolsafe_letters(school_id, syear, created_at DESC);
CREATE INDEX IF NOT EXISTS schoolsafe_letters_student_idx ON schoolsafe_letters(student_id);
GRANT SELECT, INSERT, UPDATE ON schoolsafe_letters TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_letters_id_seq TO schoolsafe_app;
COMMIT;
