\set ON_ERROR_STOP on

-- Pickup persons: human photo decision, parent suspend, school reactivation.
-- Replaces the confirm/list/validate bodies. Does not create a second family model.
-- A denial is returned, not raised, so the audit row survives the request transaction.

begin;
set local role schoolsafe_owner;

alter table app.pickup_authorizations drop constraint if exists pickup_authorizations_status_check;
alter table app.pickup_authorizations add constraint pickup_authorizations_status_check
  check (status in ('draft','pending','active','suspended','expired','revoked','reactivation_pending'));

-- Un père, une mère, un tuteur actifs : mêmes index que auth v8.
create unique index if not exists student_guardians_one_active_father
  on app.student_guardians (school_id, student_id)
  where is_active and guardian_type = 'pere';
create unique index if not exists student_guardians_one_active_mother
  on app.student_guardians (school_id, student_id)
  where is_active and guardian_type = 'mere';
create unique index if not exists student_guardians_one_active_tutor
  on app.student_guardians (school_id, student_id)
  where is_active and guardian_type = 'tuteur';

alter table app.student_guardians add column if not exists pickup_hold text;
do $schoolsafe$
begin
  if not exists (
    select 1 from pg_constraint where conname = 'student_guardians_pickup_hold_check'
  ) then
    alter table app.student_guardians
      add constraint student_guardians_pickup_hold_check
      check (pickup_hold is null or pickup_hold = 'reactivation_pending');
  end if;
end
$schoolsafe$;

create table if not exists app.pickup_photo_reviews (
  id uuid primary key default pg_catalog.gen_random_uuid(),
  school_id uuid not null,
  guardian_id uuid not null,
  photo_path text not null,
  decision text not null,
  reviewed_by uuid not null,
  note text,
  created_at timestamptz not null default pg_catalog.now(),
  constraint pickup_photo_reviews_school_fkey foreign key (school_id) references app.schools(id) on delete cascade,
  constraint pickup_photo_reviews_guardian_fkey foreign key (guardian_id) references app.student_guardians(id) on delete cascade,
  constraint pickup_photo_reviews_decision_check check (decision in ('accepted','rejected'))
);

alter table app.pickup_photo_reviews enable row level security;
alter table app.pickup_photo_reviews force row level security;
do $schoolsafe$
begin
  if not exists (
    select 1 from pg_policies
    where schemaname = 'app' and tablename = 'pickup_photo_reviews' and policyname = 'pickup_photo_reviews_isolation'
  ) then
    create policy pickup_photo_reviews_isolation on app.pickup_photo_reviews
      for all to schoolsafe_owner
      using (school_id = iam.current_school_id())
      with check (school_id = iam.current_school_id());
  end if;
end
$schoolsafe$;
revoke all on app.pickup_photo_reviews from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_auditor;

create or replace function api.pickup_current_photo(p_guardian_id uuid)
returns text
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_path text;
begin
  select coalesce(ap.photo_path, pr.photo_path) into v_path
    from app.student_guardians g
    left join app.authorized_person_portraits ap
      on ap.guardian_id = g.id and ap.school_id = g.school_id
    left join iam.profiles pr
      on pr.id = g.profile_id and pr.school_id = g.school_id
   where g.id = p_guardian_id
     and g.school_id = v_school_id;
  return nullif(btrim(v_path), '');
end
$schoolsafe$;

create or replace function api.pickup_photo_accepted(p_guardian_id uuid)
returns boolean
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_path text := api.pickup_current_photo(p_guardian_id);
  v_decision text;
begin
  if v_path is null then
    return false;
  end if;
  select r.decision into v_decision
    from app.pickup_photo_reviews r
   where r.school_id = v_school_id
     and r.guardian_id = p_guardian_id
     and r.photo_path = v_path
   order by r.created_at desc
   limit 1;
  return v_decision = 'accepted';
end
$schoolsafe$;

create or replace function api.pickup_photo_review(
  p_guardian_id uuid,
  p_decision text,
  p_note text default null
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_path text;
  v_id uuid;
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  if p_decision not in ('accepted','rejected') then
    raise check_violation using message = 'Invalid photo decision';
  end if;
  perform 1 from app.student_guardians g
   where g.id = p_guardian_id and g.school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Guardian not found';
  end if;
  perform 1 from app.students s
    join app.student_guardians g on g.student_id = s.id and g.school_id = s.school_id
   where g.id = p_guardian_id and g.school_id = v_school_id
   for update of s;
  v_path := api.pickup_current_photo(p_guardian_id);
  if v_path is null then
    raise check_violation using message = 'PHOTO_REQUIRED';
  end if;
  insert into app.pickup_photo_reviews (school_id, guardian_id, photo_path, decision, reviewed_by, note)
  values (v_school_id, p_guardian_id, v_path, p_decision, v_profile_id, nullif(btrim(p_note), ''))
  returning id into v_id;
  perform audit.write_event('pickup.photo.reviewed', 'guardian', p_guardian_id,
    jsonb_build_object('decision', p_decision, 'photo_path', v_path));
  return jsonb_build_object('id', v_id, 'decision', p_decision, 'photo_path', v_path);
end
$schoolsafe$;

create or replace function api.pickup_authorization_view(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform 1 from app.students s where s.id = p_student_id and s.school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  return jsonb_build_object(
    'student_id', p_student_id,
    'family', (
      select coalesce(jsonb_agg(jsonb_build_object(
        'guardian_id', g.id,
        'name', g.full_name,
        'type', g.guardian_type,
        'is_primary', g.is_primary,
        'authorized', (g.is_authorized_pickup and g.is_active and g.guardian_type in ('pere','mere','tuteur')),
        'pickup_hold', g.pickup_hold,
        'pickup_ready', (
          g.is_authorized_pickup and g.is_active
          and g.guardian_type in ('pere','mere','tuteur')
          and api.pickup_photo_accepted(g.id)
        ),
        'photo_path', api.pickup_current_photo(g.id),
        'photo_accepted', api.pickup_photo_accepted(g.id)
      ) order by g.is_primary desc, g.guardian_type), '[]'::jsonb)
      from app.student_guardians g
      where g.student_id = p_student_id and g.school_id = v_school_id and g.is_active
        and g.guardian_type in ('pere','mere','tuteur')
    ),
    'accredited', (
      select coalesce(jsonb_agg(jsonb_build_object(
        'authorization_id', a.id,
        'guardian_id', a.guardian_id,
        'name', g.full_name,
        'status', a.status,
        'slot_no', a.slot_no,
        'reason', a.reason,
        'photo_path', api.pickup_current_photo(g.id),
        'photo_accepted', api.pickup_photo_accepted(g.id),
        'pickup_ready', (
          a.status = 'active'
          and api.pickup_photo_accepted(g.id)
          and (a.starts_on is null or a.starts_on <= current_date)
          and (a.ends_on is null or a.ends_on >= current_date)
        ),
        'starts_on', a.starts_on,
        'ends_on', a.ends_on
      ) order by a.slot_no nulls last), '[]'::jsonb)
      from app.pickup_authorizations a
      join app.student_guardians g on g.id = a.guardian_id and g.school_id = a.school_id
      where a.student_id = p_student_id and a.school_id = v_school_id
    )
  );
end
$schoolsafe$;

create or replace function api.pickup_authorization_list(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
begin
  perform iam.require_access('security.scan', null, p_student_id, null);
  return api.pickup_authorization_view(p_student_id);
end
$schoolsafe$;

create or replace function api.pickup_school_authorization_list(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
begin
  perform iam.require_access('school.guardian.manage', null, p_student_id, null);
  return api.pickup_authorization_view(p_student_id);
end
$schoolsafe$;

create or replace function api.pickup_parent_authorization_list(p_student_id uuid)
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
  perform 1 from app.student_guardians g
   where g.student_id = p_student_id and g.school_id = v_school_id
     and g.profile_id = v_profile_id and g.is_primary and g.is_active;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  return api.pickup_authorization_view(p_student_id);
end
$schoolsafe$;

create or replace function api.pickup_authorization_validate(p_authorization_id uuid)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_auth app.pickup_authorizations%rowtype;
  v_slot int;
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  select student_id into v_auth.student_id
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  perform 1 from app.students where id = v_auth.student_id and school_id = v_school_id for update;
  select * into v_auth
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id
   for update;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  if v_auth.status <> 'pending' then
    raise exception using errcode = '22023', message = 'Autorisation non en attente';
  end if;
  if not api.pickup_photo_accepted(v_auth.guardian_id) then
    raise exception using errcode = '22023', message = 'PHOTO_NOT_VALIDATED';
  end if;
  if (v_auth.starts_on is not null and v_auth.starts_on > current_date)
     or (v_auth.ends_on is not null and v_auth.ends_on < current_date) then
    raise exception using errcode = '22023', message = 'Authorization period is not valid';
  end if;
  select s.slot_no into v_slot
    from generate_series(1, 3) as s(slot_no)
   where not exists (
     select 1 from app.pickup_authorizations a
      where a.school_id = v_school_id and a.student_id = v_auth.student_id
        and a.slot_no = s.slot_no and a.status = 'active'
   )
   order by s.slot_no
   limit 1;
  if v_slot is null then
    raise exception using errcode = '22023', message = 'PICKUP_LIMIT_REACHED';
  end if;
  update app.pickup_authorizations
     set status = 'active', slot_no = v_slot, validated_by = v_profile_id,
         photo_verified = true, updated_at = pg_catalog.now()
   where id = p_authorization_id;
  insert into app.security_events (school_id, student_id, event_type, decision, metadata)
  values (v_school_id, v_auth.student_id, 'pickup_authorization_validated', 'noted',
    jsonb_build_object('authorization_id', p_authorization_id, 'slot_no', v_slot));
  return jsonb_build_object('id', p_authorization_id, 'status', 'active', 'slot_no', v_slot);
end
$schoolsafe$;

create or replace function api.pickup_parent_actor(p_student_id uuid)
returns uuid
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_id uuid;
begin
  select g.id into v_id
    from app.student_guardians g
   where g.student_id = p_student_id
     and g.school_id = iam.current_school_id()
     and g.profile_id = iam.current_profile_id()
     and g.is_primary
     and g.is_active;
  return v_id;
end
$schoolsafe$;

create or replace function api.pickup_parent_suspend(p_authorization_id uuid, p_reason text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_auth app.pickup_authorizations%rowtype;
begin
  if nullif(btrim(p_reason), '') is null then
    raise check_violation using message = 'Suspension reason required';
  end if;
  select student_id into v_auth.student_id
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  perform 1 from app.students where id = v_auth.student_id and school_id = v_school_id for update;
  select * into v_auth from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id
   for update;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  if api.pickup_parent_actor(v_auth.student_id) is null then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  if v_auth.status <> 'active' then
    raise check_violation using message = 'Authorization is not active';
  end if;
  update app.pickup_authorizations
     set status = 'suspended', reason = btrim(p_reason), updated_at = pg_catalog.now()
   where id = p_authorization_id;
  insert into app.security_events (school_id, student_id, event_type, decision, metadata)
  values (v_school_id, v_auth.student_id, 'pickup_authorization_suspended', 'noted',
    jsonb_build_object('authorization_id', p_authorization_id, 'reason', btrim(p_reason), 'actor', 'primary_parent'));
  perform audit.write_event('pickup.authorization.suspended', 'pickup_authorization', p_authorization_id,
    jsonb_build_object('reason', btrim(p_reason)));
  return jsonb_build_object('id', p_authorization_id, 'status', 'suspended');
end
$schoolsafe$;

create or replace function api.pickup_parent_reactivation_request(p_authorization_id uuid, p_reason text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_auth app.pickup_authorizations%rowtype;
begin
  select student_id into v_auth.student_id
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  perform 1 from app.students where id = v_auth.student_id and school_id = v_school_id for update;
  select * into v_auth from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id
   for update;
  if not found or api.pickup_parent_actor(v_auth.student_id) is null then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  if v_auth.status = 'revoked' then
    raise check_violation using message = 'Revoked authorization requires a new request';
  end if;
  if v_auth.status <> 'suspended' then
    raise check_violation using message = 'Authorization is not suspended';
  end if;
  update app.pickup_authorizations
     set status = 'reactivation_pending', reason = nullif(btrim(p_reason), ''), updated_at = pg_catalog.now()
   where id = p_authorization_id;
  perform audit.write_event('pickup.reactivation.requested', 'pickup_authorization', p_authorization_id,
    jsonb_build_object('reason', nullif(btrim(p_reason), '')));
  return jsonb_build_object('id', p_authorization_id, 'status', 'reactivation_pending');
end
$schoolsafe$;

create or replace function api.pickup_authorization_reactivate(p_authorization_id uuid)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_auth app.pickup_authorizations%rowtype;
  v_slot int;
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  select student_id into v_auth.student_id
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  perform 1 from app.students where id = v_auth.student_id and school_id = v_school_id for update;
  select * into v_auth from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id
   for update;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  if v_auth.status = 'revoked' then
    raise check_violation using message = 'Revoked authorization requires a new request';
  end if;
  if v_auth.status <> 'reactivation_pending' then
    raise check_violation using message = 'Reactivation is not pending';
  end if;
  if not api.pickup_photo_accepted(v_auth.guardian_id) then
    raise check_violation using message = 'PHOTO_NOT_VALIDATED';
  end if;
  if (v_auth.starts_on is not null and v_auth.starts_on > current_date)
     or (v_auth.ends_on is not null and v_auth.ends_on < current_date) then
    raise check_violation using message = 'Authorization period is not valid';
  end if;
  select s.slot_no into v_slot
    from generate_series(1, 3) as s(slot_no)
   where not exists (
     select 1 from app.pickup_authorizations a
      where a.school_id = v_school_id and a.student_id = v_auth.student_id
        and a.slot_no = s.slot_no and a.status = 'active'
        and a.id <> p_authorization_id
   )
   order by s.slot_no
   limit 1;
  if v_slot is null then
    raise check_violation using message = 'PICKUP_LIMIT_REACHED';
  end if;
  update app.pickup_authorizations
     set status = 'active', slot_no = v_slot, validated_by = v_profile_id,
         photo_verified = true, updated_at = pg_catalog.now()
   where id = p_authorization_id;
  perform audit.write_event('pickup.authorization.reactivated', 'pickup_authorization', p_authorization_id,
    jsonb_build_object('slot_no', v_slot));
  return jsonb_build_object('id', p_authorization_id, 'status', 'active', 'slot_no', v_slot);
end
$schoolsafe$;

create or replace function api.pickup_confirm(
  p_student_id uuid,
  p_guardian_id uuid,
  p_guardian_profile_id uuid,
  p_request_key text,
  p_note text default null
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_guardian app.student_guardians%rowtype;
  v_auth app.pickup_authorizations%rowtype;
  v_existing record;
  v_allowed boolean := false;
  v_basis text;
  v_code text := 'PICKUP_RESTRICTED';
begin
  perform 1 from app.students where id = p_student_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  perform iam.require_access('security.scan', null, p_student_id, null);
  select event_type, metadata into v_existing
    from app.security_events
   where school_id = v_school_id and student_id = p_student_id
     and event_type = 'pickup_confirmed'
     and metadata->>'request_key' = p_request_key
   limit 1;
  if found then
    return jsonb_build_object('status', 'already_confirmed', 'idempotent', true);
  end if;
  perform 1 from app.students where id = p_student_id and school_id = v_school_id for update;
  if exists (
    select 1 from app.security_events
     where school_id = v_school_id and student_id = p_student_id
       and event_type = 'pickup_confirmed'
       and occurred_at::date = current_date
  ) then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_ALREADY_DONE',
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_ALREADY_DONE');
  end if;
  select * into v_guardian
    from app.student_guardians
   where id = p_guardian_id and student_id = p_student_id and school_id = v_school_id
   for update;
  if not found then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_RESTRICTED',
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_RESTRICTED');
  end if;
  if not api.pickup_photo_accepted(p_guardian_id) then
    v_code := 'PHOTO_NOT_VALIDATED';
  elsif v_guardian.is_active and v_guardian.is_authorized_pickup
        and v_guardian.guardian_type in ('pere','mere','tuteur') then
    v_allowed := true;
    v_basis := 'family';
  else
    select * into v_auth
      from app.pickup_authorizations
     where guardian_id = p_guardian_id and student_id = p_student_id and school_id = v_school_id
       and status = 'active'
     order by updated_at desc
     limit 1
     for update;
    if found
       and (v_auth.starts_on is null or v_auth.starts_on <= current_date)
       and (v_auth.ends_on is null or v_auth.ends_on >= current_date) then
      v_allowed := true;
      v_basis := 'accredited';
    end if;
  end if;
  if not v_allowed then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', v_code, p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'note', p_note));
    return jsonb_build_object('status', 'denied', 'code', v_code);
  end if;
  insert into app.security_events (school_id, student_id, event_type, decision, scanned_by, metadata)
  values (v_school_id, p_student_id, 'pickup_confirmed', 'allowed', p_guardian_profile_id,
    jsonb_build_object('guardian_id', p_guardian_id, 'basis', v_basis, 'request_key', p_request_key, 'note', p_note));
  return jsonb_build_object('status', 'confirmed', 'basis', v_basis, 'guardian_name', v_guardian.full_name);
end
$schoolsafe$;

create or replace function api.pickup_reactivation_refuse(p_authorization_id uuid, p_reason text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_auth app.pickup_authorizations%rowtype;
  v_previous text;
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  if nullif(btrim(p_reason), '') is null then
    raise check_violation using message = 'Refusal reason required';
  end if;
  select student_id into v_auth.student_id
    from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  perform 1 from app.students where id = v_auth.student_id and school_id = v_school_id for update;
  select * into v_auth from app.pickup_authorizations
   where id = p_authorization_id and school_id = v_school_id
   for update;
  if not found then
    raise exception using errcode = 'P0002', message = 'Autorisation introuvable';
  end if;
  if v_auth.status = 'revoked' then
    raise check_violation using message = 'Revoked authorization requires a new request';
  end if;
  if v_auth.status <> 'reactivation_pending' then
    raise check_violation using message = 'Reactivation is not pending';
  end if;
  v_previous := v_auth.reason;
  update app.pickup_authorizations
     set status = 'suspended', reason = btrim(p_reason), updated_at = pg_catalog.now()
   where id = p_authorization_id;
  perform audit.write_event('pickup.reactivation.refused', 'pickup_authorization', p_authorization_id,
    jsonb_build_object('reason', btrim(p_reason), 'previous_reason', v_previous));
  return jsonb_build_object('id', p_authorization_id, 'status', 'suspended');
end
$schoolsafe$;

create or replace function api.pickup_parent_family_suspend(p_guardian_id uuid, p_reason text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_guardian app.student_guardians%rowtype;
begin
  if nullif(btrim(p_reason), '') is null then
    raise check_violation using message = 'Suspension reason required';
  end if;
  select student_id into v_guardian.student_id
    from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id and is_active;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  perform 1 from app.students where id = v_guardian.student_id and school_id = v_school_id for update;
  select * into v_guardian from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id
   for update;
  if not found or api.pickup_parent_actor(v_guardian.student_id) is null then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  if v_guardian.guardian_type not in ('pere','mere','tuteur') or not v_guardian.is_active then
    raise check_violation using message = 'Authorization is not active';
  end if;
  if not v_guardian.is_authorized_pickup then
    raise check_violation using message = 'Authorization is not active';
  end if;
  update app.student_guardians
     set is_authorized_pickup = false, pickup_hold = null, updated_at = pg_catalog.now()
   where id = p_guardian_id;
  insert into app.security_events (school_id, student_id, event_type, decision, metadata)
  values (v_school_id, v_guardian.student_id, 'pickup_family_suspended', 'noted',
    jsonb_build_object('guardian_id', p_guardian_id, 'reason', btrim(p_reason), 'actor', 'primary_parent'));
  perform audit.write_event('pickup.family.suspended', 'student_guardian', p_guardian_id,
    jsonb_build_object('reason', btrim(p_reason)));
  return jsonb_build_object('guardian_id', p_guardian_id, 'status', 'suspended');
end
$schoolsafe$;

create or replace function api.pickup_parent_family_reactivation_request(p_guardian_id uuid, p_reason text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_guardian app.student_guardians%rowtype;
begin
  select student_id into v_guardian.student_id
    from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id and is_active;
  if not found then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  perform 1 from app.students where id = v_guardian.student_id and school_id = v_school_id for update;
  select * into v_guardian from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id
   for update;
  if not found or api.pickup_parent_actor(v_guardian.student_id) is null then
    raise insufficient_privilege using message = 'Access denied: pickup parent';
  end if;
  if v_guardian.is_authorized_pickup then
    raise check_violation using message = 'Authorization is not suspended';
  end if;
  update app.student_guardians
     set pickup_hold = 'reactivation_pending', updated_at = pg_catalog.now()
   where id = p_guardian_id;
  perform audit.write_event('pickup.family.reactivation.requested', 'student_guardian', p_guardian_id,
    jsonb_build_object('reason', nullif(btrim(p_reason), '')));
  return jsonb_build_object('guardian_id', p_guardian_id, 'status', 'reactivation_pending');
end
$schoolsafe$;

create or replace function api.pickup_family_reactivation_decide(
  p_guardian_id uuid,
  p_decision text,
  p_reason text default null
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_guardian app.student_guardians%rowtype;
begin
  perform iam.require_access('school.guardian.manage', null, null, null);
  if p_decision not in ('approved','refused') then
    raise check_violation using message = 'Invalid reactivation decision';
  end if;
  select student_id into v_guardian.student_id
    from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Guardian not found';
  end if;
  perform 1 from app.students where id = v_guardian.student_id and school_id = v_school_id for update;
  select * into v_guardian from app.student_guardians
   where id = p_guardian_id and school_id = v_school_id
   for update;
  if v_guardian.pickup_hold <> 'reactivation_pending' then
    raise check_violation using message = 'Reactivation is not pending';
  end if;
  if p_decision = 'refused' then
    update app.student_guardians
       set pickup_hold = null, is_authorized_pickup = false, updated_at = pg_catalog.now()
     where id = p_guardian_id;
    perform audit.write_event('pickup.family.reactivation.refused', 'student_guardian', p_guardian_id,
      jsonb_build_object('reason', nullif(btrim(p_reason), '')));
    return jsonb_build_object('guardian_id', p_guardian_id, 'status', 'suspended');
  end if;
  if not api.pickup_photo_accepted(p_guardian_id) then
    raise check_violation using message = 'PHOTO_NOT_VALIDATED';
  end if;
  update app.student_guardians
     set is_authorized_pickup = true, pickup_hold = null, updated_at = pg_catalog.now()
   where id = p_guardian_id;
  perform audit.write_event('pickup.family.reactivation.approved', 'student_guardian', p_guardian_id,
    jsonb_build_object('reason', nullif(btrim(p_reason), '')));
  return jsonb_build_object('guardian_id', p_guardian_id, 'status', 'active');
end
$schoolsafe$;

create or replace function api.pickup_external_register(p_student_id uuid, p_payload jsonb)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_primary uuid;
  v_guardian uuid;
  v_auth uuid;
  v_photo text := nullif(btrim(p_payload->>'photo_path'), '');
begin
  perform iam.require_access('school.guardian.manage', null, p_student_id, null);
  perform 1 from app.students where id = p_student_id and school_id = v_school_id for update;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  if nullif(btrim(p_payload->>'full_name'), '') is null then
    raise check_violation using message = 'Guardian full name is required';
  end if;
  if nullif(btrim(p_payload->>'consent'), '') is null then
    raise check_violation using message = 'Primary consent is required';
  end if;
  select id into v_primary
    from app.student_guardians
   where student_id = p_student_id and school_id = v_school_id
     and is_primary and is_active;
  if v_primary is null then
    raise check_violation using message = 'Aucun responsable principal actif : demande impossible';
  end if;
  if v_photo is not null and v_photo !~ '^/uploads/authorized-persons/[A-Za-z0-9._/-]+$' then
    raise check_violation using message = 'Authorized person portrait path is invalid';
  end if;
  insert into app.student_guardians (
    school_id, student_id, profile_id, guardian_type, is_primary, full_name,
    phone, email, address, is_authorized_pickup, is_active, created_by
  ) values (
    v_school_id, p_student_id, null, 'autre', false, btrim(p_payload->>'full_name'),
    nullif(btrim(p_payload->>'phone'), ''), nullif(btrim(p_payload->>'email'), ''),
    nullif(btrim(p_payload->>'address'), ''), false, true, v_profile_id
  ) returning id into v_guardian;
  if v_photo is not null then
    insert into app.authorized_person_portraits (school_id, guardian_id, photo_path, updated_by)
    values (v_school_id, v_guardian, v_photo, v_profile_id);
  end if;
  insert into app.pickup_authorizations (
    school_id, student_id, guardian_id, slot_no, status, requested_by, reason, starts_on, ends_on
  ) values (
    v_school_id, p_student_id, v_guardian, 1, 'pending', v_primary, btrim(p_payload->>'consent'),
    nullif(p_payload->>'starts_on', '')::date, nullif(p_payload->>'ends_on', '')::date
  ) returning id into v_auth;
  perform audit.write_event('pickup.external.registered', 'pickup_authorization', v_auth,
    jsonb_build_object('guardian_id', v_guardian, 'student_id', p_student_id));
  return jsonb_build_object('guardian_id', v_guardian, 'authorization_id', v_auth, 'status', 'pending');
end
$schoolsafe$;

create or replace function api.pickup_portrait_readable(p_photo_path text)
returns boolean
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_guardian uuid;
  v_student uuid;
begin
  select ap.guardian_id, g.student_id into v_guardian, v_student
    from app.authorized_person_portraits ap
    join app.student_guardians g on g.id = ap.guardian_id and g.school_id = ap.school_id
   where ap.school_id = v_school_id and ap.photo_path = p_photo_path;
  if not found then
    return false;
  end if;
  if iam.can_access('school.guardian.manage', null, v_student, null, null, null, '{}'::jsonb) then
    return true;
  end if;
  if iam.can_access('security.scan', null, v_student, null, null, null, '{}'::jsonb) then
    return true;
  end if;
  return exists (
    select 1 from app.student_guardians parent
     where parent.student_id = v_student
       and parent.school_id = v_school_id
       and parent.profile_id = iam.current_profile_id()
       and parent.is_primary
       and parent.is_active
  );
end
$schoolsafe$;

revoke all on function api.pickup_current_photo(uuid) from public;
revoke all on function api.pickup_photo_accepted(uuid) from public;
revoke all on function api.pickup_photo_review(uuid, text, text) from public;
revoke all on function api.pickup_authorization_view(uuid) from public;
revoke all on function api.pickup_school_authorization_list(uuid) from public;
revoke all on function api.pickup_parent_authorization_list(uuid) from public;
revoke all on function api.pickup_parent_actor(uuid) from public;
revoke all on function api.pickup_parent_suspend(uuid, text) from public;
revoke all on function api.pickup_parent_reactivation_request(uuid, text) from public;
revoke all on function api.pickup_authorization_reactivate(uuid) from public;
revoke all on function api.pickup_reactivation_refuse(uuid, text) from public;
revoke all on function api.pickup_parent_family_suspend(uuid, text) from public;
revoke all on function api.pickup_parent_family_reactivation_request(uuid, text) from public;
revoke all on function api.pickup_family_reactivation_decide(uuid, text, text) from public;
revoke all on function api.pickup_external_register(uuid, jsonb) from public;
revoke all on function api.pickup_portrait_readable(text) from public;
grant execute on function api.pickup_photo_review(uuid, text, text) to schoolsafe_api;
grant execute on function api.pickup_authorization_list(uuid) to schoolsafe_api;
grant execute on function api.pickup_authorization_validate(uuid) to schoolsafe_api;
grant execute on function api.pickup_authorization_revoke(uuid, text, text) to schoolsafe_api;
grant execute on function api.pickup_confirm(uuid, uuid, uuid, text, text) to schoolsafe_api;
grant execute on function api.pickup_school_authorization_list(uuid) to schoolsafe_api;
grant execute on function api.pickup_parent_authorization_list(uuid) to schoolsafe_api;
grant execute on function api.pickup_parent_suspend(uuid, text) to schoolsafe_api;
grant execute on function api.pickup_parent_reactivation_request(uuid, text) to schoolsafe_api;
grant execute on function api.pickup_authorization_reactivate(uuid) to schoolsafe_api;
grant execute on function api.pickup_reactivation_refuse(uuid, text) to schoolsafe_api;
grant execute on function api.pickup_parent_family_suspend(uuid, text) to schoolsafe_api;
grant execute on function api.pickup_parent_family_reactivation_request(uuid, text) to schoolsafe_api;
grant execute on function api.pickup_family_reactivation_decide(uuid, text, text) to schoolsafe_api;
grant execute on function api.pickup_external_register(uuid, jsonb) to schoolsafe_api;
grant execute on function api.pickup_portrait_readable(text) to schoolsafe_api;

commit;
