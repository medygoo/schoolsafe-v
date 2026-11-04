-- Rattrapage, famille et paiement juste (04/11, valide par le proprietaire).
-- 1. Tarif reduit motive ; 2. reponse du parent au plan ; 3. part enseignant attribuee a l'enseignant
-- qui donne les seances ; 4. l'enseignant qui donne les seances est informe comme celui de la matiere.
BEGIN;
ALTER TABLE schoolsafe_remediation_cases
    ADD COLUMN IF NOT EXISTS base_tariff numeric(14,2) CHECK (base_tariff IS NULL OR base_tariff >= 0),
    ADD COLUMN IF NOT EXISTS reduction_percent numeric(5,2) CHECK (reduction_percent IS NULL OR reduction_percent BETWEEN 0 AND 100),
    ADD COLUMN IF NOT EXISTS reduction_reason text CHECK (reduction_reason IS NULL OR length(reduction_reason) <= 500),
    ADD COLUMN IF NOT EXISTS parent_response text CHECK (parent_response IS NULL OR parent_response IN ('accepted','meeting','declined')),
    ADD COLUMN IF NOT EXISTS parent_response_at timestamptz,
    ADD COLUMN IF NOT EXISTS parent_response_by integer REFERENCES staff(staff_id),
    ADD COLUMN IF NOT EXISTS parent_note text CHECK (parent_note IS NULL OR length(parent_note) <= 1000);
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN IF NOT EXISTS teacher_id integer REFERENCES staff(staff_id);

-- La repartition confirmee reste immuable, enseignant beneficiaire compris.
CREATE OR REPLACE FUNCTION public.schoolsafe_remediation_allocation_immutable()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
 IF ROW(NEW.id,NEW.case_id,NEW.amount,NEW.school_share,NEW.teacher_share,NEW.confirmed_by,NEW.confirmed_at,
        NEW.source_payment_id,NEW.supersedes_id,NEW.confirmation_version,NEW.source_fee_id,NEW.source_payment_date,NEW.receipt_reference,NEW.teacher_id)
 IS DISTINCT FROM ROW(OLD.id,OLD.case_id,OLD.amount,OLD.school_share,OLD.teacher_share,OLD.confirmed_by,OLD.confirmed_at,
        OLD.source_payment_id,OLD.supersedes_id,OLD.confirmation_version,OLD.source_fee_id,OLD.source_payment_date,OLD.receipt_reference,OLD.teacher_id)
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 IF OLD.invalidated_at IS NOT NULL AND NEW.invalidated_at IS DISTINCT FROM OLD.invalidated_at
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 IF NEW.payment_id IS DISTINCT FROM OLD.payment_id AND NOT (NEW.payment_id IS NULL AND NEW.invalidated_at IS NOT NULL)
 THEN RAISE EXCEPTION 'REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE'; END IF;
 RETURN NEW;
END $function$;

-- Notifications internes : l'enseignant qui donne les seances est informe en plus de celui de la matiere.
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
END $function$;
COMMIT;
