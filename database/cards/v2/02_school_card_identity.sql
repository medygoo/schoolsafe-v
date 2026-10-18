\set ON_ERROR_STOP on

-- Limited read of the official school identity for card production.
-- The school comes only from iam.current_school_id(). No argument can
-- select another school. This read does not grant administration.

begin;
set local role schoolsafe_owner;

create or replace function api.school_card_identity()
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
  perform iam.require_access('cards.request.print', null, null, null);
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

revoke all on function api.school_card_identity() from public;
grant execute on function api.school_card_identity() to schoolsafe_api;

commit;
