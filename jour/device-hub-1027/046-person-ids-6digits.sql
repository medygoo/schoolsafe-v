-- 046 — Système d'ID universel à 6 chiffres (personnel 2xxxxx / enfants 3xxxxx)
-- L'ID est la référence unique par école : numéro employé sur l'appareil,
-- code de secours sur le terminal, liaison présence → paie.
-- Appliqué le 2026-10-27 (table créée + ID 200001 → Medy Lomela, school 3).
CREATE TABLE IF NOT EXISTS schoolsafe_person_ids (
    id BIGSERIAL PRIMARY KEY,
    school_id INTEGER NOT NULL,
    syear INTEGER NOT NULL,
    person_type VARCHAR(10) NOT NULL CHECK (person_type IN ('staff','student')),
    person_id INTEGER NOT NULL,
    id6 VARCHAR(6) NOT NULL CHECK (id6 ~ '^[0-9]{6}$'),
    created_by INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT schoolsafe_person_ids_unique_person UNIQUE (school_id, syear, person_type, person_id),
    CONSTRAINT schoolsafe_person_ids_unique_id UNIQUE (school_id, id6)
);
CREATE INDEX IF NOT EXISTS schoolsafe_person_ids_id6_idx ON schoolsafe_person_ids (school_id, id6);
