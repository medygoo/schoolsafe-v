BEGIN;
CREATE ROLE schoolsafe_arrival_native_writer LOGIN PASSWORD '__WORKER_PASSWORD__'
 NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 2;
GRANT CONNECT ON DATABASE rosario_addons_test TO schoolsafe_arrival_native_writer;
GRANT USAGE ON SCHEMA schoolsafe_attendance TO schoolsafe_arrival_native_writer;
CREATE VIEW schoolsafe_attendance.transfer_enrollments WITH (security_barrier=true) AS
 SELECT e.school_id,e.student_id,e.syear,e.start_date,e.end_date
 FROM public.student_enrollment e JOIN public.students s USING(student_id) WHERE e.school_id=1;
REVOKE ALL ON schoolsafe_attendance.transfer_enrollments FROM PUBLIC;
GRANT SELECT ON schoolsafe_attendance.transfer_enrollments TO schoolsafe_arrival_native_writer;
CREATE FUNCTION schoolsafe_attendance.lock_enrollment_at(p_school integer,p_student integer,p_year numeric,p_day date)
 RETURNS TABLE(id integer) LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $fn$
BEGIN
 IF current_database()<>'rosario_addons_test' OR p_school<>1 THEN
  RAISE EXCEPTION 'ISOLATED_SCHOOL_REQUIRED' USING ERRCODE='42501';
 END IF;
 RETURN QUERY SELECT e.id FROM public.student_enrollment e JOIN public.students s ON s.student_id=e.student_id
 WHERE e.school_id=p_school AND e.student_id=p_student AND e.syear=p_year
 AND e.start_date<=p_day AND (e.end_date IS NULL OR e.end_date>=p_day) FOR SHARE OF e,s;
END $fn$;
REVOKE ALL ON FUNCTION schoolsafe_attendance.lock_enrollment_at(integer,integer,numeric,date) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION schoolsafe_attendance.lock_enrollment_at(integer,integer,numeric,date) TO schoolsafe_arrival_native_writer;
ALTER TABLE schoolsafe_attendance.arrival_ledger ENABLE ROW LEVEL SECURITY;
CREATE POLICY arrival_worker_read ON schoolsafe_attendance.arrival_ledger FOR SELECT TO schoolsafe_arrival_native_writer
 USING (school_id=1 AND source_instance='codex-addons-local' AND target_school_id='8f1c3850-6568-4a26-8312-ed582c95891b'::uuid);
CREATE POLICY arrival_worker_insert ON schoolsafe_attendance.arrival_ledger FOR INSERT TO schoolsafe_arrival_native_writer
 WITH CHECK (school_id=1 AND source_instance='codex-addons-local' AND target_school_id='8f1c3850-6568-4a26-8312-ed582c95891b'::uuid);
GRANT SELECT ON schoolsafe_attendance.arrival_ledger TO schoolsafe_arrival_native_writer;
GRANT INSERT (source_instance,source_event_id,target_school_id,target_student_id,school_id,student_id,syear,enrollment_id,
 occurred_at,school_timezone,school_date,status) ON schoolsafe_attendance.arrival_ledger TO schoolsafe_arrival_native_writer;
COMMIT;
