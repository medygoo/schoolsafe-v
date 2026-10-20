-- SchoolSafe — Lot FEES : historique des consultations autorisées (Rosario moteur principal).
--
-- Additif et idempotent : `billing_fees` et `billing_payments` ne sont ni
-- modifiés ni réécrits (lecture seule). Chaque consultation autorisée au
-- scanner y laisse une trace (qui, quand, quel solde constaté), sans jamais
-- créer, modifier ou supprimer un paiement.
--
-- Appliquer en local/qualification uniquement ; jamais en production sans GO.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_fee_consults (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    consulted_by integer NOT NULL REFERENCES staff(staff_id),
    balance_status varchar(16) NOT NULL,
    fee_total numeric(14,2) NOT NULL DEFAULT 0,
    paid_total numeric(14,2) NOT NULL DEFAULT 0,
    balance numeric(14,2) NOT NULL DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    CONSTRAINT schoolsafe_fee_consults_status_allowed
        CHECK (balance_status IN ('paid', 'partial', 'due', 'exempt', 'no_data'))
);

CREATE INDEX IF NOT EXISTS schoolsafe_fee_consults_student_idx
    ON schoolsafe_fee_consults (school_id, student_id, created_at DESC);

COMMIT;
