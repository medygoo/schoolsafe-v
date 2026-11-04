-- Ecole 3 (04/11, lot 4) : les 3 classes « font l'appel » (case native), pour que l'appel rapide compte aussi dans les ecrans natifs.
BEGIN;
UPDATE course_periods SET does_attendance=',0,' WHERE school_id=3 AND syear=2026 AND course_period_id IN (1,4,5) AND does_attendance IS NULL;
COMMIT;
