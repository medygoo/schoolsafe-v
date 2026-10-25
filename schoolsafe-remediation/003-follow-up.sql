BEGIN;
ALTER TABLE schoolsafe_remediation_sessions ADD COLUMN progress text;
ALTER TABLE schoolsafe_remediation_sessions ADD COLUMN updated_by integer REFERENCES staff(staff_id);
ALTER TABLE schoolsafe_remediation_sessions ADD COLUMN updated_at timestamptz;
CREATE TABLE schoolsafe_remediation_reassessments (
 id bigserial PRIMARY KEY,
 case_id bigint NOT NULL REFERENCES schoolsafe_remediation_cases(id),
 assignment_id integer NOT NULL REFERENCES gradebook_assignments(assignment_id),
 points numeric(6,2) NOT NULL CHECK(points>=0),
 maximum integer NOT NULL CHECK(maximum>0),
 outcome text NOT NULL CHECK(outcome IN ('passed','repeat','closed')),
 note text NOT NULL CHECK(length(trim(note))>0),
 decided_by integer NOT NULL REFERENCES staff(staff_id),
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE schoolsafe_remediation_notifications (
 id bigserial PRIMARY KEY,
 event_id bigint NOT NULL REFERENCES schoolsafe_remediation_events(id),
 audience text NOT NULL CHECK(audience IN ('teacher','direction','principal_parent')),
 recipient_id integer REFERENCES staff(staff_id),
 created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE NULLS NOT DISTINCT(event_id,audience,recipient_id)
);
CREATE FUNCTION schoolsafe_remediation_notify() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 SELECT DISTINCT NEW.id,'teacher',cp.teacher_id FROM schoolsafe_remediation_cases r
 JOIN course_periods cp ON cp.course_id=r.course_id AND cp.school_id=r.school_id AND cp.syear=r.syear
 WHERE r.id=NEW.case_id ON CONFLICT DO NOTHING;
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 SELECT DISTINCT NEW.id,'direction',s.staff_id FROM staff s JOIN user_profiles p ON p.id=s.profile_id
 JOIN profile_exceptions pe ON pe.profile_id=p.id AND pe.modname='Grades/StudentGrades.php' AND pe.can_use='Y'
 JOIN schoolsafe_remediation_cases r ON r.id=NEW.case_id
 WHERE p.title='Directeur pédagogique' AND s.syear=r.syear
 AND (s.schools IS NULL OR s.schools='' OR s.schools LIKE '%,'||r.school_id||',%')
 ON CONFLICT DO NOTHING;
 -- Keep delivery blocked until an authoritative principal-parent association exists.
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 VALUES(NEW.id,'principal_parent',NULL) ON CONFLICT DO NOTHING;
 IF NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_notifications WHERE event_id=NEW.id AND audience='direction') THEN
  INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id) VALUES(NEW.id,'direction',NULL);
 END IF;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_event_notification AFTER INSERT ON schoolsafe_remediation_events
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_notify();
COMMIT;
