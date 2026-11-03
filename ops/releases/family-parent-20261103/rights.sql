-- F3 : le profil Parent (3) consulte l'onglet Famille et les personnes de récupération,
-- et peut suspendre / réactiver (le serveur n'autorise que le responsable principal).
\set ON_ERROR_STOP on
BEGIN;
INSERT INTO profile_exceptions(profile_id, modname, can_use, can_edit)
SELECT 3, v.m, 'Y', v.e FROM (VALUES
  ('Students/Student.php&category_id=' || (SELECT id FROM student_field_categories WHERE include='SchoolSafe/FamilyTab'), NULL::varchar),
  ('SchoolSafe/FamilyRead.php', NULL::varchar),
  ('SchoolSafe/ParentToggle.php', 'Y')) v(m, e)
WHERE NOT EXISTS (SELECT 1 FROM profile_exceptions x WHERE x.profile_id=3 AND x.modname=v.m);
COMMIT;
SELECT modname, can_use, can_edit FROM profile_exceptions WHERE profile_id=3
 AND (modname LIKE 'SchoolSafe/%' OR modname LIKE 'Students/Student.php&category_id=%') ORDER BY 1;
