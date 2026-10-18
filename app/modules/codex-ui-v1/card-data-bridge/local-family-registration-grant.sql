begin;
do $$ begin
 if current_database() <> 'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
end $$;
-- Existing function enforces school.guardian.manage and the student school.
grant execute on function api.student_guardian_add(uuid,jsonb) to schoolsafe_card_bridge;
commit;
