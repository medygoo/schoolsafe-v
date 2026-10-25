BEGIN;
ALTER TABLE schoolsafe_remediation_cases DROP CONSTRAINT schoolsafe_remediation_cases_fee_id_fkey;
ALTER TABLE schoolsafe_remediation_cases ADD FOREIGN KEY(fee_id) REFERENCES billing_fees(id) ON DELETE SET NULL;
ALTER TABLE schoolsafe_remediation_reassessments DROP CONSTRAINT schoolsafe_remediation_reassessments_assignment_id_fkey;
ALTER TABLE schoolsafe_remediation_reassessments ALTER COLUMN assignment_id DROP NOT NULL;
ALTER TABLE schoolsafe_remediation_reassessments ADD FOREIGN KEY(assignment_id) REFERENCES gradebook_assignments(assignment_id) ON DELETE SET NULL;
ALTER TABLE schoolsafe_remediation_types DROP CONSTRAINT schoolsafe_remediation_types_assignment_type_id_fkey;
ALTER TABLE schoolsafe_remediation_types ADD FOREIGN KEY(assignment_type_id) REFERENCES gradebook_assignment_types(assignment_type_id) ON DELETE CASCADE;
CREATE FUNCTION schoolsafe_remediation_type_deleted() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 DELETE FROM schoolsafe_remediation_types WHERE assignment_type_id=OLD.assignment_type_id;
 FOR item IN SELECT DISTINCT gg.student_id,cp.course_id FROM gradebook_grades gg JOIN course_periods cp ON cp.course_period_id=gg.course_period_id WHERE cp.course_id=OLD.course_id LOOP
  PERFORM schoolsafe_remediation_refresh(item.student_id,item.course_id);
 END LOOP;
 RETURN OLD;
END $$;
CREATE TRIGGER schoolsafe_remediation_category_removed BEFORE DELETE ON gradebook_assignment_types
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_type_deleted();
COMMIT;
