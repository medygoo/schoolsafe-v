-- Rattrapage, lot 2 (04/11, valide par le proprietaire) : la direction informee n'est plus le profil au
-- titre « Directeur pedagogique » (absent en production) mais tout compte administrateur de l'ecole qui a
-- la tache attribuable « Valider ou refuser le rattrapage » (SchoolSafe/RemediationDecide.php), par son
-- profil ou, sans profil, par ses droits individuels.
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
 SELECT DISTINCT NEW.id,'direction',s.staff_id FROM staff s
 JOIN schoolsafe_remediation_cases r ON r.id=NEW.case_id
 WHERE s.profile='admin' AND s.syear=r.syear
 AND (s.schools IS NULL OR s.schools='' OR s.schools LIKE '%,'||r.school_id||',%')
 AND (EXISTS (SELECT 1 FROM profile_exceptions pe WHERE pe.profile_id=s.profile_id
        AND pe.modname='SchoolSafe/RemediationDecide.php' AND pe.can_use='Y')
   OR (s.profile_id IS NULL AND EXISTS (SELECT 1 FROM staff_exceptions x WHERE x.user_id=s.staff_id
        AND x.modname='SchoolSafe/RemediationDecide.php' AND x.can_use='Y')))
 ON CONFLICT DO NOTHING;
 -- Keep delivery blocked until an authoritative principal-parent association exists.
 INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id)
 VALUES(NEW.id,'principal_parent',NULL) ON CONFLICT DO NOTHING;
 IF NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_notifications WHERE event_id=NEW.id AND audience='direction') THEN
  INSERT INTO schoolsafe_remediation_notifications(event_id,audience,recipient_id) VALUES(NEW.id,'direction',NULL);
 END IF;
 RETURN NULL;
END $function$;
COMMIT;
