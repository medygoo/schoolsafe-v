-- SchoolSafe 069 : canal SMS de secours pour les alertes importantes (04/11, decision du proprietaire :
-- « Alertes importantes », seulement aux parents sans e-mail ni WhatsApp). Additif : meme liste + 'sms'.
-- Aucun SMS ne part sans ligne SMSGate active (telephone Android + SIM de l'ecole).
BEGIN;
ALTER TABLE schoolsafe_notify_deliveries DROP CONSTRAINT IF EXISTS schoolsafe_notify_deliveries_channel_allowed;
ALTER TABLE schoolsafe_notify_deliveries ADD CONSTRAINT schoolsafe_notify_deliveries_channel_allowed
    CHECK (channel::text = ANY (ARRAY['feed','email','push','whatsapp','sms']::text[]));
COMMIT;
