BEGIN;
CREATE FUNCTION schoolsafe_remediation_assignment_deleted() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 FOR item IN
  SELECT gg.student_id,cp.course_id FROM gradebook_grades gg
  JOIN course_periods cp ON cp.course_period_id=gg.course_period_id
  WHERE gg.assignment_id=OLD.assignment_id
  UNION
  SELECT r.student_id,r.course_id FROM schoolsafe_remediation_cases r
  WHERE r.status='pending' AND OLD.assignment_id=ANY(r.evidence)
 LOOP
  PERFORM schoolsafe_remediation_refresh(item.student_id,item.course_id);
 END LOOP;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_assignment_removed AFTER DELETE ON gradebook_assignments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_assignment_deleted();
COMMIT;
