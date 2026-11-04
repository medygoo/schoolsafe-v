-- SchoolSafe 061 : archives administratives de l'ecole (04/11, lot 8 valide par le proprietaire).
-- Tout document venu d'ailleurs (assurance, preuve de paiement, achat, courrier recu, ministere...) depose
-- par photo ou en PDF, numerote (ARC-2026-0001), classe et retrouvable. Le fichier est garde dans la base :
-- il suit la sauvegarde chiffree hors serveur deja en place. Rien ne s'efface : un retrait garde sa raison.
BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_archive_documents (
    id bigserial PRIMARY KEY,
    school_id integer NOT NULL,
    syear integer NOT NULL,
    reference text NOT NULL CHECK (reference ~ '^ARC-[0-9]{4}-[0-9]{4,5}$'),
    category text NOT NULL CHECK (category IN ('assurance','paiement','achat','facture','contrat','ministere','courrier_recu','eleve','personnel','reunion','batiment','autre')),
    title text NOT NULL CHECK (length(btrim(title)) BETWEEN 1 AND 200),
    issuer text NOT NULL DEFAULT '' CHECK (length(issuer) <= 200),
    doc_date date,
    amount numeric(14,2) CHECK (amount IS NULL OR amount >= 0),
    currency text CHECK (currency IS NULL OR currency IN ('CDF','USD','EUR')),
    tags text NOT NULL DEFAULT '' CHECK (length(tags) <= 300),
    notes text NOT NULL DEFAULT '' CHECK (length(notes) <= 2000),
    student_id integer REFERENCES students(student_id),
    mime text NOT NULL CHECK (mime IN ('image/jpeg','image/png','application/pdf')),
    size_bytes integer NOT NULL CHECK (size_bytes BETWEEN 1 AND 10485760),
    sha256 text NOT NULL CHECK (sha256 ~ '^[a-f0-9]{64}$'),
    content bytea NOT NULL,
    created_by integer NOT NULL REFERENCES staff(staff_id),
    created_at timestamptz NOT NULL DEFAULT now(),
    withdrawn_at timestamptz,
    withdrawn_by integer REFERENCES staff(staff_id),
    withdrawn_reason text CHECK (withdrawn_reason IS NULL OR length(withdrawn_reason) <= 300),
    UNIQUE (school_id, reference),
    CHECK ((withdrawn_at IS NULL) = (withdrawn_reason IS NULL))
);
CREATE INDEX IF NOT EXISTS schoolsafe_archive_documents_school_idx ON schoolsafe_archive_documents(school_id, category, doc_date DESC);
CREATE INDEX IF NOT EXISTS schoolsafe_archive_documents_sha_idx ON schoolsafe_archive_documents(school_id, sha256);
GRANT SELECT, INSERT, UPDATE ON schoolsafe_archive_documents TO schoolsafe_app;
GRANT USAGE, SELECT, UPDATE ON SEQUENCE schoolsafe_archive_documents_id_seq TO schoolsafe_app;
COMMIT;
