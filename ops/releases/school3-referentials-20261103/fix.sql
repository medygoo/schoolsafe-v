-- École 3 : champ Ethnie sans liste imposée ; niveaux 7/8 en français (système RDC) ;
-- chaîne de passage de classe complète. Une transaction ; toute divergence annule tout.
\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE n int;
BEGIN
  -- Ethnie : le champ et les valeurs saisies restent ; seule la liste de choix US est retirée.
  UPDATE custom_fields SET select_options=NULL WHERE id=200000001 AND title='Ethnie' AND select_options IS NOT NULL;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'Ethnie attendu 1, obtenu %', n; END IF;

  UPDATE school_gradelevels SET title='7ème EB' WHERE id=26 AND school_id=3 AND title='7th';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION '7th attendu 1, obtenu %', n; END IF;
  UPDATE school_gradelevels SET title='8ème EB' WHERE id=27 AND school_id=3 AND title='8th';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION '8th attendu 1, obtenu %', n; END IF;

  -- Passage de classe : Maternelle(19) -> 1ère(20) -> ... -> 6ème(25) -> 7ème EB(26) -> 8ème EB(27) -> fin.
  UPDATE school_gradelevels g SET next_grade_id = v.nxt
    FROM (VALUES (19,20),(20,21),(21,22),(22,23),(23,24),(24,25),(25,26),(26,27)) v(id,nxt)
   WHERE g.id=v.id AND g.school_id=3 AND g.next_grade_id IS NULL;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>8 THEN RAISE EXCEPTION 'chaine attendue 8, obtenue %', n; END IF;
END $$;
COMMIT;
SELECT id, title, next_grade_id FROM school_gradelevels WHERE school_id=3 ORDER BY sort_order;
SELECT title, select_options IS NULL sans_liste FROM custom_fields WHERE id=200000001;
