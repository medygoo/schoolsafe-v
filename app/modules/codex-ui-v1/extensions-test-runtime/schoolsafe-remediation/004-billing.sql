BEGIN;
ALTER TABLE schoolsafe_remediation_cases ADD COLUMN billing_mode text CHECK(billing_mode IN ('free','paid'));
ALTER TABLE schoolsafe_remediation_cases ADD COLUMN tariff numeric(14,2) CHECK(tariff>=0);
ALTER TABLE schoolsafe_remediation_cases ADD COLUMN fee_id integer UNIQUE REFERENCES billing_fees(id);
CREATE TABLE schoolsafe_remediation_payments (
 id bigserial PRIMARY KEY,
 case_id bigint NOT NULL REFERENCES schoolsafe_remediation_cases(id),
 payment_id integer NOT NULL UNIQUE REFERENCES billing_payments(id),
 amount numeric(14,2) NOT NULL CHECK(amount>0),
 school_share numeric(14,2) NOT NULL CHECK(school_share>=0),
 teacher_share numeric(14,2) NOT NULL CHECK(teacher_share>=0),
 confirmed_by integer NOT NULL REFERENCES staff(staff_id),
 confirmed_at timestamptz NOT NULL DEFAULT now(),
 invalidated_at timestamptz,
 CHECK(school_share+teacher_share=amount)
);
CREATE FUNCTION schoolsafe_remediation_payment_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE item record; pid integer;
BEGIN
 IF TG_OP='INSERT' THEN pid=NEW.refunded_payment_id;
 ELSE pid=OLD.id; END IF;
 FOR item IN UPDATE schoolsafe_remediation_payments SET invalidated_at=now()
 WHERE payment_id=pid AND invalidated_at IS NULL RETURNING case_id LOOP
  INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(item.case_id,'payment_review_required');
 END LOOP;
 RETURN NULL;
END $$;
CREATE TRIGGER schoolsafe_remediation_payment_corrected AFTER UPDATE OF amount,student_id,school_id,syear OR DELETE ON billing_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_changed();
CREATE TRIGGER schoolsafe_remediation_refund_saved AFTER INSERT ON billing_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_changed();
COMMIT;
