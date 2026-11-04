BEGIN;
CREATE OR REPLACE FUNCTION public.schoolsafe_remediation_allocation_immutable()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
 IF ROW(NEW.id,NEW.case_id,NEW.amount,NEW.school_share,NEW.teacher_share,NEW.confirmed_by,NEW.confirmed_at,
        NEW.source_payment_id,NEW.supersedes_id,NEW.confirmation_version,NEW.source_fee_id,NEW.source_payment_date,NEW.receipt_reference)
 IS DISTINCT FROM ROW(OLD.id,OLD.case_id,OLD.amount,OLD.school_share,OLD.teacher_share,OLD.confirmed_by,OLD.confirmed_at,
        OLD.source_payment_id,OLD.supersedes_id,OLD.confirmation_version,OLD.source_fee_id,OLD.source_payment_date,OLD.receipt_reference)
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 IF OLD.invalidated_at IS NOT NULL AND NEW.invalidated_at IS DISTINCT FROM OLD.invalidated_at
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 IF NEW.payment_id IS DISTINCT FROM OLD.payment_id AND NOT (NEW.payment_id IS NULL AND NEW.invalidated_at IS NOT NULL)
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 RETURN NEW;
END $function$

;
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
ALTER TABLE schoolsafe_remediation_payments DROP COLUMN IF EXISTS teacher_id;
ALTER TABLE schoolsafe_remediation_cases DROP COLUMN IF EXISTS base_tariff, DROP COLUMN IF EXISTS reduction_percent, DROP COLUMN IF EXISTS reduction_reason, DROP COLUMN IF EXISTS parent_response, DROP COLUMN IF EXISTS parent_response_at, DROP COLUMN IF EXISTS parent_response_by, DROP COLUMN IF EXISTS parent_note;
COMMIT;
