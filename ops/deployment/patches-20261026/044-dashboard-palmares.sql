-- 044-dashboard-palmares.sql - 2026-10-25
-- Cause prouvee : fonctionnalite deployee sans sa migration (5 tables absentes,
-- flag SCHOOLSAFE_PALMARES absent). 5 tables NOUVELLES, rien d'existant modifie.
\set ON_ERROR_STOP on
BEGIN;

CREATE TABLE IF NOT EXISTS schoolsafe_dashboard_runs (
  school_id integer NOT NULL,
  syear numeric NOT NULL,
  scope_id integer NOT NULL DEFAULT 0,
  snapshot_date date NOT NULL,
  updated_at timestamp NOT NULL DEFAULT clock_timestamp(),
  CONSTRAINT schoolsafe_dashboard_runs_key UNIQUE (school_id, syear, scope_id, snapshot_date)
);

CREATE TABLE IF NOT EXISTS schoolsafe_palmares_snapshots (
  school_id integer NOT NULL,
  syear numeric NOT NULL,
  scope_id integer NOT NULL DEFAULT 0,
  snapshot_date date NOT NULL,
  student_id integer NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  score numeric(8,2),
  position integer NOT NULL,
  marking_period_id integer NOT NULL REFERENCES school_marking_periods(marking_period_id) ON DELETE CASCADE,
  score_basis text NOT NULL DEFAULT 'native_weighted',
  CONSTRAINT schoolsafe_palmares_snapshots_key UNIQUE (school_id, syear, scope_id, snapshot_date, student_id)
);
CREATE INDEX IF NOT EXISTS idx_palmares_snapshots_reader
  ON schoolsafe_palmares_snapshots (school_id, syear, scope_id, marking_period_id, score_basis, snapshot_date);

CREATE TABLE IF NOT EXISTS schoolsafe_birthday_alerts (
  school_id integer NOT NULL,
  syear numeric NOT NULL,
  student_id integer NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  birthday_date date NOT NULL,
  notice_date date NOT NULL,
  school_open boolean NOT NULL DEFAULT false,
  CONSTRAINT schoolsafe_birthday_alerts_key UNIQUE (school_id, syear, student_id, birthday_date)
);

CREATE TABLE IF NOT EXISTS schoolsafe_palmares_awards (
  id serial PRIMARY KEY,
  school_id integer NOT NULL,
  syear numeric NOT NULL,
  scope_id integer NOT NULL DEFAULT 0,
  position integer NOT NULL,
  marking_period_id integer NOT NULL REFERENCES school_marking_periods(marking_period_id) ON DELETE CASCADE,
  student_id integer NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  active boolean NOT NULL DEFAULT true,
  CONSTRAINT schoolsafe_palmares_awards_key UNIQUE (school_id, syear, scope_id, marking_period_id, student_id)
);

CREATE TABLE IF NOT EXISTS schoolsafe_palmares_events (
  id serial PRIMARY KEY,
  award_id integer NOT NULL REFERENCES schoolsafe_palmares_awards(id) ON DELETE CASCADE,
  created_at timestamp NOT NULL DEFAULT clock_timestamp()
);

UPDATE config SET config_value = 'Y'
WHERE school_id = 3 AND title = 'SCHOOLSAFE_PALMARES';
INSERT INTO config (school_id, title, config_value)
SELECT 3, 'SCHOOLSAFE_PALMARES', 'Y'
WHERE NOT EXISTS (SELECT 1 FROM config WHERE school_id = 3 AND title = 'SCHOOLSAFE_PALMARES');

COMMIT;
