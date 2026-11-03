-- SchoolSafe 050 : accuse WhatsApp (1 envoye au serveur, 2 remis au telephone, 3 lu, 4 ecoute).
-- Additif et rejouable. Le statut d'envoi existant (accepted, delivered...) n'est pas modifie.
BEGIN;
ALTER TABLE schoolsafe_wa_outbox ADD COLUMN IF NOT EXISTS ack_level smallint NOT NULL DEFAULT 0;
ALTER TABLE schoolsafe_wa_outbox ADD COLUMN IF NOT EXISTS ack_at timestamp;
CREATE INDEX IF NOT EXISTS schoolsafe_wa_outbox_ack_idx ON schoolsafe_wa_outbox (status, ack_level, created_at);
COMMIT;
