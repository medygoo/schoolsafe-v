-- Retour arrière du lot F2 Finance (5 novembre 2026).
-- Retire le déclencheur et les fonctions des reçus. Les deux tables sont RENOMMÉES (pas effacées) pour garder
-- les reçus déjà émis et la trace des annulations ; les paiements eux-mêmes ne sont pas touchés.
DROP TRIGGER IF EXISTS schoolsafe_receipt_on_payment ON billing_payments;
DROP FUNCTION IF EXISTS schoolsafe_receipt_on_payment();
DROP FUNCTION IF EXISTS schoolsafe_receipt_issue(billing_payments, integer);
ALTER TABLE IF EXISTS schoolsafe_payment_receipts RENAME TO schoolsafe_bak_f2_payment_receipts;
ALTER TABLE IF EXISTS schoolsafe_money_cancellations RENAME TO schoolsafe_bak_f2_money_cancellations;
