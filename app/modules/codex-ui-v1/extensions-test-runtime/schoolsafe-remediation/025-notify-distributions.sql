-- SchoolSafe — Lot U0 : distributions, destinataires et modeles (Rosario moteur principal).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite. La table deliveries EST l'outbox transactionnelle :
-- une ligne 'pending' par destinataire x canal, jamais supprimee, reprise par
-- le dispatcher independant. L'unicite (event, audience, destinataire, canal)
-- interdit tout double envoi.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_notify_templates (
    event_type varchar(64) NOT NULL,
    audience varchar(16) NOT NULL,
    channel varchar(16) NOT NULL,
    title_template varchar(200) NOT NULL DEFAULT '',
    body_template varchar(1000) NOT NULL DEFAULT '',
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT schoolsafe_notify_templates_pkey PRIMARY KEY (event_type, audience, channel),
    CONSTRAINT schoolsafe_notify_templates_audience_allowed
        CHECK (audience IN ('parent', 'teacher', 'direction', 'staff')),
    CONSTRAINT schoolsafe_notify_templates_channel_allowed
        CHECK (channel IN ('feed', 'email', 'push'))
);

CREATE TABLE IF NOT EXISTS schoolsafe_notify_deliveries (
    id bigserial PRIMARY KEY,
    event_id bigint NOT NULL REFERENCES schoolsafe_notify_events(id) ON DELETE CASCADE,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    audience varchar(16) NOT NULL,
    recipient_staff_id integer NOT NULL REFERENCES staff(staff_id),
    channel varchar(16) NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'pending',
    attempts integer NOT NULL DEFAULT 0,
    last_error varchar(500) NOT NULL DEFAULT '',
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone,
    CONSTRAINT schoolsafe_notify_deliveries_unique
        UNIQUE (event_id, audience, recipient_staff_id, channel),
    CONSTRAINT schoolsafe_notify_deliveries_audience_allowed
        CHECK (audience IN ('parent', 'teacher', 'direction', 'staff')),
    CONSTRAINT schoolsafe_notify_deliveries_channel_allowed
        CHECK (channel IN ('feed', 'email', 'push')),
    CONSTRAINT schoolsafe_notify_deliveries_status_allowed
        CHECK (status IN ('pending', 'sent', 'failed'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_notify_deliveries_outbox_idx
    ON schoolsafe_notify_deliveries (status, created_at);

CREATE INDEX IF NOT EXISTS schoolsafe_notify_deliveries_recipient_idx
    ON schoolsafe_notify_deliveries (school_id, recipient_staff_id, status, created_at DESC);

-- Modeles minimaux : insertion sans ecrasement (rejouable).
INSERT INTO schoolsafe_notify_templates (event_type, audience, channel, title_template, body_template)
VALUES
    ('security.entry', 'parent', 'feed', 'Arrivee enregistree', 'Arrivee de {student} a {time}.'),
    ('security.entry', 'parent', 'email', 'Arrivee enregistree', 'Arrivee de {student} a {time}.'),
    ('student.pickup', 'parent', 'feed', 'Sortie confirmee', 'Sortie de {student} a {time}.'),
    ('student.pickup', 'parent', 'email', 'Sortie confirmee', 'Sortie de {student} a {time}.')
ON CONFLICT (event_type, audience, channel) DO NOTHING;

COMMIT;
