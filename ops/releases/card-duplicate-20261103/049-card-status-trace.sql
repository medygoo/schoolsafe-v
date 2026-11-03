-- SchoolSafe 049 : trace des changements d'etat des cartes (duplicata, annulation).
-- Additif et rejouable. Les etats existants (active, lost, replaced, revoked) sont inchanges ;
-- on enregistre seulement le motif, l'auteur et la date du changement.
BEGIN;
ALTER TABLE schoolsafe_student_cards ADD COLUMN IF NOT EXISTS status_reason varchar(20);
ALTER TABLE schoolsafe_student_cards ADD COLUMN IF NOT EXISTS status_changed_by integer;
ALTER TABLE schoolsafe_student_cards ADD COLUMN IF NOT EXISTS status_changed_at timestamp;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'schoolsafe_student_cards_reason_allowed') THEN
    ALTER TABLE schoolsafe_student_cards ADD CONSTRAINT schoolsafe_student_cards_reason_allowed
      CHECK (status_reason IS NULL OR status_reason IN ('lost', 'stolen', 'damaged', 'cancel'));
  END IF;
END $$;
-- Une seule carte active par eleve et par ecole : un duplicata remplace, il ne s'ajoute jamais.
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_student_cards_one_active
  ON schoolsafe_student_cards (school_id, student_id) WHERE status = 'active';
COMMIT;
