-- SchoolSafe 059 : banque d'exercices de l'ecole (04/11, lot 5 « Devoirs et travaux », valide).
-- Les enseignants d'une meme ecole y rangent et reprennent des consignes ; aucune note ni donnee d'eleve.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_exercise_bank (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    subject text NOT NULL DEFAULT '' CHECK (length(subject) <= 120),
    level text NOT NULL DEFAULT '' CHECK (length(level) <= 120),
    title text NOT NULL CHECK (length(btrim(title)) BETWEEN 1 AND 200),
    content text NOT NULL CHECK (length(content) BETWEEN 1 AND 20000),
    staff_id integer NOT NULL REFERENCES staff(staff_id),
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS schoolsafe_exercise_bank_school_idx ON schoolsafe_exercise_bank(school_id, subject, level);
GRANT SELECT, INSERT, UPDATE ON schoolsafe_exercise_bank TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_exercise_bank_id_seq TO schoolsafe_app;
COMMIT;
