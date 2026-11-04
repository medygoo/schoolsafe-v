-- Rattrapage, fin du conflit d'interet (04/11, valide par le proprietaire) : l'ecole peut exiger que
-- le rattrapage soit donne par un autre enseignant que celui qui enseigne et note la matiere.
-- Desactive par defaut : l'ecole choisit, rien n'est impose.
BEGIN;
ALTER TABLE schoolsafe_remediation_settings ADD COLUMN IF NOT EXISTS other_teacher boolean NOT NULL DEFAULT false;
CREATE INDEX IF NOT EXISTS schoolsafe_remediation_sessions_teacher_idx ON schoolsafe_remediation_sessions(teacher_id, case_id);
COMMIT;
