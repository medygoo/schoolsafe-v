-- Rattrapage, seances regroupees et presence par carte (04/11, valide par le proprietaire).
-- Une seance de groupe = une ligne par dossier partageant la meme cle ; la presence reste individuelle.
BEGIN;
ALTER TABLE schoolsafe_remediation_sessions
    ADD COLUMN IF NOT EXISTS group_key text CHECK (group_key IS NULL OR group_key ~ '^[a-f0-9]{32}$'),
    ADD COLUMN IF NOT EXISTS attendance_card boolean NOT NULL DEFAULT false;
CREATE INDEX IF NOT EXISTS schoolsafe_remediation_sessions_group_idx ON schoolsafe_remediation_sessions(group_key) WHERE group_key IS NOT NULL;
COMMIT;
