-- Retour arriere des horaires d'arrivee de l'ecole 3 enregistres le 04/11 (en vigueur le 05/11).
BEGIN;
DELETE FROM schoolsafe_attendance_policy WHERE school_id=3 AND syear=2026 AND course_period_id IS NULL AND effective_on='2026-11-05'
  AND arrival_time='07:30' AND closure_time='07:45' AND detection_time='08:00';
COMMIT;
