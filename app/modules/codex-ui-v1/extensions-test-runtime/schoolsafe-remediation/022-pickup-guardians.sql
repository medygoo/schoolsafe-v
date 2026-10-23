-- SchoolSafe — Lot PICKUP : personnes autorisées et preuves de remise (Rosario moteur principal).
--
-- Additif et idempotent : aucune table native n'est modifiée, aucune donnée
-- existante n'est réécrite. Les liens parents principaux restent natifs
-- (`students_join_users` → staff, sans compte en double : `staff_id`).
-- Les personnes externes ne sont jamais des comptes : simples lignes.
-- Les sorties réutilisent `entry_exit_student_records` (type 'out', migration 021).
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_pickup_guardians (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    guardian_type varchar(16) NOT NULL DEFAULT 'externe',
    full_name varchar(200) NOT NULL,
    phone varchar(64) NOT NULL DEFAULT '',
    staff_id integer NULL REFERENCES staff(staff_id),
    is_primary boolean NOT NULL DEFAULT false,
    is_active boolean NOT NULL DEFAULT true,
    is_authorized_pickup boolean NOT NULL DEFAULT false,
    photo_path varchar(500) NOT NULL DEFAULT '',
    photo_accepted boolean NOT NULL DEFAULT false,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp,
    CONSTRAINT schoolsafe_pickup_guardians_type_allowed
        CHECK (guardian_type IN ('pere', 'mere', 'tuteur', 'externe')),
    CONSTRAINT schoolsafe_pickup_guardians_name_required
        CHECK (btrim(full_name) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_pickup_guardians_primary_unique
    ON schoolsafe_pickup_guardians (student_id) WHERE is_primary AND is_active;

CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_pickup_guardians_staff_unique
    ON schoolsafe_pickup_guardians (student_id, staff_id) WHERE staff_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS schoolsafe_pickup_guardians_school_student_idx
    ON schoolsafe_pickup_guardians (school_id, student_id);

CREATE TABLE IF NOT EXISTS schoolsafe_pickup_authorizations (
    id serial PRIMARY KEY,
    guardian_id integer NOT NULL REFERENCES schoolsafe_pickup_guardians(id) ON DELETE CASCADE,
    student_id integer NOT NULL REFERENCES students(student_id),
    school_id integer NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'pending',
    slot_no integer NULL,
    photo_verified boolean NOT NULL DEFAULT false,
    starts_on date NULL,
    ends_on date NULL,
    requested_by integer NULL REFERENCES staff(staff_id),
    reason varchar(500) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp,
    CONSTRAINT schoolsafe_pickup_authorizations_status_allowed
        CHECK (status IN ('pending', 'active', 'suspended', 'revoked')),
    CONSTRAINT schoolsafe_pickup_authorizations_slot_allowed
        CHECK (slot_no IS NULL OR slot_no IN (1, 2, 3))
);

CREATE INDEX IF NOT EXISTS schoolsafe_pickup_authorizations_guardian_idx
    ON schoolsafe_pickup_authorizations (guardian_id, status);

CREATE INDEX IF NOT EXISTS schoolsafe_pickup_authorizations_student_idx
    ON schoolsafe_pickup_authorizations (school_id, student_id, status);

CREATE TABLE IF NOT EXISTS schoolsafe_pickup_proofs (
    id serial PRIMARY KEY,
    token varchar(64) NOT NULL,
    school_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    guardian_id integer NOT NULL REFERENCES schoolsafe_pickup_guardians(id) ON DELETE CASCADE,
    authorization_id integer NULL REFERENCES schoolsafe_pickup_authorizations(id) ON DELETE SET NULL,
    is_principal boolean NOT NULL DEFAULT false,
    location_id integer NOT NULL REFERENCES entry_exit_checkpoints(id),
    prepared_by integer NOT NULL REFERENCES staff(staff_id),
    record_id integer NULL REFERENCES entry_exit_student_records(id) ON DELETE SET NULL,
    expires_at timestamp NOT NULL,
    used_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_pickup_proofs_token_unique UNIQUE (token)
);

CREATE INDEX IF NOT EXISTS schoolsafe_pickup_proofs_student_idx
    ON schoolsafe_pickup_proofs (school_id, student_id, expires_at);

COMMIT;
