BEGIN;
CREATE FUNCTION schoolsafe_remediation_assignment_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 FOR item IN SELECT DISTINCT gg.student_id,cp.course_id FROM gradebook_grades gg JOIN course_periods cp ON cp.course_period_id=gg.course_period_id WHERE gg.assignment_id=NEW.assignment_id LOOP
  PERFORM schoolsafe_remediation_refresh(item.student_id,item.course_id);
 END LOOP;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_assignment_saved AFTER UPDATE OF points,assignment_type_id,course_id,course_period_id ON gradebook_assignments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_assignment_changed();
COMMIT;
