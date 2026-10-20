-- SchoolSafe — communications WhatsApp (WAHA principal, Evolution secours).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante reecrite, SMS 036 intact. Utilise par
-- ProgramFunctions/SchoolSafeWhatsapp.fnc.php (sessions, consentement,
-- routage WAHA->Evolution, idempotence, statuts), plateforme-prodeli-whatsapp.php
-- (route /plateforme/prodeli/whatsapp) et le miroir Studio (canal 'whatsapp').
--
-- Regles de securite portees par le schema :
-- - moteurs fermes (waha|evolution), sessions : une seule session active par
--   moteur ; le meme nom de session ne peut JAMAIS etre partage entre les
--   deux moteurs (UNIQUE + garde applicative) ;
-- - AUCUN secret dans ces tables : cles/jetons lus cote SERVEUR uniquement
--   (env WAHA_* / EVOLUTION_*), jamais en base, jamais au navigateur ;
-- - consentement explicite par destinataire (opt-in ecole) : sans ligne
--   consentie, envoi refuse (CONSENT_REQUIRED) ;
-- - anti-doublons : idempotency_key UNIQUE — le rejeu ne recree rien ;
--   le secours Evolution n'est tente QU'APRES echec confirme du primaire
--   (jamais d'envoi parallele, jamais de double envoi volontaire) ;
-- - confidentialite : les copies WhatsApp ne contiennent JAMAIS mot de passe,
--   lien secret ni code de recuperation (garde applicative SENSITIVE_BLOCKED) ;
-- - canal Studio 'whatsapp' ajoute en sur-ensemble (anciennes valeurs intactes).

BEGIN;

-- Sur-ensemble : 'whatsapp' rejoint feed/email/push (lignes existantes intactes).
ALTER TABLE schoolsafe_notify_deliveries DROP CONSTRAINT IF EXISTS schoolsafe_notify_deliveries_channel_allowed;
-- Le CHECK d'origine est restaure avec whatsapp en plus (noms exacts U0).
ALTER TABLE schoolsafe_notify_deliveries
    ADD CONSTRAINT schoolsafe_notify_deliveries_channel_allowed
        CHECK (channel IN ('feed', 'email', 'push', 'whatsapp'));

CREATE TABLE IF NOT EXISTS schoolsafe_wa_sessions (
    id serial PRIMARY KEY,
    engine varchar(16) NOT NULL,
    session_name varchar(128) NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'disabled',
    phone_e164 varchar(20) NOT NULL DEFAULT '',
    conn_state varchar(16) NOT NULL DEFAULT 'unknown',
    conn_checked_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_wa_sessions_engine_allowed
        CHECK (engine IN ('waha', 'evolution')),
    CONSTRAINT schoolsafe_wa_sessions_status_allowed
        CHECK (status IN ('active', 'suspended', 'disabled')),
    CONSTRAINT schoolsafe_wa_sessions_conn_allowed
        CHECK (conn_state IN ('unknown', 'online', 'offline', 'error')),
    CONSTRAINT schoolsafe_wa_sessions_name_unique UNIQUE (session_name)
);

CREATE TABLE IF NOT EXISTS schoolsafe_wa_outbox (
    id serial PRIMARY KEY,
    idempotency_key varchar(128) NOT NULL,
    school_id integer NOT NULL DEFAULT 0,
    recipient_e164 varchar(20) NOT NULL,
    message_text varchar(2000) NOT NULL,
    engine varchar(16) NOT NULL DEFAULT 'waha',
    status varchar(16) NOT NULL DEFAULT 'queued',
    provider_msg_id varchar(128) NOT NULL DEFAULT '',
    error_code varchar(64) NOT NULL DEFAULT '',
    created_by varchar(128) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_wa_outbox_idempotency_unique UNIQUE (idempotency_key),
    CONSTRAINT schoolsafe_wa_outbox_engine_allowed
        CHECK (engine IN ('waha', 'evolution', 'simulated', 'dedup')),
    CONSTRAINT schoolsafe_wa_outbox_status_allowed
        CHECK (status IN ('queued', 'accepted', 'sent', 'delivered',
            'failed', 'blocked'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_wa_outbox_school_idx
    ON schoolsafe_wa_outbox (school_id, created_at);

CREATE INDEX IF NOT EXISTS schoolsafe_wa_outbox_status_idx
    ON schoolsafe_wa_outbox (status, created_at);

CREATE TABLE IF NOT EXISTS schoolsafe_wa_consents (
    id serial PRIMARY KEY,
    school_id integer NOT NULL DEFAULT 0,
    staff_id integer NOT NULL DEFAULT 0,
    phone_e164 varchar(20) NOT NULL,
    consented varchar(1) NOT NULL DEFAULT 'N',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_wa_consents_flag_allowed CHECK (consented IN ('Y', 'N')),
    CONSTRAINT schoolsafe_wa_consents_unique UNIQUE (school_id, staff_id, phone_e164)
);

CREATE TABLE IF NOT EXISTS schoolsafe_wa_audit (
    id serial PRIMARY KEY,
    actor varchar(128) NOT NULL DEFAULT '',
    action varchar(32) NOT NULL,
    session_id integer NULL,
    detail varchar(500) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_wa_audit_action_allowed
        CHECK (action IN ('session_save', 'session_status', 'consent_set',
            'test_send', 'send', 'fallback', 'poll'))
);

-- Deux sessions, une par moteur, desactivees tant que la connexion reelle
-- (compte WhatsApp + session) n'est pas verifiee. Noms distincts : jamais
-- la meme session partagee entre WAHA et Evolution.
INSERT INTO schoolsafe_wa_sessions (engine, session_name, status)
SELECT e.engine, e.session_name, 'disabled'
FROM (VALUES ('waha', 'schoolsafe-waha-1'), ('evolution', 'schoolsafe-evolution-1')) AS e(engine, session_name)
WHERE NOT EXISTS (
    SELECT 1 FROM schoolsafe_wa_sessions AS s WHERE s.engine = e.engine
);

COMMIT;
