\set ON_ERROR_STOP on
-- Master School Access V1. The Supabase principal subject is the lock of one school.
-- Applied SQL v1-v8 is unchanged. This unit only adds the link and replaces the trigger function.
begin;
set local role schoolsafe_owner;

create table ops.supabase_school_principals (
  school_id uuid primary key references app.schools (id),
  principal_user_id uuid not null references iam.users (id),
  supabase_subject uuid not null unique,
  created_at timestamptz not null default pg_catalog.clock_timestamp(),
  updated_at timestamptz not null default pg_catalog.clock_timestamp(),
  last_verified_at timestamptz
);

alter table ops.supabase_school_principals enable row level security;
alter table ops.supabase_school_principals force row level security;

create policy supabase_school_principals_owner on ops.supabase_school_principals
  to schoolsafe_owner
  using (true)
  with check (true);

revoke all on ops.supabase_school_principals
  from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_migrator, schoolsafe_auditor;

create policy supabase_school_access_master_sync on ops.supabase_school_access
  to schoolsafe_owner
  using (pg_catalog.current_setting('schoolsafe.master_access', true) = 'on')
  with check (pg_catalog.current_setting('schoolsafe.master_access', true) = 'on');

create or replace function auth.open_supabase_school_access()
returns trigger
language plpgsql
security definer
set search_path = pg_catalog
as $fn$
begin
  if old.completed_at is null
     and new.completed_at is not null
     and old.bootstrap_school_id is not null then
    insert into ops.supabase_school_access (school_id, access_status, source)
    values (old.bootstrap_school_id, 'active', 'supabase_principal_onboarding')
    on conflict (school_id) do nothing;
    insert into ops.supabase_school_principals (school_id, principal_user_id, supabase_subject)
    values (old.bootstrap_school_id, new.user_id, new.subject::uuid)
    on conflict (school_id) do nothing;
  end if;
  return new;
end
$fn$;

do $backfill$
declare
  v_user uuid := 'ede32bc9-77f1-4d4d-86b0-08deaae13203';
  v_subject uuid := 'd2ed61a9-da87-4a2a-b41d-260ea9e5b562';
  v_profiles integer;
  v_schools integer;
  v_school uuid;
begin
  perform pg_catalog.set_config('schoolsafe.preauth', 'on', true);
  perform pg_catalog.set_config('schoolsafe.master_access', 'on', true);
  if not exists (select 1 from iam.users where id = v_user) then
    return;
  end if;
  if (
    select count(*)
      from iam.users
     where id = v_user
       and auth_provider = 'supabase'
       and external_subject = v_subject::text
       and is_active
  ) <> 1 then
    raise exception 'master school access backfill is ambiguous';
  end if;
  select count(*)::integer, count(distinct school_id)::integer, min(school_id)
    into v_profiles, v_schools, v_school
    from iam.profiles
   where user_id = v_user
     and is_active;
  if v_profiles <> 1 or v_schools <> 1 or v_school is null then
    raise exception 'master school access backfill is ambiguous';
  end if;
  if not exists (select 1 from app.schools where id = v_school and is_active) then
    raise exception 'master school access backfill is ambiguous';
  end if;
  if (
    select count(*)
      from auth.supabase_principal_bootstrap
     where user_id = v_user
       and completed_at is not null
       and bootstrap_school_id is null
       and subject = v_subject::text
  ) <> 1 then
    raise exception 'master school access backfill is ambiguous';
  end if;
  if (
    select count(*)
      from ops.supabase_school_access
     where school_id = v_school
       and access_status = 'active'
       and source = 'supabase_principal_onboarding'
  ) <> 1 then
    raise exception 'master school access backfill is ambiguous';
  end if;
  insert into ops.supabase_school_principals (school_id, principal_user_id, supabase_subject)
  values (v_school, v_user, v_subject)
  on conflict (school_id) do nothing;
end
$backfill$;

create function api.master_school_access_targets()
returns table (
  school_id uuid,
  principal_user_id uuid,
  principal_profile_id uuid,
  supabase_subject uuid,
  access_status text,
  last_verified_at timestamptz
)
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $fn$
declare
  v_preauth text := coalesce(pg_catalog.current_setting('schoolsafe.preauth', true), '');
  v_master text := coalesce(pg_catalog.current_setting('schoolsafe.master_access', true), '');
begin
  if session_user <> 'schoolsafe_auth' then
    raise insufficient_privilege using message = 'Master school access is reserved to schoolsafe_auth';
  end if;
  perform pg_catalog.set_config('schoolsafe.preauth', 'on', true);
  perform pg_catalog.set_config('schoolsafe.master_access', 'on', true);
  return query
  select p.school_id, p.principal_user_id, profile.id, p.supabase_subject, a.access_status, p.last_verified_at
    from ops.supabase_school_principals p
    join ops.supabase_school_access a on a.school_id = p.school_id
    join lateral (
      select pr.id
        from iam.profiles pr
       where pr.user_id = p.principal_user_id
         and pr.school_id = p.school_id
         and pr.is_active
         and pr.account_status = 'active'
       order by pr.created_at
       limit 1
    ) profile on true;
  perform pg_catalog.set_config('schoolsafe.preauth', v_preauth, true);
  perform pg_catalog.set_config('schoolsafe.master_access', v_master, true);
end
$fn$;

create function api.master_school_access_status(p_school_id uuid)
returns text
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $fn$
declare
  v_status text;
  v_master text := coalesce(pg_catalog.current_setting('schoolsafe.master_access', true), '');
begin
  if session_user <> 'schoolsafe_auth' then
    raise insufficient_privilege using message = 'Master school access is reserved to schoolsafe_auth';
  end if;
  if p_school_id is null then
    return null;
  end if;
  perform pg_catalog.set_config('schoolsafe.master_access', 'on', true);
  select a.access_status into v_status
    from ops.supabase_school_access a
   where a.school_id = p_school_id;
  perform pg_catalog.set_config('schoolsafe.master_access', v_master, true);
  return v_status;
end
$fn$;

create function api.master_school_access_apply(
  p_school_id uuid,
  p_subject uuid,
  p_status text
)
returns text
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $fn$
declare
  v_user uuid;
  v_stored uuid;
  v_current text;
  v_profile uuid;
  v_preauth text := coalesce(pg_catalog.current_setting('schoolsafe.preauth', true), '');
  v_master text := coalesce(pg_catalog.current_setting('schoolsafe.master_access', true), '');
begin
  if session_user <> 'schoolsafe_auth' then
    raise insufficient_privilege using message = 'Master school access is reserved to schoolsafe_auth';
  end if;
  if p_status not in ('active', 'suspended', 'revoked') then
    raise check_violation using message = 'Invalid master school access status';
  end if;
  perform pg_catalog.set_config('schoolsafe.preauth', 'on', true);
  perform pg_catalog.set_config('schoolsafe.master_access', 'on', true);
  select p.principal_user_id, p.supabase_subject
    into v_user, v_stored
    from ops.supabase_school_principals p
   where p.school_id = p_school_id
   for update;
  if not found or v_stored is distinct from p_subject then
    raise check_violation using message = 'Master subject does not match';
  end if;
  select a.access_status into v_current
    from ops.supabase_school_access a
   where a.school_id = p_school_id
   for update;
  if not found then
    raise check_violation using message = 'Master school access is missing';
  end if;
  if v_current = 'revoked' and p_status <> 'revoked' then
    raise check_violation using message = 'Revoked school access cannot reopen automatically';
  end if;
  if v_current = p_status then
    update ops.supabase_school_principals
       set last_verified_at = pg_catalog.clock_timestamp(),
           updated_at = pg_catalog.clock_timestamp()
     where school_id = p_school_id;
    perform pg_catalog.set_config('schoolsafe.preauth', v_preauth, true);
    perform pg_catalog.set_config('schoolsafe.master_access', v_master, true);
    return v_current;
  end if;
  if not (
    (v_current = 'active' and p_status in ('suspended', 'revoked'))
    or (v_current = 'suspended' and p_status in ('active', 'revoked'))
  ) then
    raise check_violation using message = 'Forbidden master school access transition';
  end if;
  select pr.id into v_profile
    from iam.profiles pr
   where pr.user_id = v_user
     and pr.school_id = p_school_id
     and pr.is_active
     and pr.account_status = 'active'
   order by pr.created_at
   limit 1;
  if v_profile is null then
    raise insufficient_privilege using message = 'Master principal profile is missing';
  end if;
  perform api.set_request_context(v_user, v_profile, p_school_id, pg_catalog.gen_random_uuid());
  update ops.supabase_school_access
     set access_status = p_status,
         updated_at = pg_catalog.clock_timestamp()
   where school_id = p_school_id;
  update ops.supabase_school_principals
     set last_verified_at = pg_catalog.clock_timestamp(),
         updated_at = pg_catalog.clock_timestamp()
   where school_id = p_school_id;
  if p_status in ('suspended', 'revoked') then
    update auth.sessions as s
       set revoked_at = pg_catalog.clock_timestamp()
      from iam.profiles as pr
     where s.profile_id = pr.id
       and pr.school_id = p_school_id
       and s.revoked_at is null;
  end if;
  perform audit.write_event(
    case p_status
      when 'suspended' then 'school.master_access.suspended'
      when 'active' then 'school.master_access.reactivated'
      else 'school.master_access.revoked'
    end,
    'school',
    p_school_id,
    pg_catalog.jsonb_build_object('school_id', p_school_id, 'status', p_status)
  );
  perform pg_catalog.set_config('schoolsafe.preauth', v_preauth, true);
  perform pg_catalog.set_config('schoolsafe.master_access', v_master, true);
  return p_status;
end
$fn$;

create function api.master_school_access_relink(
  p_school_id uuid,
  p_old_subject uuid,
  p_new_subject uuid
)
returns text
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $fn$
declare
  v_user uuid;
  v_stored uuid;
  v_profile uuid;
  v_preauth text := coalesce(pg_catalog.current_setting('schoolsafe.preauth', true), '');
  v_master text := coalesce(pg_catalog.current_setting('schoolsafe.master_access', true), '');
begin
  if session_user <> 'schoolsafe_auth' then
    raise insufficient_privilege using message = 'Master school access is reserved to schoolsafe_auth';
  end if;
  if p_school_id is null or p_old_subject is null or p_new_subject is null or p_old_subject = p_new_subject then
    raise check_violation using message = 'Exact master subject required';
  end if;
  perform pg_catalog.set_config('schoolsafe.preauth', 'on', true);
  perform pg_catalog.set_config('schoolsafe.master_access', 'on', true);
  select p.principal_user_id, p.supabase_subject
    into v_user, v_stored
    from ops.supabase_school_principals p
   where p.school_id = p_school_id
   for update;
  if not found or v_stored is distinct from p_old_subject then
    raise check_violation using message = 'Master subject does not match';
  end if;
  if exists (
    select 1 from ops.supabase_school_principals where supabase_subject = p_new_subject
  ) or exists (
    select 1 from iam.users where external_subject = p_new_subject::text and id <> v_user
  ) then
    raise check_violation using message = 'Master subject is already linked';
  end if;
  select pr.id into v_profile
    from iam.profiles pr
   where pr.user_id = v_user
     and pr.school_id = p_school_id
     and pr.is_active
     and pr.account_status = 'active'
   order by pr.created_at
   limit 1;
  if v_profile is null then
    raise insufficient_privilege using message = 'Master principal profile is missing';
  end if;
  perform api.set_request_context(v_user, v_profile, p_school_id, pg_catalog.gen_random_uuid());
  update iam.users
     set external_subject = p_new_subject::text,
         updated_at = pg_catalog.clock_timestamp()
   where id = v_user
     and external_subject = p_old_subject::text;
  if not found then
    raise check_violation using message = 'Master subject does not match';
  end if;
  update ops.supabase_school_principals
     set supabase_subject = p_new_subject,
         last_verified_at = pg_catalog.clock_timestamp(),
         updated_at = pg_catalog.clock_timestamp()
   where school_id = p_school_id;
  update ops.supabase_school_access
     set access_status = 'active',
         updated_at = pg_catalog.clock_timestamp()
   where school_id = p_school_id;
  update auth.sessions as s
     set revoked_at = pg_catalog.clock_timestamp()
    from iam.profiles as pr
   where s.profile_id = pr.id
     and pr.school_id = p_school_id
     and s.revoked_at is null;
  perform audit.write_event(
    'school.master_access.relinked',
    'school',
    p_school_id,
    pg_catalog.jsonb_build_object('school_id', p_school_id, 'old_subject', p_old_subject, 'new_subject', p_new_subject)
  );
  perform pg_catalog.set_config('schoolsafe.preauth', v_preauth, true);
  perform pg_catalog.set_config('schoolsafe.master_access', v_master, true);
  return 'active';
end
$fn$;

revoke all on function api.master_school_access_targets()
  from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_migrator, schoolsafe_auditor;
revoke all on function api.master_school_access_status(uuid)
  from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_migrator, schoolsafe_auditor;
revoke all on function api.master_school_access_apply(uuid, uuid, text)
  from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_migrator, schoolsafe_auditor;
revoke all on function api.master_school_access_relink(uuid, uuid, uuid)
  from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_migrator, schoolsafe_auditor;
grant execute on function api.master_school_access_targets() to schoolsafe_auth;
grant execute on function api.master_school_access_status(uuid) to schoolsafe_auth;
grant execute on function api.master_school_access_apply(uuid, uuid, text) to schoolsafe_auth;
grant execute on function api.master_school_access_relink(uuid, uuid, uuid) to schoolsafe_auth;

commit;
