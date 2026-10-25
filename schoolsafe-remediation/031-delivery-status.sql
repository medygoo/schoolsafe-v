-- SchoolSafe — Lot U4 : etats de livraison distingues, sans faux succes.
--
-- Additif : les lignes existantes ('pending', 'sent', 'failed') restent
-- valides. Nouveaux etats :
--   pending  -> en attente dans l'outbox transactionnelle ;
--   accepted -> accepte par le fournisseur (Brevo 201, push service 2xx) ;
--   sent     -> heritage U0 : fil local ecrit (feed), pre-verifie par TestFeed ;
--   delivered-> livraison verifiee (webhook fournisseur, si disponible) ;
--   read     -> lu par le destinataire (accuse explicite, jamais automatique) ;
--   failed   -> echec conserve (panne, fournisseur absent, adresse invalide).
-- provider_msg_id : identifiant renvoye par le fournisseur (Brevo message-id),
-- vide tant que non accepte. Aucun 'sent'/'accepted' sans preuve d'envoi.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

ALTER TABLE schoolsafe_notify_deliveries DROP CONSTRAINT IF EXISTS schoolsafe_notify_deliveries_status_allowed;
ALTER TABLE schoolsafe_notify_deliveries
    ADD CONSTRAINT schoolsafe_notify_deliveries_status_allowed
        CHECK (status IN ('pending', 'accepted', 'sent', 'delivered', 'read', 'failed'));

ALTER TABLE schoolsafe_notify_deliveries
    ADD COLUMN IF NOT EXISTS provider_msg_id varchar(128) NOT NULL DEFAULT '';

COMMIT;
