-- SchoolSafe — communications SMS (gestionnaire PRODELI, SMSGate).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante reecrite. Utilise par ProgramFunctions/SchoolSafeSms.fnc.php
-- (routeur + quotas + idempotence + statuts), plateforme-prodeli-sms.php
-- (route /plateforme/prodeli/sms) et le raccord OTP L4.
--
-- Regles de securite portees par le schema :
-- - operateurs fermes (vodacom|airtel|orange|africell), statuts fermes
--   (active|suspended|disabled) : une ligne ne devient operationnelle que
--   lorsque appareil + SIM + forfait + connexion sont verifies (statut
--   pose par le hub, jamais devine) ;
-- - quotas : quota_used ne depasse jamais quota_total (CHECK) ; a 0 le
--   routeur bloque (statut 'blocked', jamais d'envoi) ;
-- - anti-doublons : idempotency_key UNIQUE — le rejeu d'un meme envoi ne
--   cree aucun doublon et ne re-decompte aucun quota ;
-- - AUCUN secret dans ces tables : identifiants SMSGate lus cote SERVEUR
--   uniquement (env), jamais en base, jamais au navigateur, jamais en journal ;
-- - tracabilite : chaque ecriture sensible est journalisee (audit).
-- - segments : 1 SMS = 160 caracteres GSM ; le decompte utilise
--   ceil(longueur/160), documente dans SchoolSafeSmsSegments().

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_sms_lines (
    id serial PRIMARY KEY,
    operator varchar(16) NOT NULL,
    phone_e164 varchar(20) NOT NULL DEFAULT '',
    device_id varchar(128) NOT NULL DEFAULT '',
    sim_number integer NOT NULL DEFAULT 1,
    status varchar(16) NOT NULL DEFAULT 'disabled',
    quota_total integer NOT NULL DEFAULT 0,
    quota_used integer NOT NULL DEFAULT 0,
    alert_threshold integer NOT NULL DEFAULT 0,
    conn_state varchar(16) NOT NULL DEFAULT 'unknown',
    conn_checked_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_sms_lines_operator_allowed
        CHECK (operator IN ('vodacom', 'airtel', 'orange', 'africell')),
    CONSTRAINT schoolsafe_sms_lines_status_allowed
        CHECK (status IN ('active', 'suspended', 'disabled')),
    CONSTRAINT schoolsafe_sms_lines_conn_allowed
        CHECK (conn_state IN ('unknown', 'online', 'offline', 'error')),
    CONSTRAINT schoolsafe_sms_lines_quota_bounds
        CHECK (quota_total >= 0 AND quota_used >= 0
            AND quota_used <= quota_total
            AND alert_threshold >= 0 AND sim_number BETWEEN 1 AND 4)
);

CREATE TABLE IF NOT EXISTS schoolsafe_sms_outbox (
    id serial PRIMARY KEY,
    idempotency_key varchar(128) NOT NULL,
    school_id integer NOT NULL DEFAULT 0,
    recipient_e164 varchar(20) NOT NULL,
    message_text varchar(1600) NOT NULL,
    segments integer NOT NULL DEFAULT 1,
    line_id integer NULL,
    status varchar(16) NOT NULL DEFAULT 'queued',
    provider_msg_id varchar(128) NOT NULL DEFAULT '',
    error_code varchar(64) NOT NULL DEFAULT '',
    created_by varchar(128) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_sms_outbox_idempotency_unique UNIQUE (idempotency_key),
    CONSTRAINT schoolsafe_sms_outbox_status_allowed
        CHECK (status IN ('queued', 'accepted', 'sent', 'delivered',
            'failed', 'blocked')),
    CONSTRAINT schoolsafe_sms_outbox_segments_bounds
        CHECK (segments BETWEEN 1 AND 10)
);

CREATE INDEX IF NOT EXISTS schoolsafe_sms_outbox_school_idx
    ON schoolsafe_sms_outbox (school_id, created_at);

CREATE INDEX IF NOT EXISTS schoolsafe_sms_outbox_status_idx
    ON schoolsafe_sms_outbox (status, created_at);

CREATE TABLE IF NOT EXISTS schoolsafe_sms_quota_ledger (
    id serial PRIMARY KEY,
    line_id integer NOT NULL,
    delta integer NOT NULL,
    reason varchar(32) NOT NULL,
    actor varchar(128) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_sms_quota_ledger_reason_allowed
        CHECK (reason IN ('send', 'reprovision', 'correction'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_sms_quota_ledger_line_idx
    ON schoolsafe_sms_quota_ledger (line_id, created_at);

CREATE TABLE IF NOT EXISTS schoolsafe_sms_audit (
    id serial PRIMARY KEY,
    actor varchar(128) NOT NULL DEFAULT '',
    action varchar(32) NOT NULL,
    line_id integer NULL,
    detail varchar(500) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_sms_audit_action_allowed
        CHECK (action IN ('line_create', 'line_update', 'line_status',
            'quota_set', 'test_send', 'send', 'poll'))
);

-- Quatre emplacements, un par operateur, desactives tant que l'appareil, la
-- SIM, le forfait et la connexion ne sont pas verifies (aucun numero, aucun
-- secret : poses par le hub PRODELI, jamais ici).
INSERT INTO schoolsafe_sms_lines (operator, status)
SELECT op, 'disabled'
FROM (VALUES ('vodacom'), ('airtel'), ('orange'), ('africell')) AS v(op)
WHERE NOT EXISTS (
    SELECT 1 FROM schoolsafe_sms_lines AS l WHERE l.operator = v.op
);

COMMIT;
