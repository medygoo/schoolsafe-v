-- Retour arriere de 062 : retire les declencheurs du journal (les notes ne sont pas touchees).
-- La table du journal est gardee (preuves) ; la supprimer seulement sur decision du proprietaire.
BEGIN;
DROP TRIGGER IF EXISTS schoolsafe_grade_journal_homework ON gradebook_grades;
DROP TRIGGER IF EXISTS schoolsafe_grade_journal_report_card ON student_report_card_grades;
COMMIT;
