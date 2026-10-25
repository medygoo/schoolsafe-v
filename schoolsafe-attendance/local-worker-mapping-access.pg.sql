BEGIN;
CREATE ROLE schoolsafe_arrival_mapping_reader LOGIN PASSWORD '__WORKER_PASSWORD__'
 NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS CONNECTION LIMIT 2;
ALTER ROLE schoolsafe_arrival_mapping_reader SET default_transaction_read_only=on;
GRANT CONNECT ON DATABASE schoolsafe_card_bridge_test TO schoolsafe_arrival_mapping_reader;
CREATE SCHEMA schoolsafe_arrival_mapping;
REVOKE ALL ON SCHEMA schoolsafe_arrival_mapping FROM PUBLIC;
CREATE VIEW schoolsafe_arrival_mapping.identities WITH (security_barrier=true) AS
 SELECT s.source_instance,s.native_school_id,s.target_school_id,
 m.native_entity_id AS native_student_id,m.target_entity_id AS target_student_id
 FROM card_bridge.school_map s JOIN card_bridge.entity_map m USING(source_instance,native_school_id)
 WHERE s.source_instance='codex-addons-local' AND s.native_school_id=1
 AND s.target_school_id='8f1c3850-6568-4a26-8312-ed582c95891b'::uuid AND m.entity_type='student';
REVOKE ALL ON schoolsafe_arrival_mapping.identities FROM PUBLIC;
GRANT USAGE ON SCHEMA schoolsafe_arrival_mapping TO schoolsafe_arrival_mapping_reader;
GRANT SELECT ON schoolsafe_arrival_mapping.identities TO schoolsafe_arrival_mapping_reader;
COMMIT;
