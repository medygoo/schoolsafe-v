-- Annule category.sql : retire l'onglet (droits puis catégorie) et rend les titres d'origine.
\set ON_ERROR_STOP on
BEGIN;
DELETE FROM profile_exceptions WHERE modname = 'Students/Student.php&category_id='
  || (SELECT id FROM student_field_categories WHERE include='SchoolSafe/FamilyTab');
DELETE FROM student_field_categories WHERE include='SchoolSafe/FamilyTab';
UPDATE student_field_categories SET title=v.t, sort_order=v.s
  FROM (VALUES (1,'General Info',1), (3,'Addresses & Contacts',2), (2,'Medical',3),
               (4,'Comments',4), (5,'Food Service',5), (6,'Entry and Exit',NULL::int)) v(id,t,s)
 WHERE student_field_categories.id=v.id;
COMMIT;
