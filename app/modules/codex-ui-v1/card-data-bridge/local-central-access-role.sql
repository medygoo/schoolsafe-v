\set ON_ERROR_STOP on
BEGIN;
DO $$
BEGIN
  IF current_database() <> 'schoolsafe_cards_isolated' THEN
    RAISE EXCEPTION 'LOCAL_ISOLATED_DATABASE_REQUIRED';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'schoolsafe_auth') THEN
    CREATE ROLE schoolsafe_auth LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE
      NOINHERIT NOREPLICATION NOBYPASSRLS;
  ELSE
    RAISE EXCEPTION 'EXISTING_AUTH_ROLE_REQUIRES_REVIEW';
  END IF;
END $$;
-- No password: remote password authentication remains unavailable until provisioned privately.
GRANT CONNECT ON DATABASE schoolsafe_cards_isolated TO schoolsafe_auth;
GRANT USAGE ON SCHEMA api TO schoolsafe_auth;
GRANT EXECUTE ON FUNCTION api.master_school_access_status(uuid) TO schoolsafe_auth;
COMMIT;
