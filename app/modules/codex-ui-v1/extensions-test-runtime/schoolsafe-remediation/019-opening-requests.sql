-- SchoolSafe — demandes d'ouverture d'école (suivi central PRODELI).
--
-- Additif et idempotent : aucune table native n'est modifiée, aucune donnée
-- existante n'est réécrite. Utilisée par plugins/SchoolSafe_Keycloak/Opening.php
-- (record/approve/attach/close) et Wizard.php (reprise d'école orpheline,
-- rattachement du compte principal). Sans cette table, ces écrans échouent :
-- la migration comble exactement cet usage, sans rien inventer.
--
-- États : requested → approved → prepared, close possible depuis requested
-- ou approved. Seules les transitions autorisées par le code existent ici
-- comme garde CHECK ; l'ordre reste contrôlé par Opening.php.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_opening_requests (
    id serial PRIMARY KEY,
    syear integer NOT NULL,
    school_name varchar(150) NOT NULL,
    person_name varchar(150) NOT NULL,
    email varchar(254) NOT NULL,
    school_id integer NULL,
    responsible_id integer NULL,
    state varchar(16) NOT NULL DEFAULT 'requested',
    created_by integer NULL,
    updated_by integer NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_opening_requests_state_allowed
        CHECK (state IN ('requested', 'approved', 'prepared', 'closed'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_opening_requests_syear_idx
    ON schoolsafe_opening_requests (syear);

CREATE INDEX IF NOT EXISTS schoolsafe_opening_requests_school_idx
    ON schoolsafe_opening_requests (school_id);

CREATE TABLE IF NOT EXISTS schoolsafe_opening_events (
    id serial PRIMARY KEY,
    request_id integer NOT NULL
        REFERENCES schoolsafe_opening_requests (id) ON DELETE CASCADE,
    actor_staff_id integer NULL,
    action varchar(16) NOT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp
);

CREATE INDEX IF NOT EXISTS schoolsafe_opening_events_request_idx
    ON schoolsafe_opening_events (request_id);

COMMIT;
