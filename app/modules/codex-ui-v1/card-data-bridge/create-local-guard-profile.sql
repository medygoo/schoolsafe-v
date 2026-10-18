-- Native Rosario profile data only; no schema or core change.
\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE existing_id bigint;
BEGIN
  IF current_database() <> 'rosario_addons_test' THEN
    RAISE EXCEPTION 'ISOLATED_ROSARIO_DATABASE_REQUIRED';
  END IF;
  LOCK TABLE user_profiles IN SHARE ROW EXCLUSIVE MODE;
  IF (SELECT count(*) FROM user_profiles WHERE lower(title)='gardien') > 1 THEN
    RAISE EXCEPTION 'AMBIGUOUS_GUARD_PROFILE';
  END IF;
  SELECT id INTO existing_id FROM user_profiles WHERE lower(title)='gardien';
  IF existing_id IS NULL THEN
    INSERT INTO user_profiles (title,profile) VALUES ('Gardien','teacher') RETURNING id INTO existing_id;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM user_profiles WHERE id=existing_id AND profile='teacher') THEN
    RAISE EXCEPTION 'GUARD_PROFILE_CATEGORY_MISMATCH';
  END IF;
  IF EXISTS (SELECT 1 FROM profile_exceptions WHERE profile_id=existing_id
             AND (can_use='Y' OR can_edit='Y')) THEN
    RAISE EXCEPTION 'EXISTING_GUARD_PERMISSIONS_REQUIRE_REVIEW';
  END IF;
END $$;
COMMIT;
SELECT p.id,p.title,p.profile,
  (SELECT count(*) FROM profile_exceptions e WHERE e.profile_id=p.id
    AND (e.can_use='Y' OR e.can_edit='Y')) AS enabled_program_permissions,
  (SELECT count(*) FROM staff s WHERE s.profile_id=p.id) AS assigned_accounts
FROM user_profiles p WHERE lower(p.title)='gardien';
