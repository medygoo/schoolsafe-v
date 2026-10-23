-- SchoolSafe — Lot ENTRY : stockage des arrivées (Rosario moteur principal).
--
-- Additif et idempotent : reprend les définitions du module Entry_Exit
-- (modules/Entry_Exit/install.sql) pour les deux tables strictement
-- nécessaires au mode ENTRY, sans les menus ni la configuration du module.
-- Aucune table native modifiée, aucune donnée réécrite.
--
-- Limite connue : entry_exit_* ne porte pas school_id (modèle natif Rosario).
-- Le périmètre école est garanti par la jointure inscription
-- (student_enrollment) dans le code, pas par ces tables.
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS entry_exit_checkpoints (
    id serial PRIMARY KEY,
    title varchar(100) NOT NULL,
    type varchar(3),
    authorized_from time,
    authorized_to time,
    sort_order numeric,
    created_at timestamp DEFAULT current_timestamp,
    updated_at timestamp
);

CREATE TABLE IF NOT EXISTS entry_exit_student_records (
    id serial PRIMARY KEY,
    student_id integer NOT NULL REFERENCES students(student_id),
    checkpoint_id integer NOT NULL REFERENCES entry_exit_checkpoints(id) ON DELETE CASCADE,
    type varchar(3) NOT NULL,
    unauthorized varchar(1),
    comments varchar(255),
    recorded_at timestamp NOT NULL,
    created_at timestamp DEFAULT current_timestamp,
    updated_at timestamp
);

CREATE INDEX IF NOT EXISTS entry_exit_student_records_day_idx
    ON entry_exit_student_records (student_id, type, recorded_at);

COMMIT;
