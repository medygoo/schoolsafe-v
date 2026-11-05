-- Finance lot F4a (5 novembre 2026) : deux monnaies, franc congolais (FC) et dollar (USD).
-- Décision du propriétaire : docs/DECISIONS.md (5 novembre 2026) ; plan : docs/qualification/ANALYSE_FINANCE_20261105.md.
-- Retour arrière : rollback.sql. Les montants déjà enregistrés ne changent pas.
--
-- - Chaque frais et chaque paiement porte sa monnaie (CURRENCY, USD par défaut).
-- - Un paiement garde ce qui a été réellement reçu (PAID_CURRENCY, PAID_AMOUNT) et le taux appliqué (RATE,
--   FC pour 1 USD) quand la monnaie reçue n'est pas celle du compte réglé.
-- - schoolsafe_exchange_rates : le taux du jour par école.
-- - Format français des montants : 75 000,00 (virgule, espace insécable).
-- - Le reçu calcule le reste par monnaie et indique le montant reçu, le taux et l'équivalent.

-- Sauvegardes pour le retour arrière.
CREATE TABLE schoolsafe_bak_f4a_config AS
    SELECT school_id, title, config_value FROM config
    WHERE title IN ('CURRENCY', 'DECIMAL_SEPARATOR', 'THOUSANDS_SEPARATOR');
CREATE TABLE schoolsafe_bak_f4a_receipts AS
    SELECT id, snapshot FROM schoolsafe_payment_receipts;

ALTER TABLE billing_fees ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT billing_fees_currency_check CHECK (currency IN ('USD', 'FC'));
ALTER TABLE billing_payments ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT billing_payments_currency_check CHECK (currency IN ('USD', 'FC'));
ALTER TABLE billing_payments ADD COLUMN paid_currency varchar(3)
    CONSTRAINT billing_payments_paid_currency_check CHECK (paid_currency IN ('USD', 'FC'));
ALTER TABLE billing_payments ADD COLUMN paid_amount numeric(14,2);
ALTER TABLE billing_payments ADD COLUMN rate numeric(14,4) CONSTRAINT billing_payments_rate_check CHECK (rate > 0);

CREATE TABLE schoolsafe_exchange_rates (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    rate_date date NOT NULL,
    rate numeric(14,4) NOT NULL CHECK (rate > 0),
    created_by text NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, rate_date)
);

-- Libellé par défaut et format français (les pages SchoolSafe affichent la monnaie de chaque ligne).
UPDATE config SET config_value = 'USD' WHERE title = 'CURRENCY';
UPDATE config SET config_value = ',' WHERE title = 'DECIMAL_SEPARATOR';
-- Espace insécable (U+00A0) : un montant ne se coupe jamais en fin de ligne.
UPDATE config SET config_value = chr(160) WHERE title = 'THOUSANDS_SEPARATOR';

-- Reçu : reste calculé dans la monnaie du compte réglé ; montant reçu, taux et équivalent gardés.
CREATE OR REPLACE FUNCTION schoolsafe_receipt_issue(p billing_payments, rev integer) RETURNS integer
LANGUAGE plpgsql AS $$
DECLARE
    next_number integer;
    fees numeric;
    previous numeric;
    student_name text;
    grade text;
    new_id integer;
BEGIN
    PERFORM pg_advisory_xact_lock(hashtext('schoolsafe_receipt'), (p.school_id * 10000 + p.syear)::integer);
    SELECT COALESCE(MAX(number), 0) + 1 INTO next_number FROM schoolsafe_payment_receipts
        WHERE school_id = p.school_id AND syear = p.syear;

    SELECT COALESCE(SUM(amount), 0) INTO fees FROM billing_fees
        WHERE student_id = p.student_id AND school_id = p.school_id AND syear = p.syear
        AND currency = p.currency;
    SELECT COALESCE(SUM(amount), 0) INTO previous FROM billing_payments
        WHERE student_id = p.student_id AND school_id = p.school_id AND syear = p.syear
        AND currency = p.currency AND id < p.id;
    SELECT btrim(COALESCE(s.first_name, '') || ' ' || COALESCE(s.middle_name, '') || ' ' || COALESCE(s.last_name, ''))
        INTO student_name FROM students s WHERE s.student_id = p.student_id;
    SELECT g.title INTO grade FROM student_enrollment e JOIN school_gradelevels g ON g.id = e.grade_id
        WHERE e.student_id = p.student_id AND e.school_id = p.school_id AND e.syear = p.syear
        ORDER BY e.start_date DESC NULLS LAST LIMIT 1;

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
            'currency', p.currency,
            'paid_currency', COALESCE(p.paid_currency, p.currency),
            'paid_amount', COALESCE(p.paid_amount, p.amount),
            'rate', p.rate
        )::text)
    RETURNING id INTO new_id;

    RETURN new_id;
END;
$$;

-- Reçus déjà émis : la monnaie « $ » venait de l'ancien réglage ; les montants étaient en USD par défaut.
UPDATE schoolsafe_payment_receipts
    SET snapshot = (snapshot::jsonb || '{"currency": "USD"}'::jsonb)::text, updated_at = CURRENT_TIMESTAMP
    WHERE snapshot::jsonb->>'currency' = '$';
