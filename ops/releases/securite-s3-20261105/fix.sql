-- Sécurité, lot S3 (5 novembre 2026) : codes de présence et formulaire d'incident en français.
-- docs/qualification/ANALYSE_SECURITE_20261105.md (C1). L'effet des codes (STATE_CODE A/P) est inchangé.
-- Une seule transaction ; toute divergence annule tout. Retour arrière : rollback.sql.
\set ON_ERROR_STOP on
BEGIN;
CREATE TABLE schoolsafe_bak_s3_attendance_codes AS SELECT * FROM attendance_codes;
CREATE TABLE schoolsafe_bak_s3_discipline_fields AS SELECT * FROM discipline_fields;
CREATE TABLE schoolsafe_bak_s3_discipline_field_usage AS SELECT * FROM discipline_field_usage;
DO $$
DECLARE n int;
BEGIN
  SELECT count(*) INTO n FROM discipline_referrals;
  IF n<>0 THEN RAISE EXCEPTION 'des incidents existent (%) : traduction des options à revoir', n; END IF;

  UPDATE attendance_codes SET title='Présent' WHERE title='Present';
  UPDATE attendance_codes SET title='Retard', short_name='R' WHERE title='Tardy' AND short_name='T';
  UPDATE attendance_codes SET title='Absence justifiée', short_name='J' WHERE title='Excused Absence' AND short_name='E';
  SELECT count(*) INTO n FROM attendance_codes WHERE title IN ('Present','Tardy','Excused Absence');
  IF n<>0 THEN RAISE EXCEPTION 'codes anglais restants : %', n; END IF;

  UPDATE discipline_fields SET title='Faute' WHERE title='Violation';
  UPDATE discipline_fields SET title='Retenue' WHERE title='Detention Assigned';
  UPDATE discipline_fields SET title='Exclusion (direction seulement)' WHERE title='Suspensions (Office Only)';
  UPDATE discipline_fields SET title='Parents prévenus par l’enseignant' WHERE title='Parents Contacted by Teacher';
  UPDATE discipline_fields SET title='Parents prévenus par la direction' WHERE title='Parent Contacted by Administrator';
  UPDATE discipline_fields SET title='Commentaires' WHERE title='Comments';

  UPDATE discipline_field_usage SET title='Faute', select_options=
    'Absence au cours'||chr(10)||'Langage grossier ou injurieux'||chr(10)||'Insubordination (refus d’obéir, manque de respect)'||chr(10)||
    'État d’ivresse (alcool ou drogue)'||chr(10)||'Bavardage'||chr(10)||'Harcèlement'||chr(10)||'Bagarre'||chr(10)||'Gestes déplacés'||chr(10)||'Autre'
   WHERE title='Violation';
  UPDATE discipline_field_usage SET title='Retenue', select_options=
    '10 minutes'||chr(10)||'20 minutes'||chr(10)||'30 minutes'||chr(10)||'Envisager une exclusion'
   WHERE title='Detention Assigned';
  UPDATE discipline_field_usage SET title='Exclusion (direction seulement)', select_options=
    'Une demi-journée'||chr(10)||'Exclusion interne'||chr(10)||'1 jour'||chr(10)||'2 jours'||chr(10)||'3 jours'||chr(10)||'5 jours'||chr(10)||'7 jours'||chr(10)||'Renvoi définitif'
   WHERE title='Suspensions (Office Only)';
  UPDATE discipline_field_usage SET title='Parents prévenus par l’enseignant' WHERE title='Parents Contacted by Teacher';
  UPDATE discipline_field_usage SET title='Parents prévenus par la direction' WHERE title='Parent Contacted by Administrator';
  UPDATE discipline_field_usage SET title='Commentaires' WHERE title='Comments';

  SELECT count(*) INTO n FROM discipline_field_usage WHERE title IN ('Violation','Detention Assigned','Suspensions (Office Only)','Parents Contacted by Teacher','Parent Contacted by Administrator','Comments');
  IF n<>0 THEN RAISE EXCEPTION 'champs anglais restants : %', n; END IF;
END $$;
COMMIT;
