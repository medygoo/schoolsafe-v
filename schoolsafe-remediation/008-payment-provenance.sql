BEGIN;
CREATE TABLE schoolsafe_remediation_payment_purpose (
 id bigserial PRIMARY KEY,
 payment_id integer UNIQUE REFERENCES billing_payments(id) ON DELETE SET NULL,
 fee_id integer REFERENCES billing_fees(id) ON DELETE SET NULL,
 recorded_by integer NOT NULL REFERENCES staff(staff_id),
 receipt_reference text NOT NULL CHECK(length(trim(receipt_reference)) BETWEEN 1 AND 100),
 recorded_at timestamptz NOT NULL DEFAULT now()
);
CREATE FUNCTION schoolsafe_remediation_payment_provenance() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item schoolsafe_remediation_cases%ROWTYPE; paid numeric;
BEGIN
 SELECT * INTO item FROM schoolsafe_remediation_cases WHERE id=NEW.case_id FOR UPDATE;
 IF item.billing_mode IS DISTINCT FROM 'paid' OR item.fee_id IS NULL THEN RAISE EXCEPTION 'REMEDIATION_FEE_REQUIRED'; END IF;
 SELECT p.amount INTO paid FROM billing_payments p
 JOIN schoolsafe_remediation_payment_purpose purpose ON purpose.payment_id=p.id AND purpose.fee_id=item.fee_id
 WHERE p.id=NEW.payment_id AND p.student_id=item.student_id AND p.school_id=item.school_id AND p.syear=item.syear
 AND p.amount>0 AND p.payment_date IS NOT NULL AND p.refunded_payment_id IS NULL
 AND NOT EXISTS(SELECT 1 FROM billing_payments ref WHERE ref.refunded_payment_id=p.id);
 IF paid IS NULL OR NEW.amount<>paid OR NEW.school_share<>round(paid*0.4,2) OR NEW.teacher_share<>paid-round(paid*0.4,2)
 THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_PROVENANCE_REQUIRED'; END IF;
 IF paid>item.tariff-COALESCE((SELECT sum(amount) FROM schoolsafe_remediation_payments WHERE case_id=item.id AND invalidated_at IS NULL),0)
 THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_EXCEEDS_BALANCE'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER schoolsafe_remediation_payment_origin BEFORE INSERT ON schoolsafe_remediation_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_provenance();
COMMIT;
