-- Rattrapage : reglages par ecole et alerte « vigilance » (04/11, valide par le proprietaire).
-- Sans ligne de reglage, l'ecole garde exactement la regle d'origine (3 notes sous 50 %, 2 sortes, 40/60).
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_remediation_settings (
    school_id integer PRIMARY KEY,
    threshold_percent numeric(5,2) NOT NULL DEFAULT 50 CHECK (threshold_percent > 0 AND threshold_percent <= 100),
    failures integer NOT NULL DEFAULT 3 CHECK (failures BETWEEN 2 AND 6),
    min_kinds integer NOT NULL DEFAULT 2 CHECK (min_kinds BETWEEN 1 AND 3),
    vigilance boolean NOT NULL DEFAULT true,
    school_share_percent numeric(5,2) NOT NULL DEFAULT 40 CHECK (school_share_percent BETWEEN 0 AND 100),
    pass_percent numeric(5,2) NOT NULL DEFAULT 50 CHECK (pass_percent > 0 AND pass_percent <= 100),
    pass_gain_points numeric(5,2) CHECK (pass_gain_points IS NULL OR (pass_gain_points > 0 AND pass_gain_points <= 100)),
    updated_by integer REFERENCES staff(staff_id),
    updated_at timestamptz NOT NULL DEFAULT now()
);
-- Une alerte par eleve, matiere et annee ; « cleared » quand les notes remontent.
CREATE TABLE IF NOT EXISTS schoolsafe_remediation_watch (
    id bigserial PRIMARY KEY,
    student_id integer NOT NULL REFERENCES students(student_id),
    course_id integer NOT NULL REFERENCES courses(course_id),
    school_id integer NOT NULL,
    syear numeric(4,0) NOT NULL,
    failures integer NOT NULL CHECK (failures > 0),
    cleared boolean NOT NULL DEFAULT false,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (student_id, course_id, school_id, syear)
);
-- Le role de l'application ecrit ces tables depuis les declencheurs (fonction non SECURITY DEFINER).
GRANT SELECT, INSERT, UPDATE, DELETE ON schoolsafe_remediation_settings, schoolsafe_remediation_watch TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_remediation_watch_id_seq TO schoolsafe_app;
-- Le nombre de notes justificatives suit le reglage de l'ecole (toutes les notes sous le seuil, 50 au plus).
ALTER TABLE schoolsafe_remediation_cases DROP CONSTRAINT IF EXISTS schoolsafe_remediation_cases_evidence_check;
ALTER TABLE schoolsafe_remediation_cases ADD CONSTRAINT schoolsafe_remediation_cases_evidence_check CHECK (cardinality(evidence) BETWEEN 1 AND 50);

CREATE OR REPLACE FUNCTION public.schoolsafe_remediation_refresh(p_student integer, p_course integer)
 RETURNS void
 LANGUAGE plpgsql
AS $function$
DECLARE v_school integer; v_year numeric(4,0); v_ids integer[]; v_types text[]; v_id bigint;
 v_threshold numeric := 50; v_failures integer := 3; v_kinds integer := 2; v_vigilance boolean := true; v_count integer;
BEGIN
 SELECT school_id,syear INTO v_school,v_year FROM courses WHERE course_id=p_course;
 IF NOT FOUND THEN RETURN; END IF;
 SELECT threshold_percent,failures,min_kinds,vigilance INTO v_threshold,v_failures,v_kinds,v_vigilance
 FROM schoolsafe_remediation_settings WHERE school_id=v_school;
 IF NOT FOUND THEN v_threshold:=50; v_failures:=3; v_kinds:=2; v_vigilance:=true; END IF;
 PERFORM pg_advisory_xact_lock(p_student,p_course);
 SELECT array_agg(x.assignment_id ORDER BY x.assignment_id),array_agg(x.kind ORDER BY x.assignment_id)
 INTO v_ids,v_types FROM (
 SELECT DISTINCT ga.assignment_id,mt.kind
 FROM gradebook_grades gg
 JOIN course_periods cp ON cp.course_period_id=gg.course_period_id
 JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id
 JOIN gradebook_assignment_types gt ON gt.assignment_type_id=ga.assignment_type_id AND gt.course_id=cp.course_id
 JOIN schoolsafe_remediation_types mt ON mt.assignment_type_id=gt.assignment_type_id
 WHERE gg.student_id=p_student AND cp.course_id=p_course AND cp.school_id=v_school AND cp.syear=v_year
 AND ga.points>0 AND gg.points>=0 AND gg.points<ga.points*v_threshold/100
 AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id)
 ) x;
 v_count := COALESCE(cardinality(v_ids),0);
 IF v_count>=v_failures AND (SELECT count(DISTINCT k) FROM unnest(v_types) k)>=v_kinds THEN
  INSERT INTO schoolsafe_remediation_cases(student_id,course_id,school_id,syear,evidence)
  VALUES(p_student,p_course,v_school,v_year,v_ids[1:50])
  ON CONFLICT(student_id,course_id,school_id,syear) DO NOTHING RETURNING id INTO v_id;
  IF v_id IS NOT NULL THEN
   INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(v_id,'detected');
  ELSE
   UPDATE schoolsafe_remediation_cases SET evidence=v_ids[1:50],evidence_valid=true
   WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year AND status='pending';
  END IF;
 ELSE
  UPDATE schoolsafe_remediation_cases SET evidence_valid=false
  WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year AND status='pending';
 END IF;
 -- Vigilance : au moins 2 notes sous le seuil, avant tout dossier ; une seule alerte par annee.
 IF v_vigilance AND v_count>=2 AND NOT EXISTS (SELECT 1 FROM schoolsafe_remediation_cases
    WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year) THEN
  INSERT INTO schoolsafe_remediation_watch(student_id,course_id,school_id,syear,failures)
  VALUES(p_student,p_course,v_school,v_year,v_count)
  ON CONFLICT(student_id,course_id,school_id,syear) DO UPDATE SET failures=EXCLUDED.failures,cleared=false;
 ELSIF v_count<2 THEN
  UPDATE schoolsafe_remediation_watch SET cleared=true,failures=GREATEST(v_count,1)
  WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year AND NOT cleared;
 END IF;
END $function$;
COMMIT;
