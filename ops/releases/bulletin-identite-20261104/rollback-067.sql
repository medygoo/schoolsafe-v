-- Retour arriere de 067 (seulement sur decision du proprietaire) : retire le champ « Lieu de naissance »
-- uniquement s'il n'a encore ete rempli pour aucun eleve ; sinon rien n'est touche (donnees d'eleves).
BEGIN;
DO $$
DECLARE v_id integer; v_used boolean;
BEGIN
  SELECT id INTO v_id FROM custom_fields WHERE title LIKE '%fr_FR.utf8:Lieu de naissance%' LIMIT 1;
  IF v_id IS NULL THEN RETURN; END IF;
  EXECUTE format('SELECT EXISTS (SELECT 1 FROM students WHERE %I IS NOT NULL AND %I <> '''')', 'custom_' || v_id, 'custom_' || v_id) INTO v_used;
  IF v_used THEN RAISE EXCEPTION 'CHAMP_DEJA_REMPLI : rien retire'; END IF;
  EXECUTE format('ALTER TABLE students DROP COLUMN %I', 'custom_' || v_id);
  DELETE FROM custom_fields WHERE id = v_id;
END $$;
COMMIT;
