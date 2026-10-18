\set ON_ERROR_STOP on

-- Additive card surfaces. The existing family read stays unchanged.
-- Phone is read from the existing app.student_guardians.phone column.
-- The official portrait stays on app.students.photo_path. No new table.

begin;
set local role schoolsafe_owner;

create or replace function api.student_card_guardian_phones(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform iam.require_access('school.guardian.read', null, p_student_id, null);
  return coalesce((
    select jsonb_agg(jsonb_build_object(
      'id', g.id,
      'phone', g.phone
    ) order by g.is_primary desc, g.created_at)
    from app.student_guardians g
    where g.school_id = v_school_id and g.student_id = p_student_id and g.is_active = true
  ), '[]'::jsonb);
end
$schoolsafe$;

create or replace function api.student_photo_set(p_student_id uuid, p_photo_path text)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_path text := nullif(btrim(p_photo_path), '');
begin
  perform iam.require_access('school.student.create', null, p_student_id, null);
  if v_path is null or v_path !~ '^/uploads/students/[A-Za-z0-9._/-]+$' then
    raise check_violation using message = 'Official student photo path required';
  end if;
  update app.students
     set photo_path = v_path, updated_at = pg_catalog.now()
   where id = p_student_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  return api.student_record_read(p_student_id);
end
$schoolsafe$;

revoke all on function api.student_card_guardian_phones(uuid) from public;
revoke all on function api.student_photo_set(uuid, text) from public;
grant execute on function api.student_card_guardian_phones(uuid) to schoolsafe_api;
grant execute on function api.student_photo_set(uuid, text) to schoolsafe_api;

commit;
