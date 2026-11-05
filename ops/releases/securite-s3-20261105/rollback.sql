-- Retour arrière du lot S3 : remet les libellés et options d'origine depuis les sauvegardes.
\set ON_ERROR_STOP on
BEGIN;
UPDATE attendance_codes c SET title=b.title, short_name=b.short_name FROM schoolsafe_bak_s3_attendance_codes b WHERE c.id=b.id;
UPDATE discipline_fields f SET title=b.title FROM schoolsafe_bak_s3_discipline_fields b WHERE f.id=b.id;
UPDATE discipline_field_usage u SET title=b.title, select_options=b.select_options FROM schoolsafe_bak_s3_discipline_field_usage b WHERE u.id=b.id;
DROP TABLE schoolsafe_bak_s3_attendance_codes;
DROP TABLE schoolsafe_bak_s3_discipline_fields;
DROP TABLE schoolsafe_bak_s3_discipline_field_usage;
COMMIT;
