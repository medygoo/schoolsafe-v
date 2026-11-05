-- École, lot E3 (5 novembre 2026) : codes d'inscription en français RDC.
-- docs/qualification/ANALYSE_ECOLE_20261105.md (C5).
-- Les codes déjà utilisés gardent leur numéro et leur sens (renommage seulement) ;
-- les codes jamais utilisés sont renommés ; « Fin de cycle » est ajouté.
-- Une seule transaction ; toute divergence annule tout. Retour arrière : rollback.sql.
\set ON_ERROR_STOP on
BEGIN;

CREATE TABLE schoolsafe_bak_e3_enrollment_codes AS
  SELECT * FROM student_enrollment_codes WHERE syear=2026;

DO $$
DECLARE n int;
BEGIN
  SELECT count(*) INTO n FROM student_enrollment_codes WHERE syear=2026;
  IF n<>6 THEN RAISE EXCEPTION 'codes 2026 attendus 6, trouvés %', n; END IF;

  -- Utilisés : même sens, renommés.
  UPDATE student_enrollment_codes SET title='Nouvelle inscription', short_name='NOUV', sort_order=1
   WHERE id=3 AND short_name='EBY' AND type='Add';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'EBY attendu 1, obtenu %', n; END IF;
  UPDATE student_enrollment_codes SET title='Transfert entrant', short_name='TRIN', sort_order=3
   WHERE id=4 AND short_name='OTHER' AND type='Add';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'OTHER attendu 1, obtenu %', n; END IF;

  -- Jamais utilisés : renommés (on vérifie qu'ils ne servent toujours à aucun élève).
  SELECT count(*) INTO n FROM student_enrollment WHERE enrollment_code IN (1,2,5,6) OR drop_code IN (1,2,5,6);
  IF n<>0 THEN RAISE EXCEPTION 'codes 1,2,5,6 désormais utilisés par % inscription(s)', n; END IF;

  UPDATE student_enrollment_codes SET title='Réinscription', short_name='REIN', sort_order=2 WHERE id=6 AND type='Add';
  UPDATE student_enrollment_codes SET title='Transfert sortant', short_name='TROUT', sort_order=4 WHERE id=1 AND type='Drop';
  UPDATE student_enrollment_codes SET title='Abandon', short_name='ABAN', sort_order=5 WHERE id=5 AND type='Drop';
  UPDATE student_enrollment_codes SET title='Exclusion', short_name='EXCL', sort_order=6 WHERE id=2 AND type='Drop';

  INSERT INTO student_enrollment_codes (syear,title,short_name,type,default_code,sort_order)
  VALUES (2026,'Fin de cycle','FINC','Drop',NULL,7);

  SELECT count(*) INTO n FROM student_enrollment_codes WHERE syear=2026;
  IF n<>7 THEN RAISE EXCEPTION 'codes 2026 attendus 7 après le lot, trouvés %', n; END IF;
END $$;

COMMIT;
