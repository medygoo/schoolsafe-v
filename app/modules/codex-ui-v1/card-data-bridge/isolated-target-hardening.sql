\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE item record;
BEGIN
    FOR item IN SELECT nspname FROM pg_namespace
        WHERE nspname NOT LIKE 'pg_%' AND nspname <> 'information_schema'
    LOOP
        EXECUTE format('REVOKE ALL ON SCHEMA %I FROM PUBLIC', item.nspname);
        EXECUTE format('REVOKE ALL ON ALL FUNCTIONS IN SCHEMA %I FROM PUBLIC', item.nspname);
        EXECUTE format('REVOKE ALL ON ALL TABLES IN SCHEMA %I FROM PUBLIC', item.nspname);
        EXECUTE format('REVOKE ALL ON ALL SEQUENCES IN SCHEMA %I FROM PUBLIC', item.nspname);
        EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA %I REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC', item.nspname);
    END LOOP;
END $$;
COMMIT;
