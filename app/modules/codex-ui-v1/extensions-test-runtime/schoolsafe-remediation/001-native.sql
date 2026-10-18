BEGIN;
CREATE TABLE schoolsafe_remediation_types (
 assignment_type_id integer PRIMARY KEY REFERENCES gradebook_assignment_types(assignment_type_id),
 kind text NOT NULL CHECK(kind IN ('homework','quiz','exam')),
 configured_by integer NOT NULL REFERENCES staff(staff_id),
 configured_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE schoolsafe_remediation_cases (
 id bigserial PRIMARY KEY,
 student_id integer NOT NULL REFERENCES students(student_id),
 course_id integer NOT NULL REFERENCES courses(course_id),
 school_id integer NOT NULL,
 syear numeric(4,0) NOT NULL,
 status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','refused','passed','repeat','closed')),
 evidence integer[] NOT NULL CHECK(cardinality(evidence)=3),
 evidence_valid boolean NOT NULL DEFAULT true,
 created_at timestamptz NOT NULL DEFAULT now(),
 decided_by integer REFERENCES staff(staff_id),
 decided_at timestamptz,
 decision_note text,
 UNIQUE(student_id,course_id,school_id,syear)
);
CREATE TABLE schoolsafe_remediation_sessions (
 id bigserial PRIMARY KEY,
 case_id bigint NOT NULL REFERENCES schoolsafe_remediation_cases(id),
 teacher_id integer NOT NULL REFERENCES staff(staff_id),
 starts_at timestamptz NOT NULL,
 duration_minutes integer NOT NULL CHECK(duration_minutes BETWEEN 1 AND 480),
 objective text NOT NULL CHECK(length(trim(objective))>0),
 status text NOT NULL DEFAULT 'scheduled' CHECK(status IN ('scheduled','held','postponed','cancelled')),
 attendance text CHECK(attendance IN ('present','absent','excused')),
 report text,
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE schoolsafe_remediation_events (
 id bigserial PRIMARY KEY,
 case_id bigint NOT NULL REFERENCES schoolsafe_remediation_cases(id),
 kind text NOT NULL,
 actor_id integer REFERENCES staff(staff_id),
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE FUNCTION schoolsafe_remediation_refresh(p_student integer,p_course integer) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE v_school integer; v_year numeric(4,0); v_ids integer[]; v_types text[]; v_evidence integer[]; v_id bigint;
BEGIN
 SELECT school_id,syear INTO v_school,v_year FROM courses WHERE course_id=p_course;
 IF NOT FOUND THEN RETURN; END IF;
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
 AND ga.points>0 AND gg.points>=0 AND gg.points<ga.points*0.5
 AND (ga.course_id=cp.course_id OR ga.course_period_id=cp.course_period_id)
 ) x;
 IF cardinality(v_ids)>=3 AND (SELECT count(DISTINCT k) FROM unnest(v_types) k)>=2 THEN
  SELECT ARRAY[v_ids[1],v_ids[i],v_ids[j]] INTO v_evidence
  FROM generate_subscripts(v_ids,1) i CROSS JOIN generate_subscripts(v_ids,1) j
  WHERE v_types[i]<>v_types[1] AND j<>1 AND j<>i ORDER BY i,j LIMIT 1;
  INSERT INTO schoolsafe_remediation_cases(student_id,course_id,school_id,syear,evidence)
  VALUES(p_student,p_course,v_school,v_year,v_evidence)
  ON CONFLICT(student_id,course_id,school_id,syear) DO NOTHING RETURNING id INTO v_id;
  IF v_id IS NOT NULL THEN
   INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(v_id,'detected');
  ELSE
   UPDATE schoolsafe_remediation_cases SET evidence=v_evidence,evidence_valid=true
   WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year AND status='pending';
  END IF;
 ELSE
  UPDATE schoolsafe_remediation_cases SET evidence_valid=false
  WHERE student_id=p_student AND course_id=p_course AND school_id=v_school AND syear=v_year AND status='pending';
 END IF;
END $$;
CREATE FUNCTION schoolsafe_remediation_grade_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v_course integer;
BEGIN
 IF TG_OP<>'INSERT' THEN
  SELECT course_id INTO v_course FROM course_periods WHERE course_period_id=OLD.course_period_id;
  PERFORM schoolsafe_remediation_refresh(OLD.student_id,v_course);
 END IF;
 IF TG_OP<>'DELETE' THEN
  SELECT course_id INTO v_course FROM course_periods WHERE course_period_id=NEW.course_period_id;
  PERFORM schoolsafe_remediation_refresh(NEW.student_id,v_course);
 END IF;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_grade_saved AFTER INSERT OR UPDATE OR DELETE ON gradebook_grades
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_grade_changed();
COMMIT;
