-- Retour arriere de creation.sql (ecole 3, classes P1 et P3 du 04/11) : retire seulement ce qui a ete cree.
BEGIN;
DELETE FROM schedule WHERE course_period_id IN (SELECT cp.course_period_id FROM course_periods cp JOIN courses c USING(course_id) WHERE c.school_id=3 AND c.syear=2026 AND c.short_name IN ('P1','P3'));
DELETE FROM schoolsafe_remediation_types WHERE assignment_type_id IN (SELECT assignment_type_id FROM gradebook_assignment_types t JOIN courses c USING(course_id) WHERE c.school_id=3 AND c.syear=2026 AND c.short_name IN ('P1','P3'));
DELETE FROM gradebook_assignment_types WHERE course_id IN (SELECT course_id FROM courses WHERE school_id=3 AND syear=2026 AND short_name IN ('P1','P3'));
DELETE FROM course_period_school_periods WHERE course_period_id IN (SELECT cp.course_period_id FROM course_periods cp JOIN courses c USING(course_id) WHERE c.school_id=3 AND c.syear=2026 AND c.short_name IN ('P1','P3'));
DELETE FROM course_periods WHERE course_id IN (SELECT course_id FROM courses WHERE school_id=3 AND syear=2026 AND short_name IN ('P1','P3'));
DELETE FROM courses WHERE school_id=3 AND syear=2026 AND short_name IN ('P1','P3');
DELETE FROM course_subjects WHERE school_id=3 AND syear=2026 AND title='|fr_FR.utf8:Primaire' AND NOT EXISTS (SELECT 1 FROM courses c WHERE c.subject_id=course_subjects.subject_id);
COMMIT;
