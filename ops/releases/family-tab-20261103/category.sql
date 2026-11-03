-- F2 : catégorie native « Famille et récupération » dans la fiche élève,
-- droits (administrateurs d'école) et titres d'onglets en français.
\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE n int; cat int;
BEGIN
  SELECT id INTO cat FROM student_field_categories WHERE include='SchoolSafe/FamilyTab';
  IF cat IS NULL THEN
    INSERT INTO student_field_categories(title, sort_order, include)
         VALUES ('Famille et récupération', 2, 'SchoolSafe/FamilyTab') RETURNING id INTO cat;
  END IF;

  INSERT INTO profile_exceptions(profile_id, modname, can_use, can_edit)
  SELECT p, 'Students/Student.php&category_id=' || cat, 'Y', 'Y' FROM unnest(ARRAY[1,4,7]) p
   WHERE NOT EXISTS (SELECT 1 FROM profile_exceptions e WHERE e.profile_id=p AND e.modname='Students/Student.php&category_id=' || cat);

  UPDATE student_field_categories SET title=v.t, sort_order=v.s
    FROM (VALUES (1,'Informations générales',1), (3,'Adresses et contacts',3), (2,'Médical',4),
                 (4,'Commentaires',5), (5,'Cantine',6), (6,'Entrées et sorties',7)) v(id,t,s)
   WHERE student_field_categories.id=v.id;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>6 THEN RAISE EXCEPTION 'categories natives attendues 6, obtenues %', n; END IF;
  PERFORM set_config('f2.cat', cat::text, true);
END $$;
COMMIT;
SELECT id, title, sort_order, include FROM student_field_categories ORDER BY sort_order;
SELECT profile_id, modname, can_use, can_edit FROM profile_exceptions WHERE modname LIKE 'Students/Student.php&category_id=%'
   AND modname = 'Students/Student.php&category_id=' || (SELECT id FROM student_field_categories WHERE include='SchoolSafe/FamilyTab');
