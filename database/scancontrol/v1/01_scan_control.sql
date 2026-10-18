\set ON_ERROR_STOP on

-- Additive scan-control layer. Existing Rosario functions stay in place.
-- No CREATE OR REPLACE of attendance_apply, pickup_confirm, card_replace,
-- fee_control_campaign_create, or security scan.

begin;
set local role schoolsafe_owner;

create table if not exists app.scan_control_grants (
  id uuid primary key default pg_catalog.gen_random_uuid(),
  school_id uuid not null,
  profile_id uuid not null,
  capability text not null,
  granted_by uuid not null,
  granted_at timestamptz not null default pg_catalog.now(),
  revoked_at timestamptz,
  revoked_by uuid,
  reason text not null,
  constraint scan_control_grants_school_fkey foreign key (school_id) references app.schools(id) on delete cascade,
  constraint scan_control_grants_profile_fkey foreign key (profile_id) references iam.profiles(id) on delete cascade,
  constraint scan_control_grants_capability_check check (capability in (
    'SCAN_ENTRY','SCAN_PRE_EXIT','SCAN_GATE_EXIT','SCAN_PICKUP','SCAN_FEES','SCAN_VERIFY',
    'CARD_CREATE','CARD_PRINT','CARD_LOST','CARD_REPLACE','CARD_REPRINT','CARD_REVOKE',
    'SCAN_PICKUP_OVERRIDE'
  ))
);

create unique index if not exists scan_control_grants_active_uidx
  on app.scan_control_grants (school_id, profile_id, capability)
  where revoked_at is null;

create table if not exists app.authorized_person_portraits (
  id uuid primary key default pg_catalog.gen_random_uuid(),
  school_id uuid not null,
  guardian_id uuid not null,
  photo_path text not null,
  updated_by uuid not null,
  updated_at timestamptz not null default pg_catalog.now(),
  constraint authorized_person_portraits_school_fkey foreign key (school_id) references app.schools(id) on delete cascade,
  constraint authorized_person_portraits_guardian_fkey foreign key (guardian_id) references app.student_guardians(id) on delete cascade,
  constraint authorized_person_portraits_guardian_key unique (school_id, guardian_id),
  constraint authorized_person_portraits_path_check check (photo_path ~ '^/uploads/authorized-persons/[A-Za-z0-9._/-]+$')
);

alter table app.scan_control_grants enable row level security;
alter table app.scan_control_grants force row level security;
alter table app.authorized_person_portraits enable row level security;
alter table app.authorized_person_portraits force row level security;

do $$
begin
  if not exists (
    select 1 from pg_policies
    where schemaname = 'app' and tablename = 'scan_control_grants' and policyname = 'scan_control_grants_isolation'
  ) then
    create policy scan_control_grants_isolation on app.scan_control_grants
      for all to schoolsafe_owner
      using (school_id = iam.current_school_id())
      with check (school_id = iam.current_school_id());
  end if;
  if not exists (
    select 1 from pg_policies
    where schemaname = 'app' and tablename = 'authorized_person_portraits' and policyname = 'authorized_person_portraits_isolation'
  ) then
    create policy authorized_person_portraits_isolation on app.authorized_person_portraits
      for all to schoolsafe_owner
      using (school_id = iam.current_school_id())
      with check (school_id = iam.current_school_id());
  end if;
end $$;

revoke all on app.scan_control_grants from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_auditor;
revoke all on app.authorized_person_portraits from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_auditor;

create or replace function api.scan_control_require(p_capability text)
returns boolean
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
begin
  if not exists (
    select 1 from app.scan_control_grants g
    where g.school_id = v_school_id
      and g.profile_id = v_profile_id
      and g.capability = p_capability
      and g.revoked_at is null
  ) then
    raise insufficient_privilege using message = format('Scan control capability denied: %s', p_capability);
  end if;
  return true;
end
$schoolsafe$;

create or replace function api.scan_control_grant_set(
  p_profile_id uuid,
  p_capability text,
  p_action text,
  p_reason text
)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_actor uuid := iam.current_profile_id();
  v_id uuid;
begin
  perform iam.require_access('roles.manage', null, null, null);
  if p_action not in ('assign','revoke') then
    raise check_violation using message = 'Scan control action must be assign or revoke';
  end if;
  if p_reason is null or pg_catalog.char_length(pg_catalog.btrim(p_reason)) < 1
     or pg_catalog.char_length(pg_catalog.btrim(p_reason)) > 500 then
    raise check_violation using message = 'Scan control reason is required';
  end if;
  perform 1 from iam.profiles p where p.id = p_profile_id and p.school_id = v_school_id and p.is_active = true;
  if not found then
    raise foreign_key_violation using message = 'Profile not found';
  end if;
  if p_action = 'assign' then
    select g.id into v_id
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = p_profile_id
      and g.capability = p_capability and g.revoked_at is null;
    if v_id is null then
      insert into app.scan_control_grants (school_id, profile_id, capability, granted_by, reason)
      values (v_school_id, p_profile_id, p_capability, v_actor, pg_catalog.btrim(p_reason))
      returning id into v_id;
    end if;
  else
    update app.scan_control_grants
    set revoked_at = pg_catalog.now(), revoked_by = v_actor
    where school_id = v_school_id and profile_id = p_profile_id
      and capability = p_capability and revoked_at is null
    returning id into v_id;
    if v_id is null then
      raise foreign_key_violation using message = 'Active grant not found';
    end if;
  end if;
  perform audit.write_event(
    'scan_control.grant.' || p_action,
    'profile',
    p_profile_id,
    jsonb_build_object('capability', p_capability, 'grant_id', v_id, 'reason', pg_catalog.btrim(p_reason))
  );
  return jsonb_build_object('id', v_id, 'profile_id', p_profile_id, 'capability', p_capability, 'action', p_action, 'granted_by', v_actor);
end
$schoolsafe$;

create or replace function api.scan_control_grant_list()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform iam.require_access('roles.manage', null, null, null);
  return (
    select coalesce(jsonb_agg(jsonb_build_object(
      'id', g.id,
      'profile_id', g.profile_id,
      'operator_name', p.display_name,
      'capability', g.capability,
      'granted_by', g.granted_by,
      'granted_by_name', actor.display_name,
      'granted_at', g.granted_at,
      'revoked_at', g.revoked_at,
      'reason', g.reason
    ) order by p.display_name, g.capability), '[]'::jsonb)
    from app.scan_control_grants g
    join iam.profiles p on p.id = g.profile_id
    left join iam.profiles actor on actor.id = g.granted_by
    where g.school_id = v_school_id and g.revoked_at is null
  );
end
$schoolsafe$;

create or replace function api.scan_control_my_capabilities()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_open boolean;
  v_campaign uuid;
begin
  select c.id into v_campaign
  from app.fee_control_campaigns c
  where c.school_id = v_school_id and c.status = 'published'
    and (c.starts_at is null or c.starts_at <= pg_catalog.now())
    and (c.ends_at is null or c.ends_at >= pg_catalog.now())
  order by c.created_at desc
  limit 1;
  v_open := v_campaign is not null;
  return jsonb_build_object(
    'capabilities', coalesce((
      select jsonb_agg(g.capability order by g.capability)
      from app.scan_control_grants g
      where g.school_id = v_school_id and g.profile_id = v_profile_id and g.revoked_at is null
    ), '[]'::jsonb),
    'fee_campaign_open', v_open,
    'open_campaign_id', v_campaign
  );
end
$schoolsafe$;

create or replace function api.scan_control_card_by_number(p_card_number text, p_capability text)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_card app.student_cards%rowtype;
  v_student app.students%rowtype;
  v_class_name text;
  v_visible boolean;
begin
  perform api.scan_control_require(p_capability);
  select * into v_card
  from app.student_cards c
  where c.school_id = v_school_id and c.card_number = p_card_number;
  if not found then
    return jsonb_build_object('found', false);
  end if;
  select * into v_student from app.students s where s.id = v_card.student_id and s.school_id = v_school_id;
  select c.name into v_class_name from app.classes c where c.id = v_student.class_id;
  v_visible := iam.can_access('school.student.read', null, v_student.id, v_student.class_id);
  return jsonb_build_object(
    'found', true,
    'card_id', v_card.id,
    'card_number', v_card.card_number,
    'status', v_card.status,
    'student_id', case when v_visible then v_student.id else null end,
    'matricule', case when v_visible then v_student.matricule else null end,
    'first_name', case when v_visible then v_student.first_name else null end,
    'last_name', case when v_visible then v_student.last_name else null end,
    'class_id', case when v_visible then v_student.class_id else null end,
    'class_name', case when v_visible then v_class_name else null end,
    'scope_visible', v_visible
  );
end
$schoolsafe$;

create or replace function api.scan_control_presence(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_row app.attendance_records%rowtype;
  v_event record;
begin
  select * into v_row
  from app.attendance_records
  where school_id = v_school_id and student_id = p_student_id and day = current_date;
  select e.id, e.occurred_at, e.scanned_by, p.display_name
    into v_event
  from app.security_events e
  left join iam.profiles p on p.id = e.scanned_by
  where e.school_id = v_school_id and e.student_id = p_student_id
    and e.event_type = 'exit' and e.decision in ('allowed','manual_override')
    and e.occurred_at::date = current_date
  order by e.occurred_at asc
  limit 1;
  return jsonb_build_object(
    'arrived_at', v_row.arrived_at,
    'left_at', v_row.left_at,
    'status', v_row.status,
    'currently_on_site', v_row.arrived_at is not null and v_row.left_at is null,
    'original_exit_time', v_event.occurred_at,
    'original_operator_id', v_event.scanned_by,
    'original_operator', v_event.display_name,
    'original_event', v_event.id
  );
end
$schoolsafe$;

create or replace function api.scan_control_presence_mark(
  p_student_id uuid,
  p_action text,
  p_source_event_id uuid
)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_row app.attendance_records%rowtype;
  v_exit record;
begin
  if p_action = 'check_in' then
    perform api.scan_control_require('SCAN_ENTRY');
  elsif p_action = 'check_out' then
    if not exists (
      select 1 from app.scan_control_grants g
      where g.school_id = v_school_id and g.profile_id = v_profile_id and g.revoked_at is null
        and g.capability in ('SCAN_GATE_EXIT','SCAN_PICKUP','SCAN_PICKUP_OVERRIDE')
    ) then
      raise insufficient_privilege using message = 'Scan control capability denied: SCAN_GATE_EXIT';
    end if;
  else
    raise check_violation using message = 'Presence action must be check_in or check_out';
  end if;
  perform 1 from app.students where id = p_student_id and school_id = v_school_id for update;
  select * into v_row
  from app.attendance_records
  where school_id = v_school_id and student_id = p_student_id and day = current_date
  for update;
  if p_action = 'check_in' then
    if v_row.arrived_at is not null and v_row.left_at is null then
      return jsonb_build_object('result', 'ALREADY_PRESENT');
    end if;
    if v_row.id is null then
      insert into app.attendance_records (school_id, student_id, day, arrived_at, status, source_event_ids, created_by)
      values (v_school_id, p_student_id, current_date, pg_catalog.now(), 'present',
        case when p_source_event_id is null then '{}'::uuid[] else array[p_source_event_id] end,
        v_profile_id);
    elsif v_row.arrived_at is null then
      update app.attendance_records
      set arrived_at = pg_catalog.now(),
          status = 'present',
          source_event_ids = case when p_source_event_id is null then source_event_ids else array_append(source_event_ids, p_source_event_id) end,
          updated_at = pg_catalog.now()
      where id = v_row.id;
    else
      return jsonb_build_object('result', 'ALREADY_PRESENT');
    end if;
    return jsonb_build_object('result', 'PRESENT');
  end if;
  if v_row.left_at is not null then
    select e.id, e.occurred_at, e.scanned_by, p.display_name
      into v_exit
    from app.security_events e
    left join iam.profiles p on p.id = e.scanned_by
    where e.school_id = v_school_id and e.student_id = p_student_id
      and e.event_type = 'exit' and e.decision in ('allowed','manual_override')
      and e.occurred_at::date = current_date
    order by e.occurred_at asc
    limit 1;
    return jsonb_build_object(
      'result', 'ALREADY_EXITED',
      'original_exit_time', v_exit.occurred_at,
      'original_operator_id', v_exit.scanned_by,
      'original_operator', v_exit.display_name,
      'original_event', v_exit.id
    );
  end if;
  if v_row.arrived_at is null then
    return jsonb_build_object('result', 'NOT_ON_SITE');
  end if;
  update app.attendance_records
  set left_at = pg_catalog.now(),
      status = 'left',
      source_event_ids = case when p_source_event_id is null then source_event_ids else array_append(source_event_ids, p_source_event_id) end,
      updated_at = pg_catalog.now()
  where id = v_row.id;
  return jsonb_build_object('result', 'EXITED');
end
$schoolsafe$;

create or replace function api.scan_control_note(
  p_event_type text,
  p_student_id uuid,
  p_payload jsonb
)
returns uuid
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_name text;
  v_payload jsonb;
begin
  select display_name into v_name from iam.profiles where id = iam.current_profile_id();
  v_payload := coalesce(p_payload, '{}'::jsonb) || jsonb_build_object(
    'operator_id', iam.current_profile_id(),
    'operator_name', v_name
  );
  return audit.write_event(p_event_type, 'student', p_student_id, v_payload);
end
$schoolsafe$;

create or replace function api.scan_control_audit_read(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform iam.require_access('security.events.read', null, p_student_id, null);
  return (
    select coalesce(jsonb_agg(row_to_json(q)::jsonb order by q.occurred_at), '[]'::jsonb)
    from (
      select e.occurred_at,
             e.actor_profile_id as operator_id,
             coalesce(e.payload->>'operator_name', p.display_name) as operator_name,
             coalesce(e.payload->>'mode', e.event_type) as mode,
             e.entity_id as student_id,
             e.payload->>'card_id' as card_id,
             e.payload->>'authorized_person_id' as authorized_person_id,
             coalesce(e.payload->>'decision', e.payload->>'result') as decision,
             e.payload->>'result' as result,
             e.payload->>'reason' as reason
      from audit.events e
      left join iam.profiles p on p.id = e.actor_profile_id
      where e.school_id = v_school_id
        and e.event_type like 'scan_control.%'
        and (p_student_id is null or e.entity_id = p_student_id)
      union all
      select s.occurred_at,
             s.scanned_by,
             sp.display_name,
             s.event_type,
             s.student_id,
             s.card_id::text,
             s.authorized_person_id::text,
             s.decision,
             s.decision,
             s.denial_reason
      from app.security_events s
      left join iam.profiles sp on sp.id = s.scanned_by
      where s.school_id = v_school_id
        and (p_student_id is null or s.student_id = p_student_id)
    ) q
  );
end
$schoolsafe$;

create or replace function api.scan_control_on_site_student(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_class_id uuid;
  v_on boolean;
begin
  select class_id into v_class_id from app.students where id = p_student_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  perform iam.require_access('school.student.read', null, p_student_id, v_class_id);
  select arrived_at is not null and left_at is null into v_on
  from app.attendance_records
  where school_id = v_school_id and student_id = p_student_id and day = current_date;
  return jsonb_build_object('student_id', p_student_id, 'currently_on_site', coalesce(v_on, false));
end
$schoolsafe$;

create or replace function api.scan_control_on_site_class(p_class_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_count int;
begin
  perform iam.require_access('school.student.read', null, null, p_class_id);
  select count(*) into v_count
  from app.students s
  join app.attendance_records ar on ar.student_id = s.id and ar.school_id = s.school_id and ar.day = current_date
  where s.school_id = v_school_id and s.class_id = p_class_id
    and ar.arrived_at is not null and ar.left_at is null;
  return jsonb_build_object('class_id', p_class_id, 'currently_on_site', v_count);
end
$schoolsafe$;

create or replace function api.scan_control_on_site_school()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_count int;
begin
  perform iam.require_access('pilotage.dashboard.read', null, null, null);
  select count(*) into v_count
  from app.attendance_records ar
  where ar.school_id = v_school_id and ar.day = current_date
    and ar.arrived_at is not null and ar.left_at is null;
  return jsonb_build_object('currently_on_site', v_count);
end
$schoolsafe$;

create or replace function api.scan_control_teacher_board()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_total int := 0;
  v_present int := 0;
  v_on int := 0;
  v_pre int := 0;
begin
  select count(*),
         count(*) filter (where ar.arrived_at is not null),
         count(*) filter (where ar.arrived_at is not null and ar.left_at is null),
         count(*) filter (where pre.student_id is not null and ar.left_at is null)
    into v_total, v_present, v_on, v_pre
  from app.students s
  join app.student_enrollments e
    on e.student_id = s.id and e.school_id = s.school_id and e.status = 'active' and e.class_id = s.class_id
  join app.classes c on c.id = s.class_id and c.school_id = s.school_id
  left join app.attendance_records ar
    on ar.student_id = s.id and ar.school_id = s.school_id and ar.day = current_date
  left join lateral (
    select ev.student_id
    from app.security_events ev
    where ev.student_id = s.id and ev.school_id = s.school_id
      and ev.event_type = 'exit_prepared' and ev.decision in ('allowed','manual_override')
      and ev.occurred_at::date = current_date
    limit 1
  ) pre on true
  where s.school_id = v_school_id
    and s.lifecycle_status = 'active'
    and (
      c.teacher_profile_id = v_profile_id
      or exists (
        select 1 from app.teacher_assignments ta
        where ta.school_id = s.school_id and ta.class_id = s.class_id
          and ta.teacher_profile_id = v_profile_id and ta.is_active = true
      )
    )
    and iam.can_access('school.student.read', null, s.id, s.class_id);
  return jsonb_build_object(
    'total', v_total,
    'present_today', v_present,
    'absent_today', v_total - v_present,
    'currently_on_site', v_on,
    'pre_exit', v_pre
  );
end
$schoolsafe$;

create or replace function api.scan_control_school_board()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_total int := 0;
  v_present int := 0;
  v_on int := 0;
  v_exited int := 0;
  v_pre int := 0;
  v_pickups int := 0;
begin
  perform iam.require_access('pilotage.dashboard.read', null, null, null);
  select count(*),
         count(*) filter (where ar.arrived_at is not null),
         count(*) filter (where ar.arrived_at is not null and ar.left_at is null),
         count(*) filter (where ar.left_at is not null),
         count(*) filter (where pre.student_id is not null)
    into v_total, v_present, v_on, v_exited, v_pre
  from app.students s
  join app.student_enrollments e
    on e.student_id = s.id and e.school_id = s.school_id and e.status = 'active' and e.class_id = s.class_id
  left join app.attendance_records ar
    on ar.student_id = s.id and ar.school_id = s.school_id and ar.day = current_date
  left join lateral (
    select ev.student_id
    from app.security_events ev
    where ev.student_id = s.id and ev.school_id = s.school_id
      and ev.event_type = 'exit_prepared' and ev.decision in ('allowed','manual_override')
      and ev.occurred_at::date = current_date
    limit 1
  ) pre on true
  where s.school_id = v_school_id and s.lifecycle_status = 'active';
  select count(*) into v_pickups
  from app.security_events
  where school_id = v_school_id and event_type = 'pickup_confirmed' and occurred_at::date = current_date;
  return jsonb_build_object(
    'total_students', v_total,
    'present_today', v_present,
    'absent_today', v_total - v_present,
    'currently_on_site', v_on,
    'exited', v_exited,
    'pre_exit', v_pre,
    'pickups', v_pickups
  );
end
$schoolsafe$;

create or replace function api.scan_control_campaign_transition(p_campaign_id uuid, p_status text)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_status text;
begin
  perform iam.require_access('finance.control.manage', null, null, null);
  if p_status not in ('published','closed','cancelled') then
    raise check_violation using message = 'Campaign status not supported';
  end if;
  select status into v_status
  from app.fee_control_campaigns
  where id = p_campaign_id and school_id = v_school_id
  for update;
  if not found then
    raise foreign_key_violation using message = 'Campaign not found';
  end if;
  if p_status = 'published' and v_status <> 'draft' then
    raise check_violation using message = 'Campaign cannot be published';
  elsif p_status = 'closed' and v_status <> 'published' then
    raise check_violation using message = 'Campaign cannot be closed';
  elsif p_status = 'cancelled' and v_status not in ('draft','published') then
    raise check_violation using message = 'Campaign cannot be cancelled';
  end if;
  update app.fee_control_campaigns
  set status = p_status, updated_at = pg_catalog.now()
  where id = p_campaign_id;
  perform audit.write_event('scan_control.campaign.' || p_status, 'fee_control_campaign', p_campaign_id,
    jsonb_build_object('from', v_status, 'to', p_status));
  return jsonb_build_object('id', p_campaign_id, 'status', p_status);
end
$schoolsafe$;

create or replace function api.scan_control_student_fees(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform api.scan_control_require('SCAN_FEES');
  return (
    select coalesce(jsonb_agg(jsonb_build_object(
      'student_fee_id', sf.id,
      'fee_structure_id', sf.fee_structure_id,
      'status', sf.status,
      'amount_expected', sf.amount_expected,
      'amount_paid', sf.amount_paid,
      'amount_remaining', sf.amount_remaining
    )), '[]'::jsonb)
    from app.student_fees sf
    where sf.school_id = v_school_id and sf.student_id = p_student_id
  );
end
$schoolsafe$;

create or replace function api.scan_control_authorized_people(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
begin
  if not exists (
    select 1 from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = v_profile_id and g.revoked_at is null
      and g.capability in ('SCAN_PICKUP','SCAN_PRE_EXIT','SCAN_GATE_EXIT')
  ) then
    raise insufficient_privilege using message = 'Scan control capability denied: SCAN_PICKUP';
  end if;
  return (
    select coalesce(jsonb_agg(jsonb_build_object(
      'id', g.id,
      'name', g.full_name,
      'relation', g.guardian_type,
      'phone', g.phone,
      'status', coalesce(a.status, case when g.is_active then 'active' else 'inactive' end),
      'authorized', g.is_authorized_pickup and g.is_active,
      'photo_path', coalesce(pr.photo_path, ap.photo_path),
      'photo_source', case
        when pr.photo_path is not null then 'profile'
        when ap.photo_path is not null then 'portrait'
        else null
      end
    ) order by g.is_primary desc, g.full_name), '[]'::jsonb)
    from app.student_guardians g
    left join iam.profiles pr on pr.id = g.profile_id and pr.school_id = g.school_id
    left join app.authorized_person_portraits ap on ap.guardian_id = g.id and ap.school_id = g.school_id
    left join lateral (
      select pa.status
      from app.pickup_authorizations pa
      where pa.guardian_id = g.id and pa.student_id = g.student_id and pa.school_id = g.school_id
      order by pa.updated_at desc nulls last
      limit 1
    ) a on true
    where g.school_id = v_school_id and g.student_id = p_student_id
  );
end
$schoolsafe$;

create or replace function api.scan_control_portrait_set(p_guardian_id uuid, p_photo_path text)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  if p_photo_path !~ '^/uploads/authorized-persons/[A-Za-z0-9._/-]+$' then
    raise check_violation using message = 'Authorized person portrait path is invalid';
  end if;
  perform 1 from app.student_guardians g where g.id = p_guardian_id and g.school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Guardian not found';
  end if;
  insert into app.authorized_person_portraits (school_id, guardian_id, photo_path, updated_by)
  values (v_school_id, p_guardian_id, p_photo_path, v_profile_id)
  on conflict (school_id, guardian_id) do update
    set photo_path = excluded.photo_path, updated_by = excluded.updated_by, updated_at = pg_catalog.now();
  perform audit.write_event('scan_control.portrait.set', 'student_guardian', p_guardian_id,
    jsonb_build_object('photo_path', p_photo_path));
  return jsonb_build_object('guardian_id', p_guardian_id, 'photo_path', p_photo_path);
end
$schoolsafe$;

create or replace function api.scan_control_card_replace(
  p_old_card_id uuid,
  p_card_number text,
  p_signature text,
  p_card_secret text
)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_old app.student_cards%rowtype;
  v_new_id uuid;
begin
  perform api.scan_control_require('CARD_REPLACE');
  if nullif(pg_catalog.btrim(p_card_number), '') is null
     or nullif(pg_catalog.btrim(p_signature), '') is null
     or nullif(pg_catalog.btrim(p_card_secret), '') is null then
    raise check_violation using message = 'Server card credential required';
  end if;
  select * into v_old
  from app.student_cards
  where id = p_old_card_id and school_id = v_school_id
  for update;
  if not found then
    raise foreign_key_violation using message = 'Card not found';
  end if;
  if v_old.status <> 'active' then
    raise check_violation using message = 'Only an active card can be replaced';
  end if;
  insert into app.student_cards (school_id, student_id, card_number, card_secret, signature, status)
  values (v_school_id, v_old.student_id, pg_catalog.btrim(p_card_number), pg_catalog.btrim(p_card_secret), pg_catalog.btrim(p_signature), 'active')
  returning id into v_new_id;
  update app.student_cards
  set status = 'replaced', revoked_at = pg_catalog.now(), replaced_by_card_id = v_new_id, updated_at = pg_catalog.now()
  where id = v_old.id;
  perform audit.write_event('scan_control.card.replace', 'student', v_old.student_id,
    jsonb_build_object('old_card_id', v_old.id, 'new_card_id', v_new_id, 'card_number', pg_catalog.btrim(p_card_number)));
  return jsonb_build_object(
    'old_card_id', v_old.id,
    'old_status', 'replaced',
    'card_id', v_new_id,
    'card_number', pg_catalog.btrim(p_card_number),
    'signature', pg_catalog.btrim(p_signature),
    'status', 'active',
    'student_id', v_old.student_id
  );
end
$schoolsafe$;

create or replace function api.scan_control_card_revoke(p_card_id uuid, p_reason text)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_card app.student_cards%rowtype;
begin
  perform api.scan_control_require('CARD_REVOKE');
  if p_reason is null or pg_catalog.char_length(pg_catalog.btrim(p_reason)) < 1 then
    raise check_violation using message = 'Revocation reason is required';
  end if;
  select * into v_card from app.student_cards where id = p_card_id and school_id = v_school_id for update;
  if not found then
    raise foreign_key_violation using message = 'Card not found';
  end if;
  if v_card.status <> 'active' then
    raise check_violation using message = 'Only an active card can be revoked';
  end if;
  update app.student_cards
  set status = 'revoked', revoked_at = pg_catalog.now(), updated_at = pg_catalog.now()
  where id = v_card.id;
  perform audit.write_event('scan_control.card.revoke', 'student', v_card.student_id,
    jsonb_build_object('card_id', v_card.id, 'reason', pg_catalog.btrim(p_reason)));
  return jsonb_build_object('card_id', v_card.id, 'status', 'revoked', 'student_id', v_card.student_id);
end
$schoolsafe$;

revoke all on function api.scan_control_require(text) from public;
revoke all on function api.scan_control_grant_set(uuid, text, text, text) from public;
revoke all on function api.scan_control_grant_list() from public;
revoke all on function api.scan_control_my_capabilities() from public;
revoke all on function api.scan_control_card_by_number(text, text) from public;
revoke all on function api.scan_control_presence(uuid) from public;
revoke all on function api.scan_control_presence_mark(uuid, text, uuid) from public;
revoke all on function api.scan_control_note(text, uuid, jsonb) from public;
revoke all on function api.scan_control_audit_read(uuid) from public;
revoke all on function api.scan_control_on_site_student(uuid) from public;
revoke all on function api.scan_control_on_site_class(uuid) from public;
revoke all on function api.scan_control_on_site_school() from public;
revoke all on function api.scan_control_teacher_board() from public;
revoke all on function api.scan_control_school_board() from public;
revoke all on function api.scan_control_campaign_transition(uuid, text) from public;
revoke all on function api.scan_control_student_fees(uuid) from public;
revoke all on function api.scan_control_authorized_people(uuid) from public;
revoke all on function api.scan_control_portrait_set(uuid, text) from public;
revoke all on function api.scan_control_card_replace(uuid, text, text, text) from public;
revoke all on function api.scan_control_card_revoke(uuid, text) from public;

grant execute on function api.scan_control_require(text) to schoolsafe_api;
grant execute on function api.scan_control_grant_set(uuid, text, text, text) to schoolsafe_api;
grant execute on function api.scan_control_grant_list() to schoolsafe_api;
grant execute on function api.scan_control_my_capabilities() to schoolsafe_api;
grant execute on function api.scan_control_card_by_number(text, text) to schoolsafe_api;
grant execute on function api.scan_control_presence(uuid) to schoolsafe_api;
grant execute on function api.scan_control_presence_mark(uuid, text, uuid) to schoolsafe_api;
grant execute on function api.scan_control_note(text, uuid, jsonb) to schoolsafe_api;
grant execute on function api.scan_control_audit_read(uuid) to schoolsafe_api;
grant execute on function api.scan_control_on_site_student(uuid) to schoolsafe_api;
grant execute on function api.scan_control_on_site_class(uuid) to schoolsafe_api;
grant execute on function api.scan_control_on_site_school() to schoolsafe_api;
grant execute on function api.scan_control_teacher_board() to schoolsafe_api;
grant execute on function api.scan_control_school_board() to schoolsafe_api;
grant execute on function api.scan_control_campaign_transition(uuid, text) to schoolsafe_api;
grant execute on function api.scan_control_student_fees(uuid) to schoolsafe_api;
grant execute on function api.scan_control_authorized_people(uuid) to schoolsafe_api;
grant execute on function api.scan_control_portrait_set(uuid, text) to schoolsafe_api;
grant execute on function api.scan_control_card_replace(uuid, text, text, text) to schoolsafe_api;
grant execute on function api.scan_control_card_revoke(uuid, text) to schoolsafe_api;

grant execute on function api.pickup_confirm(uuid, uuid, uuid, text, text) to schoolsafe_api;
grant execute on function api.card_loss_report(uuid, uuid, text, text) to schoolsafe_api;
grant execute on function api.card_reprint_authorize(uuid, text) to schoolsafe_api;
grant execute on function api.card_mark_distributed(uuid) to schoolsafe_api;

commit;
