-- Annulation : noms anglais d'origine (ecole 3).
BEGIN;
UPDATE school_periods SET title='Full Day', short_name='FD' WHERE school_id=3 AND syear=2026 AND period_id=23;
UPDATE school_periods SET title='Half Day AM', short_name='AM' WHERE school_id=3 AND syear=2026 AND period_id=24;
UPDATE school_periods SET title='Half Day PM', short_name='PM' WHERE school_id=3 AND syear=2026 AND period_id=25;
UPDATE school_periods SET title='Period '||(period_id-25)::text WHERE school_id=3 AND syear=2026 AND period_id BETWEEN 26 AND 33;
UPDATE report_card_grade_scales SET title='Main' WHERE school_id=3 AND syear=2026 AND id=3;
UPDATE course_periods SET title=replace(title,'Journée entière - ','Full Day - ') WHERE school_id=3 AND syear=2026 AND title LIKE 'Journée entière - %';
COMMIT;
