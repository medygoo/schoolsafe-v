-- Ecole 3 (04/11) : noms francais des tranches horaires, du bareme et de la classe. Aucun fonctionnement change.
BEGIN;
UPDATE school_periods SET title='Journée entière', short_name='JE' WHERE school_id=3 AND syear=2026 AND period_id=23 AND title='Full Day';
UPDATE school_periods SET title='Matinée', short_name='MAT' WHERE school_id=3 AND syear=2026 AND period_id=24 AND title='Half Day AM';
UPDATE school_periods SET title='Après-midi', short_name='APM' WHERE school_id=3 AND syear=2026 AND period_id=25 AND title='Half Day PM';
UPDATE school_periods SET title=CASE period_id WHEN 26 THEN '1re heure' ELSE (period_id-25)::text||'e heure' END
  WHERE school_id=3 AND syear=2026 AND period_id BETWEEN 26 AND 33 AND title LIKE 'Period %';
UPDATE report_card_grade_scales SET title='Barème principal' WHERE school_id=3 AND syear=2026 AND id=3 AND title='Main';
UPDATE course_periods SET title=replace(title,'Full Day - ','Journée entière - ') WHERE school_id=3 AND syear=2026 AND title LIKE 'Full Day - %';
DO $$ BEGIN
  IF (SELECT count(*) FROM school_periods WHERE school_id=3 AND syear=2026 AND (title ~ '^(Full Day|Half Day|Period )')) <> 0 THEN RAISE EXCEPTION 'tranches non traduites'; END IF;
  IF (SELECT count(*) FROM report_card_grade_scales WHERE school_id=3 AND syear=2026 AND title='Main') <> 0 THEN RAISE EXCEPTION 'bareme non traduit'; END IF;
END $$;
COMMIT;
