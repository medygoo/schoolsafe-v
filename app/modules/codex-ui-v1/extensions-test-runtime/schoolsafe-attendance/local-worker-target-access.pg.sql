BEGIN;
CREATE ROLE schoolsafe_arrival_source_reader LOGIN PASSWORD '__WORKER_PASSWORD__'
 NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 2;
ALTER ROLE schoolsafe_arrival_source_reader SET default_transaction_read_only=on;
GRANT CONNECT ON DATABASE schoolsafe_cards_isolated TO schoolsafe_arrival_source_reader;
CREATE SCHEMA schoolsafe_arrival_source;
REVOKE ALL ON SCHEMA schoolsafe_arrival_source FROM PUBLIC;
CREATE VIEW schoolsafe_arrival_source.arrivals WITH (security_barrier=true) AS
 SELECT id,school_id,student_id,occurred_at,event_type,decision,metadata->>'source' AS source
 FROM app.security_events
 WHERE school_id='8f1c3850-6568-4a26-8312-ed582c95891b'::uuid
 AND event_type='entry' AND decision='allowed' AND metadata->>'source'='scanner_entry'
 AND occurred_at>=TIMESTAMPTZ '__ACTIVATED_AT__';
REVOKE ALL ON schoolsafe_arrival_source.arrivals FROM PUBLIC;
GRANT USAGE ON SCHEMA schoolsafe_arrival_source TO schoolsafe_arrival_source_reader;
GRANT SELECT ON schoolsafe_arrival_source.arrivals TO schoolsafe_arrival_source_reader;
COMMIT;
