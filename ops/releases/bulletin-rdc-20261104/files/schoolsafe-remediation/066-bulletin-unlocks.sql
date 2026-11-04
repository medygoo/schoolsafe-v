-- SchoolSafe 066 : ouverture exceptionnelle d'un bulletin malgre les frais (04/11, etape D validee).
-- Le bulletin valide s'ouvre au parent quand les frais dus sont payes ; la direction peut l'ouvrir a titre
-- exceptionnel (indigent, arrangement), avec la raison. Chaque ouverture est gardee ; rien n'est efface.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_bulletin_unlocks (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    class_course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    student_id integer NOT NULL REFERENCES students(student_id),
    marking_period_id integer NOT NULL,
    reason text NOT NULL CHECK (length(btrim(reason)) BETWEEN 3 AND 300),
    unlocked_by integer NOT NULL REFERENCES staff(staff_id),
    unlocked_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (class_course_period_id, student_id, marking_period_id)
);
GRANT SELECT, INSERT ON schoolsafe_bulletin_unlocks TO schoolsafe_app;
GRANT USAGE, SELECT ON SEQUENCE schoolsafe_bulletin_unlocks_id_seq TO schoolsafe_app;
COMMIT;
