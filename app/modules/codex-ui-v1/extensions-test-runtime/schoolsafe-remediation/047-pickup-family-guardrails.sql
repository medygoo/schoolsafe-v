-- 047 — Familles et récupération : modèle v1.1 et garde-fous en base.
-- Spécification : docs/superpowers/specs/2026-11-03-schoolsafe-familles-recuperation-v1.1.md
-- Additif et idempotent : aucune colonne ni table supprimée ; le code existant
-- (SchoolSafePickupManage.fnc.php) applique déjà ces règles, la base les garantit.
BEGIN;

-- R5 fratries : une personne = une identité, une pièce, une photo, une validation.
CREATE TABLE IF NOT EXISTS schoolsafe_pickup_persons (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    full_name varchar(200) NOT NULL,
    phone varchar(64) NOT NULL DEFAULT '',
    id_doc_type varchar(32) NOT NULL DEFAULT '',
    id_doc_number varchar(64) NOT NULL DEFAULT '',
    photo_path varchar(500) NOT NULL DEFAULT '',
    photo_status varchar(16) NOT NULL DEFAULT 'pending',
    validated_by integer NULL REFERENCES staff(staff_id),
    validated_at timestamp NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp,
    CONSTRAINT schoolsafe_pickup_persons_name_required CHECK (btrim(full_name) <> ''),
    CONSTRAINT schoolsafe_pickup_persons_photo_status_allowed
        CHECK (photo_status IN ('pending', 'accepted', 'rejected'))
);
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_pickup_persons_document_unique
    ON schoolsafe_pickup_persons (school_id, id_doc_type, id_doc_number) WHERE id_doc_number <> '';

ALTER TABLE schoolsafe_pickup_guardians
    ADD COLUMN IF NOT EXISTS person_id integer NULL REFERENCES schoolsafe_pickup_persons(id);
CREATE INDEX IF NOT EXISTS schoolsafe_pickup_guardians_person_idx
    ON schoolsafe_pickup_guardians (person_id) WHERE person_id IS NOT NULL;

-- Un seul père actif et une seule mère active par enfant.
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_pickup_guardians_parent_type_unique
    ON schoolsafe_pickup_guardians (student_id, guardian_type)
    WHERE is_active AND guardian_type IN ('pere', 'mere');

-- Une personne accréditée n'a jamais de compte.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'schoolsafe_pickup_guardians_external_no_account') THEN
        ALTER TABLE schoolsafe_pickup_guardians ADD CONSTRAINT schoolsafe_pickup_guardians_external_no_account
            CHECK (guardian_type <> 'externe' OR staff_id IS NULL);
    END IF;
END $$;

-- Accréditation active = une place 1..3, unique par enfant : plafond de 3 garanti sans comptage.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'schoolsafe_pickup_authorizations_active_has_slot') THEN
        ALTER TABLE schoolsafe_pickup_authorizations ADD CONSTRAINT schoolsafe_pickup_authorizations_active_has_slot
            CHECK (status <> 'active' OR slot_no IS NOT NULL);
    END IF;
END $$;
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_pickup_authorizations_active_slot_unique
    ON schoolsafe_pickup_authorizations (student_id, slot_no) WHERE status = 'active';

-- Activation d'une accréditation : personne active, photo présente et validée.
CREATE OR REPLACE FUNCTION schoolsafe_pickup_authorization_guard() RETURNS trigger AS $$
DECLARE g record;
BEGIN
    IF NEW.status = 'active' AND (TG_OP = 'INSERT' OR OLD.status IS DISTINCT FROM 'active') THEN
        SELECT is_active, photo_accepted, photo_path INTO g
          FROM schoolsafe_pickup_guardians WHERE id = NEW.guardian_id;
        IF NOT FOUND OR NOT g.is_active THEN
            RAISE EXCEPTION 'SCHOOLSAFE_PICKUP_INVALID_PERSON' USING ERRCODE = 'check_violation';
        END IF;
        IF NOT NEW.photo_verified OR NOT g.photo_accepted OR btrim(g.photo_path) = '' THEN
            RAISE EXCEPTION 'SCHOOLSAFE_PICKUP_PHOTO_NOT_VALIDATED' USING ERRCODE = 'check_violation';
        END IF;
    END IF;
    RETURN NEW;
END $$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS schoolsafe_pickup_authorization_guard ON schoolsafe_pickup_authorizations;
CREATE TRIGGER schoolsafe_pickup_authorization_guard
    BEFORE INSERT OR UPDATE ON schoolsafe_pickup_authorizations
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_pickup_authorization_guard();

-- Responsables : pas de récupération sans photo validée ; R3 identité/photo modifiée -> en attente.
CREATE OR REPLACE FUNCTION schoolsafe_pickup_guardian_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.full_name IS DISTINCT FROM OLD.full_name
                             OR NEW.photo_path IS DISTINCT FROM OLD.photo_path) THEN
        NEW.photo_accepted := false;
        NEW.is_authorized_pickup := false;
    END IF;
    IF NEW.is_authorized_pickup AND (NOT NEW.photo_accepted OR btrim(NEW.photo_path) = '' OR NOT NEW.is_active) THEN
        RAISE EXCEPTION 'SCHOOLSAFE_PICKUP_PHOTO_NOT_VALIDATED' USING ERRCODE = 'check_violation';
    END IF;
    RETURN NEW;
END $$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS schoolsafe_pickup_guardian_guard ON schoolsafe_pickup_guardians;
CREATE TRIGGER schoolsafe_pickup_guardian_guard
    BEFORE INSERT OR UPDATE ON schoolsafe_pickup_guardians
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_pickup_guardian_guard();

CREATE OR REPLACE FUNCTION schoolsafe_pickup_guardian_revalidate() RETURNS trigger AS $$
BEGIN
    IF NEW.full_name IS DISTINCT FROM OLD.full_name OR NEW.photo_path IS DISTINCT FROM OLD.photo_path THEN
        UPDATE schoolsafe_pickup_authorizations
           SET status = CASE WHEN status = 'active' THEN 'pending' ELSE status END,
               photo_verified = false, updated_at = current_timestamp
         WHERE guardian_id = NEW.id AND status IN ('active', 'pending', 'suspended');
    END IF;
    RETURN NULL;
END $$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS schoolsafe_pickup_guardian_revalidate ON schoolsafe_pickup_guardians;
CREATE TRIGGER schoolsafe_pickup_guardian_revalidate
    AFTER UPDATE ON schoolsafe_pickup_guardians
    FOR EACH ROW EXECUTE FUNCTION schoolsafe_pickup_guardian_revalidate();

-- Restrictions (décision de justice ou de l'école), y compris contre un responsable.
CREATE TABLE IF NOT EXISTS schoolsafe_pickup_restrictions (
    id serial PRIMARY KEY,
    school_id integer NOT NULL,
    student_id integer NOT NULL REFERENCES students(student_id),
    guardian_id integer NULL REFERENCES schoolsafe_pickup_guardians(id),
    person_id integer NULL REFERENCES schoolsafe_pickup_persons(id),
    full_name varchar(200) NOT NULL DEFAULT '',
    reason varchar(500) NOT NULL,
    source varchar(32) NOT NULL DEFAULT 'ecole',
    starts_on date NOT NULL DEFAULT current_date,
    ends_on date NULL,
    is_active boolean NOT NULL DEFAULT true,
    created_by integer NULL REFERENCES staff(staff_id),
    created_at timestamp NOT NULL DEFAULT current_timestamp,
    updated_at timestamp,
    CONSTRAINT schoolsafe_pickup_restrictions_target_required
        CHECK (guardian_id IS NOT NULL OR person_id IS NOT NULL OR btrim(full_name) <> ''),
    CONSTRAINT schoolsafe_pickup_restrictions_reason_required CHECK (btrim(reason) <> ''),
    CONSTRAINT schoolsafe_pickup_restrictions_source_allowed CHECK (source IN ('justice', 'ecole')),
    CONSTRAINT schoolsafe_pickup_restrictions_dates CHECK (ends_on IS NULL OR ends_on >= starts_on)
);
CREATE INDEX IF NOT EXISTS schoolsafe_pickup_restrictions_student_idx
    ON schoolsafe_pickup_restrictions (school_id, student_id) WHERE is_active;

-- Droits de l'application identiques aux autres tables familles.
DO $$ DECLARE r text; BEGIN
    FOR r IN SELECT DISTINCT grantee FROM information_schema.role_table_grants
              WHERE table_name = 'schoolsafe_pickup_guardians' AND grantee NOT IN ('postgres', 'PUBLIC') LOOP
        EXECUTE format('GRANT SELECT, INSERT, UPDATE ON schoolsafe_pickup_persons, schoolsafe_pickup_restrictions TO %I', r);
        EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE schoolsafe_pickup_persons_id_seq, schoolsafe_pickup_restrictions_id_seq TO %I', r);
    END LOOP;
END $$;

COMMIT;
