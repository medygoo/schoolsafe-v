-- SchoolSafe — direction d'une école (directeur principal et adjoints).
--
-- Additif et idempotent : aucune table native n'est modifiée, aucune donnée
-- existante n'est réécrite. Une école peut avoir plusieurs directeurs, dont
-- UN SEUL principal (index unique partiel). Les droits restent portés par
-- staff.PROFILE_ID (profil nommé « Directeur », migration 015) : cette table
-- ne donne aucun droit, elle décrit seulement qui dirige l'école.
--
-- PostgreSQL : l'index unique partiel n'a pas d'équivalent MySQL ; SchoolSafe
-- s'appuie sur PostgreSQL pour cette couche (comme 014/016).

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_school_leadership (
    school_id integer NOT NULL,
    staff_id integer NOT NULL,
    role varchar(16) NOT NULL DEFAULT 'adjoint',
    since timestamp NOT NULL DEFAULT current_timestamp,
    changed_by varchar(100),
    CONSTRAINT schoolsafe_school_leadership_pk PRIMARY KEY (school_id, staff_id),
    CONSTRAINT schoolsafe_school_leadership_role_allowed
        CHECK (role IN ('principal', 'adjoint'))
);

-- Un seul directeur principal par école.
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_school_leadership_principal_idx
    ON schoolsafe_school_leadership (school_id)
    WHERE role = 'principal';

CREATE INDEX IF NOT EXISTS schoolsafe_school_leadership_staff_idx
    ON schoolsafe_school_leadership (staff_id);

COMMIT;
