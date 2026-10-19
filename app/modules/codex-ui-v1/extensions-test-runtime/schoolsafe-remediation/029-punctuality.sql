-- SchoolSafe — Lot U5 : ponctualite (seuils configurables, sans sanction auto).
--
-- Additif et idempotent : aucune table native ni U0-U4 n'est modifiee.
-- - schoolsafe_punctuality_thresholds : seuils par ecole/annee (retards
--   injustifies, minutes cumulees, fenetre glissante, suivi direction).
-- - Vue schoolsafe_punctuality_summary : par eleve, retards resolus
--   (resolved_arrived), minutes cumulees, part justifiee (justification
--   acceptee sur le dossier du meme jour) / injustifiee.
-- Aucune sanction automatique : seuls des suivis (notifications) sont emis.

BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_punctuality_thresholds (
    school_id integer NOT NULL,
    syear integer NOT NULL,
    max_unexcused_lates integer NOT NULL DEFAULT 3,
    max_late_minutes integer NOT NULL DEFAULT 120,
    window_days integer NOT NULL DEFAULT 30,
    notify_direction boolean NOT NULL DEFAULT true,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone,
    CONSTRAINT schoolsafe_punctuality_thresholds_unique UNIQUE (school_id, syear),
    CONSTRAINT schoolsafe_punctuality_thresholds_positive
        CHECK (max_unexcused_lates >= 0 AND max_late_minutes >= 0 AND window_days >= 1)
);

CREATE OR REPLACE VIEW schoolsafe_punctuality_summary AS
SELECT ae.school_id, ae.syear, ae.student_id,
    COUNT(*) AS total_lates,
    COALESCE(SUM(ae.late_minutes), 0) AS total_minutes,
    COUNT(*) FILTER (
        WHERE EXISTS (
            SELECT 1 FROM schoolsafe_case_files cf
            JOIN schoolsafe_justifications j ON j.file_id = cf.id AND j.status = 'accepted'
            WHERE cf.school_id = ae.school_id
              AND cf.student_id = ae.student_id
              AND cf.school_date = ae.school_date
        )
    ) AS justified_lates,
    COUNT(*) FILTER (
        WHERE NOT EXISTS (
            SELECT 1 FROM schoolsafe_case_files cf
            JOIN schoolsafe_justifications j ON j.file_id = cf.id AND j.status = 'accepted'
            WHERE cf.school_id = ae.school_id
              AND cf.student_id = ae.student_id
              AND cf.school_date = ae.school_date
        )
    ) AS unjustified_lates
FROM schoolsafe_absence_events ae
WHERE ae.status = 'resolved_arrived'
GROUP BY ae.school_id, ae.syear, ae.student_id;

COMMIT;
