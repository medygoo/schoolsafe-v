BEGIN;
DO $guard$
BEGIN
 IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'REMEDIATION_ISOLATED_DATABASE_REQUIRED'; END IF;
END $guard$;
SET LOCAL lock_timeout='5s';
SET LOCAL statement_timeout='30s';
-- An allocation is historical evidence, not a valid balance after its source changes.
CREATE OR REPLACE FUNCTION schoolsafe_remediation_payment_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record; pids integer[];
BEGIN
 IF TG_OP='UPDATE' AND ROW(OLD.amount,OLD.student_id,OLD.school_id,OLD.syear,OLD.refunded_payment_id,OLD.payment_date)
 IS NOT DISTINCT FROM ROW(NEW.amount,NEW.student_id,NEW.school_id,NEW.syear,NEW.refunded_payment_id,NEW.payment_date) THEN RETURN NULL; END IF;
 IF TG_OP='INSERT' THEN pids=ARRAY[NEW.refunded_payment_id];
 ELSIF TG_OP='DELETE' THEN pids=ARRAY[OLD.id,OLD.refunded_payment_id];
 ELSE pids=ARRAY[OLD.id,OLD.refunded_payment_id,NEW.refunded_payment_id]; END IF;
 -- Refund creation must serialize with confirmation of its original payment.
 PERFORM id FROM billing_payments WHERE id=ANY(pids) ORDER BY id FOR UPDATE;
 FOR item IN UPDATE schoolsafe_remediation_payments SET invalidated_at=now()
 WHERE payment_id=ANY(pids) AND invalidated_at IS NULL RETURNING case_id LOOP
  INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(item.case_id,'payment_review_required');
 END LOOP;
 IF TG_OP='DELETE' THEN RETURN OLD; END IF;
 RETURN NULL;
END $$;
DROP TRIGGER schoolsafe_remediation_payment_corrected ON billing_payments;
CREATE TRIGGER schoolsafe_remediation_payment_corrected
AFTER UPDATE OF amount,student_id,school_id,syear,refunded_payment_id,payment_date ON billing_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_changed();

CREATE FUNCTION schoolsafe_remediation_fee_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 IF TG_OP='UPDATE' AND ROW(OLD.amount,OLD.student_id,OLD.school_id,OLD.syear)
 IS NOT DISTINCT FROM ROW(NEW.amount,NEW.student_id,NEW.school_id,NEW.syear) THEN RETURN NULL; END IF;
 FOR item IN UPDATE schoolsafe_remediation_payments allocation SET invalidated_at=now()
 FROM schoolsafe_remediation_cases dossier
 WHERE allocation.case_id=dossier.id AND dossier.fee_id=OLD.id AND allocation.invalidated_at IS NULL
 RETURNING allocation.case_id LOOP
  INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(item.case_id,'payment_review_required');
 END LOOP;
 IF TG_OP='DELETE' THEN RETURN OLD; END IF;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_fee_corrected
AFTER UPDATE OF amount,student_id,school_id,syear ON billing_fees
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_fee_changed();
CREATE TRIGGER schoolsafe_remediation_fee_deleted BEFORE DELETE ON billing_fees
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_fee_changed();

CREATE FUNCTION schoolsafe_remediation_purpose_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record;
BEGIN
 IF TG_OP='UPDATE' AND ROW(OLD.payment_id,OLD.fee_id,OLD.receipt_reference)
 IS NOT DISTINCT FROM ROW(NEW.payment_id,NEW.fee_id,NEW.receipt_reference) THEN RETURN NULL; END IF;
 FOR item IN UPDATE schoolsafe_remediation_payments SET invalidated_at=now()
 WHERE payment_id=OLD.payment_id AND invalidated_at IS NULL RETURNING case_id LOOP
  INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(item.case_id,'payment_review_required');
 END LOOP;
 IF TG_OP='DELETE' THEN RETURN OLD; END IF;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_purpose_corrected
AFTER UPDATE OF payment_id,fee_id,receipt_reference ON schoolsafe_remediation_payment_purpose
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_purpose_changed();
CREATE TRIGGER schoolsafe_remediation_purpose_deleted BEFORE DELETE ON schoolsafe_remediation_payment_purpose
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_purpose_changed();

CREATE OR REPLACE FUNCTION schoolsafe_remediation_payment_provenance() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item schoolsafe_remediation_cases%ROWTYPE; paid numeric; native_fee integer;
BEGIN
 IF current_setting('transaction_isolation')<>'read committed'
 THEN RAISE EXCEPTION 'REMEDIATION_READ_COMMITTED_REQUIRED'; END IF;
 -- Native fee deletion locks fee before its FK updates the dossier: use the same order.
 SELECT fee_id INTO native_fee FROM schoolsafe_remediation_cases WHERE id=NEW.case_id;
 IF native_fee IS NULL THEN RAISE EXCEPTION 'REMEDIATION_FEE_REQUIRED'; END IF;
 PERFORM id FROM billing_fees WHERE id=native_fee FOR SHARE;
 IF NOT FOUND THEN RAISE EXCEPTION 'REMEDIATION_FEE_CONTEXT_REQUIRED'; END IF;
 SELECT * INTO item FROM schoolsafe_remediation_cases WHERE id=NEW.case_id FOR UPDATE;
 IF item.billing_mode IS DISTINCT FROM 'paid' OR item.fee_id IS NULL THEN RAISE EXCEPTION 'REMEDIATION_FEE_REQUIRED'; END IF;
 IF item.fee_id IS DISTINCT FROM native_fee THEN RAISE EXCEPTION 'REMEDIATION_FEE_CONTEXT_REQUIRED'; END IF;
 PERFORM id FROM billing_fees WHERE id=item.fee_id AND student_id=item.student_id
 AND school_id=item.school_id AND syear=item.syear AND amount=item.tariff AND amount>0 FOR SHARE;
 IF NOT FOUND THEN RAISE EXCEPTION 'REMEDIATION_FEE_CONTEXT_REQUIRED'; END IF;
 PERFORM id FROM billing_payments WHERE id=NEW.payment_id FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_PROVENANCE_REQUIRED'; END IF;
 -- Separate statement after the lock wait: see a refund committed while waiting.
 SELECT p.amount INTO paid FROM billing_payments p
 WHERE p.id=NEW.payment_id AND p.student_id=item.student_id AND p.school_id=item.school_id AND p.syear=item.syear
 AND p.amount>0 AND p.payment_date IS NOT NULL AND p.refunded_payment_id IS NULL
 AND NOT EXISTS(SELECT 1 FROM billing_payments ref WHERE ref.refunded_payment_id=p.id);
 -- Lock and recheck purpose AFTER locking the payment, before inserting any allocation.
 PERFORM id FROM schoolsafe_remediation_payment_purpose
 WHERE payment_id=NEW.payment_id AND fee_id=item.fee_id FOR SHARE;
 IF NOT FOUND THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_PROVENANCE_REQUIRED'; END IF;
 IF paid IS NULL OR NEW.amount<>paid OR NEW.school_share<>round(paid*0.4,2) OR NEW.teacher_share<>paid-round(paid*0.4,2)
 THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_PROVENANCE_REQUIRED'; END IF;
 IF paid>item.tariff-COALESCE((SELECT sum(amount) FROM schoolsafe_remediation_payments WHERE case_id=item.id AND invalidated_at IS NULL),0)
 THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_EXCEEDS_BALANCE'; END IF;
 RETURN NEW;
END $$;
COMMIT;
