\set ON_ERROR_STOP on

-- Readback of the existing homeroom flag. No new table, permission or rule.
-- app.teacher_assignments.teacher_profile_id stays the teacher.
-- The JSON key profile_id is unchanged. is_tutor is the stored boolean.

begin;
set local role schoolsafe_owner;

create or replace function api.teacher_assignment_list()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform iam.require_access('school.structure.manage', null, null, null);
  return (
    select coalesce(jsonb_agg(
      jsonb_build_object('id', ta.id, 'profile_id', ta.teacher_profile_id, 'is_tutor', ta.is_tutor, 'subject_id', ta.subject_id, 'class_id', ta.class_id, 'academic_year_id', ta.academic_year_id,
        'teacher_name', p.display_name, 'subject_name', s.name, 'class_name', c.name)
      order by p.display_name
    ), '[]'::jsonb)
    from app.teacher_assignments ta
    join iam.profiles p on p.id = ta.teacher_profile_id and p.school_id = v_school_id
    join app.subjects s on s.id = ta.subject_id and s.school_id = v_school_id
    join app.classes c on c.id = ta.class_id and c.school_id = v_school_id
    where ta.school_id = v_school_id
  );
end
$schoolsafe$;
grant execute on function api.teacher_assignment_list() to schoolsafe_api;

commit;
