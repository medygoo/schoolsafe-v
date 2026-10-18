\set ON_ERROR_STOP on
begin;
set local lock_timeout='5s';
do $$ begin
  if current_database()<>'rosario_addons_test' then raise exception 'ISOLATED_NATIVE_DATABASE_REQUIRED'; end if;
end $$;
create table schoolsafe_notification_push_activation (
  singleton boolean primary key check(singleton), activated_at timestamptz not null
);
-- Deliberately no activation row.
create table schoolsafe_notification_push_delivery (
  event_id uuid not null,
  recipient_profile_id uuid not null,
  event_type text not null check(event_type in ('student.arrival','student.pickup','student.fee_control','student.birthday')),
  endpoint text not null references schoolsafe_notification_push_subscriptions(endpoint),
  staff_id integer not null references staff(staff_id),
  school_id integer not null,
  syear integer not null,
  student_id integer not null references students(student_id),
  state text not null default 'pending' check(state in ('pending','leased','retry','sent','expired','blocked')),
  attempts integer not null default 0 check(attempts between 0 and 5),
  available_at timestamptz not null default clock_timestamp(),
  lease_token uuid, lease_until timestamptz,
  primary key(event_id,endpoint),
  foreign key(school_id,syear) references schools(id,syear)
);
revoke all on schoolsafe_notification_push_activation,schoolsafe_notification_push_delivery from public;
create function schoolsafe_notification_push_claim(p_token uuid)
returns setof schoolsafe_notification_push_delivery language sql volatile set search_path=pg_catalog as $$
  with candidate as (
    select event_id,endpoint from public.schoolsafe_notification_push_delivery
    where attempts<5 and ((state in ('pending','retry') and available_at<=clock_timestamp())
      or (state='leased' and lease_until<=clock_timestamp()))
    order by available_at,event_id,endpoint for update skip locked limit 1
  ) update public.schoolsafe_notification_push_delivery d set
    state='leased',attempts=d.attempts+1,lease_token=p_token,lease_until=clock_timestamp()+interval '60 seconds'
  from candidate c where d.event_id=c.event_id and d.endpoint=c.endpoint returning d.*
$$;
create function schoolsafe_notification_push_finish(p_event uuid,p_endpoint text,p_token uuid,p_status text)
returns boolean language plpgsql set search_path=pg_catalog as $$
declare changed integer;
begin
  if p_status not in ('sent','retry','expired','blocked') then raise exception 'INVALID_DELIVERY_STATUS'; end if;
  update public.schoolsafe_notification_push_delivery set
    state=case when p_status='retry' and attempts>=5 then 'blocked' else p_status end,
    available_at=clock_timestamp()+make_interval(secs=>least(3600,30*(2^attempts)::integer)),
    lease_token=null,lease_until=null
  where event_id=p_event and endpoint=p_endpoint and state='leased' and lease_token=p_token and lease_until>clock_timestamp();
  get diagnostics changed=row_count;
  return changed=1;
end $$;
revoke all on function schoolsafe_notification_push_claim(uuid),schoolsafe_notification_push_finish(uuid,text,uuid,text) from public;
commit;
