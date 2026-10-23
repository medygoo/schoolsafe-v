-- SchoolSafe — Lot A1 : registre local des cartes QR (Rosario moteur principal).
--
-- Additif et idempotent : aucune table native n'est modifiée, aucune donnée
-- existante n'est réécrite. Ce registre rattache chaque carte émise à son
-- élève, son école et son état, sans le bridge : la vérification QR devient
-- possible en local avec le même secret HMAC (jamais stocké ici).
--
-- PostgreSQL : pas d'équivalent MySQL pour to_regclass côté PHP, mais ce
-- fichier n'utilise que des constructions portables (types simples, CHECK).
-- Le statut suit le moteur natif : active, lost, replaced, revoked.
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_student_cards (
    school_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    card_number varchar(64) NOT NULL,
    signature varchar(64) NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'active',
    issued_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp,
    CONSTRAINT schoolsafe_student_cards_number_unique UNIQUE (card_number),
    CONSTRAINT schoolsafe_student_cards_status_allowed
        CHECK (status IN ('active', 'lost', 'replaced', 'revoked'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_student_cards_school_student_idx
    ON schoolsafe_student_cards (school_id, student_id);

COMMIT;
