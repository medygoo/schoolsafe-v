BEGIN;
DO $$ BEGIN
IF current_database() <> 'schoolsafe_card_bridge_test' THEN RAISE EXCEPTION 'Isolated mapping database required'; END IF;
END $$;
INSERT INTO card_bridge.school_map(source_instance,native_school_id,target_school_id)
VALUES('codex-addons-local',1,'8f1c3850-6568-4a26-8312-ed582c95891b') ON CONFLICT DO NOTHING;
INSERT INTO card_bridge.entity_map(source_instance,native_school_id,entity_type,native_entity_id,target_entity_id) VALUES
('codex-addons-local',1,'staff',1,'04286836-0c33-4744-aec2-dfab27c15f22'),
('codex-addons-local',1,'staff',2,'9132f9e3-dead-4283-a31d-3c482ab14a22'),
('codex-addons-local',1,'staff',3,'9889f9f4-ba6b-4a0b-8103-b80fcb1da11c'),
('codex-addons-local',1,'staff',4,'d591171e-08ea-4891-bf68-f0a52fa573fe'),
('codex-addons-local',1,'student',1,'46141a61-9270-4164-bce4-655ac96a5dd1'),
('codex-addons-local',1,'student',2,'23b7054e-3e9d-4141-b1ca-fbf175a94647') ON CONFLICT DO NOTHING;
COMMIT;
