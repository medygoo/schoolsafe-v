-- SchoolSafe 068 : texte lu dans les documents d'archives (OCR, 04/11, lot 11 valide par le proprietaire).
-- La photo ou le PDF reste intact ; le texte lu sur le serveur (Tesseract, Poppler) est garde a cote pour
-- retrouver un document par n'importe quel mot. Additif, rejouable.
BEGIN;
ALTER TABLE schoolsafe_archive_documents ADD COLUMN IF NOT EXISTS ocr_text text CHECK (ocr_text IS NULL OR length(ocr_text) <= 20000);
ALTER TABLE schoolsafe_archive_documents ADD COLUMN IF NOT EXISTS ocr_at timestamptz;
COMMIT;
