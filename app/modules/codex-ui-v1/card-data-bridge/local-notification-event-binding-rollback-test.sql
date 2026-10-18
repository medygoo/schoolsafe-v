\set ON_ERROR_STOP on
-- Run only after a separately approved migration application. No migration included here.
begin isolation level read committed;
set local lock_timeout = '5s';
do $$
declare
  school uuid; student uuid; parent uuid; guardian uuid; replacement uuid;
  event uuid; saved_at timestamptz; body jsonb; baseline bigint; cases integer := 0;
begin
  if current_database() <> 'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
  if to_regclass('card_bridge_notifications.binding_activation') is null then
    raise exception 'BINDING_MIGRATION_NOT_APPLIED';
  end if;
  if (select count(*) from pg_trigger where tgname = 'aa_notification_bind_recipient'
      and tgenabled = 'O' and tgrelid in ('app.security_events'::regclass,
        'app.fee_control_scans'::regclass,'audit.events'::regclass)) <> 3 then
    raise exception 'BINDING_TRIGGERS_REQUIRED';
  end if;
  select activated_at into strict saved_at
    from card_bridge_notifications.binding_activation where singleton;
  -- Existing identities only; fixture changes and events are all rolled back.
  select g.school_id,g.student_id,g.profile_id,g.id into school,student,parent,guardian
    from app.student_guardians g join iam.profiles p on p.id=g.profile_id and p.school_id=g.school_id
    where g.is_primary and g.is_active and p.is_active and p.account_status='active'
    order by g.school_id,g.student_id,g.id limit 1;
  if parent is null then raise exception 'EXISTING_ACTIVE_PRIMARY_FIXTURE_REQUIRED'; end if;
  perform 1 from app.students where id=student and school_id=school for no key update;
  select count(*) into baseline from card_bridge_notifications.outbox;
  body := jsonb_build_object('result','FEE_STATUS','decision','allowed',
    'balance_status','partial','fee_scan_id',null,'test_fixture','BINDING_ROLLBACK');

  event := gen_random_uuid();
  insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
    values(event,school,'scan_control.fees','student',student,body,saved_at - interval '1 microsecond');
  if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
    raise exception 'HISTORY_WAS_BOUND'; end if;

  -- Empty activation table means disabled, never fallback to the old activation.
  delete from card_bridge_notifications.binding_activation where singleton;
  event := gen_random_uuid();
  insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
    values(event,school,'scan_control.fees','student',student,body,clock_timestamp());
  if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
    raise exception 'UNACTIVATED_BINDING'; end if;
  insert into card_bridge_notifications.binding_activation values(true,saved_at);

  event := gen_random_uuid();
  insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
    values(event,school,'scan_control.fees','student',student,body,clock_timestamp());
  if not exists(select 1 from card_bridge_notifications.outbox
      where source_id=event and recipient_profile_id=parent and payload->>'balanceStatus'='partial') then
    raise exception 'EVENT_TIME_RECIPIENT_MISSING'; end if;

  -- Move the principal flag to a second EXISTING eligible link when available.
  select g.id into replacement from app.student_guardians g
    join iam.profiles p on p.id=g.profile_id and p.school_id=g.school_id
    where g.school_id=school and g.student_id=student and g.id<>guardian
      and g.is_active and g.profile_id<>parent and p.is_active and p.account_status='active'
    order by g.id limit 1;
  if replacement is not null then
    update app.student_guardians set is_primary=false where id=guardian;
    update app.student_guardians set is_primary=true where id=replacement;
    if card_bridge_notifications.collect() <> 0 then raise exception 'COLLECTOR_NOT_ZERO'; end if;
    if (select count(*) from card_bridge_notifications.outbox where source_id=event)=1
        and exists(select 1 from card_bridge_notifications.outbox
          where source_id=event and recipient_profile_id=parent) then
      raise notice 'PASS existing-link transfer retains event-time recipient';
    else raise exception 'HISTORY_RECIPIENT_CHANGED'; end if;
    update app.student_guardians set is_primary=false where id=replacement;
    update app.student_guardians set is_primary=true where id=guardian;
  else raise notice 'SKIP transfer: second existing eligible guardian unavailable';
  end if;

  -- No primary at insertion must never become a late-bound notification.
  update app.student_guardians set is_primary=false where id=guardian;
  event := gen_random_uuid();
  insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
    values(event,school,'scan_control.fees','student',student,body,clock_timestamp());
  update app.student_guardians set is_primary=true where id=guardian;
  perform card_bridge_notifications.collect();
  if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
    raise exception 'ABSENT_PRIMARY_BOUND_LATE'; end if;

  for body in select value from jsonb_array_elements('[
    null, [], {},
    {"result":"FEE_STATUS","decision":"allowed","balance_status":"partial"},
    {"result":"FEE_STATUS","decision":"allowed","balance_status":true,"fee_scan_id":null},
    {"result":"FEE_STATUS","decision":"allowed","balance_status":"partial","fee_scan_id":"null"},
    {"result":"FEE_STATUS","decision":true,"balance_status":"partial","fee_scan_id":null},
    {"result":"FEE_STATUS","decision":"allowed","balance_status":"unexpected","fee_scan_id":null}
  ]'::jsonb) loop
    event := gen_random_uuid();
    insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
      values(event,school,'scan_control.fees','student',student,body,clock_timestamp());
    if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
      raise exception 'MALFORMED_AUDIT_ACCEPTED'; end if;
    cases := cases + 1;
  end loop;
  for body in select value from jsonb_array_elements('[
    {"source":"rosario_billing","color":"yellow","label":true},
    {"source":"rosario_billing","color":true,"label":"fixture"},
    {"source":"rosario_billing","color":"yellow","label":" "},
    {"source":true,"color":"yellow","label":"fixture"}
  ]'::jsonb) loop
    event := gen_random_uuid();
    insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
      values(event,school,'bridge.fees.result','student',student,body,clock_timestamp());
    if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
      raise exception 'MALFORMED_POLICY_ACCEPTED'; end if;
    cases := cases + 1;
  end loop;

  event := gen_random_uuid();
  insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
    values(event,school,'scan_control.verify','student',student,
      '{"result":"VALID","decision":"allowed"}',clock_timestamp());
  if exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
    raise exception 'VERIFY_NOT_SILENT'; end if;

  -- Subtransaction rollback must remove both source event and recipient snapshot.
  event := gen_random_uuid();
  begin
    insert into audit.events(id,school_id,event_type,entity_type,entity_id,payload,occurred_at)
      values(event,school,'bridge.fees.result','student',student,
        '{"source":"rosario_billing","color":"yellow","label":"BINDING ROLLBACK"}',clock_timestamp());
    if not exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
      raise exception 'POLICY_BINDING_MISSING'; end if;
    raise exception using errcode='ZX001', message='EXPECTED_ROLLBACK';
  exception when sqlstate 'ZX001' then null;
  end;
  if exists(select 1 from audit.events where id=event)
      or exists(select 1 from card_bridge_notifications.outbox where source_id=event) then
    raise exception 'ROLLED_BACK_BINDING_SURVIVED'; end if;
  if card_bridge_notifications.collect() <> 0 then raise exception 'COLLECTOR_NOT_ZERO'; end if;
  if (select count(*) from card_bridge_notifications.outbox) <> baseline + 1 then
    raise exception 'UNEXPECTED_OUTBOX_ROWS'; end if;
  raise notice 'PASS activation/history, capture, absent principal, % malformed cases, verify and rollback. Synthetic audit events only; no real scan.', cases;
end $$;
rollback;
