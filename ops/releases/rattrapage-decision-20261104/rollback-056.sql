BEGIN;
CREATE OR REPLACE FUNCTION public.schoolsafe_remediation_notify()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 SELECT DISTINCT NEW.id,'teacher',cp.teacher_id FROM schoolsafe_remediation_cases r
 JOIN course_periods cp ON cp.course_id=r.course_id AND cp.school_id=r.school_id AND cp.syear=r.syear
 WHERE r.id=NEW.case_id ON CONFLICT DO NOTHING;
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 SELECT DISTINCT NEW.id,'teacher',se.teacher_id FROM schoolsafe_remediation_sessions se
 WHERE se.case_id=NEW.case_id AND NOT EXISTS (SELECT 1 FROM schoolsafe_remediation_notifications n
   WHERE n.event_id=NEW.id AND n.audience='teacher' AND n.recipient_id=se.teacher_id) ON CONFLICT DO NOTHING;
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
END $function$

;
COMMIT;
