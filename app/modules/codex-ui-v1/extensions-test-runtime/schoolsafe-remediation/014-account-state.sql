-- SchoolSafe L1 — état de compte + révocation de session.
--
-- Additif, idempotent. Ne modifie aucune table native.
-- Etat par PERSONNE (clé = nom d'utilisateur en minuscules, l'identité que le
-- natif utilise déjà pour retrouver un compte d'une année sur l'autre) et par
-- ECOLE (school_id = 0 signifie « toutes les écoles de la personne »).
-- Ainsi une suspension survit au rollover, et une personne présente dans deux
-- écoles peut être suspendue dans l'une sans perdre l'autre.

CREATE TABLE IF NOT EXISTS schoolsafe_account_state (
    person_key varchar(100) NOT NULL,
    school_id integer NOT NULL DEFAULT 0,
    state varchar(16) NOT NULL DEFAULT 'active',
    reason text,
    until timestamp,
    session_epoch integer NOT NULL DEFAULT 0,
    changed_by varchar(100),
    changed_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_account_state_pk PRIMARY KEY (person_key, school_id),
    CONSTRAINT schoolsafe_account_state_allowed
        CHECK (state IN ('active', 'readonly', 'suspended', 'expired'))
);

-- Journal des changements d'état : qui, quand, quoi, pourquoi.
CREATE TABLE IF NOT EXISTS schoolsafe_account_audit (
    id serial PRIMARY KEY,
    person_key varchar(100) NOT NULL,
    school_id integer NOT NULL DEFAULT 0,
    action varchar(32) NOT NULL,
    from_state varchar(16),
    to_state varchar(16),
    reason text,
    actor varchar(100),
    created_at timestamp NOT NULL DEFAULT current_timestamp
);

CREATE INDEX IF NOT EXISTS schoolsafe_account_audit_person_idx
    ON schoolsafe_account_audit (person_key, created_at);
