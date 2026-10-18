\set ON_ERROR_STOP on

-- Scanner unique : entrée native, retrait via le moteur de remise existant,
-- contrôle des frais consultatif, vérification sans mouvement.
-- Aucune clé HMAC. Aucun paiement créé.

begin;
set local role schoolsafe_owner;

alter table app.scan_control_grants
  add column if not exists expires_at timestamptz,
  add column if not exists class_ids uuid[],
  add column if not exists campaign_id uuid;

alter table app.scan_control_grants drop constraint if exists scan_control_grants_capability_check;
alter table app.scan_control_grants add constraint scan_control_grants_capability_check check (capability in (
  'SCAN_ENTRY','SCAN_PRE_EXIT','SCAN_GATE_EXIT','SCAN_PICKUP','SCAN_FEES','SCAN_VERIFY',
  'CARD_CREATE','CARD_PRINT','CARD_LOST','CARD_REPLACE','CARD_REPRINT','CARD_REVOKE',
  'SCAN_PICKUP_OVERRIDE','SCAN_FEES_DELEGATE'
));

create table if not exists app.scanner_fee_settings (
  school_id uuid primary key,
  enabled boolean not null default false,
  updated_by uuid,
  updated_at timestamptz not null default pg_catalog.now(),
  constraint scanner_fee_settings_school_fkey foreign key (school_id) references app.schools(id) on delete cascade
);

alter table app.scanner_fee_settings enable row level security;
alter table app.scanner_fee_settings force row level security;

do $$
begin
  if not exists (
    select 1 from pg_policies
    where schemaname = 'app' and tablename = 'scanner_fee_settings' and policyname = 'scanner_fee_settings_isolation'
  ) then
    create policy scanner_fee_settings_isolation on app.scanner_fee_settings
      for all to schoolsafe_owner
      using (school_id = iam.current_school_id())
      with check (school_id = iam.current_school_id());
  end if;
end $$;

revoke all on app.scanner_fee_settings from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_auditor;

create table if not exists app.scanner_pickup_proofs (
  id uuid primary key default gen_random_uuid(),
  school_id uuid not null,
  student_id uuid not null,
  card_id uuid not null,
  profile_id uuid not null,
  portal_id uuid,
  location_id uuid not null,
  guardian_id uuid not null,
  token text not null,
  expires_at timestamptz not null,
  consumed_at timestamptz,
  created_at timestamptz not null default pg_catalog.now(),
  constraint scanner_pickup_proofs_school_fkey foreign key (school_id) references app.schools(id) on delete cascade,
  constraint scanner_pickup_proofs_token_unique unique (school_id, token)
);

alter table app.scanner_pickup_proofs enable row level security;
alter table app.scanner_pickup_proofs force row level security;

do $$
begin
  if not exists (
    select 1 from pg_policies
    where schemaname = 'app' and tablename = 'scanner_pickup_proofs' and policyname = 'scanner_pickup_proofs_isolation'
  ) then
    create policy scanner_pickup_proofs_isolation on app.scanner_pickup_proofs
      for all to schoolsafe_owner
      using (school_id = iam.current_school_id())
      with check (school_id = iam.current_school_id());
  end if;
end $$;

revoke all on app.scanner_pickup_proofs from public, schoolsafe_api, schoolsafe_auth, schoolsafe_worker, schoolsafe_auditor;

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
      and (g.expires_at is null or g.expires_at > pg_catalog.now())
  ) then
    raise insufficient_privilege using message = format('Scan control capability denied: %s', p_capability);
  end if;
  return true;
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
  v_fees boolean;
begin
  select c.id into v_campaign
  from app.fee_control_campaigns c
  where c.school_id = v_school_id and c.status = 'published'
    and (c.starts_at is null or c.starts_at <= pg_catalog.now())
    and (c.ends_at is null or c.ends_at >= pg_catalog.now())
  order by c.created_at desc
  limit 1;
  v_open := v_campaign is not null;
  select s.enabled into v_fees from app.scanner_fee_settings s where s.school_id = v_school_id;
  return jsonb_build_object(
    'capabilities', coalesce((
      select jsonb_agg(g.capability order by g.capability)
      from app.scan_control_grants g
      where g.school_id = v_school_id and g.profile_id = v_profile_id and g.revoked_at is null
        and (g.expires_at is null or g.expires_at > pg_catalog.now())
    ), '[]'::jsonb),
    'fee_campaign_open', v_open,
    'open_campaign_id', v_campaign,
    'fees_module_enabled', coalesce(v_fees, false)
  );
end
$schoolsafe$;

create or replace function api.scanner_current_portal()
returns uuid
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_text text := nullif(pg_catalog.current_setting('schoolsafe.scan_portal_id', true), '');
begin
  if v_text is null then
    return null;
  end if;
  return v_text::uuid;
exception
  when invalid_text_representation then
    return null;
end
$schoolsafe$;

create or replace function api.scanner_bind_portal(p_location_id uuid)
returns uuid
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_portal uuid;
begin
  if not exists (
    select 1 from app.locations l
    where l.id = p_location_id and l.school_id = v_school_id and l.is_active = true
  ) then
    return null;
  end if;
  select p.id into v_portal
  from app.security_portals p
  where p.school_id = v_school_id and p.location_id = p_location_id and p.is_active = true
  order by p.created_at
  limit 1;
  perform pg_catalog.set_config('schoolsafe.scan_portal_id', coalesce(v_portal::text, ''), true);
  return v_portal;
end
$schoolsafe$;

create or replace function api.scanner_card_lookup(
  p_card_number text,
  p_signature text,
  p_capability text
)
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
  v_match boolean;
begin
  if p_capability = 'SCAN_FEES' then
    if not iam.context_is_valid() then
      raise insufficient_privilege using message = 'SchoolSafe request context does not match an active identity';
    end if;
  else
    perform api.scan_control_require(p_capability);
  end if;
  select * into v_card
  from app.student_cards c
  where c.school_id = v_school_id and c.card_number = p_card_number;
  if not found then
    return jsonb_build_object('found', false, 'signature_matches', false);
  end if;
  v_match := v_card.signature = p_signature;
  if not v_match then
    return jsonb_build_object('found', true, 'signature_matches', false, 'status', v_card.status);
  end if;
  select * into v_student from app.students s where s.id = v_card.student_id and s.school_id = v_school_id;
  select c.name into v_class_name from app.classes c where c.id = v_student.class_id and c.school_id = v_school_id;
  v_visible := iam.can_access('school.student.read', null, v_student.id, v_student.class_id);
  return jsonb_build_object(
    'found', true,
    'signature_matches', true,
    'card_id', v_card.id,
    'card_number', v_card.card_number,
    'status', v_card.status,
    'operational', app.is_student_operational(v_card.student_id),
    'student_id', v_student.id,
    'matricule', v_student.matricule,
    'first_name', v_student.first_name,
    'last_name', v_student.last_name,
    'class_id', v_student.class_id,
    'class_name', v_class_name,
    'scope_visible', v_visible
  );
end
$schoolsafe$;

create or replace function api.scanner_entry_record(
  p_card_id uuid,
  p_student_id uuid,
  p_location_id uuid
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
  v_card app.student_cards%rowtype;
  v_class_id uuid;
  v_class_name text;
  v_first text;
  v_last text;
  v_portal uuid;
  v_event_id uuid;
  v_at timestamptz;
  v_present boolean;
  v_mark jsonb;
begin
  perform api.scan_control_require('SCAN_ENTRY');
  if not exists (
    select 1 from app.locations l
    where l.id = p_location_id and l.school_id = v_school_id and l.is_active = true
  ) then
    return jsonb_build_object('result', 'LOCATION_DENIED');
  end if;
  select * into v_card
  from app.student_cards
  where id = p_card_id and school_id = v_school_id and student_id = p_student_id;
  if not found or v_card.status <> 'active' then
    return jsonb_build_object('result', 'INVALID');
  end if;
  select s.class_id, s.first_name, s.last_name into v_class_id, v_first, v_last
  from app.students s
  where s.id = p_student_id and s.school_id = v_school_id
  for update;
  if not found then
    return jsonb_build_object('result', 'INVALID');
  end if;
  if not app.is_student_operational(p_student_id) then
    perform audit.write_event('scan_control.entry', 'student', p_student_id,
      jsonb_build_object('result', 'STUDENT_NOT_OPERATIONAL', 'decision', 'denied'));
    return jsonb_build_object('result', 'STUDENT_NOT_OPERATIONAL');
  end if;
  select c.name into v_class_name from app.classes c where c.id = v_class_id and c.school_id = v_school_id;
  select p.id into v_portal
  from app.security_portals p
  where p.school_id = v_school_id and p.location_id = p_location_id and p.is_active = true
  order by p.created_at
  limit 1;
  begin
    perform iam.require_access('security.scan', null, p_student_id, v_class_id, null, v_portal);
  exception
    when insufficient_privilege then
      perform audit.write_event('scan_control.entry', 'student', p_student_id,
        jsonb_build_object('result', 'PORTAL_DENIED', 'decision', 'denied', 'location_id', p_location_id));
      return jsonb_build_object('result', 'PORTAL_DENIED');
  end;
  select (arrived_at is not null) into v_present
  from app.attendance_records
  where school_id = v_school_id and student_id = p_student_id and day = current_date;
  if coalesce(v_present, false) then
    return jsonb_build_object(
      'result', 'ALREADY_PRESENT',
      'student_name', v_first || ' ' || v_last,
      'class_name', v_class_name
    );
  end if;
  insert into app.security_events (
    school_id, student_id, card_id, location_id, portal_id, event_type, scanned_by, decision, metadata
  ) values (
    v_school_id, p_student_id, p_card_id, p_location_id, v_portal, 'entry', v_profile_id, 'allowed',
    jsonb_build_object('source', 'scanner_entry', 'fees_ignored', true)
  ) returning id, occurred_at into v_event_id, v_at;
  v_mark := api.scan_control_presence_mark(p_student_id, 'check_in', v_event_id);
  return jsonb_build_object(
    'result', coalesce(v_mark->>'result', 'PRESENT'),
    'event_id', v_event_id,
    'occurred_at', v_at,
    'student_name', v_first || ' ' || v_last,
    'class_name', v_class_name,
    'decision', 'allowed'
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
  perform iam.require_access('security.scan', null, p_student_id, null, null, api.scanner_current_portal());
  return api.pickup_authorization_view(p_student_id);
end
$schoolsafe$;

drop function if exists api.pickup_confirm(uuid, uuid, uuid, text, text);

create function api.pickup_confirm(
  p_student_id uuid,
  p_guardian_id uuid,
  p_guardian_profile_id uuid,
  p_request_key text,
  p_note text,
  p_proof_token text
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
  v_proof app.scanner_pickup_proofs%rowtype;
  v_allowed boolean := false;
  v_basis text;
  v_code text := 'PICKUP_RESTRICTED';
begin
  perform 1 from app.students where id = p_student_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  perform iam.require_access('security.scan', null, p_student_id, null, null, api.scanner_current_portal());
  select event_type, metadata into v_existing
    from app.security_events
   where school_id = v_school_id and student_id = p_student_id
     and event_type = 'pickup_confirmed'
     and metadata->>'request_key' = p_request_key
   limit 1;
  if found then
    return jsonb_build_object('status', 'already_confirmed', 'idempotent', true);
  end if;
  if p_proof_token is null or length(btrim(p_proof_token)) < 16 then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_PROOF_REQUIRED', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_PROOF_REQUIRED');
  end if;
  select * into v_proof
    from app.scanner_pickup_proofs p
   where p.school_id = v_school_id and p.token = p_proof_token
   for update;
  if not found then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_PROOF_INVALID', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_PROOF_INVALID');
  end if;
  if v_proof.consumed_at is not null then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_PROOF_REUSED', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'proof_id', v_proof.id));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_PROOF_REUSED');
  end if;
  if v_proof.expires_at <= pg_catalog.now() then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_PROOF_EXPIRED', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'proof_id', v_proof.id));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_PROOF_EXPIRED');
  end if;
  if v_proof.profile_id is distinct from iam.current_profile_id()
     or v_proof.profile_id is distinct from p_guardian_profile_id
     or v_proof.student_id is distinct from p_student_id
     or v_proof.guardian_id is distinct from p_guardian_id then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PICKUP_PROOF_INVALID', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'proof_id', v_proof.id));
    return jsonb_build_object('status', 'denied', 'code', 'PICKUP_PROOF_INVALID');
  end if;
  if v_proof.portal_id is distinct from api.scanner_current_portal() then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'PORTAL_DENIED', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'proof_id', v_proof.id));
    return jsonb_build_object('status', 'denied', 'code', 'PORTAL_DENIED');
  end if;
  update app.scanner_pickup_proofs set consumed_at = pg_catalog.now() where id = v_proof.id;
  perform 1 from app.student_cards c
   where c.id = v_proof.card_id and c.school_id = v_school_id and c.student_id = p_student_id and c.status = 'active';
  if not found then
    insert into app.security_events (school_id, student_id, event_type, decision, denial_reason, scanned_by, metadata)
    values (v_school_id, p_student_id, 'pickup_denied', 'denied', 'CARD_NOT_ACTIVE', p_guardian_profile_id,
      jsonb_build_object('guardian_id', p_guardian_id, 'request_key', p_request_key, 'card_id', v_proof.card_id));
    return jsonb_build_object('status', 'denied', 'code', 'CARD_NOT_ACTIVE');
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

create or replace function api.scanner_pickup_prepare(
  p_student_id uuid,
  p_card_id uuid,
  p_location_id uuid,
  p_guardian_id uuid
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
  v_portal uuid;
  v_token text;
  v_expires timestamptz;
begin
  perform api.scan_control_require('SCAN_PICKUP');
  if api.scanner_bind_portal(p_location_id) is null and not exists (
    select 1 from app.locations l
    where l.id = p_location_id and l.school_id = v_school_id and l.is_active
  ) then
    return jsonb_build_object('result', 'LOCATION_DENIED');
  end if;
  v_portal := api.scanner_current_portal();
  begin
    perform iam.require_access('security.scan', null, p_student_id, null, null, v_portal);
  exception
    when insufficient_privilege then
      perform audit.write_event('scan_control.pickup', 'student', p_student_id,
        jsonb_build_object('result', 'PORTAL_DENIED', 'decision', 'denied'));
      return jsonb_build_object('result', 'PORTAL_DENIED');
  end;
  perform 1 from app.student_cards c
   where c.id = p_card_id and c.school_id = v_school_id and c.student_id = p_student_id and c.status = 'active';
  if not found then
    return jsonb_build_object('result', 'CARD_NOT_ACTIVE');
  end if;
  perform 1 from app.student_guardians g
   where g.id = p_guardian_id and g.student_id = p_student_id and g.school_id = v_school_id;
  if not found then
    return jsonb_build_object('result', 'PICKUP_RESTRICTED');
  end if;
  v_token := gen_random_uuid()::text || gen_random_uuid()::text;
  v_expires := pg_catalog.now() + interval '2 minutes';
  insert into app.scanner_pickup_proofs (
    school_id, student_id, card_id, profile_id, portal_id, location_id, guardian_id, token, expires_at
  ) values (
    v_school_id, p_student_id, p_card_id, v_profile_id, v_portal, p_location_id, p_guardian_id, v_token, v_expires
  );
  return jsonb_build_object('result', 'PREPARED', 'proof_token', v_token, 'expires_at', v_expires, 'portal_id', v_portal);
end
$schoolsafe$;

create or replace function api.scanner_pickup_people(p_student_id uuid, p_location_id uuid)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_portal uuid;
begin
  perform api.scan_control_require('SCAN_PICKUP');
  if api.scanner_bind_portal(p_location_id) is null and not exists (
    select 1 from app.locations l
    where l.id = p_location_id and l.school_id = iam.current_school_id() and l.is_active
  ) then
    return jsonb_build_object('result', 'LOCATION_DENIED');
  end if;
  v_portal := api.scanner_current_portal();
  begin
    return api.pickup_authorization_list(p_student_id) || jsonb_build_object('portal_id', v_portal);
  exception
    when insufficient_privilege then
      perform audit.write_event('scan_control.pickup', 'student', p_student_id,
        jsonb_build_object('result', 'PORTAL_DENIED', 'decision', 'denied'));
      return jsonb_build_object('result', 'PORTAL_DENIED');
  end;
end
$schoolsafe$;

create or replace function api.scanner_pickup_confirm(
  p_student_id uuid,
  p_guardian_id uuid,
  p_location_id uuid,
  p_request_key text,
  p_note text,
  p_proof_token text
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
begin
  perform api.scan_control_require('SCAN_PICKUP');
  if not exists (
    select 1 from app.locations l
    where l.id = p_location_id and l.school_id = iam.current_school_id() and l.is_active
  ) then
    return jsonb_build_object('status', 'denied', 'code', 'LOCATION_DENIED');
  end if;
  perform api.scanner_bind_portal(p_location_id);
  begin
    return api.pickup_confirm(p_student_id, p_guardian_id, iam.current_profile_id(), p_request_key, p_note, p_proof_token);
  exception
    when insufficient_privilege then
      perform audit.write_event('scan_control.pickup', 'student', p_student_id,
        jsonb_build_object('result', 'PORTAL_DENIED', 'decision', 'denied'));
      return jsonb_build_object('status', 'denied', 'code', 'PORTAL_DENIED');
  end;
end
$schoolsafe$;

create or replace function api.scanner_fee_module_set(p_enabled boolean)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
begin
  perform iam.require_access('finance.control.manage', null, null, null);
  insert into app.scanner_fee_settings (school_id, enabled, updated_by)
  values (v_school_id, p_enabled, v_profile_id)
  on conflict (school_id) do update
    set enabled = excluded.enabled, updated_by = excluded.updated_by, updated_at = pg_catalog.now();
  perform audit.write_event('scanner.fees.module', 'school', v_school_id,
    jsonb_build_object('enabled', p_enabled));
  return jsonb_build_object('enabled', p_enabled);
end
$schoolsafe$;

create or replace function api.scanner_fee_module_get()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_enabled boolean;
begin
  if not iam.context_is_valid() then
    raise insufficient_privilege using message = 'SchoolSafe request context does not match an active identity';
  end if;
  select s.enabled into v_enabled from app.scanner_fee_settings s where s.school_id = v_school_id;
  return jsonb_build_object('enabled', coalesce(v_enabled, false));
end
$schoolsafe$;

create or replace function api.scanner_fee_delegation_set(
  p_profile_id uuid,
  p_capability text,
  p_action text,
  p_reason text,
  p_expires_at timestamptz,
  p_class_ids uuid[],
  p_campaign_id uuid
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_actor uuid := iam.current_profile_id();
  v_manage boolean;
  v_delegate boolean;
  v_actor_grant app.scan_control_grants%rowtype;
  v_target app.scan_control_grants%rowtype;
  v_id uuid;
begin
  if p_capability not in ('SCAN_FEES', 'SCAN_FEES_DELEGATE') then
    raise check_violation using message = 'Fee delegation capability is invalid';
  end if;
  if p_action not in ('assign', 'revoke') then
    raise check_violation using message = 'Scan control action must be assign or revoke';
  end if;
  if p_reason is null or pg_catalog.char_length(pg_catalog.btrim(p_reason)) < 1
     or pg_catalog.char_length(pg_catalog.btrim(p_reason)) > 500 then
    raise check_violation using message = 'Scan control reason is required';
  end if;
  if p_expires_at is not null and p_expires_at <= pg_catalog.now() and p_action = 'assign' then
    raise check_violation using message = 'Fee delegation expiry must be in the future';
  end if;
  v_manage := iam.can_access('finance.control.manage', null, null, null);
  v_delegate := exists (
    select 1 from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = v_actor and g.capability = 'SCAN_FEES_DELEGATE'
      and g.revoked_at is null and (g.expires_at is null or g.expires_at > pg_catalog.now())
  );
  if p_capability = 'SCAN_FEES_DELEGATE' and not v_manage then
    raise insufficient_privilege using message = 'Access denied: finance.control.manage';
  end if;
  if not v_manage and not v_delegate then
    raise insufficient_privilege using message = 'Access denied: fee delegation';
  end if;
  perform 1 from iam.profiles p where p.id = p_profile_id and p.school_id = v_school_id and p.is_active = true;
  if not found then
    raise foreign_key_violation using message = 'Profile not found';
  end if;
  if p_campaign_id is not null and not exists (
    select 1 from app.fee_control_campaigns c where c.id = p_campaign_id and c.school_id = v_school_id
  ) then
    raise foreign_key_violation using message = 'Campaign not found';
  end if;
  if not v_manage then
    select * into v_actor_grant
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = v_actor and g.capability = 'SCAN_FEES_DELEGATE'
      and g.revoked_at is null and (g.expires_at is null or g.expires_at > pg_catalog.now());
    if v_actor_grant.id is null then
      raise insufficient_privilege using message = 'Access denied: delegation scope';
    end if;
    if p_action = 'assign' then
      if v_actor_grant.class_ids is not null and cardinality(v_actor_grant.class_ids) > 0
         and (p_class_ids is null or cardinality(p_class_ids) = 0 or not (p_class_ids <@ v_actor_grant.class_ids)) then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
      if v_actor_grant.campaign_id is not null and p_campaign_id is distinct from v_actor_grant.campaign_id then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
      if v_actor_grant.expires_at is not null and (p_expires_at is null or p_expires_at > v_actor_grant.expires_at) then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
    end if;
    select * into v_target
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = p_profile_id
      and g.capability = p_capability and g.revoked_at is null;
    if v_target.id is not null then
      if v_actor_grant.class_ids is not null and cardinality(v_actor_grant.class_ids) > 0
         and (v_target.class_ids is null or cardinality(v_target.class_ids) = 0 or not (v_target.class_ids <@ v_actor_grant.class_ids)) then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
      if v_actor_grant.campaign_id is not null and v_target.campaign_id is distinct from v_actor_grant.campaign_id then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
      if v_actor_grant.expires_at is not null and (v_target.expires_at is null or v_target.expires_at > v_actor_grant.expires_at) then
        raise insufficient_privilege using message = 'Access denied: delegation scope';
      end if;
    end if;
  end if;
  if p_action = 'assign' then
    select g.id into v_id
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = p_profile_id
      and g.capability = p_capability and g.revoked_at is null;
    if v_id is null then
      insert into app.scan_control_grants (
        school_id, profile_id, capability, granted_by, reason, expires_at, class_ids, campaign_id
      ) values (
        v_school_id, p_profile_id, p_capability, v_actor, pg_catalog.btrim(p_reason),
        p_expires_at, p_class_ids, p_campaign_id
      ) returning id into v_id;
    else
      update app.scan_control_grants
      set reason = pg_catalog.btrim(p_reason),
          expires_at = p_expires_at,
          class_ids = p_class_ids,
          campaign_id = p_campaign_id,
          granted_by = v_actor,
          granted_at = pg_catalog.now()
      where id = v_id;
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
    'scanner.fees.delegation.' || p_action,
    'profile',
    p_profile_id,
    jsonb_build_object('capability', p_capability, 'grant_id', v_id, 'expires_at', p_expires_at, 'campaign_id', p_campaign_id)
  );
  return jsonb_build_object('id', v_id, 'profile_id', p_profile_id, 'capability', p_capability, 'action', p_action);
end
$schoolsafe$;

create or replace function api.scanner_fee_delegation_list()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_actor uuid := iam.current_profile_id();
  v_allowed boolean;
begin
  v_allowed := iam.can_access('finance.control.manage', null, null, null)
    or exists (
      select 1 from app.scan_control_grants g
      where g.school_id = v_school_id and g.profile_id = v_actor and g.capability = 'SCAN_FEES_DELEGATE'
        and g.revoked_at is null and (g.expires_at is null or g.expires_at > pg_catalog.now())
    );
  if not v_allowed then
    raise insufficient_privilege using message = 'Access denied: fee delegation';
  end if;
  return (
    select coalesce(jsonb_agg(jsonb_build_object(
      'id', g.id,
      'profile_id', g.profile_id,
      'operator_name', p.display_name,
      'capability', g.capability,
      'granted_at', g.granted_at,
      'expires_at', g.expires_at,
      'revoked_at', g.revoked_at,
      'class_ids', g.class_ids,
      'campaign_id', g.campaign_id,
      'reason', g.reason
    ) order by g.granted_at desc), '[]'::jsonb)
    from app.scan_control_grants g
    join iam.profiles p on p.id = g.profile_id
    where g.school_id = v_school_id
      and g.capability in ('SCAN_FEES', 'SCAN_FEES_DELEGATE')
  );
end
$schoolsafe$;

create or replace function api.scanner_fee_authorize(p_student_id uuid, p_campaign_id uuid)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_enabled boolean;
  v_school_wide boolean;
  v_grant app.scan_control_grants%rowtype;
  v_class_id uuid;
  v_campaign uuid := p_campaign_id;
  v_classes jsonb;
begin
  if not iam.context_is_valid() then
    raise insufficient_privilege using message = 'SchoolSafe request context does not match an active identity';
  end if;
  select s.enabled into v_enabled from app.scanner_fee_settings s where s.school_id = v_school_id;
  if coalesce(v_enabled, false) is not true then
    return jsonb_build_object('result', 'FEES_DISABLED');
  end if;
  select s.class_id into v_class_id
  from app.students s
  where s.id = p_student_id and s.school_id = v_school_id;
  if not found then
    return jsonb_build_object('result', 'INVALID');
  end if;
  v_school_wide := iam.can_access('finance.control.scan', null, p_student_id, v_class_id, null, null, '{}'::jsonb);
  if not v_school_wide and not iam.can_access(
    'finance.control.scan', null, p_student_id, v_class_id, null, null,
    case when p_campaign_id is null then '{}'::jsonb else jsonb_build_object('campaign_id', p_campaign_id::text) end
  ) then
    select * into v_grant
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
      and g.revoked_at is null and (g.expires_at is null or g.expires_at > pg_catalog.now());
    if v_grant.id is null then
      if exists (
        select 1 from app.scan_control_grants g
        where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
          and g.revoked_at is null and g.expires_at is not null and g.expires_at <= pg_catalog.now()
      ) then
        return jsonb_build_object('result', 'DELEGATION_EXPIRED');
      end if;
      if exists (
        select 1 from app.scan_control_grants g
        where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
          and g.revoked_at is not null
      ) then
        return jsonb_build_object('result', 'DELEGATION_REVOKED');
      end if;
      return jsonb_build_object('result', 'NOT_DELEGATED');
    end if;
    if v_grant.class_ids is not null and cardinality(v_grant.class_ids) > 0 and not (v_class_id = any(v_grant.class_ids)) then
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
    if v_grant.campaign_id is not null and p_campaign_id is not null and v_grant.campaign_id is distinct from p_campaign_id then
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
    if v_campaign is null then
      v_campaign := v_grant.campaign_id;
    end if;
  end if;
  if v_campaign is not null then
    select c.classes into v_classes
    from app.fee_control_campaigns c
    where c.id = v_campaign and c.school_id = v_school_id and c.status = 'published';
    if not found or v_class_id is null or v_classes is null or jsonb_typeof(v_classes) <> 'array' or not (v_classes ? v_class_id::text) then
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
  end if;
  return jsonb_build_object('result', 'ALLOWED');
end
$schoolsafe$;

create or replace function api.scanner_fee_consult(p_student_id uuid, p_campaign_id uuid)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_profile_id uuid := iam.current_profile_id();
  v_enabled boolean;
  v_school_wide boolean;
  v_grant app.scan_control_grants%rowtype;
  v_class_id uuid;
  v_class_name text;
  v_first text;
  v_last text;
  v_campaign uuid := p_campaign_id;
  v_structure uuid;
  v_classes jsonb;
  v_active integer;
  v_waived integer;
  v_expected numeric;
  v_paid numeric;
  v_remaining numeric;
  v_status text;
  v_scan_id uuid;
begin
  select s.enabled into v_enabled from app.scanner_fee_settings s where s.school_id = v_school_id;
  if coalesce(v_enabled, false) is not true then
    perform audit.write_event('scan_control.fees', 'student', p_student_id,
      jsonb_build_object('result', 'FEES_DISABLED', 'decision', 'denied'));
    return jsonb_build_object('result', 'FEES_DISABLED');
  end if;
  select s.class_id, s.first_name, s.last_name into v_class_id, v_first, v_last
  from app.students s
  where s.id = p_student_id and s.school_id = v_school_id;
  if not found then
    return jsonb_build_object('result', 'INVALID');
  end if;
  select c.name into v_class_name from app.classes c where c.id = v_class_id and c.school_id = v_school_id;
  v_school_wide := iam.can_access('finance.control.scan', null, p_student_id, v_class_id, null, null, '{}'::jsonb);
  if not v_school_wide and not iam.can_access(
    'finance.control.scan', null, p_student_id, v_class_id, null, null,
    case when p_campaign_id is null then '{}'::jsonb else jsonb_build_object('campaign_id', p_campaign_id::text) end
  ) then
    select * into v_grant
    from app.scan_control_grants g
    where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
      and g.revoked_at is null and (g.expires_at is null or g.expires_at > pg_catalog.now());
    if v_grant.id is null then
      if exists (
        select 1 from app.scan_control_grants g
        where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
          and g.revoked_at is null and g.expires_at is not null and g.expires_at <= pg_catalog.now()
      ) then
        perform audit.write_event('scan_control.fees', 'student', p_student_id,
          jsonb_build_object('result', 'DELEGATION_EXPIRED', 'decision', 'denied'));
        return jsonb_build_object('result', 'DELEGATION_EXPIRED');
      end if;
      if exists (
        select 1 from app.scan_control_grants g
        where g.school_id = v_school_id and g.profile_id = v_profile_id and g.capability = 'SCAN_FEES'
          and g.revoked_at is not null
      ) then
        perform audit.write_event('scan_control.fees', 'student', p_student_id,
          jsonb_build_object('result', 'DELEGATION_REVOKED', 'decision', 'denied'));
        return jsonb_build_object('result', 'DELEGATION_REVOKED');
      end if;
      perform audit.write_event('scan_control.fees', 'student', p_student_id,
        jsonb_build_object('result', 'NOT_DELEGATED', 'decision', 'denied'));
      return jsonb_build_object('result', 'NOT_DELEGATED');
    end if;
    if v_grant.class_ids is not null and cardinality(v_grant.class_ids) > 0 and not (v_class_id = any(v_grant.class_ids)) then
      perform audit.write_event('scan_control.fees', 'student', p_student_id,
        jsonb_build_object('result', 'OUT_OF_SCOPE', 'decision', 'denied', 'reason', 'class'));
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
    if v_grant.campaign_id is not null and p_campaign_id is not null and v_grant.campaign_id is distinct from p_campaign_id then
      perform audit.write_event('scan_control.fees', 'student', p_student_id,
        jsonb_build_object('result', 'OUT_OF_SCOPE', 'decision', 'denied', 'reason', 'campaign'));
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
    if v_campaign is null then
      v_campaign := v_grant.campaign_id;
    end if;
  end if;
  if v_campaign is not null then
    select c.fee_structure_id, c.classes into v_structure, v_classes
    from app.fee_control_campaigns c
    where c.id = v_campaign and c.school_id = v_school_id and c.status = 'published';
    if not found or v_class_id is null or v_classes is null or jsonb_typeof(v_classes) <> 'array' or not (v_classes ? v_class_id::text) then
      perform audit.write_event('scan_control.fees', 'student', p_student_id,
        jsonb_build_object('result', 'OUT_OF_SCOPE', 'decision', 'denied', 'reason', 'campaign_classes', 'campaign_id', v_campaign));
      return jsonb_build_object('result', 'OUT_OF_SCOPE');
    end if;
  end if;
  select
    count(*) filter (where sf.status <> 'cancelled'),
    count(*) filter (where sf.status = 'waived'),
    coalesce(sum(sf.amount_expected) filter (where sf.status not in ('cancelled', 'waived')), 0),
    coalesce(sum(sf.amount_paid) filter (where sf.status not in ('cancelled', 'waived')), 0),
    coalesce(sum(sf.amount_remaining) filter (where sf.status not in ('cancelled', 'waived')), 0)
  into v_active, v_waived, v_expected, v_paid, v_remaining
  from app.student_fees sf
  where sf.school_id = v_school_id and sf.student_id = p_student_id
    and (v_structure is null or sf.fee_structure_id = v_structure);
  if coalesce(v_active, 0) = 0 then
    v_status := 'no_data';
  elsif v_waived = v_active then
    v_status := 'exempt';
  elsif v_remaining = 0 and v_expected > 0 then
    v_status := 'paid';
  elsif v_paid > 0 and v_remaining > 0 then
    v_status := 'partial';
  elsif v_expected > 0 and v_paid = 0 and v_remaining > 0 then
    v_status := 'due';
  else
    v_status := 'no_data';
  end if;
  if v_campaign is not null and app.is_student_operational(p_student_id) then
    insert into app.fee_control_scans (
      school_id, campaign_id, student_id, scanned_by, student_fee_status, result, notes, metadata
    ) values (
      v_school_id, v_campaign, p_student_id, v_profile_id, v_status, 'consulted', 'consultation',
      jsonb_build_object('balance_status', v_status, 'consultative', true)
    ) returning id into v_scan_id;
  end if;
  perform audit.write_event('scan_control.fees', 'student', p_student_id,
    jsonb_build_object('result', 'FEE_STATUS', 'decision', 'allowed', 'balance_status', v_status, 'campaign_id', v_campaign, 'fee_scan_id', v_scan_id));
  return jsonb_build_object(
    'result', 'FEE_STATUS',
    'balance_status', v_status,
    'amount_expected', v_expected,
    'amount_paid', v_paid,
    'amount_remaining', v_remaining,
    'student_name', v_first || ' ' || v_last,
    'class_name', v_class_name,
    'campaign_id', v_campaign,
    'fee_scan_id', v_scan_id,
    'consultative', true
  );
end
$schoolsafe$;

create or replace function api.fee_control_scan_create(
  p_campaign_id uuid,
  p_student_id uuid,
  p_result text,
  p_notes text default null,
  p_student_fee_status text default null
)
returns uuid
language plpgsql
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_body jsonb;
begin
  -- Le statut et le résultat fournis par l'appelant ne sont pas une décision.
  v_body := api.scanner_fee_consult(p_student_id, p_campaign_id);
  if coalesce(v_body->>'result', '') <> 'FEE_STATUS' then
    raise insufficient_privilege using message = coalesce(v_body->>'result', 'Fee control denied');
  end if;
  return nullif(v_body->>'fee_scan_id', '')::uuid;
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
        and (g.expires_at is null or g.expires_at > pg_catalog.now())
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

revoke all on function api.scanner_current_portal() from public;
revoke all on function api.scanner_bind_portal(uuid) from public;
revoke all on function api.scanner_card_lookup(text, text, text) from public;
revoke all on function api.scanner_entry_record(uuid, uuid, uuid) from public;
revoke all on function api.scanner_pickup_prepare(uuid, uuid, uuid, uuid) from public;
revoke all on function api.scanner_pickup_people(uuid, uuid) from public;
revoke all on function api.scanner_pickup_confirm(uuid, uuid, uuid, text, text, text) from public;
revoke all on function api.scanner_fee_module_set(boolean) from public;
revoke all on function api.scanner_fee_module_get() from public;
revoke all on function api.scanner_fee_delegation_set(uuid, text, text, text, timestamptz, uuid[], uuid) from public;
revoke all on function api.scanner_fee_delegation_list() from public;
revoke all on function api.scanner_fee_authorize(uuid, uuid) from public;
revoke all on function api.scanner_fee_consult(uuid, uuid) from public;

grant execute on function api.scanner_bind_portal(uuid) to schoolsafe_api;
grant execute on function api.scanner_card_lookup(text, text, text) to schoolsafe_api;
grant execute on function api.scanner_entry_record(uuid, uuid, uuid) to schoolsafe_api;
grant execute on function api.pickup_confirm(uuid, uuid, uuid, text, text, text) to schoolsafe_api;
grant execute on function api.scanner_pickup_prepare(uuid, uuid, uuid, uuid) to schoolsafe_api;
grant execute on function api.scanner_pickup_people(uuid, uuid) to schoolsafe_api;
grant execute on function api.scanner_pickup_confirm(uuid, uuid, uuid, text, text, text) to schoolsafe_api;
grant execute on function api.scanner_fee_module_set(boolean) to schoolsafe_api;
grant execute on function api.scanner_fee_module_get() to schoolsafe_api;
grant execute on function api.scanner_fee_delegation_set(uuid, text, text, text, timestamptz, uuid[], uuid) to schoolsafe_api;
grant execute on function api.scanner_fee_delegation_list() to schoolsafe_api;
grant execute on function api.scanner_fee_authorize(uuid, uuid) to schoolsafe_api;
grant execute on function api.scanner_fee_consult(uuid, uuid) to schoolsafe_api;

commit;
