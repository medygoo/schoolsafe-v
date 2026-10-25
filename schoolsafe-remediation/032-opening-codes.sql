-- SchoolSafe — codes d'autorisation d'ouverture d'ecole (LOT L2, parcours /demarrer).
--
-- Additif et idempotent : aucune table native n'est modifiee, aucune donnee
-- existante n'est reecrite. Utilisee par
-- ProgramFunctions/SchoolSafeOpeningCode.fnc.php (emission centralisee et
-- verification atomique) et demarrer.php (presentation publique du code).
--
-- Regles de securite portees par le schema :
-- - seul le hash SHA-256 du code est conserve (jamais le code brut) ;
-- - expiration obligatoire (expires_at, sans defaut : l'emission impose une duree) ;
-- - usage unique (consumed_at, renseigne atomiquement avec l'approbation) ;
-- - liaison a la demande (request_id) et a l'operation autorisee (operation) ;
-- - limitation des tentatives (attempts / max_attempts) ;
-- - journalisation sans secret dans schoolsafe_opening_events (table 019).

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_opening_codes (
    id serial PRIMARY KEY,
    request_id integer NOT NULL
        REFERENCES schoolsafe_opening_requests (id) ON DELETE CASCADE,
    operation varchar(16) NOT NULL DEFAULT 'approve',
    code_hash varchar(64) NOT NULL,
    expires_at timestamp NOT NULL,
    max_attempts integer NOT NULL DEFAULT 5,
    attempts integer NOT NULL DEFAULT 0,
    consumed_at timestamp NULL,
    consumed_by integer NULL,
    created_by integer NOT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_opening_codes_operation_allowed
        CHECK (operation IN ('approve')),
    CONSTRAINT schoolsafe_opening_codes_hash_format
        CHECK (code_hash ~ '^[0-9a-f]{64}$'),
    CONSTRAINT schoolsafe_opening_codes_attempts_bounds
        CHECK (attempts >= 0 AND max_attempts BETWEEN 1 AND 10
            AND attempts <= max_attempts)
);

CREATE INDEX IF NOT EXISTS schoolsafe_opening_codes_request_idx
    ON schoolsafe_opening_codes (request_id);

CREATE INDEX IF NOT EXISTS schoolsafe_opening_codes_active_idx
    ON schoolsafe_opening_codes (request_id, operation)
    WHERE consumed_at IS NULL;

-- Liaisons d'identite et journal d'audit : referencés par
-- plugins/SchoolSafe_Keycloak (Management.php, Opening.php, Wizard.php) mais
-- jamais crees par une migration precedente (constat L2 : les ecrans
-- centraux echouent sans elles). Creation additive STRICTE (IF NOT EXISTS,
-- aucune donnee reecrite) pour combler exactement cet usage.

CREATE TABLE IF NOT EXISTS schoolsafe_identity_links (
    staff_id integer NOT NULL PRIMARY KEY,
    issuer varchar(254) NULL,
    subject varchar(64) NULL,
    state varchar(16) NOT NULL DEFAULT 'onboarding',
    enabled varchar(1) NOT NULL DEFAULT 'Y',
    created_by integer NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_identity_links_state_allowed
        CHECK (state IN ('active', 'onboarding', 'suspended'))
);

CREATE TABLE IF NOT EXISTS schoolsafe_identity_audit (
    id serial PRIMARY KEY,
    actor_staff_id integer NULL,
    target_staff_id integer NULL,
    action varchar(64) NOT NULL,
    result varchar(16) NOT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp
);

CREATE INDEX IF NOT EXISTS schoolsafe_identity_audit_target_idx
    ON schoolsafe_identity_audit (target_staff_id);

COMMIT;
