-- Retour arrière du lot F4c. Les monnaies saisies sont d'abord gardées dans des tables de sauvegarde.
CREATE TABLE schoolsafe_bak_f4c_salaries AS SELECT id, currency FROM accounting_salaries;
CREATE TABLE schoolsafe_bak_f4c_incomes AS SELECT id, currency FROM accounting_incomes;
CREATE TABLE schoolsafe_bak_f4c_payments AS SELECT id, currency, paid_currency, paid_amount, rate FROM accounting_payments;
CREATE TABLE schoolsafe_bak_f4c_remediation AS SELECT id, currency FROM schoolsafe_remediation_cases;

ALTER TABLE accounting_payments DROP COLUMN rate, DROP COLUMN paid_amount, DROP COLUMN paid_currency, DROP COLUMN currency;
ALTER TABLE accounting_incomes DROP COLUMN currency;
ALTER TABLE accounting_salaries DROP COLUMN currency;
ALTER TABLE schoolsafe_remediation_cases DROP COLUMN currency;
DELETE FROM config WHERE title = 'SCHOOLSAFE_CANTEEN_CURRENCY';
