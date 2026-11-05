-- Retour arrière du lot E3 : remet les 6 codes d'origine et retire « Fin de cycle »
-- (seulement s'il n'est utilisé par aucune inscription).
\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE n int;
BEGIN
  SELECT count(*) INTO n FROM student_enrollment e JOIN student_enrollment_codes c ON c.id IN (e.enrollment_code, e.drop_code)
   WHERE c.short_name='FINC' AND c.syear=2026;
  IF n<>0 THEN RAISE EXCEPTION '« Fin de cycle » déjà utilisé par % inscription(s) : retour arrière à décider', n; END IF;
END $$;
DELETE FROM student_enrollment_codes WHERE syear=2026 AND short_name='FINC';
UPDATE student_enrollment_codes c SET title=b.title, short_name=b.short_name, sort_order=b.sort_order
  FROM schoolsafe_bak_e3_enrollment_codes b WHERE c.id=b.id;
DROP TABLE schoolsafe_bak_e3_enrollment_codes;
COMMIT;
