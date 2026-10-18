begin;
do $$ begin
 if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
end $$;
create unique index if not exists one_notification_per_source
 on card_bridge_notifications.outbox(source_id,event_type);
-- Keep the first persisted recipient; transfers must not duplicate old events.
create or replace function card_bridge_notifications.collect() returns integer
language plpgsql security definer set search_path=pg_catalog as $$
declare inserted integer;
begin
 if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
 with events as (
  select e.id,e.school_id,e.student_id,
   case e.event_type when 'entry' then 'student.arrival' else 'student.pickup' end as kind,
   jsonb_build_object('occurredAt',e.occurred_at) as payload
  from app.security_events e
  where e.decision='allowed' and ((e.event_type='entry' and e.metadata->>'source'='scanner_entry') or e.event_type='pickup_confirmed')
   and e.occurred_at >= (select activated_at from card_bridge_notifications.activation)
  union all
  select f.id,f.school_id,f.student_id,'student.fee_control',
   jsonb_build_object('occurredAt',f.scanned_at,'balanceStatus',f.student_fee_status)
  from app.fee_control_scans f
  where f.result='consulted' and f.metadata->>'consultative'='true'
   and f.student_fee_status in ('paid','partial','due','exempt','no_data')
   and f.scanned_at >= (select activated_at from card_bridge_notifications.activation)
  union all
  select a.id,a.school_id,a.entity_id,'student.fee_control',
   jsonb_build_object('occurredAt',a.occurred_at,'balanceStatus',a.payload->>'balance_status')
  from audit.events a
  where a.event_type='scan_control.fees' and a.entity_type='student'
   and a.payload->>'result'='FEE_STATUS' and a.payload->>'decision'='allowed'
   and a.payload->>'balance_status' in ('paid','partial','due','exempt','no_data')
   and a.payload->>'fee_scan_id' is null
   and a.occurred_at >= (select activated_at from card_bridge_notifications.activation)
  union all
  select a.id,a.school_id,a.entity_id,'student.fee_control',
   jsonb_build_object('occurredAt',a.occurred_at,'feeColor',a.payload->>'color','feeLabel',a.payload->>'label')
  from audit.events a where a.event_type='bridge.fees.result' and a.entity_type='student'
   and a.payload->>'source'='rosario_billing' and a.payload->>'color' in ('blue','yellow','red')
   and a.occurred_at >= (select activated_at from card_bridge_notifications.activation)
 )
 insert into card_bridge_notifications.outbox(school_id,student_id,recipient_profile_id,source_id,event_type,payload)
 select e.school_id,e.student_id,g.profile_id,e.id,e.kind,e.payload
 from events e join app.student_guardians g on g.school_id=e.school_id and g.student_id=e.student_id
  and g.is_primary and g.is_active and g.profile_id is not null
 join iam.profiles p on p.id=g.profile_id and p.school_id=g.school_id and p.is_active and p.account_status='active'
 on conflict(source_id,event_type) do nothing;
 get diagnostics inserted=row_count;return inserted;
end $$;
revoke all on function card_bridge_notifications.collect() from public;
grant execute on function card_bridge_notifications.collect() to schoolsafe_card_bridge;
commit;
