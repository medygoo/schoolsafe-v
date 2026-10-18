BEGIN;
DO $guard$
BEGIN
 IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'REMEDIATION_ISOLATED_DATABASE_REQUIRED'; END IF;
END $guard$;
SET LOCAL lock_timeout='5s';
SET LOCAL statement_timeout='30s';

ALTER TABLE schoolsafe_remediation_payments ADD COLUMN source_payment_id integer;
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN supersedes_id bigint REFERENCES schoolsafe_remediation_payments(id);
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN confirmation_version integer NOT NULL DEFAULT 1 CHECK(confirmation_version>0);
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN source_fee_id integer;
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN source_payment_date date;
ALTER TABLE schoolsafe_remediation_payments ADD COLUMN receipt_reference text;
-- Only the known native identifier is backfilled. No historical date/receipt is invented.
UPDATE schoolsafe_remediation_payments SET source_payment_id=payment_id WHERE payment_id IS NOT NULL;
ALTER TABLE schoolsafe_remediation_payments DROP CONSTRAINT schoolsafe_remediation_payments_payment_id_key;
CREATE UNIQUE INDEX schoolsafe_remediation_payment_active_unique ON schoolsafe_remediation_payments(payment_id) WHERE invalidated_at IS NULL;
CREATE UNIQUE INDEX schoolsafe_remediation_payment_version_unique ON schoolsafe_remediation_payments(source_payment_id,confirmation_version);

CREATE FUNCTION schoolsafe_remediation_payment_version() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE previous schoolsafe_remediation_payments%ROWTYPE;
BEGIN
 -- The origin trigger runs first and holds fee, dossier, payment and purpose locks.
 SELECT * INTO previous FROM schoolsafe_remediation_payments
 WHERE source_payment_id=NEW.payment_id ORDER BY confirmation_version DESC LIMIT 1;
 IF FOUND AND previous.case_id<>NEW.case_id THEN RAISE EXCEPTION 'REMEDIATION_PAYMENT_CASE_IMMUTABLE'; END IF;
 NEW.source_payment_id=NEW.payment_id;
 NEW.confirmation_version=COALESCE(previous.confirmation_version,0)+1;
 NEW.supersedes_id=CASE WHEN previous.invalidated_at IS NOT NULL THEN previous.id ELSE NULL END;
 NEW.invalidated_at=NULL;
 NEW.confirmed_at=clock_timestamp();
 SELECT purpose.fee_id,purpose.receipt_reference,p.payment_date
 INTO NEW.source_fee_id,NEW.receipt_reference,NEW.source_payment_date
 FROM schoolsafe_remediation_payment_purpose purpose JOIN billing_payments p ON p.id=purpose.payment_id
 WHERE p.id=NEW.payment_id;
 RETURN NEW;
END $$;
CREATE TRIGGER schoolsafe_remediation_payment_versioned BEFORE INSERT ON schoolsafe_remediation_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_payment_version();

CREATE FUNCTION schoolsafe_remediation_allocation_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
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
END $$;
CREATE TRIGGER schoolsafe_remediation_allocation_history BEFORE UPDATE ON schoolsafe_remediation_payments
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_allocation_immutable();
COMMIT;
