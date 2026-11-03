\set ON_ERROR_STOP on
BEGIN;
DELETE FROM profile_exceptions WHERE profile_id=3 AND modname IN ('SchoolSafe/FamilyRead.php','SchoolSafe/ParentToggle.php',
  'Students/Student.php&category_id=' || (SELECT id FROM student_field_categories WHERE include='SchoolSafe/FamilyTab'));
COMMIT;
