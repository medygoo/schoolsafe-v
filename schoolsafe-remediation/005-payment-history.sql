BEGIN;
ALTER TABLE schoolsafe_remediation_payments DROP CONSTRAINT schoolsafe_remediation_payments_payment_id_fkey;
ALTER TABLE schoolsafe_remediation_payments ALTER COLUMN payment_id DROP NOT NULL;
ALTER TABLE schoolsafe_remediation_payments ADD FOREIGN KEY(payment_id) REFERENCES billing_payments(id) ON DELETE SET NULL;
DROP TRIGGER schoolsafe_remediation_payment_corrected ON billing_payments;
CREATE OR REPLACE FUNCTION schoolsafe_remediation_payment_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record; pids integer[];
BEGIN
 IF TG_OP='INSERT' THEN pids=ARRAY[NEW.refunded_payment_id];
 ELSIF TG_OP='DELETE' THEN pids=ARRAY[OLD.id,OLD.refunded_payment_id];
 ELSE pids=ARRAY[OLD.id,OLD.refunded_payment_id,NEW.refunded_payment_id]; END IF;
 FOR item IN UPDATE schoolsafe_remediation_payments SET invalidated_at=now()
 WHERE payment_id=ANY(pids) AND invalidated_at IS NULL RETURNING case_id LOOP
  INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(item.case_id,'payment_review_required');
 END LOOP;
 IF TG_OP='DELETE' THEN RETURN OLD; END IF;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_payment_corrected AFTER UPDATE OF amount,student_id,school_id,syear,refunded_payment_id ON billing_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_changed();
CREATE TRIGGER schoolsafe_remediation_payment_deleted BEFORE DELETE ON billing_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_changed();
COMMIT;
