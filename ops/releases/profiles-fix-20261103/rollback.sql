-- Annule fix.sql (profils-fix-20261103). Sauvegardes complètes : *-before.private.csv et database-before.private.dump.
\set ON_ERROR_STOP on
BEGIN;
UPDATE user_profiles SET title=regexp_replace(title,'^Obsolète — ','') WHERE id=10;
UPDATE staff SET profile_id=10 WHERE staff_id=6 AND syear=2026 AND profile_id=7;
DELETE FROM profile_exceptions WHERE profile_id=7
  AND modname LIKE ANY (ARRAY['SchoolSafe/%','SchoolSafe_Jaspe/%','Student_ID_Card/%']);
UPDATE staff SET schools=',3' WHERE staff_id IN (26,27) AND syear=2026;
UPDATE staff SET profile_id=NULL WHERE staff_id IN (13,14,15,16,17,18,26,27) AND syear=2026 AND profile_id=3;
COMMIT;
