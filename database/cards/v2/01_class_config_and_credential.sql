\set ON_ERROR_STOP on

-- Additive card reads. The existing class card config list stays unchanged.
-- Visual config uses the existing teacher_profile_id column.
-- Motto is the existing app.schools.motto value. No second motto.
-- Card number and signature stay on app.student_cards.
-- The HMAC secret never enters this function. No new table.

begin;
set local role schoolsafe_owner;

create or replace function api.class_card_visual_list()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
begin
  perform iam.require_access('school.class.read', null, null, null);
  return (
    select coalesce(jsonb_agg(
      jsonb_build_object(
        'id', c.id,
        'name', c.name,
        'cycle_key', c.cycle_key,
        'option', c.option,
        'teacher_profile_id', c.teacher_profile_id,
        'academic_year_id', c.academic_year_id,
        'card_color', c.card_color,
        'card_color_soft', c.card_color_soft,
        'card_color_dark', c.card_color_dark,
        'card_pat', c.card_pat,
        'card_family', c.card_family,
        'card_variant', c.card_variant,
        'card_pat_style', c.card_pat_style,
        'is_active', c.is_active
      ) order by c.name
    ), '[]'::jsonb)
    from app.classes c
    where c.school_id = v_school_id
  );
end
$schoolsafe$;

create or replace function api.school_card_motto()
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_motto text;
begin
  perform iam.require_access('cards.request.print', null, null, null);
  select s.motto into v_motto from app.schools s where s.id = v_school_id;
  return jsonb_build_object('motto', v_motto);
end
$schoolsafe$;

create or replace function api.student_card_read(p_student_id uuid)
returns jsonb
language plpgsql
stable
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_matricule text;
  v_card app.student_cards%rowtype;
begin
  perform iam.require_access('cards.request.print', null, p_student_id, null);
  select s.matricule into v_matricule
    from app.students s
   where s.id = p_student_id and s.school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  select * into v_card
    from app.student_cards c
   where c.student_id = p_student_id and c.school_id = v_school_id and c.status = 'active'
   order by c.issued_at desc
   limit 1;
  if not found then
    return jsonb_build_object('matricule', v_matricule, 'card_number', null, 'signature', null);
  end if;
  return jsonb_build_object(
    'matricule', v_matricule,
    'card_id', v_card.id,
    'card_number', v_card.card_number,
    'signature', v_card.signature,
    'status', v_card.status
  );
end
$schoolsafe$;

create or replace function api.student_card_issue(
  p_student_id uuid,
  p_card_number text,
  p_signature text,
  p_card_secret text
)
returns jsonb
language plpgsql
volatile
security definer
set search_path = pg_catalog
as $schoolsafe$
declare
  v_school_id uuid := iam.current_school_id();
  v_id uuid;
begin
  perform iam.require_access('cards.request.print', null, p_student_id, null);
  perform 1 from app.students where id = p_student_id and school_id = v_school_id;
  if not found then
    raise foreign_key_violation using message = 'Student not found';
  end if;
  if exists (
    select 1 from app.student_cards
     where student_id = p_student_id and school_id = v_school_id and status = 'active'
  ) then
    raise unique_violation using message = 'Active card already exists';
  end if;
  if nullif(btrim(p_card_number), '') is null or nullif(btrim(p_signature), '') is null or nullif(btrim(p_card_secret), '') is null then
    raise check_violation using message = 'Server card credential required';
  end if;
  insert into app.student_cards (school_id, student_id, card_number, card_secret, signature, status)
  values (v_school_id, p_student_id, btrim(p_card_number), btrim(p_card_secret), btrim(p_signature), 'active')
  returning id into v_id;
  return jsonb_build_object('card_id', v_id, 'card_number', btrim(p_card_number), 'signature', btrim(p_signature), 'status', 'active');
end
$schoolsafe$;

revoke all on function api.class_card_visual_list() from public;
revoke all on function api.school_card_motto() from public;
revoke all on function api.student_card_read(uuid) from public;
revoke all on function api.student_card_issue(uuid, text, text, text) from public;
grant execute on function api.class_card_visual_list() to schoolsafe_api;
grant execute on function api.school_card_motto() to schoolsafe_api;
grant execute on function api.student_card_read(uuid) to schoolsafe_api;
grant execute on function api.student_card_issue(uuid, text, text, text) to schoolsafe_api;

commit;
