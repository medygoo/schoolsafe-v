\set ON_ERROR_STOP on

-- Card year label from the official class enrollment, and the native
-- school profile write used by the school form. cards.request.print
-- can read the year. Only school.manage can change the school profile.
-- Neither function accepts a school id.

begin;
set local role schoolsafe_owner;

create or replace function api.card_academic_year(
  p_student_id uuid,
  p_class_id uuid
)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_year_id uuid;
  v_label text;
  v_student_class uuid;
begin
  perform iam.require_access('cards.request.print', null, p_student_id, p_class_id);
  select s.class_id into v_student_class
    from app.students s
   where s.id = p_student_id
     and s.school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  select c.academic_year_id into v_year_id
    from app.classes c
   where c.id = p_class_id
     and c.school_id = v_school_id;
  if not found then
    raise insufficient_privilege using message = 'Access denied: academic year';
  end if;
  if v_year_id is null or v_student_class is distinct from p_class_id then
    return jsonb_build_object('academic_year_id', null, 'label', null);
  end if;
  select y.label into v_label
    from app.academic_years y
   where y.id = v_year_id
     and y.school_id = v_school_id;
  if not found then
    raise insufficient_privilege using message = 'Access denied: academic year';
  end if;
  if not exists (
    select 1
      from app.student_enrollments e
     where e.student_id = p_student_id
       and e.school_id = v_school_id
       and e.class_id = p_class_id
       and e.academic_year_id = v_year_id
       and e.status = 'active'
  ) then
    return jsonb_build_object('academic_year_id', null, 'label', null);
  end if;
  return jsonb_build_object('academic_year_id', v_year_id, 'label', v_label);
end
$schoolsafe$;

create or replace function api.school_official_profile_read()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_row jsonb;
begin
  perform iam.require_access('school.manage', null, null, null);
  select jsonb_build_object(
    'identity', jsonb_build_object('name', s.name),
    'brand', jsonb_build_object('logo_path', s.logo_path),
    'contact', jsonb_build_object(
      'address', sc.address,
      'city', sc.city,
      'province', sc.province,
      'country', sc.country,
      'phone', sc.phone,
      'email', sc.email,
      'website_url', sc.website_url
    )
  ) into v_row
  from app.schools s
  left join app.school_contacts sc on sc.school_id = s.id
  where s.id = v_school_id;
  if v_row is null then
    raise foreign_key_violation using message = 'School not found';
  end if;
  return v_row;
end
$schoolsafe$;

create or replace function api.school_official_profile_update(p_payload jsonb)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_name text;
  v_logo text;
  v_email text;
  v_website text;
begin
  perform iam.require_access('school.manage', null, null, null);
  if p_payload is null or jsonb_typeof(p_payload) <> 'object' then
    raise check_violation using message = 'Invalid school profile';
  end if;
  if exists (
    select 1
      from jsonb_object_keys(p_payload) as key
     where key not in (
       'name', 'logo_path', 'address', 'city', 'province', 'country', 'phone', 'email', 'website_url'
     )
  ) then
    raise check_violation using message = 'Invalid school profile field';
  end if;
  if not exists (select 1 from app.schools s where s.id = v_school_id) then
    raise foreign_key_violation using message = 'School not found';
  end if;
  if p_payload ? 'name' then
    v_name := nullif(btrim(p_payload->>'name'), '');
    if v_name is null then
      raise check_violation using message = 'School name required';
    end if;
    update app.schools
       set name = v_name,
           updated_at = pg_catalog.now()
     where id = v_school_id;
  end if;
  if p_payload ? 'logo_path' then
    v_logo := nullif(btrim(p_payload->>'logo_path'), '');
    if v_logo ~* '(data:|base64)' then
      raise check_violation using message = 'Invalid logo path';
    end if;
    update app.schools
       set logo_path = v_logo,
           updated_at = pg_catalog.now()
     where id = v_school_id;
  end if;
  if p_payload ? 'email' then
    v_email := nullif(btrim(p_payload->>'email'), '');
    if v_email is not null and v_email !~* '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$' then
      raise check_violation using message = 'Invalid school email';
    end if;
  end if;
  if p_payload ? 'website_url' then
    v_website := nullif(btrim(p_payload->>'website_url'), '');
    if v_website is not null and v_website !~* '^https?://[^[:space:]]+$' then
      raise check_violation using message = 'Invalid school website';
    end if;
  end if;
  if p_payload ? 'address' or p_payload ? 'city' or p_payload ? 'province'
     or p_payload ? 'country' or p_payload ? 'phone' or p_payload ? 'email'
     or p_payload ? 'website_url' then
    insert into app.school_contacts (school_id)
    select v_school_id
     where not exists (
       select 1 from app.school_contacts sc where sc.school_id = v_school_id
     );
    update app.school_contacts sc
       set address = case when p_payload ? 'address' then nullif(btrim(p_payload->>'address'), '') else sc.address end,
           city = case when p_payload ? 'city' then coalesce(nullif(btrim(p_payload->>'city'), ''), sc.city) else sc.city end,
           province = case when p_payload ? 'province' then coalesce(nullif(btrim(p_payload->>'province'), ''), sc.province) else sc.province end,
           country = case when p_payload ? 'country' then coalesce(nullif(btrim(p_payload->>'country'), ''), sc.country) else sc.country end,
           phone = case when p_payload ? 'phone' then nullif(btrim(p_payload->>'phone'), '') else sc.phone end,
           email = case when p_payload ? 'email' then v_email else sc.email end,
           website_url = case when p_payload ? 'website_url' then v_website else sc.website_url end,
           updated_at = pg_catalog.now()
     where sc.school_id = v_school_id;
  end if;
  perform audit.write_event(
    'school.official_profile.updated',
    'school',
    v_school_id,
    jsonb_build_object('fields', coalesce((select jsonb_agg(key) from jsonb_object_keys(p_payload) as key), '[]'::jsonb))
  );
  return api.school_official_profile_read();
end
$schoolsafe$;

revoke all on function api.card_academic_year(uuid, uuid) from public;
revoke all on function api.school_official_profile_read() from public;
revoke all on function api.school_official_profile_update(jsonb) from public;
grant execute on function api.card_academic_year(uuid, uuid) to schoolsafe_api;
grant execute on function api.school_official_profile_read() to schoolsafe_api;
grant execute on function api.school_official_profile_update(jsonb) to schoolsafe_api;

commit;
