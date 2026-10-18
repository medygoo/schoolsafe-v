\set ON_ERROR_STOP on
begin isolation level read committed;
set local lock_timeout = '5s';
do $$
declare
  school uuid; student uuid; recipient uuid; actor uuid; campaign uuid;
  source uuid; kind text;
begin
  if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
  if to_regclass('card_bridge_notifications.binding_activation') is null then
    raise exception 'TRANSIENT_BINDING_MIGRATION_REQUIRED'; end if;
  select g.school_id,g.student_id,g.profile_id into school,student,recipient
    from app.student_guardians g join iam.profiles p on p.id=g.profile_id and p.school_id=g.school_id
    where g.is_primary and g.is_active and p.is_active and p.account_status='active'
      and app.is_student_operational(g.student_id)
    order by g.school_id,g.student_id,g.id limit 1;
  if student is null then
    raise notice 'SKIP security_events entry: no existing operational student with eligible principal';
    raise notice 'SKIP security_events pickup: no existing operational student with eligible principal';
    raise notice 'SKIP fee_control_scans: no existing operational student with eligible principal';
    return;
  end if;
  perform 1 from app.students where id=student and school_id=school for no key update;
  foreach kind in array array['entry','pickup_confirmed'] loop
    source:=gen_random_uuid();
    insert into app.security_events(id,school_id,student_id,event_type,decision,metadata,occurred_at)
      values(source,school,student,kind,'allowed',jsonb_build_object('source','scanner_entry',
        'test_fixture','NOTIFICATION SOURCE ROLLBACK'),clock_timestamp());
    if not exists(select 1 from card_bridge_notifications.outbox
        where source_id=source and recipient_profile_id=recipient
          and event_type=case kind when 'entry' then 'student.arrival' else 'student.pickup' end) then
      raise exception 'SECURITY_SOURCE_BINDING_FAILED'; end if;
    raise notice 'PASS security_events % captures existing principal', kind;
  end loop;
  select id into actor from iam.profiles where school_id=school and is_active
    and account_status='active' order by id limit 1;
  select id into campaign from app.fee_control_campaigns where school_id=school order by id limit 1;
  if actor is null or campaign is null then
    raise notice 'SKIP fee_control_scans: existing same-school actor/campaign unavailable';
  else
    source:=gen_random_uuid();
    insert into app.fee_control_scans(id,school_id,student_id,campaign_id,scanned_by,
      result,student_fee_status,metadata,scanned_at)
      values(source,school,student,campaign,actor,'consulted','partial',
        '{"consultative":true,"test_fixture":"NOTIFICATION SOURCE ROLLBACK"}',clock_timestamp());
    if not exists(select 1 from card_bridge_notifications.outbox where source_id=source
        and recipient_profile_id=recipient and event_type='student.fee_control') then
      raise exception 'FEE_SOURCE_BINDING_FAILED'; end if;
    raise notice 'PASS fee_control_scans captures existing principal';
  end if;
end $$;
-- Existing execute privilege only: activation remains private to the definer.
set local role schoolsafe_card_bridge;
do $$ begin
  if card_bridge_notifications.collect() <> 0 then raise exception 'COLLECTOR_NOT_ZERO'; end if;
  raise notice 'PASS existing bridge role executes zero collector without new grants';
end $$;
reset role;
rollback;
