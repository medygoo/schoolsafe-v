\set ON_ERROR_STOP on
BEGIN;
UPDATE staff_field_categories SET title=v.o FROM (VALUES (1,'General Info','Informations générales'),(2,'Schedule','Emploi du temps'),(3,'Food Service','Cantine')) v(id,o,t) WHERE staff_field_categories.id=v.id AND staff_field_categories.title=v.t;
COMMIT;
