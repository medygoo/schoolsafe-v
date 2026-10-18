\set ON_ERROR_STOP on
begin;
set local lock_timeout = '5s';
do $$ begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
  if to_regclass('card_bridge_notifications.outbox') is null
      or to_regclass('card_bridge_notifications.one_notification_per_source') is null then
    raise exception 'NOTIFICATION_HISTORY_SCHEMA_REQUIRED';
  end if;
  if not exists (select 1 from pg_index i
      where i.indexrelid = 'card_bridge_notifications.one_notification_per_source'::regclass
        and i.indrelid = 'card_bridge_notifications.outbox'::regclass
        and i.indisunique and i.indisvalid and i.indnkeyatts = 2
        and i.indpred is null
        and pg_get_indexdef(i.indexrelid,1,true) = 'source_id'
        and pg_get_indexdef(i.indexrelid,2,true) = 'event_type') then
    raise exception 'UNIQUE_SOURCE_EVENT_INDEX_REQUIRED';
  end if;
end $$;

-- Block source inserts and the old collector while switching atomically.
-- No student/guardian row locks are acquired by the migration itself.
lock table app.security_events, app.fee_control_scans, audit.events,
  card_bridge_notifications.outbox in share row exclusive mode;

create table if not exists card_bridge_notifications.binding_activation (
  singleton boolean primary key check (singleton),
  activated_at timestamptz not null
);
revoke all on card_bridge_notifications.binding_activation from public;

create or replace function card_bridge_notifications.bind_event_recipient()
returns trigger language plpgsql security definer set search_path = pg_catalog as $$
declare
  source_at timestamptz;
  activation_at timestamptz;
  school uuid;
  student uuid;
  kind text;
  details jsonb;
  body jsonb;
  recipient uuid;
  primary_count integer;
begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
  select activated_at into activation_at
    from card_bridge_notifications.binding_activation where singleton;
  if activation_at is null then return new; end if;

  if tg_table_schema = 'app' and tg_table_name = 'security_events' then
    if new.decision is distinct from 'allowed'
        or jsonb_typeof(new.metadata) is distinct from 'object' then return new; end if;
    if new.event_type = 'entry'
        and new.metadata->'source' = '"scanner_entry"'::jsonb then
      kind := 'student.arrival';
    elsif new.event_type = 'pickup_confirmed' then
      kind := 'student.pickup';
    else return new;
    end if;
    school := new.school_id; student := new.student_id; source_at := new.occurred_at;
    details := jsonb_build_object('occurredAt', source_at);
  elsif tg_table_schema = 'app' and tg_table_name = 'fee_control_scans' then
    if new.result is distinct from 'consulted'
        or jsonb_typeof(new.metadata) is distinct from 'object'
        or new.metadata->'consultative' is distinct from 'true'::jsonb
        or new.student_fee_status is null
        or new.student_fee_status not in ('paid','partial','due','exempt','no_data') then
      return new;
    end if;
    school := new.school_id; student := new.student_id; source_at := new.scanned_at;
    kind := 'student.fee_control';
    details := jsonb_build_object('occurredAt', source_at, 'balanceStatus', new.student_fee_status);
  elsif tg_table_schema = 'audit' and tg_table_name = 'events' then
    if new.entity_type is distinct from 'student'
        or jsonb_typeof(new.payload) is distinct from 'object' then return new; end if;
    body := new.payload;
    school := new.school_id; student := new.entity_id; source_at := new.occurred_at;
    kind := 'student.fee_control';
    if new.event_type = 'scan_control.fees' then
      -- JSON types matter: missing/null/string booleans must not pass as success.
      if body->'result' is distinct from '"FEE_STATUS"'::jsonb
          or body->'decision' is distinct from '"allowed"'::jsonb
          or jsonb_typeof(body->'balance_status') is distinct from 'string'
          or body->>'balance_status' not in ('paid','partial','due','exempt','no_data')
          or not (body ? 'fee_scan_id')
          or body->'fee_scan_id' is distinct from 'null'::jsonb then return new; end if;
      details := jsonb_build_object('occurredAt', source_at, 'balanceStatus', body->>'balance_status');
    elsif new.event_type = 'bridge.fees.result' then
      if body->'source' is distinct from '"rosario_billing"'::jsonb
          or jsonb_typeof(body->'color') is distinct from 'string'
          or body->>'color' not in ('blue','yellow','red')
          or jsonb_typeof(body->'label') is distinct from 'string'
          or length(btrim(body->>'label')) not between 1 and 500 then return new; end if;
      details := jsonb_build_object('occurredAt', source_at,
        'feeColor', body->>'color', 'feeLabel', body->>'label');
    else return new;
    end if;
  else raise exception 'UNSUPPORTED_NOTIFICATION_SOURCE';
  end if;

  -- Source defaults use now(): pre-activation transactions are intentionally excluded.
  if school is null or student is null or source_at is null
      or source_at < activation_at or statement_timestamp() < activation_at then return new; end if;
  if current_setting('transaction_isolation') <> 'read committed' then
    raise exception 'NOTIFICATION_BINDING_REQUIRES_READ_COMMITTED';
  end if;

  -- Same order as family_primary_transfer: student -> guardians -> outbox.
  -- NO KEY UPDATE avoids FK KEY SHARE lock-upgrade deadlocks between scans.
  perform 1 from app.students where id = student and school_id = school for no key update;
  if not found then return new; end if;
  select count(*) into primary_count from app.student_guardians g
    where g.school_id = school and g.student_id = student and g.is_primary and g.is_active;
  if primary_count <> 1 then return new; end if;
  select g.profile_id into recipient from app.student_guardians g
    join iam.profiles p on p.id = g.profile_id and p.school_id = g.school_id
      and p.is_active and p.account_status = 'active'
    where g.school_id = school and g.student_id = student and g.is_primary and g.is_active;
  if recipient is null then return new; end if;
  insert into card_bridge_notifications.outbox
    (school_id,student_id,recipient_profile_id,source_id,event_type,payload)
    values (school,student,recipient,new.id,kind,details || jsonb_build_object('recipientBindingVersion',1))
    on conflict (source_id,event_type) do nothing;
  return new;
end $$;
revoke all on function card_bridge_notifications.bind_event_recipient() from public;

drop trigger if exists aa_notification_bind_recipient on app.security_events;
create trigger aa_notification_bind_recipient before insert on app.security_events
  for each row execute function card_bridge_notifications.bind_event_recipient();
drop trigger if exists aa_notification_bind_recipient on app.fee_control_scans;
create trigger aa_notification_bind_recipient before insert on app.fee_control_scans
  for each row execute function card_bridge_notifications.bind_event_recipient();
drop trigger if exists aa_notification_bind_recipient on audit.events;
create trigger aa_notification_bind_recipient before insert on audit.events
  for each row execute function card_bridge_notifications.bind_event_recipient();

-- Preserve the existing worker contract and ACL; never resolve history again.
create or replace function card_bridge_notifications.collect() returns integer
language plpgsql security definer set search_path = pg_catalog as $$
begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
  if (select count(*) from card_bridge_notifications.binding_activation
      where singleton and activated_at <= clock_timestamp()) <> 1 then
    raise exception 'NOTIFICATION_BINDING_INACTIVE';
  end if;
  return 0;
end $$;

-- Explicit cutover only after all triggers exist. Re-runs never reset history.
insert into card_bridge_notifications.binding_activation(singleton,activated_at)
  values (true,clock_timestamp()) on conflict (singleton) do nothing;
commit;
