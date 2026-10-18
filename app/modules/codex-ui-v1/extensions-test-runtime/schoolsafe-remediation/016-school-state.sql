-- SchoolSafe — état d'un ÉTABLISSEMENT (école cliente entière).
--
-- Additif, idempotent. Ne modifie ni ne supprime aucune table native : une
-- école suspendue garde ses classes, ses élèves, ses notes et ses paiements.
-- Seul l'ACCÈS est coupé.
--
--   active    : établissement en service (défaut quand aucune ligne n'existe)
--   suspended : accès coupé, données conservées, date de fin optionnelle
--               (à cette date l'accès revient tout seul)
--   inactive  : établissement désactivé (fermé/hors service), accès coupé,
--               données conservées, réactivation manuelle
--
-- Portée : l'école, pas la personne. Une personne affectée à deux écoles perd
-- l'accès à l'école coupée et garde l'autre. Le propriétaire de la plateforme
-- (aucune école assignée) conserve l'accès pour administrer.

CREATE TABLE IF NOT EXISTS schoolsafe_school_state (
    school_id integer PRIMARY KEY,
    state varchar(16) NOT NULL DEFAULT 'active',
    reason text,
    until timestamp,
    changed_by varchar(100),
    changed_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_school_state_allowed
        CHECK (state IN ('active', 'suspended', 'inactive'))
);

CREATE TABLE IF NOT EXISTS schoolsafe_school_audit (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    action varchar(32) NOT NULL,
    from_state varchar(16),
    to_state varchar(16),
    reason text,
    actor varchar(100),
    created_at timestamp NOT NULL DEFAULT current_timestamp
);

CREATE INDEX IF NOT EXISTS schoolsafe_school_audit_school_idx
    ON schoolsafe_school_audit (school_id, created_at);
