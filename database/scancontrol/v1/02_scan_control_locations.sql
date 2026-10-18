\set ON_ERROR_STOP on

-- Additive location reads. Existing Rosario functions and table grants stay in place.

begin;
set local role schoolsafe_owner;

create or replace function api.scan_control_location_list()
returns table (id uuid, code text, label text, kind text)
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_capability text;
  v_allowed boolean := false;
begin
  if not iam.context_is_valid() then
    raise insufficient_privilege using message = 'SchoolSafe request context does not match an active identity';
  end if;
  foreach v_capability in array array['SCAN_ENTRY', 'SCAN_PRE_EXIT', 'SCAN_GATE_EXIT', 'SCAN_PICKUP']
  loop
    begin
      perform api.scan_control_require(v_capability);
      v_allowed := true;
      exit;
    exception
      when insufficient_privilege then
        v_allowed := false;
    end;
  end loop;
  if not v_allowed then
    perform api.scan_control_require('SCAN_ENTRY');
  end if;
  return query
    select l.id, l.code, l.label, l.kind
    from app.locations l
    where l.school_id = iam.current_school_id()
      and l.is_active = true
    order by l.code, l.id;
end
$schoolsafe$;

create or replace function api.scan_control_location_check(p_location_id uuid)
returns boolean
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_capability text;
  v_allowed boolean := false;
begin
  if not iam.context_is_valid() then
    raise insufficient_privilege using message = 'SchoolSafe request context does not match an active identity';
  end if;
  foreach v_capability in array array['SCAN_ENTRY', 'SCAN_PRE_EXIT', 'SCAN_GATE_EXIT', 'SCAN_PICKUP']
  loop
    begin
      perform api.scan_control_require(v_capability);
      v_allowed := true;
      exit;
    exception
      when insufficient_privilege then
        v_allowed := false;
    end;
  end loop;
  if not v_allowed then
    perform api.scan_control_require('SCAN_ENTRY');
  end if;
  return exists (
    select 1
    from app.locations l
    where l.id = p_location_id
      and l.school_id = iam.current_school_id()
      and l.is_active = true
  );
end
$schoolsafe$;

revoke all on function api.scan_control_location_list() from public;
revoke all on function api.scan_control_location_check(uuid) from public;
grant execute on function api.scan_control_location_list() to schoolsafe_api;
grant execute on function api.scan_control_location_check(uuid) to schoolsafe_api;

commit;
