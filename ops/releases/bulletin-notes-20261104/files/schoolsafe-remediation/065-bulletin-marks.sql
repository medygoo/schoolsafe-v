-- SchoolSafe 065 : notes de bulletin (04/11, etape C validee par le proprietaire).
-- Chaque enseignant donne, pour chaque branche, la note de periode et la note d'examen de ses eleves dans sa
-- langue (francais : titulaire ; anglais : second enseignant). Le titulaire fait la synthese (application,
-- conduite, decision) et la propose ; la direction valide, ce qui verrouille le trimestre. Chaque note de
-- bulletin ajoutee, changee ou retiree va dans le journal des notes (062). Additif.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_bulletin_marks (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    class_course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    branch_course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    student_id integer NOT NULL REFERENCES students(student_id),
    marking_period_id integer NOT NULL,
    kind text NOT NULL CHECK (kind IN ('periode','examen')),
    lang text NOT NULL CHECK (lang IN ('fr','en')),
    points numeric(7,2) CHECK (points IS NULL OR (points >= 0 AND points <= 2000)),
    entered_by integer NOT NULL REFERENCES staff(staff_id),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (branch_course_period_id, student_id, marking_period_id, kind, lang)
);
CREATE INDEX IF NOT EXISTS schoolsafe_bulletin_marks_class_idx ON schoolsafe_bulletin_marks(class_course_period_id, marking_period_id);

CREATE TABLE IF NOT EXISTS schoolsafe_bulletin_status (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    class_course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    student_id integer NOT NULL REFERENCES students(student_id),
    marking_period_id integer NOT NULL,
    application text CHECK (application IS NULL OR length(application) <= 40),
    conduite text CHECK (conduite IS NULL OR length(conduite) <= 40),
    decision text CHECK (decision IS NULL OR decision IN ('passe','double','echoue','repechage','oriente')),
    decision_note text CHECK (decision_note IS NULL OR length(decision_note) <= 300),
    titulaire_note text CHECK (titulaire_note IS NULL OR length(titulaire_note) <= 600),
    status text NOT NULL DEFAULT 'brouillon' CHECK (status IN ('brouillon','propose','valide')),
    updated_by integer REFERENCES staff(staff_id),
    updated_at timestamptz NOT NULL DEFAULT now(),
    proposed_by integer REFERENCES staff(staff_id),
    proposed_at timestamptz,
    validated_by integer REFERENCES staff(staff_id),
    validated_at timestamptz,
    UNIQUE (class_course_period_id, student_id, marking_period_id)
);

CREATE OR REPLACE FUNCTION schoolsafe_bulletin_marks_journal() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE label text;
BEGIN
    BEGIN
        IF TG_OP = 'DELETE' THEN label := OLD.kind || ' ' || OLD.lang; ELSE label := NEW.kind || ' ' || NEW.lang; END IF;
        IF TG_OP = 'INSERT' THEN
            IF NEW.points IS NOT NULL THEN
                INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, marking_period_id, new_value, actor_staff_id)
                VALUES ('bulletin', 'ajout', NEW.student_id, NEW.branch_course_period_id, NEW.marking_period_id, label || ' : ' || NEW.points::text, NEW.entered_by);
            END IF;
        ELSIF TG_OP = 'UPDATE' THEN
            IF NEW.points IS DISTINCT FROM OLD.points THEN
                INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, marking_period_id, old_value, new_value, actor_staff_id)
                VALUES ('bulletin', CASE WHEN NEW.points IS NULL THEN 'retrait' ELSE 'modification' END, NEW.student_id, NEW.branch_course_period_id, NEW.marking_period_id,
                        label || ' : ' || COALESCE(OLD.points::text, '—'), label || ' : ' || COALESCE(NEW.points::text, '—'), NEW.entered_by);
            END IF;
        END IF;
    EXCEPTION WHEN OTHERS THEN
        RAISE WARNING 'schoolsafe bulletin journal: %', SQLERRM;
    END;
    RETURN NULL;
END $$;
DROP TRIGGER IF EXISTS schoolsafe_bulletin_marks_journal ON schoolsafe_bulletin_marks;
CREATE TRIGGER schoolsafe_bulletin_marks_journal AFTER INSERT OR UPDATE ON schoolsafe_bulletin_marks
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_bulletin_marks_journal();

GRANT SELECT, INSERT, UPDATE ON schoolsafe_bulletin_marks TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_bulletin_marks_id_seq TO schoolsafe_app;
GRANT SELECT, INSERT, UPDATE ON schoolsafe_bulletin_status TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_bulletin_status_id_seq TO schoolsafe_app;
COMMIT;
