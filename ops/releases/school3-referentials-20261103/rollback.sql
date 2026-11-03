-- Annule fix.sql (school3-referentials-20261103).
\set ON_ERROR_STOP on
BEGIN;
UPDATE custom_fields SET select_options=E'White, Non-Hispanic\r\nBlack, Non-Hispanic\r\nAmer. Indian or Alaskan Native\r\nAsian or Pacific Islander\r\nHispanic\r\nOther'
 WHERE id=200000001 AND select_options IS NULL;
UPDATE school_gradelevels SET title='7th' WHERE id=26 AND school_id=3;
UPDATE school_gradelevels SET title='8th' WHERE id=27 AND school_id=3;
UPDATE school_gradelevels SET next_grade_id=NULL WHERE school_id=3 AND id BETWEEN 19 AND 26;
COMMIT;
