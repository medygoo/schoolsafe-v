-- SchoolSafe 064 : branches et maxima du bulletin RDC (04/11, etape B validee par le proprietaire).
-- Modele par niveau (domaine, branche, maxima de periode et d'examen), puis lien entre une classe et les
-- cours natifs crees pour ses branches (titulaire en francais, second enseignant en anglais). Additif.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_branch_models (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    grade_level_id integer NOT NULL,
    sort_order integer NOT NULL DEFAULT 0,
    domain text NOT NULL DEFAULT '' CHECK (length(domain) <= 80),
    title text NOT NULL CHECK (length(btrim(title)) BETWEEN 1 AND 80),
    short_name text NOT NULL CHECK (length(btrim(short_name)) BETWEEN 1 AND 20),
    max_period numeric(6,2) NOT NULL CHECK (max_period > 0 AND max_period <= 1000),
    max_exam numeric(6,2) CHECK (max_exam IS NULL OR (max_exam > 0 AND max_exam <= 2000)),
    active boolean NOT NULL DEFAULT true,
    updated_by integer REFERENCES staff(staff_id),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (school_id, grade_level_id, title)
);
CREATE INDEX IF NOT EXISTS schoolsafe_branch_models_level_idx ON schoolsafe_branch_models(school_id, grade_level_id, sort_order);

CREATE TABLE IF NOT EXISTS schoolsafe_class_branches (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    class_course_period_id integer NOT NULL REFERENCES course_periods(course_period_id),
    model_id bigint NOT NULL REFERENCES schoolsafe_branch_models(id),
    course_id integer NOT NULL REFERENCES courses(course_id),
    course_period_id integer NOT NULL UNIQUE REFERENCES course_periods(course_period_id),
    teacher_fr integer REFERENCES staff(staff_id),
    teacher_en integer REFERENCES staff(staff_id),
    created_by integer NOT NULL REFERENCES staff(staff_id),
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (class_course_period_id, model_id)
);
CREATE INDEX IF NOT EXISTS schoolsafe_class_branches_class_idx ON schoolsafe_class_branches(class_course_period_id);

GRANT SELECT, INSERT, UPDATE ON schoolsafe_branch_models TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_branch_models_id_seq TO schoolsafe_app;
GRANT SELECT, INSERT, UPDATE ON schoolsafe_class_branches TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_class_branches_id_seq TO schoolsafe_app;
COMMIT;
