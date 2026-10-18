begin;
do $$ begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
end $$;
create schema if not exists card_bridge_notifications;
revoke all on schema card_bridge_notifications from public;
create table if not exists card_bridge_notifications.activation (
  singleton boolean primary key default true check (singleton),
  activated_at timestamptz not null default clock_timestamp()
);
insert into card_bridge_notifications.activation(singleton) values(true) on conflict do nothing;
create table if not exists card_bridge_notifications.outbox (
  id uuid primary key default gen_random_uuid(),
  school_id uuid not null,
  student_id uuid not null,
  recipient_profile_id uuid not null,
  source_id uuid not null,
  event_type text not null check(event_type in ('student.arrival','student.pickup','student.fee_control')),
  payload jsonb not null,
  created_at timestamptz not null default now(),
  delivered_at timestamptz,
  unique(source_id,event_type,recipient_profile_id)
);
revoke all on all tables in schema card_bridge_notifications from public;
-- Privileged local collector only; no execution right granted to web users.
create or replace function card_bridge_notifications.collect() returns integer
language plpgsql security invoker set search_path = pg_catalog as $$
declare inserted integer;
begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
  with events as (
    select e.id, e.school_id, e.student_id,
      case e.event_type when 'entry' then 'student.arrival' else 'student.pickup' end as kind,
      jsonb_build_object('occurredAt',e.occurred_at) as payload
    from app.security_events e
    where e.decision = 'allowed'
      and ((e.event_type = 'entry' and e.metadata->>'source' = 'scanner_entry')
        or e.event_type = 'pickup_confirmed')
      and e.occurred_at >= (select activated_at from card_bridge_notifications.activation)
    union all
    select f.id,f.school_id,f.student_id,'student.fee_control',
      jsonb_build_object('occurredAt',f.scanned_at,'balanceStatus',f.student_fee_status)
    from app.fee_control_scans f
    where f.result = 'consulted' and f.metadata->>'consultative' = 'true'
      and f.student_fee_status in ('paid','partial','due','exempt','no_data')
      and f.scanned_at >= (select activated_at from card_bridge_notifications.activation)
  )
  insert into card_bridge_notifications.outbox
    (school_id,student_id,recipient_profile_id,source_id,event_type,payload)
  select e.school_id,e.student_id,g.profile_id,e.id,e.kind,e.payload
  from events e join app.student_guardians g
    on g.school_id=e.school_id and g.student_id=e.student_id
      and g.is_primary and g.is_active and g.profile_id is not null
  join iam.profiles p on p.id=g.profile_id and p.school_id=g.school_id
  on conflict(source_id,event_type,recipient_profile_id) do nothing;
  get diagnostics inserted = row_count;
  return inserted;
end $$;
revoke all on function card_bridge_notifications.collect() from public;
commit;
