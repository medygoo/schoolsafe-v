BEGIN;
DO $guard$
BEGIN
 IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'REMEDIATION_ISOLATED_DATABASE_REQUIRED'; END IF;
END $guard$;
SET LOCAL lock_timeout='5s';
SET LOCAL statement_timeout='30s';
-- Native category moves and mapping imports must refresh persisted evidence too.
CREATE FUNCTION schoolsafe_remediation_category_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 FOR item IN SELECT DISTINCT gg.student_id,cp.course_id FROM gradebook_grades gg
 JOIN course_periods cp ON cp.course_period_id=gg.course_period_id
 JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id
 WHERE ga.assignment_type_id=NEW.assignment_type_id LOOP
  PERFORM schoolsafe_remediation_refresh(item.student_id,item.course_id);
 END LOOP;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_category_saved AFTER UPDATE OF course_id ON gradebook_assignment_types
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_category_changed();

CREATE FUNCTION schoolsafe_remediation_mapping_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record; type_id integer;
BEGIN
 IF TG_OP='DELETE' THEN type_id=OLD.assignment_type_id; ELSE type_id=NEW.assignment_type_id; END IF;
 FOR item IN SELECT DISTINCT gg.student_id,cp.course_id FROM gradebook_grades gg
 JOIN course_periods cp ON cp.course_period_id=gg.course_period_id
 JOIN gradebook_assignments ga ON ga.assignment_id=gg.assignment_id
 WHERE ga.assignment_type_id=type_id LOOP
  PERFORM schoolsafe_remediation_refresh(item.student_id,item.course_id);
 END LOOP;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_mapping_saved AFTER INSERT OR UPDATE OF kind OR DELETE ON schoolsafe_remediation_types
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_mapping_changed();
COMMIT;
