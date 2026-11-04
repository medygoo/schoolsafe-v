-- SchoolSafe 058 : cahier de textes (04/11, lot 4 « Ma journée de classe », valide par le proprietaire).
-- Une note par classe et par jour : ce qui a ete fait, ce qu'il faut faire. Ecrite par l'enseignant de la
-- classe ; lue plus tard par les parents (page « Travail de mon enfant »). Additif et rejouable.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_class_diary (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    school_date date NOT NULL,
    staff_id integer NOT NULL REFERENCES staff(staff_id),
    done text NOT NULL DEFAULT '' CHECK (length(done) <= 4000),
    todo text NOT NULL DEFAULT '' CHECK (length(todo) <= 2000),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz,
    UNIQUE (course_period_id, school_date)
);
GRANT SELECT, INSERT, UPDATE ON schoolsafe_class_diary TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_class_diary_id_seq TO schoolsafe_app;
COMMIT;
