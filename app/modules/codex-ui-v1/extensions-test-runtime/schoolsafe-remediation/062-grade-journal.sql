-- SchoolSafe 062 : journal des modifications de notes (04/11, lot 9 valide par le proprietaire).
-- Chaque ajout, changement ou retrait d'une note de devoir ou de bulletin est garde : qui (compte connecte,
-- pose par l'application dans schoolsafe.actor), quand, ancienne et nouvelle valeur. Le journal ne se
-- modifie pas et ne s'efface pas depuis l'application (insertion seule). Les declencheurs existants ne
-- changent pas ; une erreur du journal n'empeche jamais d'enregistrer une note.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_grade_journal (
    id bigserial PRIMARY KEY,
    source text NOT NULL CHECK (source IN ('devoir','bulletin')),
    action text NOT NULL CHECK (action IN ('ajout','modification','retrait')),
    student_id integer NOT NULL,
    course_period_id integer,
    assignment_id integer,
    marking_period_id integer,
    old_value text,
    new_value text,
    old_comment text,
    new_comment text,
    actor_staff_id integer,
    changed_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS schoolsafe_grade_journal_cp_idx ON schoolsafe_grade_journal(course_period_id, changed_at DESC);
CREATE INDEX IF NOT EXISTS schoolsafe_grade_journal_student_idx ON schoolsafe_grade_journal(student_id, changed_at DESC);

CREATE OR REPLACE FUNCTION schoolsafe_grade_journal_actor() RETURNS integer
LANGUAGE plpgsql STABLE AS $$
DECLARE raw text;
BEGIN
    raw := current_setting('schoolsafe.actor', true);
    IF raw ~ '^[0-9]{1,9}$' THEN RETURN raw::integer; END IF;
    RETURN NULL;
END $$;

CREATE OR REPLACE FUNCTION schoolsafe_grade_journal_homework() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    BEGIN
        IF TG_OP = 'INSERT' THEN
            INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, assignment_id, new_value, new_comment, actor_staff_id)
            VALUES ('devoir', 'ajout', NEW.student_id, NEW.course_period_id, NEW.assignment_id, NEW.points::text, NEW.comment, schoolsafe_grade_journal_actor());
        ELSIF TG_OP = 'UPDATE' THEN
            IF NEW.points IS DISTINCT FROM OLD.points OR NEW.comment IS DISTINCT FROM OLD.comment THEN
                INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, assignment_id, old_value, new_value, old_comment, new_comment, actor_staff_id)
                VALUES ('devoir', 'modification', NEW.student_id, NEW.course_period_id, NEW.assignment_id, OLD.points::text, NEW.points::text, OLD.comment, NEW.comment, schoolsafe_grade_journal_actor());
            END IF;
        ELSE
            INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, assignment_id, old_value, old_comment, actor_staff_id)
            VALUES ('devoir', 'retrait', OLD.student_id, OLD.course_period_id, OLD.assignment_id, OLD.points::text, OLD.comment, schoolsafe_grade_journal_actor());
        END IF;
    EXCEPTION WHEN OTHERS THEN
        RAISE WARNING 'schoolsafe grade journal: %', SQLERRM;
    END;
    RETURN NULL;
END $$;

CREATE OR REPLACE FUNCTION schoolsafe_grade_journal_report_card() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE old_v text; new_v text;
BEGIN
    BEGIN
        IF TG_OP <> 'INSERT' THEN old_v := concat_ws(' ', OLD.grade_letter, OLD.grade_percent::text || '%'); END IF;
        IF TG_OP <> 'DELETE' THEN new_v := concat_ws(' ', NEW.grade_letter, NEW.grade_percent::text || '%'); END IF;
        IF TG_OP = 'INSERT' THEN
            INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, marking_period_id, new_value, new_comment, actor_staff_id)
            VALUES ('bulletin', 'ajout', NEW.student_id, NEW.course_period_id, NEW.marking_period_id, new_v, NEW.comment, schoolsafe_grade_journal_actor());
        ELSIF TG_OP = 'UPDATE' THEN
            IF NEW.grade_percent IS DISTINCT FROM OLD.grade_percent OR NEW.grade_letter IS DISTINCT FROM OLD.grade_letter
               OR NEW.report_card_grade_id IS DISTINCT FROM OLD.report_card_grade_id OR NEW.comment IS DISTINCT FROM OLD.comment THEN
                INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, marking_period_id, old_value, new_value, old_comment, new_comment, actor_staff_id)
                VALUES ('bulletin', 'modification', NEW.student_id, NEW.course_period_id, NEW.marking_period_id, old_v, new_v, OLD.comment, NEW.comment, schoolsafe_grade_journal_actor());
            END IF;
        ELSE
            INSERT INTO schoolsafe_grade_journal(source, action, student_id, course_period_id, marking_period_id, old_value, old_comment, actor_staff_id)
            VALUES ('bulletin', 'retrait', OLD.student_id, OLD.course_period_id, OLD.marking_period_id, old_v, OLD.comment, schoolsafe_grade_journal_actor());
        END IF;
    EXCEPTION WHEN OTHERS THEN
        RAISE WARNING 'schoolsafe grade journal: %', SQLERRM;
    END;
    RETURN NULL;
END $$;

DROP TRIGGER IF EXISTS schoolsafe_grade_journal_homework ON gradebook_grades;
CREATE TRIGGER schoolsafe_grade_journal_homework AFTER INSERT OR UPDATE OR DELETE ON gradebook_grades
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_grade_journal_homework();
DROP TRIGGER IF EXISTS schoolsafe_grade_journal_report_card ON student_report_card_grades;
CREATE TRIGGER schoolsafe_grade_journal_report_card AFTER INSERT OR UPDATE OR DELETE ON student_report_card_grades
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_grade_journal_report_card();

GRANT SELECT, INSERT ON schoolsafe_grade_journal TO schoolsafe_app;
GRANT USAGE, SELECT ON SEQUENCE schoolsafe_grade_journal_id_seq TO schoolsafe_app;
COMMIT;
