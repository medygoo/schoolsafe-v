-- Retour arrière du lot F4a (deux monnaies). Les monnaies saisies sont d'abord gardées dans des tables de
-- sauvegarde (rien n'est perdu), puis la base revient à son état du lot F2.
CREATE TABLE schoolsafe_bak_f4a_fee_currency AS SELECT id, currency FROM billing_fees;
CREATE TABLE schoolsafe_bak_f4a_payment_currency AS SELECT id, currency, paid_currency, paid_amount, rate FROM billing_payments;

-- Fonction du reçu du lot F2 (avant les deux monnaies), remise avant de retirer les colonnes.
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

ALTER TABLE billing_payments DROP COLUMN rate, DROP COLUMN paid_amount, DROP COLUMN paid_currency, DROP COLUMN currency;
ALTER TABLE billing_fees DROP COLUMN currency;
ALTER TABLE schoolsafe_exchange_rates RENAME TO schoolsafe_bak_f4a_exchange_rates;

UPDATE config c SET config_value = b.config_value
    FROM schoolsafe_bak_f4a_config b WHERE b.school_id = c.school_id AND b.title = c.title;
UPDATE schoolsafe_payment_receipts r SET snapshot = b.snapshot
    FROM schoolsafe_bak_f4a_receipts b WHERE b.id = r.id;
