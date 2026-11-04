-- SchoolSafe 063 : notes visibles chaque jour par le parent (04/11, valide par le proprietaire).
-- Chaque note enregistree arrive chez le parent (tout de suite ou en resume du soir, au choix de l'ecole),
-- avec la correction : corrige type du devoir, photo de la copie corrigee, appreciation. Le parent peut
-- marquer « Vu ». Additif et rejouable ; aucune donnee existante n'est modifiee.
BEGIN;
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events ADD CONSTRAINT schoolsafe_notify_events_type_allowed CHECK (event_type IN (
  'security.entry','student.pickup','payment.receipt','school.announcement','remediation.update',
  'attendance.closure','absence.detected','case.updated','security.p1','financial.control','fee.reminder',
  'homework.published','grade.published'));

-- Corrige type d'un devoir (montre au parent apres la date de remise).
CREATE TABLE IF NOT EXISTS schoolsafe_assignment_keys (
    assignment_id integer PRIMARY KEY,
    answer text NOT NULL CHECK (length(answer) BETWEEN 1 AND 20000),
    staff_id integer NOT NULL REFERENCES staff(staff_id),
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- Photo (ou PDF) de la copie corrigee d'un eleve.
CREATE TABLE IF NOT EXISTS schoolsafe_grade_copies (
    id bigserial PRIMARY KEY,
    assignment_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    mime text NOT NULL CHECK (mime IN ('image/jpeg','image/png','application/pdf')),
    size_bytes integer NOT NULL CHECK (size_bytes BETWEEN 1 AND 10485760),
    sha256 text NOT NULL CHECK (sha256 ~ '^[a-f0-9]{64}$'),
    content bytea NOT NULL,
    created_by integer NOT NULL REFERENCES staff(staff_id),
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (assignment_id, student_id)
);

-- Notes deja annoncees aux parents (une seule annonce par note).
CREATE TABLE IF NOT EXISTS schoolsafe_grade_notices (
    assignment_id integer NOT NULL,
    student_id integer NOT NULL,
    dedup_key text NOT NULL,
    notified_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (assignment_id, student_id)
);

-- « Vu par le parent » : remplace la signature du cahier.
CREATE TABLE IF NOT EXISTS schoolsafe_grade_seen (
    assignment_id integer NOT NULL,
    student_id integer NOT NULL,
    seen_by integer NOT NULL REFERENCES staff(staff_id),
    seen_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (assignment_id, student_id, seen_by)
);

GRANT SELECT, INSERT, UPDATE ON schoolsafe_assignment_keys TO schoolsafe_app;
GRANT SELECT, INSERT, UPDATE ON schoolsafe_grade_copies TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_grade_copies_id_seq TO schoolsafe_app;
GRANT SELECT, INSERT ON schoolsafe_grade_notices TO schoolsafe_app;
GRANT SELECT, INSERT ON schoolsafe_grade_seen TO schoolsafe_app;
COMMIT;
