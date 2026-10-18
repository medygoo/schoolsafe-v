begin;
do $$ begin
 if current_database() <> 'schoolsafe_cards_isolated' then raise exception 'ISOLATED_DATABASE_REQUIRED'; end if;
end $$;
alter function card_bridge_notifications.collect() security definer;
revoke all on function card_bridge_notifications.collect() from public;
grant execute on function card_bridge_notifications.collect() to schoolsafe_card_bridge;
commit;
