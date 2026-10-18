\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN IF current_database()<>'schoolsafe_card_bridge_test' THEN RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED'; END IF; END $$;
INSERT INTO card_bridge.entity_map(source_instance,native_school_id,entity_type,native_entity_id,target_entity_id)
VALUES('codex-addons-local',1,'class',1,'04f44e06-cbd6-497e-b682-198b43a54f11'),
('codex-addons-local',1,'academic_year',2026,'34bf2f49-e42c-457b-ae72-4dda87434eaf');
COMMIT;
