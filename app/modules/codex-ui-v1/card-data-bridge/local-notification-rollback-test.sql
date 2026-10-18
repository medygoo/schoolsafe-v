begin;
do $$
declare school uuid; student uuid; parent uuid; event uuid:=gen_random_uuid(); arrival uuid:=gen_random_uuid(); pickup uuid:=gen_random_uuid(); verification uuid:=gen_random_uuid(); collected integer;
begin
 if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
 select school_id,student_id,profile_id into school,student,parent
 from app.student_guardians where is_primary and is_active and profile_id is not null limit 1;
 if parent is null then raise exception 'PRIMARY_FIXTURE_REQUIRED'; end if;
 -- Explicit synthetic notification fixture, not a real fee scan. Rolled back below.
 insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
 values(event,school,'scan_control.fees','student',student,
  jsonb_build_object('result','FEE_STATUS','decision','allowed','balance_status','partial','fee_scan_id',null,'test_fixture','TEST CODEX ROLLBACK'),clock_timestamp());
 insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
 values(verification,school,'scan_control.verify','student',student,
  jsonb_build_object('result','VALID','decision','allowed','test_fixture','TEST CODEX ROLLBACK'),clock_timestamp());
 perform card_bridge_notifications.collect();
 if not exists(select 1 from card_bridge_notifications.outbox where source_id=event and recipient_profile_id=parent and payload->>'balanceStatus'='partial') then raise exception 'NOTIFICATION_MISSING'; end if;
 perform card_bridge_notifications.collect();
 select count(*) into collected from card_bridge_notifications.outbox where source_id=event;
 if collected<>1 then raise exception 'DUPLICATE_NOTIFICATION'; end if;
 if exists(select 1 from card_bridge_notifications.outbox where source_id=verification) then raise exception 'VERIFY_MUST_NOT_NOTIFY'; end if;
 raise notice 'PASS financial fallback, verify stays silent, deduplication; synthetic fixtures rolled back. Arrival/pickup positive fixtures unavailable: native enrolled-student guard must remain intact.';
end $$;
rollback;
