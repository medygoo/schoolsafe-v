-- SchoolSafe — Lot U4 : alertes de securite P1-P4 (prise en charge humaine).
--
-- Additif et idempotent : aucune table native ni U0/U1/U2 n'est modifiee.
-- Une alerte reste OUVERTE jusqu'a cloture humaine autorisee : aucun
-- automatisme (lecture, relance, escalade, redemarrage) ne la cloture.
-- Lire un message n'est jamais une prise en charge (ack nominatif explicite).
-- Etend le CHECK des types d'evenements avec 'security.p1' (notifications
-- immediates individuelles, jamais de resume quotidien).
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events
    ADD CONSTRAINT schoolsafe_notify_events_type_allowed
        CHECK (event_type IN ('security.entry', 'student.pickup', 'payment.receipt',
            'school.announcement', 'remediation.update',
            'attendance.closure', 'absence.detected', 'case.updated',
            'security.p1'));

-- 1. Alertes : ouvertes jusqu'a cloture humaine autorisee.
CREATE TABLE IF NOT EXISTS schoolsafe_p1_alerts (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    kind varchar(24) NOT NULL DEFAULT 'security',
    severity varchar(2) NOT NULL DEFAULT 'P1',
    title varchar(150) NOT NULL,
    body varchar(2000) NOT NULL DEFAULT '',
    student_id integer NULL REFERENCES students(student_id) ON DELETE SET NULL,
    status varchar(16) NOT NULL DEFAULT 'open',
    escalated boolean NOT NULL DEFAULT false,
    escalations integer NOT NULL DEFAULT 0,
    dedup_key varchar(200) NOT NULL,
    created_by integer NULL REFERENCES staff(staff_id) ON DELETE SET NULL,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    ack_by integer NULL REFERENCES staff(staff_id) ON DELETE SET NULL,
    ack_at timestamp with time zone NULL,
    closed_by integer NULL REFERENCES staff(staff_id) ON DELETE SET NULL,
    closed_at timestamp with time zone NULL,
    close_note varchar(1000) NOT NULL DEFAULT '',
    CONSTRAINT schoolsafe_p1_alerts_unique UNIQUE (school_id, dedup_key),
    CONSTRAINT schoolsafe_p1_alerts_kind_allowed
        CHECK (kind IN ('security', 'health', 'intrusion', 'fire', 'other')),
    CONSTRAINT schoolsafe_p1_alerts_severity_allowed
        CHECK (severity IN ('P1', 'P2', 'P3', 'P4')),
    CONSTRAINT schoolsafe_p1_alerts_status_allowed
        CHECK (status IN ('open', 'closed'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_p1_alerts_open_idx
    ON schoolsafe_p1_alerts (school_id, syear, status, created_at);

-- 2. Destinataires habilites + accuses nominatifs.
CREATE TABLE IF NOT EXISTS schoolsafe_p1_recipients (
    alert_id bigint NOT NULL REFERENCES schoolsafe_p1_alerts(id) ON DELETE CASCADE,
    staff_id integer NOT NULL REFERENCES staff(staff_id) ON DELETE CASCADE,
    notified_at timestamp with time zone NOT NULL DEFAULT now(),
    ack_at timestamp with time zone NULL,
    CONSTRAINT schoolsafe_p1_recipients_unique UNIQUE (alert_id, staff_id)
);

CREATE INDEX IF NOT EXISTS schoolsafe_p1_recipients_staff_idx
    ON schoolsafe_p1_recipients (staff_id, alert_id);

COMMIT;
