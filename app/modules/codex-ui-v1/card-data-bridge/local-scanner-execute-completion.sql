begin;
do $$ begin
 if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
end $$;
grant execute on function api.scanner_card_lookup(text,text,text),
 api.scanner_entry_record(uuid,uuid,uuid),api.scanner_fee_authorize(uuid,uuid),
 api.scanner_pickup_people(uuid,uuid) to schoolsafe_card_bridge;
commit;
