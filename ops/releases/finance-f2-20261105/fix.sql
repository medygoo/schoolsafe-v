-- Finance lot F2 (5 novembre 2026) : reçus de paiement numérotés et trace des annulations.
-- docs/qualification/ANALYSE_FINANCE_20261105.md (F1, F6). Retour arrière : rollback.sql.
--
-- 1. schoolsafe_payment_receipts : un reçu par paiement d'élève, numéro qui se suit par école et par année.
--    Le reçu est créé par la base à chaque paiement, quelle que soit la page (encaissement simple ou groupé,
--    rattrapage, brouillon hors connexion). Un remboursement ou une annulation change son état ; une
--    correction du montant ou de la date le remplace par un nouveau reçu (l'ancien reste, « remplacé »).
-- 2. schoolsafe_money_cancellations : trace de toute ligne d'argent effacée (salaires, paiements du
--    personnel, recettes, dépenses) avec le motif, l'auteur et la ligne complète.

CREATE TABLE IF NOT EXISTS schoolsafe_payment_receipts (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    syear numeric(4,0) NOT NULL,
    student_id integer NOT NULL,
    source varchar(20) NOT NULL DEFAULT 'billing',
    source_id integer NOT NULL,
    category varchar(20) NOT NULL DEFAULT 'school',
    status varchar(20) NOT NULL DEFAULT 'valid' CHECK (status IN ('valid','corrected','cancelled','refunded')),
    revision integer NOT NULL DEFAULT 1,
    number integer NOT NULL,
    snapshot text NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp,
    UNIQUE (school_id, syear, number)
);
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_payment_receipts_one_valid
    ON schoolsafe_payment_receipts (source, source_id) WHERE status = 'valid';
CREATE INDEX IF NOT EXISTS schoolsafe_payment_receipts_student
    ON schoolsafe_payment_receipts (student_id, school_id, syear);

CREATE TABLE IF NOT EXISTS schoolsafe_money_cancellations (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    syear numeric(4,0) NOT NULL,
    source_table varchar(40) NOT NULL,
    source_id integer NOT NULL,
    amount numeric(14,2),
    reason text NOT NULL CHECK (length(btrim(reason)) > 0),
    cancelled_by text NOT NULL,
    row_data text NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Crée le reçu d'un paiement (photo des montants au moment du paiement).
CREATE OR REPLACE FUNCTION schoolsafe_receipt_issue(p billing_payments, rev integer) RETURNS integer
LANGUAGE plpgsql AS $$
DECLARE
    next_number integer;
    fees numeric;
    previous numeric;
    student_name text;
    grade text;
    currency text;
    new_id integer;
BEGIN
    -- Numéro suivant, sans trou ni doublon, même si deux caisses encaissent en même temps.
    PERFORM pg_advisory_xact_lock(hashtext('schoolsafe_receipt'), (p.school_id * 10000 + p.syear)::integer);
    SELECT COALESCE(MAX(number), 0) + 1 INTO next_number FROM schoolsafe_payment_receipts
        WHERE school_id = p.school_id AND syear = p.syear;

    SELECT COALESCE(SUM(amount), 0) INTO fees FROM billing_fees
        WHERE student_id = p.student_id AND school_id = p.school_id AND syear = p.syear;
    SELECT COALESCE(SUM(amount), 0) INTO previous FROM billing_payments
        WHERE student_id = p.student_id AND school_id = p.school_id AND syear = p.syear AND id < p.id;
    SELECT btrim(COALESCE(s.first_name, '') || ' ' || COALESCE(s.middle_name, '') || ' ' || COALESCE(s.last_name, ''))
        INTO student_name FROM students s WHERE s.student_id = p.student_id;
    SELECT g.title INTO grade FROM student_enrollment e JOIN school_gradelevels g ON g.id = e.grade_id
        WHERE e.student_id = p.student_id AND e.school_id = p.school_id AND e.syear = p.syear
        ORDER BY e.start_date DESC NULLS LAST LIMIT 1;
    SELECT config_value INTO currency FROM config WHERE title = 'CURRENCY' AND school_id IN (p.school_id, 0)
        ORDER BY school_id DESC LIMIT 1;

    INSERT INTO schoolsafe_payment_receipts (school_id, syear, student_id, source, source_id, category, status,
        revision, number, snapshot)
    VALUES (p.school_id, p.syear, p.student_id, 'billing', p.id,
        CASE WHEN p.comments LIKE 'Rattrapage%' THEN 'remediation' ELSE 'school' END, 'valid', rev, next_number,
        json_build_object(
            'date', to_char(p.payment_date, 'DD/MM/YYYY'),
            'student', regexp_replace(COALESCE(student_name, ''), '\s+', ' ', 'g'),
            'student_id', p.student_id,
            'grade', COALESCE(grade, ''),
            'object', COALESCE(NULLIF(btrim(p.comments), ''), 'Frais scolaires'),
            'amount', p.amount,
            'fees', fees,
            'previous_paid', previous,
            'balance', fees - previous - p.amount,
            'balance_kind', 'school_fee',
            'cashier', COALESCE(p.created_by, ''),
            'currency', COALESCE(currency, '')
        )::text)
    RETURNING id INTO new_id;

    RETURN new_id;
END;
$$;

CREATE OR REPLACE FUNCTION schoolsafe_receipt_on_payment() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    last_revision integer;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.refunded_payment_id IS NULL AND NEW.amount > 0 THEN
            PERFORM schoolsafe_receipt_issue(NEW, 1);
        ELSIF NEW.refunded_payment_id IS NOT NULL THEN
            -- Remboursement ou annulation : le reçu du paiement d'origine change d'état.
            UPDATE schoolsafe_payment_receipts
                SET status = CASE WHEN NEW.comments LIKE 'Annulation%' THEN 'cancelled' ELSE 'refunded' END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE source = 'billing' AND source_id = NEW.refunded_payment_id AND status = 'valid';
        END IF;
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        IF NEW.refunded_payment_id IS NULL AND NEW.amount > 0
            AND (NEW.amount IS DISTINCT FROM OLD.amount OR NEW.payment_date IS DISTINCT FROM OLD.payment_date) THEN
            -- Correction : l'ancien reçu reste (« remplacé »), un nouveau reçu porte les bons montants.
            UPDATE schoolsafe_payment_receipts SET status = 'corrected', updated_at = CURRENT_TIMESTAMP
                WHERE source = 'billing' AND source_id = NEW.id AND status = 'valid';
            SELECT COALESCE(MAX(revision), 0) INTO last_revision FROM schoolsafe_payment_receipts
                WHERE source = 'billing' AND source_id = NEW.id;
            PERFORM schoolsafe_receipt_issue(NEW, last_revision + 1);
        END IF;
        RETURN NEW;
    ELSE
        UPDATE schoolsafe_payment_receipts SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE source = 'billing' AND source_id = OLD.id AND status = 'valid';
        RETURN OLD;
    END IF;
END;
$$;

DROP TRIGGER IF EXISTS schoolsafe_receipt_on_payment ON billing_payments;
CREATE TRIGGER schoolsafe_receipt_on_payment
    AFTER INSERT OR UPDATE OR DELETE ON billing_payments
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_receipt_on_payment();

-- Reçus des paiements déjà enregistrés (dans l'ordre des paiements), s'ils n'en ont pas.
DO $$
DECLARE
    p billing_payments;
BEGIN
    FOR p IN SELECT * FROM billing_payments b
        WHERE b.refunded_payment_id IS NULL AND b.amount > 0
        AND NOT EXISTS (SELECT 1 FROM schoolsafe_payment_receipts r WHERE r.source = 'billing' AND r.source_id = b.id)
        ORDER BY b.payment_date, b.id
    LOOP
        PERFORM schoolsafe_receipt_issue(p, 1);
        UPDATE schoolsafe_payment_receipts r SET status = CASE WHEN x.comments LIKE 'Annulation%' THEN 'cancelled' ELSE 'refunded' END
            FROM billing_payments x WHERE x.refunded_payment_id = p.id
            AND r.source = 'billing' AND r.source_id = p.id AND r.status = 'valid';
    END LOOP;
END;
$$;
