begin;
do $$ begin
 if current_database() <> 'schoolsafe_cards_isolated' then raise exception 'ISOLATED_DATABASE_REQUIRED'; end if;
end $$;
create or replace function card_bridge_notifications.read_own(p_students uuid[]) returns jsonb
language sql stable security definer set search_path=pg_catalog as $$
 select coalesce(jsonb_agg(row_to_json(n) order by n.created_at desc),'[]'::jsonb)
 from (
   select o.id,o.student_id,concat_ws(' ',s.first_name,s.last_name) as student_name,o.event_type,o.payload,o.created_at
   from card_bridge_notifications.outbox o
   join app.students s on s.id=o.student_id and s.school_id=o.school_id
   join app.student_guardians g on g.school_id=o.school_id and g.student_id=o.student_id
     and g.profile_id=o.recipient_profile_id and g.is_primary and g.is_active
   where iam.context_is_valid() and o.school_id=iam.current_school_id()
     and o.recipient_profile_id=iam.current_profile_id()
     and o.student_id=any(p_students)
   order by o.created_at desc limit 100
 ) n;
$$;
revoke all on function card_bridge_notifications.read_own(uuid[]) from public;
grant usage on schema card_bridge_notifications to schoolsafe_card_bridge;
grant execute on function card_bridge_notifications.read_own(uuid[]) to schoolsafe_card_bridge;
commit;
