-- SchoolSafe 067 : champ natif « Lieu de naissance » sur la fiche de l'eleve (04/11, accord du proprietaire
-- « Oui, ajoute-les »), repris par le bulletin RDC (« Né(e) à … le … »). Meme procede que l'ecran natif
-- « Champs de l'eleve » : une ligne dans custom_fields (categorie Informations generales) et une colonne
-- texte dans students. Rejouable : rien n'est cree si le champ existe deja. Aucune donnee modifiee.
BEGIN;
DO $$
DECLARE v_id integer;
BEGIN
  IF EXISTS (SELECT 1 FROM custom_fields WHERE title LIKE '%fr_FR.utf8:Lieu de naissance%' OR title = 'Lieu de naissance') THEN
    RAISE NOTICE 'champ deja present';
    RETURN;
  END IF;
  INSERT INTO custom_fields(type, title, sort_order, category_id)
  VALUES ('text', '|fr_FR.utf8:Lieu de naissance|en_US.utf8:Place of birth', 4.5, 1) RETURNING id INTO v_id;
  EXECUTE format('ALTER TABLE students ADD COLUMN IF NOT EXISTS %I text', 'custom_' || v_id);
  RAISE NOTICE 'champ cree : custom_%', v_id;
END $$;
COMMIT;
