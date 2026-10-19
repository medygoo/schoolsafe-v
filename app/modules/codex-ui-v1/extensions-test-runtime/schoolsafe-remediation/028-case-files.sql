-- SchoolSafe — Lot U2 : dossiers de suivi, signalements et justifications.
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite. Un dossier = un eleve + un jour. Les evenements
-- sont APPEND-ONLY (trigger interdisant UPDATE/DELETE : l'historique ne peut
-- ni etre reecrit ni efface). L'absence initiale U1 n'est jamais supprimee.
-- Les pieces jointes ne sont que des REFERENCES (cle R2/chemin + sha256),
-- jamais de contenu binaire en PostgreSQL.
-- Motifs medicaux = confidentialite 'restricted_medical' (direction + parents
-- lies uniquement ; enseignants et gardiens : libelle redige).
-- Extension avant U0/U1 (fichiers 024/027 intacts) : type 'case.updated'.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events
    ADD CONSTRAINT schoolsafe_notify_events_type_allowed
        CHECK (event_type IN ('security.entry', 'student.pickup', 'payment.receipt',
            'school.announcement', 'remediation.update',
            'attendance.closure', 'absence.detected', 'case.updated'));

-- 1. Dossiers de suivi (un par eleve et journee).
CREATE TABLE IF NOT EXISTS schoolsafe_case_files (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    school_date date NOT NULL,
    status varchar(24) NOT NULL DEFAULT 'open',
    opened_from varchar(24) NOT NULL DEFAULT 'detection',
    absence_event_id bigint NULL REFERENCES schoolsafe_absence_events(id) ON DELETE SET NULL,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone,
    CONSTRAINT schoolsafe_case_files_unique
        UNIQUE (school_id, student_id, school_date),
    CONSTRAINT schoolsafe_case_files_status_allowed
        CHECK (status IN ('open', 'pending_decision', 'justified', 'unjustified', 'under_review')),
    CONSTRAINT schoolsafe_case_files_opened_allowed
        CHECK (opened_from IN ('detection', 'parent_report', 'teacher', 'conflict'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_case_files_day_idx
    ON schoolsafe_case_files (school_id, syear, school_date, status);

-- 2. Evenements append-only (UPDATE/DELETE interdits par trigger).
CREATE TABLE IF NOT EXISTS schoolsafe_case_events (
    id bigserial PRIMARY KEY,
    file_id bigint NOT NULL REFERENCES schoolsafe_case_files(id) ON DELETE CASCADE,
    seq integer NOT NULL,
    kind varchar(32) NOT NULL,
    actor_staff_id integer NULL REFERENCES staff(staff_id),
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    confidentiality varchar(24) NOT NULL DEFAULT 'normal',
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT schoolsafe_case_events_file_seq_unique UNIQUE (file_id, seq),
    CONSTRAINT schoolsafe_case_events_kind_allowed
        CHECK (kind IN ('absence_detected', 'late_arrival', 'parent_report',
            'justification', 'decision', 'document', 'teacher_note', 'conflict',
            'arrival_verified')),
    CONSTRAINT schoolsafe_case_events_confidentiality_allowed
        CHECK (confidentiality IN ('normal', 'restricted_medical'))
);

-- Purge de test uniquement : SET LOCAL schoolsafe.allow_case_purge = '1' dans une
-- transaction dediee autorise la suppression des lignes fictives. Par defaut
-- (hors transaction de purge), tout UPDATE/DELETE est refuse : l'historique
-- de production reste append-only. Aucun code applicatif ne positionne jamais
-- ce parametre.
CREATE OR REPLACE FUNCTION schoolsafe_case_events_append_only()
RETURNS trigger AS $$
BEGIN
    IF current_setting('schoolsafe.allow_case_purge', true) = '1' THEN
        RETURN OLD;
    END IF;
    RAISE EXCEPTION 'CASE_EVENTS_APPEND_ONLY';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS schoolsafe_case_events_no_update ON schoolsafe_case_events;
CREATE TRIGGER schoolsafe_case_events_no_update
    BEFORE UPDATE OR DELETE ON schoolsafe_case_events
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_case_events_append_only();

-- 3. Justifications (versions conservees, decision tracee).
CREATE TABLE IF NOT EXISTS schoolsafe_justifications (
    id bigserial PRIMARY KEY,
    file_id bigint NOT NULL REFERENCES schoolsafe_case_files(id) ON DELETE CASCADE,
    event_kind varchar(32) NOT NULL DEFAULT 'absence_detected',
    version integer NOT NULL DEFAULT 1,
    submitted_by integer NOT NULL REFERENCES staff(staff_id),
    motive varchar(24) NOT NULL DEFAULT 'other',
    motive_text varchar(2000) NOT NULL DEFAULT '',
    sensitive boolean NOT NULL DEFAULT false,
    attachment_ref varchar(500) NOT NULL DEFAULT '',
    attachment_sha256 varchar(64) NOT NULL DEFAULT '',
    status varchar(16) NOT NULL DEFAULT 'pending',
    decided_by integer NULL REFERENCES staff(staff_id),
    decided_at timestamp with time zone NULL,
    decision_note varchar(1000) NOT NULL DEFAULT '',
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT schoolsafe_justifications_version_unique
        UNIQUE (file_id, event_kind, version),
    CONSTRAINT schoolsafe_justifications_motive_allowed
        CHECK (motive IN ('medical', 'family', 'transport', 'other')),
    CONSTRAINT schoolsafe_justifications_status_allowed
        CHECK (status IN ('pending', 'accepted', 'refused'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_justifications_file_idx
    ON schoolsafe_justifications (file_id, status);

COMMIT;
