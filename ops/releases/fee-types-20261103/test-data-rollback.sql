-- Retrait des donnees d'essai du bareme (03/11), eleve de test 16 uniquement.
-- A executer seulement sur decision du proprietaire. Ne touche que les lignes marquees 'TEST essai bareme 03/11'.
BEGIN;
DELETE FROM billing_payments WHERE student_id=16 AND comments='TEST essai barème 03/11';
DELETE FROM billing_fees WHERE student_id=16 AND comments='TEST essai barème 03/11';
-- Attendu : 1 paiement et 3 frais retires.
COMMIT;
