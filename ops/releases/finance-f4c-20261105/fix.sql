-- Finance lot F4c (5 novembre 2026) : paie, trésorerie, rattrapage et cantine en deux monnaies.
-- Décisions du propriétaire : docs/DECISIONS.md (5 novembre 2026, lot F4c). Retour arrière : rollback.sql.
-- Les montants déjà enregistrés ne changent pas (USD par défaut ; aucune ligne de paie ni de trésorerie existante).

-- Paie et trésorerie : chaque ligne porte sa monnaie ; un paiement du personnel garde ce qui a été reçu et le taux.
ALTER TABLE accounting_salaries ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT accounting_salaries_currency_check CHECK (currency IN ('USD', 'FC'));
ALTER TABLE accounting_incomes ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT accounting_incomes_currency_check CHECK (currency IN ('USD', 'FC'));
ALTER TABLE accounting_payments ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT accounting_payments_currency_check CHECK (currency IN ('USD', 'FC'));
ALTER TABLE accounting_payments ADD COLUMN paid_currency varchar(3)
    CONSTRAINT accounting_payments_paid_currency_check CHECK (paid_currency IN ('USD', 'FC'));
ALTER TABLE accounting_payments ADD COLUMN paid_amount numeric(14,2);
ALTER TABLE accounting_payments ADD COLUMN rate numeric(14,4) CONSTRAINT accounting_payments_rate_check CHECK (rate > 0);

-- Rattrapage : monnaie choisie pour chaque dossier (USD par défaut).
ALTER TABLE schoolsafe_remediation_cases ADD COLUMN currency varchar(3) NOT NULL DEFAULT 'USD'
    CONSTRAINT schoolsafe_remediation_cases_currency_check CHECK (currency IN ('USD', 'FC'));

-- Cantine : une seule monnaie par école, le franc congolais.
INSERT INTO config (school_id, title, config_value)
    SELECT s.id, 'SCHOOLSAFE_CANTEEN_CURRENCY', 'FC' FROM (SELECT DISTINCT id FROM schools) s
    WHERE NOT EXISTS (SELECT 1 FROM config c WHERE c.school_id = s.id AND c.title = 'SCHOOLSAFE_CANTEEN_CURRENCY');
