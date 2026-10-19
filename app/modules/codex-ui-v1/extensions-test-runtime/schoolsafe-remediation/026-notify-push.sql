-- SchoolSafe — Lot U0 : abonnements Web Push (Rosario moteur principal).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite.
--
-- Verification d'absence prealable (L1, 19/10/2026) :
--   to_regclass('public.schoolsafe_notification_push_subscriptions') = false
-- et aucune table '%push%' / '%outbox%' en public. Le endpoint PHP
-- notification-push-subscription.php ecrit dans ce nom exact avec
-- ON CONFLICT (endpoint) : ce schema reprend ses colonnes pour rester
-- compatible sans le modifier.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_notification_push_subscriptions (
    staff_id integer NOT NULL REFERENCES staff(staff_id),
    school_id integer NOT NULL,
    syear integer NOT NULL,
    endpoint varchar(2048) NOT NULL,
    p256dh varchar(128) NOT NULL,
    auth varchar(64) NOT NULL,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone,
    CONSTRAINT schoolsafe_notification_push_subscriptions_endpoint_unique UNIQUE (endpoint),
    CONSTRAINT schoolsafe_notification_push_subscriptions_endpoint_required
        CHECK (btrim(endpoint) <> '')
);

CREATE INDEX IF NOT EXISTS schoolsafe_notification_push_subscriptions_staff_idx
    ON schoolsafe_notification_push_subscriptions (school_id, staff_id, syear);

COMMIT;
