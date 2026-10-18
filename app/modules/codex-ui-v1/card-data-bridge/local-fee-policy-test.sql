BEGIN;
DO $$
DECLARE school uuid; actor uuid; actor_user uuid; models jsonb; first_id uuid; second_id uuid; denied boolean; event uuid:=gen_random_uuid();
BEGIN
 IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF;
 SELECT p.school_id,p.id,p.user_id INTO school,actor,actor_user FROM iam.profiles p JOIN iam.users u ON u.id=p.user_id WHERE u.external_subject='codex-addons-local:1';
 PERFORM api.set_request_context(actor_user,actor,school,gen_random_uuid());
 PERFORM card_bridge_fees.manage(2026,'save','{"label":"TEST CODEX 150","threshold":"150","tolerance":10}'::jsonb);
 SELECT id INTO first_id FROM card_bridge_fees.models WHERE school_id=school AND label='TEST CODEX 150' ORDER BY created_at DESC LIMIT 1;
 PERFORM card_bridge_fees.manage(2026,'activate',jsonb_build_object('id',first_id));
 IF (card_bridge_fees.active_model(2026)->>'id')::uuid<>first_id THEN RAISE EXCEPTION 'Activation failed'; END IF;
 PERFORM card_bridge_fees.manage(2026,'save','{"label":"TEST CODEX 70","threshold":"70","tolerance":20}'::jsonb);
 SELECT id INTO second_id FROM card_bridge_fees.models WHERE school_id=school AND label='TEST CODEX 70' ORDER BY created_at DESC LIMIT 1;
 PERFORM card_bridge_fees.manage(2026,'activate',jsonb_build_object('id',second_id));
 IF (SELECT count(*) FROM card_bridge_fees.models WHERE school_id=school AND native_year=2026 AND active)<>1 THEN RAISE EXCEPTION 'Multiple active controls'; END IF;
 denied:=false;
 BEGIN PERFORM card_bridge_fees.manage(2027,'activate',jsonb_build_object('id',first_id)); EXCEPTION WHEN insufficient_privilege THEN denied:=true; END;
 IF NOT denied THEN RAISE EXCEPTION 'Wrong year accepted'; END IF;
 PERFORM card_bridge_fees.manage(2026,'stop','{}');
 IF card_bridge_fees.active_model(2026) IS NOT NULL THEN RAISE EXCEPTION 'Stop failed'; END IF;
 INSERT INTO audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
 VALUES(event,school,'bridge.fees.result','student','46141a61-9270-4164-bce4-655ac96a5dd1',
 '{"color":"yellow","label":"TEST CODEX NOTIFICATION ROLLBACK","source":"rosario_billing"}'::jsonb,clock_timestamp());
 PERFORM card_bridge_notifications.collect();
 IF NOT EXISTS(SELECT 1 FROM card_bridge_notifications.outbox WHERE source_id=event AND payload->>'feeColor'='yellow') THEN RAISE EXCEPTION 'Policy notification missing'; END IF;
 PERFORM card_bridge_notifications.collect();
 IF (SELECT count(*) FROM card_bridge_notifications.outbox WHERE source_id=event)<>1 THEN RAISE EXCEPTION 'Duplicate policy notification'; END IF;
 RAISE NOTICE 'PASS manual models, activation, one active control, wrong year denied and manual stop. All fixtures rolled back.';
END $$;
ROLLBACK;
