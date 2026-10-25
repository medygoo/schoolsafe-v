-- SchoolSafe — Lot U0 : journal des migrations notify + evenements officiels (Rosario moteur principal).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite. Le dedup_key garantit l'idempotence d'emission
-- (rejeu sans doublon) et la reprise apres interruption.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_notify_migrations (
    name varchar(64) NOT NULL PRIMARY KEY,
    sha256 varchar(64) NOT NULL,
    applied_at timestamp with time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS schoolsafe_notify_events (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    event_type varchar(64) NOT NULL,
    dedup_key varchar(200) NOT NULL,
    student_id integer NULL REFERENCES students(student_id),
    actor_staff_id integer NULL REFERENCES staff(staff_id),
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT schoolsafe_notify_events_dedup_unique UNIQUE (school_id, dedup_key),
    CONSTRAINT schoolsafe_notify_events_type_allowed
        CHECK (event_type IN ('security.entry', 'student.pickup', 'payment.receipt',
            'school.announcement', 'remediation.update')),
    CONSTRAINT schoolsafe_notify_events_dedup_required
        CHECK (btrim(dedup_key) <> '')
);

CREATE INDEX IF NOT EXISTS schoolsafe_notify_events_school_time_idx
    ON schoolsafe_notify_events (school_id, syear, created_at DESC);

CREATE INDEX IF NOT EXISTS schoolsafe_notify_events_student_idx
    ON schoolsafe_notify_events (school_id, student_id, created_at DESC);

COMMIT;
