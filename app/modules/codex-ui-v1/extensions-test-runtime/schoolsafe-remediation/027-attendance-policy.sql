-- SchoolSafe — Lot U1 : politiques d'horaires, passages planifies et absences provisoires.
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite. Heritage explicite ecole -> section -> classe :
--   section = school_gradelevels.id, classe = course_periods.course_period_id.
-- Les changements d'horaires ne sont jamais retroactifs : chaque passage
-- (runs) fige school_date et applique la politique dont effective_on est la
-- plus recente <= school_date.
-- Jour sans cours : presence d'une ligne attendance_calendar (minutes > 0)
-- ET aucune ligne calendar_events ce jour-la. Le planificateur ne detecte
-- jamais les jours sans cours.
-- L'appel enseignant (attendance_day / attendance_period) n'est que LU,
-- jamais ecrase ; l'absence officielle definitive n'est jamais fabriquee
-- (statut 'provisional', resolue en 'resolved_arrived' a l'arrivee tardive).
--
-- Extension avant U0 (sans modifier le fichier 024) : les types
-- 'attendance.closure' et 'absence.detected' rejoignent le CHECK des
-- evenements notify (sur-ensemble, rejouable).
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

-- 1. Sur-ensemble avant des types d'evenements U0 (fichier 024 intact).
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events
    ADD CONSTRAINT schoolsafe_notify_events_type_allowed
        CHECK (event_type IN ('security.entry', 'student.pickup', 'payment.receipt',
            'school.announcement', 'remediation.update',
            'attendance.closure', 'absence.detected'));

-- 2. Politiques d'horaires (un niveau par ligne : ecole, section ou classe).
CREATE TABLE IF NOT EXISTS schoolsafe_attendance_policy (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    gradelevel_id integer NULL REFERENCES school_gradelevels(id),
    course_period_id integer NULL REFERENCES course_periods(course_period_id),
    arrival_time time NOT NULL DEFAULT '07:30',
    closure_time time NOT NULL DEFAULT '08:00',
    detection_time time NOT NULL DEFAULT '09:00',
    timezone varchar(64) NOT NULL DEFAULT 'Africa/Kinshasa',
    effective_on date NOT NULL,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone,
    CONSTRAINT schoolsafe_attendance_policy_school_fkey
        FOREIGN KEY (school_id, syear) REFERENCES schools(id, syear),
    CONSTRAINT schoolsafe_attendance_policy_hours_ordered
        CHECK (arrival_time <= closure_time AND closure_time <= detection_time),
    CONSTRAINT schoolsafe_attendance_policy_effective_required
        CHECK (effective_on IS NOT NULL)
);

-- Unicite par niveau (heritage explicite, sans ambiguite).
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_attendance_policy_school_unique
    ON schoolsafe_attendance_policy (school_id, syear, effective_on)
    WHERE gradelevel_id IS NULL AND course_period_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_attendance_policy_section_unique
    ON schoolsafe_attendance_policy (school_id, syear, gradelevel_id, effective_on)
    WHERE gradelevel_id IS NOT NULL AND course_period_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_attendance_policy_class_unique
    ON schoolsafe_attendance_policy (school_id, syear, course_period_id, effective_on)
    WHERE course_period_id IS NOT NULL;

-- 3. Passages du planificateur (une seule cloture / detection par perimetre et jour).
CREATE TABLE IF NOT EXISTS schoolsafe_attendance_runs (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    school_date date NOT NULL,
    scope_kind varchar(16) NOT NULL DEFAULT 'school',
    scope_ref integer NULL,
    phase varchar(16) NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'done',
    expected_count integer NOT NULL DEFAULT 0,
    arrived_count integer NOT NULL DEFAULT 0,
    pending_count integer NOT NULL DEFAULT 0,
    absence_count integer NOT NULL DEFAULT 0,
    started_at timestamp with time zone NOT NULL DEFAULT now(),
    finished_at timestamp with time zone,
    CONSTRAINT schoolsafe_attendance_runs_scope_allowed
        CHECK (scope_kind IN ('school', 'section', 'class')),
    CONSTRAINT schoolsafe_attendance_runs_phase_allowed
        CHECK (phase IN ('closure', 'detection')),
    CONSTRAINT schoolsafe_attendance_runs_status_allowed
        CHECK (status IN ('done', 'catchup', 'no_school_day')),
    CONSTRAINT schoolsafe_attendance_runs_single_passage
        UNIQUE NULLS NOT DISTINCT (school_id, school_date, scope_kind, scope_ref, phase)
);

CREATE INDEX IF NOT EXISTS schoolsafe_attendance_runs_school_day_idx
    ON schoolsafe_attendance_runs (school_id, syear, school_date);

-- 4. Exceptions autorisees (exclues de la detection, sans motif U2).
CREATE TABLE IF NOT EXISTS schoolsafe_attendance_exceptions (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    school_date date NOT NULL,
    reason varchar(200) NOT NULL DEFAULT '',
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    CONSTRAINT schoolsafe_attendance_exceptions_unique
        UNIQUE (school_id, student_id, school_date)
);

-- 5. Absences provisoires (historique conserve, jamais supprime).
CREATE TABLE IF NOT EXISTS schoolsafe_absence_events (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    school_date date NOT NULL,
    status varchar(24) NOT NULL DEFAULT 'provisional',
    detected_at timestamp with time zone NOT NULL DEFAULT now(),
    resolved_at timestamp with time zone NULL,
    late_minutes integer NULL,
    notify_event_id bigint NULL REFERENCES schoolsafe_notify_events(id) ON DELETE SET NULL,
    CONSTRAINT schoolsafe_absence_events_unique
        UNIQUE (school_id, student_id, school_date),
    CONSTRAINT schoolsafe_absence_events_status_allowed
        CHECK (status IN ('provisional', 'resolved_arrived'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_absence_events_day_idx
    ON schoolsafe_absence_events (school_id, syear, school_date, status);

COMMIT;
